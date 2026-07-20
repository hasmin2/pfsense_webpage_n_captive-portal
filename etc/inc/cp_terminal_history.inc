<?php
/*
 * cp_terminal_history.inc (#57) — Terminal Status(terminal.php) 변경 이력을 MariaDB 에 기록
 *
 * 대상 DB: mariadb://192.168.209.210:3306 (radius/radius, MariaDB 5.5) — #48/#49 와 동일 DB.
 * 테이블 : radius.terminal_status_history — 없으면 자동 생성(CREATE TABLE IF NOT EXISTS, 멱등)
 *   id          INT AUTO_INCREMENT PK
 *   timestamp   DATETIME       (박스 UTC 기준 — #41/#48 과 동일 기준)
 *   admin_id    VARCHAR(64)    변경 당시 로그인 관리자 ID (get_config_user(), 세션 없으면 '')
 *   client_ip   VARCHAR(45)    변경 요청 클라이언트 IP ($_SERVER['REMOTE_ADDR'])
 *   description VARCHAR(1024)  변경 내역 요약(Manual Override 라우팅 변경 / Data Cutoff
 *                               allowance·cutoff 변경을 사람이 읽는 문자열로)
 *
 * 호출처: usr/local/www/terminal.php — routing_radiobutton(Manual Override)/allowance
 *          (Data Cutoff) POST 처리 직후, 실제로 뭔가 바뀐 경우에만 1행 기록.
 * 조회   : cp_terminal_history_fetch() — terminal_history_data.php 가 HISTORY 버튼 모달에서 사용.
 *
 * 실행부는 cp_gmt_history.inc(#48)의 mysql CLI 헬퍼(cp_gmt_history_exec_sql/_sql_str)를
 * 재사용 — 같은 DB/자격증명, defaults-extra-file(비번 argv 미노출) + connect-timeout + timeout
 * 바운드. 실패해도 throw 하지 않고 false + log_error (터미널 설정 저장 흐름 불가침).
 */

// #48 헬퍼 재사용 (버전섞임 가드 — 미배포면 record/fetch 가 조용히 false)
if (!function_exists('cp_gmt_history_exec_sql') && file_exists('/etc/inc/cp_gmt_history.inc')) {
    require_once('/etc/inc/cp_gmt_history.inc');
}

define('CP_TERMINAL_HISTORY_TABLE', 'terminal_status_history');

/* 변경 당시 로그인 관리자 ID. pfSense 세션의 실제 webConfigurator 계정명
 * (get_config_user()) 을 우선 사용 — $adminlogin(common_ui.inc) 은 role 카테고리
 * (admin/customer/vesseladmin)일 뿐이라 "누구"인지 특정 못 함. 세션 없으면 빈 문자열. */
function cp_terminal_history_actor() {
    if (function_exists('get_config_user')) {
        $u = trim((string)get_config_user());
        if ($u !== '') {
            return $u;
        }
    }
    if (!empty($_SESSION['Username'])) {
        return trim(str_replace(array("\r", "\n", "\t"), ' ', (string)$_SESSION['Username']));
    }
    return '';
}

function cp_terminal_history_client_ip() {
    return !empty($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
}

function cp_terminal_history_create_table_sql() {
    return 'CREATE TABLE IF NOT EXISTS `' . CP_TERMINAL_HISTORY_TABLE . "` (\n"
        . "  `id` INT NOT NULL AUTO_INCREMENT,\n"
        . "  `timestamp` DATETIME NOT NULL,\n"
        . "  `admin_id` VARCHAR(64) NOT NULL DEFAULT '',\n"
        . "  `client_ip` VARCHAR(45) NOT NULL DEFAULT '',\n"
        . "  `description` VARCHAR(1024) NOT NULL DEFAULT '',\n"
        . "  PRIMARY KEY (`id`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8;\n";
}

/**
 * Terminal Status 변경 1건 기록. 테이블 없으면 같은 배치에서 생성(멱등).
 *
 * @param string $description 변경 내역 ("LANDLINE: allowance 100->200GB" 등, 1024자 클램프).
 *                             빈 문자열이면 기록하지 않음(실제 변경이 없었다는 뜻).
 * @return bool 성공 여부 (실패는 log_error 만 — 호출측 흐름에 영향 없음)
 */
function cp_terminal_history_record($description) {
    if (!function_exists('cp_gmt_history_exec_sql') || !function_exists('cp_gmt_history_sql_str')) {
        return false;
    }
    $desc = trim(str_replace(array("\r", "\n", "\t"), ' ', (string)$description));
    if ($desc === '') {
        return false;
    }
    $desc = substr($desc, 0, 1024);
    $admin = substr(cp_terminal_history_actor(), 0, 64);
    $ip = substr(cp_terminal_history_client_ip(), 0, 45);

    $sql = cp_terminal_history_create_table_sql()
        . 'INSERT INTO `' . CP_TERMINAL_HISTORY_TABLE . '` (`timestamp`,`admin_id`,`client_ip`,`description`) VALUES ('
        . "'" . gmdate('Y-m-d H:i:s') . "',"
        . "'" . cp_gmt_history_sql_str($admin) . "',"
        . "'" . cp_gmt_history_sql_str($ip) . "',"
        . "'" . cp_gmt_history_sql_str($desc) . "');\n";

    $out = array();
    $ret = cp_gmt_history_exec_sql($sql, '', $out);
    if ($ret !== 0) {
        if (function_exists('log_error')) {
            log_error('TERMINAL HISTORY: record failed: ' . implode(' | ', $out));
        }
        return false;
    }
    if (function_exists('log_error')) {
        log_error("TERMINAL HISTORY: recorded ({$admin}@{$ip}): {$desc}");
    }
    return true;
}

/**
 * 기간 내 Terminal Status 변경 이력 조회 (최신순). 테이블 없으면 같은 배치에서 생성 후 빈 결과.
 *
 * @param string $from_dt UTC 'YYYY-MM-DD HH:MM:SS'
 * @param string $to_dt   UTC 'YYYY-MM-DD HH:MM:SS'
 * @param int    $limit   최대 행수 (1~5000)
 * @return array|false [['id','timestamp','admin_id','client_ip','description'], ...] — 실패 시 false
 */
function cp_terminal_history_fetch($from_dt, $to_dt, $limit = 1000) {
    if (!function_exists('cp_gmt_history_exec_sql') || !function_exists('cp_gmt_history_sql_str')) {
        return false;
    }
    $re = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
    if (!preg_match($re, (string)$from_dt) || !preg_match($re, (string)$to_dt)) {
        return false;
    }
    $limit = (int)$limit;
    if ($limit < 1) { $limit = 1; }
    if ($limit > 5000) { $limit = 5000; }

    $sql = cp_terminal_history_create_table_sql()
        . 'SELECT `id`,`timestamp`,`admin_id`,`client_ip`,`description` FROM `' . CP_TERMINAL_HISTORY_TABLE . '`'
        . " WHERE `timestamp` >= '" . cp_gmt_history_sql_str($from_dt) . "'"
        . " AND `timestamp` <= '" . cp_gmt_history_sql_str($to_dt) . "'"
        . " ORDER BY `timestamp` DESC, `id` DESC LIMIT {$limit};\n";

    $out = array();
    // -N(헤더 생략) -B(탭 구분 배치 출력)
    $ret = cp_gmt_history_exec_sql($sql, '-N -B', $out);
    if ($ret !== 0) {
        if (function_exists('log_error')) {
            log_error('TERMINAL HISTORY: fetch failed: ' . implode(' | ', $out));
        }
        return false;
    }
    $rows = array();
    foreach ($out as $line) {
        $parts = explode("\t", $line, 5);
        if (count($parts) < 5) { continue; }   // 빈/불완전 행 방어
        $rows[] = array(
            'id'          => (int)$parts[0],
            'timestamp'   => $parts[1],
            'admin_id'    => $parts[2],
            'client_ip'   => $parts[3],
            'description' => $parts[4],
        );
    }
    return $rows;
}
