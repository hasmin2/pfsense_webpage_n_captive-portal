<?php
/**
 * freeradius_migrate_users_to_radcheck.php  (#23 step 2)
 *
 * config.xml 의 FreeRADIUS 사용자 계정(비밀번호 check-item)을 SQL radcheck 테이블로
 * 이관한다. 인증 소스를 rlm_files → SQL(radcheck) 로 옮기기 위한 "사전 적재" 단계다.
 *
 * 배경:
 *   rlm_files 는 변경 후 HUP 로 재로딩되지 않아(이 박스에서 실측: 재시작해야만 반영)
 *   PW 변경/계정 생성이 무작위로 안 먹히는 문제가 있었다. radcheck(SQL)는 per-request
 *   조회라 변경이 reload/재시작 없이 즉시 반영된다. 이 스크립트로 radcheck 를 채워두면,
 *   freeradius_enable_sql_authorize.php 로 authorize 의 sql fallback 을 켰을 때
 *   files 에 없는 사용자도 인증되고, 이후 radcheck 변경이 즉시 적용된다.
 *
 * 비파괴성:
 *   - files 기반 인증은 전혀 건드리지 않는다(이 스크립트는 radcheck 만 채운다).
 *   - authorize 는 "files 먼저 → 없으면 sql" 순서이므로, files 에 있는 사용자는
 *     계속 files 로 인증된다. 즉 이 적재만으로는 동작이 바뀌지 않는다(안전한 스테이징).
 *
 * 멱등성:
 *   - 사용자별로 비번 check-item(Cleartext-Password / NT-Password / MD5-Password)을
 *     DELETE 후 INSERT 하므로 반복 실행해도 중복 없이 수렴한다.
 *   - radcheck 의 그 외 속성(쿼터/그룹 등)은 건드리지 않는다.
 *   - 생성되는 (attribute, value) 는 freeradius_build_single_user_stanza() 의
 *     비밀번호 처리 로직과 동일하게 맞춰 files 인증과 결과가 같도록 한다.
 *
 * 안전 기본값:
 *   - 인자 없이 실행 = DRY-RUN. 생성될 SQL 을 /tmp 에 쓰고 요약만 출력(DB 미변경).
 *   - 'apply' 인자를 줄 때만 실제로 DB 에 반영.
 *
 * 사용:
 *   php /usr/local/sbin/freeradius_migrate_users_to_radcheck.php          # dry-run(미반영)
 *   php /usr/local/sbin/freeradius_migrate_users_to_radcheck.php apply     # 실제 적용
 *
 * 종료코드: 0=성공, 1=오류
 */

require_once("config.inc");
require_once("util.inc");

global $config, $argv;

$APPLY = (isset($argv[1]) && strtolower((string)$argv[1]) === 'apply');

function frm_out($msg) { echo $msg . "\n"; }

/**
 * MySQL 문자열 리터럴용 이스케이프 (작은따옴표/역슬래시).
 */
function frm_sql_str($s) {
    return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string)$s);
}

/**
 * mysql/mariadb 클라이언트 바이너리 경로 탐지 (datacounter_acct.sh 와 동일 전략).
 */
function frm_mysql_bin() {
    foreach (array('mysql', 'mariadb') as $name) {
        $out = array();
        $ret = 1;
        @exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $out, $ret);
        if ($ret === 0 && !empty($out[0]) && is_executable(trim($out[0]))) {
            return trim($out[0]);
        }
    }
    // 흔한 고정 경로 폴백
    foreach (array('/usr/local/bin/mysql', '/usr/local/bin/mariadb') as $p) {
        if (is_executable($p)) {
            return $p;
        }
    }
    return '';
}

/**
 * 사용자 레코드 → radcheck 비번 (attribute, value) 매핑.
 * freeradius_build_single_user_stanza() 의 switch 와 동일하게 처리한다.
 * 반환: array(attribute, value) | null(건너뜀)
 */
function frm_password_item($u) {
    $pw  = isset($u['varuserspassword']) ? (string)$u['varuserspassword'] : '';
    $enc = (isset($u['varuserspasswordencryption']) && $u['varuserspasswordencryption'] !== '')
        ? $u['varuserspasswordencryption'] : 'Cleartext-Password';

    switch ($enc) {
        case 'MD5-Password':
            return array('MD5-Password', md5($pw));
        case 'MD5-Password-hashed':
            return array('MD5-Password', $pw);
        case 'NT-Password-hashed':
            return array('NT-Password', $pw);
        default:
            // Cleartext-Password 등: files 와 동일하게 enc 를 그대로 속성명으로 사용
            return array($enc, $pw);
    }
}

// ---------------------------------------------------------------------------
// SQL 접속 파라미터 (freeradiussqlconf DB1)
// ---------------------------------------------------------------------------
if (!is_array($config['installedpackages']['freeradiussqlconf']['config'][0] ?? null)) {
    frm_out("[ERROR] freeradiussqlconf 설정이 없습니다. SQL(MySQL) 설정이 선행되어야 합니다.");
    exit(1);
}
$sqlconf = $config['installedpackages']['freeradiussqlconf']['config'][0];

$db_server = ($sqlconf['varsqlconfserver']        ?? '') ?: 'localhost';
$db_port   = ($sqlconf['varsqlconfport']          ?? '') ?: '3306';
$db_login  = ($sqlconf['varsqlconflogin']         ?? '') ?: 'radius';
$db_pass   = ($sqlconf['varsqlconfpassword']      ?? '') ?: 'radpass';
$db_name   = ($sqlconf['varsqlconfradiusdb']      ?? '') ?: 'radius';
$db_table  = ($sqlconf['varsqlconfauthchecktable'] ?? '') ?: 'radcheck';

// ---------------------------------------------------------------------------
// 사용자 수집 + SQL 생성
// ---------------------------------------------------------------------------
$arrusers = is_array($config['installedpackages']['freeradius']['config'] ?? null)
    ? $config['installedpackages']['freeradius']['config']
    : array();

$sql_lines = array();
$count_ok = 0;
$count_skip = 0;
$samples = array();

$sql_lines[] = "-- freeradius_migrate_users_to_radcheck.php  생성: " . date('Y-m-d H:i:s');
$sql_lines[] = "-- 대상 테이블: {$db_table} @ {$db_name}";
$sql_lines[] = "START TRANSACTION;";

foreach ($arrusers as $u) {
    $username = isset($u['varusersusername']) ? trim((string)$u['varusersusername']) : '';
    if ($username === '') {
        $count_skip++;
        continue;
    }

    list($attr, $val) = frm_password_item($u);
    if ($val === '' || $attr === '') {
        // 빈 비밀번호는 files 에서도 인증 불가하므로 적재하지 않음
        $count_skip++;
        frm_out("[SKIP] 빈 비밀번호/속성: {$username}");
        continue;
    }

    $u_esc = frm_sql_str($username);
    $a_esc = frm_sql_str($attr);
    $v_esc = frm_sql_str($val);

    // 멱등: 기존 비번 check-item 제거 후 1행 INSERT
    $sql_lines[] = "DELETE FROM `{$db_table}` WHERE username='{$u_esc}' "
        . "AND attribute IN ('Cleartext-Password','NT-Password','MD5-Password');";
    $sql_lines[] = "INSERT INTO `{$db_table}` (username, attribute, op, value) "
        . "VALUES ('{$u_esc}', '{$a_esc}', ':=', '{$v_esc}');";

    $count_ok++;
    if (count($samples) < 5) {
        $samples[] = "{$username}  ({$attr})";
    }
}

$sql_lines[] = "COMMIT;";
$sql_text = implode("\n", $sql_lines) . "\n";

$sqlfile = '/tmp/fr_radcheck_migrate_' . date('Ymd-His') . '.sql';
@file_put_contents($sqlfile, $sql_text);
@chmod($sqlfile, 0600);

// ---------------------------------------------------------------------------
// 요약 출력
// ---------------------------------------------------------------------------
frm_out("==========================================================");
frm_out(" FreeRADIUS users → {$db_table} 이관");
frm_out("----------------------------------------------------------");
frm_out(" 대상 DB     : {$db_login}@{$db_server}:{$db_port} / {$db_name}");
frm_out(" 이관 대상   : {$count_ok} 명");
frm_out(" 건너뜀      : {$count_skip} 명 (빈 username/비밀번호)");
if (!empty($samples)) {
    frm_out(" 예시        : " . implode(', ', $samples) . (count($samples) >= 5 ? ' ...' : ''));
}
frm_out(" SQL 파일    : {$sqlfile}");
frm_out("==========================================================");

if ($count_ok === 0) {
    frm_out("[INFO] 이관할 사용자가 없습니다. 종료.");
    exit(0);
}

// ---------------------------------------------------------------------------
// DRY-RUN vs APPLY
// ---------------------------------------------------------------------------
if (!$APPLY) {
    frm_out("");
    frm_out("[DRY-RUN] DB 를 변경하지 않았습니다. 생성된 SQL 을 검토하세요:");
    frm_out("          less {$sqlfile}");
    frm_out("");
    frm_out("실제 적용하려면:");
    frm_out("  php " . __FILE__ . " apply");
    exit(0);
}

$mysql = frm_mysql_bin();
if ($mysql === '') {
    frm_out("[ERROR] mysql/mariadb 클라이언트를 찾지 못했습니다. 적용 중단.");
    frm_out("        수동 적용: mysql -h{$db_server} -P{$db_port} -u{$db_login} -p {$db_name} < {$sqlfile}");
    exit(1);
}

// 비밀번호가 프로세스 목록(argv)에 노출되지 않도록 임시 defaults 파일 사용
$cnf = tempnam('/tmp', 'frmycnf');
if ($cnf === false) {
    frm_out("[ERROR] 임시 자격증명 파일 생성 실패. 적용 중단.");
    exit(1);
}
$cnf_text = "[client]\n"
    . "host=" . $db_server . "\n"
    . "port=" . $db_port . "\n"
    . "user=" . $db_login . "\n"
    . 'password="' . str_replace('"', '\\"', $db_pass) . "\"\n";
@file_put_contents($cnf, $cnf_text);
@chmod($cnf, 0600);

$cmd = escapeshellarg($mysql)
    . ' --defaults-extra-file=' . escapeshellarg($cnf)
    . ' --connect-timeout=5 '
    . escapeshellarg($db_name)
    . ' < ' . escapeshellarg($sqlfile)
    . ' 2>&1';

$out = array();
$ret = 1;
@exec($cmd, $out, $ret);
@unlink($cnf);

if ($ret === 0) {
    frm_out("");
    frm_out("[APPLY OK] {$count_ok} 명을 {$db_table} 에 반영했습니다.");
    frm_out("검증: mysql ... -e \"SELECT COUNT(*) FROM {$db_table} WHERE attribute LIKE '%-Password';\"");
    exit(0);
} else {
    frm_out("");
    frm_out("[APPLY FAILED] ret={$ret}");
    frm_out(implode("\n", $out));
    frm_out("SQL 파일 보존: {$sqlfile}");
    exit(1);
}
