<?php
/**
 * freeradius_enable_radcheck_override.php  (#23 step 3-B)
 *
 * 인증(authorize)에서 radcheck(SQL)를 비밀번호 권위로 만든다.
 * 켜면 files 가 사용자를 찾아도 sql1 이 항상 실행되어 radcheck 의 ':=' 비번 check-item 이
 * files 비번을 덮어쓴다 → 비번 변경이 radiusd reload/재시작 없이 즉시 반영되고,
 * 재시작으로 인한 accounting(1813) 단절도 사라진다.
 *
 * 생성되는 unlang (sites-enabled/default authorize, 플래그 on 일 때 — serverdefault_resync):
 *   files
 *   if (ok || updated) { update control { &Tmp-Integer-0 := 1 } }   # files 매칭 표식
 *   if (Auth-Type != Accept) {
 *       sql1 { fail = 1 }          # radcheck 조회(:= override). DB 불통(fail)이어도 계속.
 *       if (fail) { ok }           # MySQL 불통 → files 비번으로 graceful fallback
 *       elsif ((notfound||noop) && 표식없음) { ldap 폴백 → reject }  # 둘 다 없을 때만 reject
 *   }
 *
 * 상태별 동작 요약:
 *   files O / radcheck O / DB up  → radcheck 비번으로 인증(즉시 반영) ← 이 기능의 목적
 *   files O / radcheck X          → files 비번(기존과 동일, 안전 강등)
 *   files O / DB down             → files 비번(기존과 동일, graceful fallback)
 *   files X / radcheck O          → radcheck 비번(step1 과 동일)
 *   files X / radcheck X          → reject(기존과 동일)
 *
 * 전제(이 도구가 검사함):
 *   1) step1 적용: varsqlconfincludeenable=on + varsqlconfenableauthorize=Enable
 *      (미적용이면 중단 — 먼저 freeradius_enable_sql_authorize.php apply)
 *   2) step2/3-A 적재: radcheck 에 비번 행 존재. 미적재 사용자는 files 강등으로 동작엔
 *      안전하나 즉시반영 효과가 없으므로 커버리지를 점검해 경고한다.
 *   3) 버전 섞임 방지: 배포된 freeradius.inc 가 step3-B 템플릿을 포함하는지
 *      (재생성된 default 사이트에 Tmp-Integer-0 마커 존재) 확인 — 없으면 롤백.
 *
 * 적용 전 검증/롤백:
 *   플래그 적용 → serverdefault_resync(파일만 생성, 재시작 X) → 마커 확인 + radiusd -C →
 *   실패 시 플래그 원복 + 재생성 후 중단(재시작 안 함) → 인증 중단 사고 방지.
 *
 * !! 적용 후 필수 검증 (CLAUDE.md #23 step3-B 위험분석) !!
 *   - radiusd -X 로: 기존 사용자 정상 인증 / 비번 변경 → 재시작 없이 즉시 새 비번 / 옛 비번 거부
 *   - DB-down 테스트: MySQL 차단 상태에서 files 비번으로 로그인 되는지(fallback) 확인
 *   - MySQL 이 위성 너머 원격이면 켜지 말 것(매 인증마다 SQL 조회 = 인증 임계경로 지연)
 *
 * 사용:
 *   php /usr/local/sbin/freeradius_enable_radcheck_override.php           # dry-run(상태/사전점검만)
 *   php /usr/local/sbin/freeradius_enable_radcheck_override.php apply     # 켜기(검증 통과 시 재시작)
 *   php /usr/local/sbin/freeradius_enable_radcheck_override.php disable   # 끄기(files 권위 복귀 + 재시작)
 *
 * 끄기(rollback)는 항상 안전: 3-A dual-write 가 files 를 계속 최신으로 유지하므로
 * 플래그를 끄는 즉시 기존(files 권위) 동작으로 정상 복귀한다.
 *
 * 동시성 안전(#10/#22): PW writer 들과 같은 lock('freeradius_user_config') +
 * parse_config(true) 재로딩 후 수정 → lost-update 방지.
 *
 * 종료코드: 0=성공, 1=오류/검증실패
 */

require_once("config.inc");
require_once("util.inc");
require_once("freeradius.inc");

global $config, $argv;

function fro_out($msg) { echo $msg . "\n"; }

$arg = isset($argv[1]) ? strtolower((string)$argv[1]) : '';
$APPLY   = ($arg === 'apply');
$DISABLE = ($arg === 'disable');

// ---------------------------------------------------------------------------
// radcheck 적재 커버리지 점검 헬퍼
//   반환: array(distinct username => true)  /  false = DB 불통  /  null = 점검 불가($why 에 사유)
// ---------------------------------------------------------------------------
function fro_radcheck_distinct_users(&$why) {
    $why = '';
    if (!function_exists('freeradius_radcheck_conn_params') ||
        !function_exists('freeradius_radcheck_mysql_bin')) {
        $why = 'freeradius.inc 에 step3-A 헬퍼 없음 = 구버전(버전 섞임)';
        return null;
    }
    $c = freeradius_radcheck_conn_params();
    if ($c === null) {
        $why = 'freeradiussqlconf 접속 파라미터 없음';
        return null;
    }
    $mysql = freeradius_radcheck_mysql_bin();
    if ($mysql === '') {
        $why = 'mysql 클라이언트 바이너리 없음';
        return null;
    }
    $cnf = @tempnam('/tmp', 'fro');
    if ($cnf === false) { return null; }
    @file_put_contents($cnf,
        "[client]\nhost={$c['server']}\nport={$c['port']}\nuser={$c['login']}\n"
        . 'password="' . str_replace('"', '\\"', $c['pass']) . "\"\n");
    @chmod($cnf, 0600);
    $q = "SELECT DISTINCT username FROM `{$c['table']}` "
       . "WHERE attribute IN ('Cleartext-Password','NT-Password','MD5-Password');";
    $cmd = escapeshellarg($mysql)
        . ' --defaults-extra-file=' . escapeshellarg($cnf)
        . ' --connect-timeout=4 -N -B -e ' . escapeshellarg($q)
        . ' ' . escapeshellarg($c['db']) . ' 2>&1';
    $out = array(); $ret = 1;
    @exec($cmd, $out, $ret);
    @unlink($cnf);
    if ($ret !== 0) { return false; }   // DB 불통/인증 실패
    $users = array();
    foreach ($out as $line) {
        $line = trim($line);
        if ($line !== '') { $users[$line] = true; }
    }
    return $users;
}

function fro_config_usernames($cfg) {
    $arr = is_array($cfg['installedpackages']['freeradius']['config'] ?? null)
        ? $cfg['installedpackages']['freeradius']['config'] : array();
    $names = array();
    foreach ($arr as $u) {
        if (!is_array($u)) { continue; }
        $n = trim((string)($u['varusersusername'] ?? ''));
        if ($n !== '') { $names[] = $n; }
    }
    return $names;
}

// ---------------------------------------------------------------------------
// 락 + 최신 config 재로딩 (lost-update 방지)
// ---------------------------------------------------------------------------
$cfglock = lock('freeradius_user_config', LOCK_EX);
parse_config(true);

if (!is_array($config['installedpackages']['freeradiussqlconf']['config'][0] ?? null)) {
    unlock($cfglock);
    fro_out("[ERROR] freeradiussqlconf 설정이 없습니다. SQL(MySQL) 연결 설정이 선행되어야 합니다.");
    exit(1);
}

$cur = $config['installedpackages']['freeradiussqlconf']['config'][0];
$cur_include   = $cur['varsqlconfincludeenable']  ?? '';
$cur_authorize = $cur['varsqlconfenableauthorize'] ?? '';
$cur_flag      = $config['system']['freeradius_radcheck_override'] ?? '';

fro_out("==========================================================");
fro_out(" FreeRADIUS radcheck(SQL) 비밀번호 권위화 토글 (#23 step3-B)");
fro_out("----------------------------------------------------------");
fro_out(" 현재 varsqlconfincludeenable          = '" . $cur_include . "'");
fro_out(" 현재 varsqlconfenableauthorize        = '" . $cur_authorize . "'");
fro_out(" 현재 system/freeradius_radcheck_override = '" . $cur_flag . "'");

// ---------------------------------------------------------------------------
// 사전점검 (dry-run 에서도 보여주고, apply 에서는 강제)
// ---------------------------------------------------------------------------
$prereq_ok = ($cur_include === 'on' && $cur_authorize === 'Enable');

$cfg_users = fro_config_usernames($config);
$rc_why    = '';
$rc_users  = fro_radcheck_distinct_users($rc_why);

fro_out("----------------------------------------------------------");
fro_out(" [사전점검]");
fro_out("  - step1(SQL authorize) : " . ($prereq_ok ? "OK" : "미적용 — freeradius_enable_sql_authorize.php apply 먼저"));
if ($rc_users === null) {
    fro_out("  - radcheck 점검        : 불가 (" . $rc_why . ")");
} elseif ($rc_users === false) {
    fro_out("  - radcheck 점검        : DB 불통 (mysql 접속 실패 — 켜도 효과 없음, 점검 필요)");
} else {
    $missing = array();
    foreach ($cfg_users as $n) {
        if (!isset($rc_users[$n])) { $missing[] = $n; }
    }
    fro_out("  - config 사용자        : " . count($cfg_users) . "명 / radcheck 비번 행: " . count($rc_users) . "명");
    if (!empty($missing)) {
        fro_out("  - radcheck 미적재      : " . count($missing) . "명 (예: "
            . implode(', ', array_slice($missing, 0, 5))
            . (count($missing) > 5 ? ' ...' : '') . ")");
        fro_out("      → 미적재 사용자는 files 비번으로 강등되어 동작엔 안전하나 즉시반영 효과가 없음.");
        fro_out("      → 보충: php /usr/local/sbin/freeradius_migrate_users_to_radcheck.php apply");
    } else {
        fro_out("  - radcheck 커버리지    : 전원 적재됨");
    }
}

// ---------------------------------------------------------------------------
// DRY-RUN
// ---------------------------------------------------------------------------
if (!$APPLY && !$DISABLE) {
    unlock($cfglock);
    fro_out("----------------------------------------------------------");
    fro_out(" [DRY-RUN] 변경하지 않았습니다.");
    fro_out("   켜기 : php " . __FILE__ . " apply");
    fro_out("   끄기 : php " . __FILE__ . " disable");
    fro_out("==========================================================");
    exit(0);
}

// 멱등 체크
if ($APPLY && $cur_flag === 'on') {
    unlock($cfglock);
    fro_out(" [NO-OP] 이미 radcheck 권위화가 켜져 있습니다.");
    exit(0);
}
if ($DISABLE && $cur_flag !== 'on') {
    unlock($cfglock);
    fro_out(" [NO-OP] 이미 radcheck 권위화가 꺼져 있습니다.");
    exit(0);
}

// apply 강제 사전조건: step1 + radcheck 점검 가능 + DB 도달
if ($APPLY) {
    if (!$prereq_ok) {
        unlock($cfglock);
        fro_out(" [ABORT] step1(SQL authorize) 미적용 상태입니다. 먼저:");
        fro_out("         php /usr/local/sbin/freeradius_enable_sql_authorize.php apply");
        exit(1);
    }
    if ($rc_users === null) {
        unlock($cfglock);
        fro_out(" [ABORT] radcheck 점검 불가: " . $rc_why);
        fro_out("         (구버전 freeradius.inc 면 최신 develop 일괄 배포, mysql 부재면 환경 점검)");
        exit(1);
    }
    if ($rc_users === false) {
        unlock($cfglock);
        fro_out(" [ABORT] MySQL(radcheck) 접속 실패. DB 가 불통인 상태에서 켜는 것은 무의미합니다.");
        fro_out("         (켜져 있어도 fallback 으로 안전하긴 하나, 효과가 없고 환경 미비 신호임)");
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// 플래그 적용 + write_config (락 안에서)
// ---------------------------------------------------------------------------
if ($APPLY) {
    $config['system']['freeradius_radcheck_override'] = 'on';
} else {
    unset($config['system']['freeradius_radcheck_override']);
}
write_config("FreeRADIUS: radcheck(SQL) 비밀번호 권위화 " . ($APPLY ? "ON" : "OFF") . " (#23 step3-B)");
unlock($cfglock);

fro_out(" 적용 system/freeradius_radcheck_override = '" . ($APPLY ? 'on' : '') . "'");
fro_out("----------------------------------------------------------");

// ---------------------------------------------------------------------------
// 적용 전 검증: 사이트 설정만 먼저 생성(재시작 X) → 마커 확인 + radiusd -C
// ---------------------------------------------------------------------------
freeradius_serverdefault_resync();

$validate_ok = true;
$fail_reason = '';

// (1) 버전 섞임 검증: 켤 때는 생성된 default 에 step3-B 마커가 있어야 한다
$site_file = '/usr/local/etc/raddb/sites-enabled/default';
if (defined('FREERADIUS_SITESENABLED')) {
    $site_file = FREERADIUS_SITESENABLED . '/default';
}
$site_conf = @file_get_contents($site_file);
$has_marker = (is_string($site_conf) && strpos($site_conf, 'Tmp-Integer-0') !== false);
if ($APPLY && !$has_marker) {
    $validate_ok = false;
    $fail_reason = "생성된 default 사이트에 step3-B 블록이 없음 — freeradius.inc 구버전(버전 섞임)";
}
if ($DISABLE && $has_marker) {
    $validate_ok = false;
    $fail_reason = "끄기 후에도 step3-B 블록이 남아 있음 — 비정상";
}

// (2) radiusd -C 문법 검증
if ($validate_ok && function_exists('freeradius_validate_radiusd_config')) {
    if (!freeradius_validate_radiusd_config()) {
        $validate_ok = false;
        $fail_reason = "radiusd -C 실패 (생성 설정이 유효하지 않음)";
    }
}

if (!$validate_ok) {
    fro_out(" [VALIDATE FAILED] " . $fail_reason);
    fro_out(" 롤백합니다(재시작 안 함).");

    // 롤백: 락 재획득 → 최신 재로딩 → 옛 값 복원 → 저장 → 사이트 재생성
    $cfglock2 = lock('freeradius_user_config', LOCK_EX);
    parse_config(true);
    if ($cur_flag === 'on') {
        $config['system']['freeradius_radcheck_override'] = 'on';
    } else {
        unset($config['system']['freeradius_radcheck_override']);
    }
    write_config("FreeRADIUS: radcheck 권위화 토글 롤백(검증 실패) (#23 step3-B)");
    unlock($cfglock2);
    freeradius_serverdefault_resync();

    fro_out("==========================================================");
    exit(1);
}

// ---------------------------------------------------------------------------
// 정식 적용: radiusd 재시작 (사이트 구조 변경은 HUP 로 반영 불가)
// ---------------------------------------------------------------------------
fro_out(" [VALIDATE OK] radiusd 재시작 진행...");
$restart_ok = freeradius_reload_or_restart_radiusd(false);
if (!$restart_ok) {
    fro_out(" [WARN] radiusd 재시작 실패 — 'service radiusd onerestart' 수동 실행 필요");
}

fro_out("==========================================================");
if ($APPLY) {
    fro_out(" 완료(ON). 필수 검증:");
    fro_out("   1) 비번 변경 → 재시작 없이 즉시 새 비번 로그인 / 옛 비번 거부");
    fro_out("   2) 기존 사용자(files) 전원 정상 인증 (radiusd -X 로 sql1 := override 확인)");
    fro_out("   3) DB-down 테스트: MySQL 차단 후 files 비번으로 로그인 되는지(fallback)");
    fro_out("   문제 발생 시 즉시: php " . __FILE__ . " disable  (files 권위 복귀, 항상 안전)");
} else {
    fro_out(" 완료(OFF). files 권위로 복귀했습니다 (3-A dual-write 덕에 files 는 항상 최신).");
}
fro_out("==========================================================");
exit(0);
