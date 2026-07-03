<?php
/*
 * cp_account_history.inc (#49) — crew wifi 계정 변경 이력을 MariaDB 에 기록
 *
 * 대상 DB: mariadb://192.168.209.210:3306 (radius/radius, MariaDB 5.5)
 * 테이블 : radius.radacct_changehistory — 없으면 자동 생성(멱등)
 *   id                 INT AUTO_INCREMENT PK
 *   timestamp          DATETIME  (박스 UTC — #41/#48 과 동일 기준)
 *   username           VARCHAR(64)   변경 대상 계정 (per-user 조회용 인덱스; description
 *                                    선두 "user=<X> " 에서 자동 추출 — 훅 수정 불필요)
 *   change_type        VARCHAR(64)   password_reset / password_change / usage_reset /
 *                                    user_create / user_delete / user_modify /
 *                                    description_change / schedule_change / quota_topup
 *   change_description VARCHAR(1024) 세부 내용(사용자·값·수행자). **실제 비밀번호 금지** —
 *                                    PW 는 "(changed)"/"initial"/"random" 등으로만 표기.
 *
 * 조회: cp_account_history_fetch($username,$from,$to) — crew_account.php 의 per-user
 *       "History" 버튼 모달(#50)이 crew_account_history_data.php 를 통해 사용.
 *
 * 호출처(#49 시점 계정 변경 writer 전수):
 *   - etc/inc/manage_crew_wifi_account.inc  (crew/prepaid 웹 + API bulk-create/delete 공용)
 *   - usr/local/www/widgets/widgets/manage_freeradiususer.widget.php (대시보드 위젯 4분기)
 *   - etc/inc/api/models/APIFreeRadiusUser{Create,Update,Topup}.inc (원격 REST)
 *   - etc/inc/captiveportal.inc commit_change_pw (포털 자가 비번변경)
 *   (주기 리셋 크론(daily/weekly/…/selfheal)은 관리자 행위가 아니라 **의도적으로 제외** —
 *    유저수×주기만큼 매일 쌓여 노이즈. 필요 시 같은 helper 로 한 줄 추가 가능.)
 *
 * 실행부는 cp_gmt_history.inc(#48)의 mysql CLI 헬퍼(cp_gmt_history_exec_sql)를 재사용
 * — 같은 DB/자격증명, defaults-extra-file(비번 argv 미노출) + connect-timeout + timeout 바운드.
 * 실패해도 throw 하지 않고 false + log_error (계정 변경 흐름 불가침).
 */

// #48 헬퍼 재사용 (버전섞임 가드 — 미배포면 record 가 조용히 false)
if (file_exists('/etc/inc/cp_gmt_history.inc')) {
	require_once('/etc/inc/cp_gmt_history.inc');
}

define('CP_ACCT_HISTORY_TABLE', 'radacct_changehistory');

/* prepaid(crewpay-) 계정이면 " (CREWPAY)" 태그, 아니면 ''.
 * 코드베이스 전역 prepaid 식별 규약과 동일(username 접두사 crewpay-, 대소문자 무시) —
 *   manage_crew_wifi_account.inc build_wifi_rows 필터 / captiveportal index.php 로그인 시
 *   접두사 부여 / cp_usage_reset.inc·prepaid 크론 제외 기준과 일치. 진입점(crew/prepaid 페이지·
 *   위젯·API)과 무관하게 "변경 대상 계정이 prepaid 인가"로 판정하므로 항상 정확. */
function cp_account_history_tag($username) {
	return preg_match('/^crewpay-/i', (string)$username) ? ' (CREWPAY)' : '';
}

/* 수행자 식별: 관리 세션 사용자명 → 호출자 IP → 'system' (cp_admin_actor 있으면 그대로) */
function cp_account_history_actor() {
	if (function_exists('cp_admin_actor')) {
		return cp_admin_actor();
	}
	if (!empty($_SESSION['Username'])) {
		return trim(str_replace(array("\r", "\n", "\t"), ' ', (string)$_SESSION['Username']));
	}
	if (!empty($_SERVER['REMOTE_ADDR'])) {
		return (string)$_SERVER['REMOTE_ADDR'];
	}
	return 'system';
}

function cp_account_history_create_table_sql() {
	return 'CREATE TABLE IF NOT EXISTS `' . CP_ACCT_HISTORY_TABLE . "` (\n"
		. "  `id` INT NOT NULL AUTO_INCREMENT,\n"
		. "  `timestamp` DATETIME NOT NULL,\n"
		. "  `username` VARCHAR(64) NOT NULL DEFAULT '',\n"
		. "  `change_type` VARCHAR(64) NOT NULL DEFAULT '',\n"
		. "  `change_description` VARCHAR(1024) NOT NULL DEFAULT '',\n"
		. "  PRIMARY KEY (`id`),\n"
		. "  KEY `idx_username` (`username`)\n"
		. ") ENGINE=InnoDB DEFAULT CHARSET=utf8;\n";
}

/* description 선두의 "user=<username> ..." 에서 대상 계정명 추출 (없으면 '').
 * 모든 #49 훅이 desc 를 "user={username} ..." 로 시작하도록 만들므로 신뢰 가능.
 * username 은 [A-Za-z0-9._-] + crewpay- 접두사(공백 없음)라 \S+ 로 안전 절취. */
function cp_account_history_extract_username($desc) {
	if (preg_match('/^user=(\S+)/', (string)$desc, $m)) {
		return substr($m[1], 0, 64);
	}
	return '';
}

/**
 * 계정 변경 이력 기록 — 사용자별 1행. 다건은 배열로 넘기면 단일 INSERT 배치.
 *
 * @param string       $change_type  변경 유형 태그 (64자 클램프)
 * @param string|array $descriptions 세부 내용 1건(문자열) 또는 여러 건(배열, 행당 1개).
 *                                   **실제 비밀번호를 절대 포함하지 말 것.**
 * @return bool 성공 여부 (실패는 log_error 만 — 호출측 흐름에 영향 없음)
 */
function cp_account_history_record($change_type, $descriptions) {
	// #48 실행부 미배포(버전섞임) → 조용히 skip
	if (!function_exists('cp_gmt_history_exec_sql') || !function_exists('cp_gmt_history_sql_str')) {
		return false;
	}
	$type = substr(trim((string)$change_type), 0, 64);
	if ($type === '') {
		return false;
	}
	if (!is_array($descriptions)) {
		$descriptions = array($descriptions);
	}
	$ts = gmdate('Y-m-d H:i:s');
	$vals = array();
	foreach ($descriptions as $d) {
		$d = trim(str_replace(array("\r", "\n", "\t"), ' ', (string)$d));
		if ($d === '') {
			continue;
		}
		$d = substr($d, 0, 1024);
		$uname = cp_account_history_extract_username($d);   // per-user 조회용
		$vals[] = "('{$ts}','" . cp_gmt_history_sql_str($uname) . "','"
			. cp_gmt_history_sql_str($type) . "','" . cp_gmt_history_sql_str($d) . "')";
	}
	if (empty($vals)) {
		return false;
	}

	$sql = cp_account_history_create_table_sql()
		. 'INSERT INTO `' . CP_ACCT_HISTORY_TABLE . '` (`timestamp`,`username`,`change_type`,`change_description`) VALUES '
		. implode(",\n", $vals) . ";\n";

	$out = array();
	$ret = cp_gmt_history_exec_sql($sql, '', $out);
	if ($ret !== 0) {
		if (function_exists('log_error')) {
			log_error("ACCT HISTORY: record failed ({$type} x" . count($vals) . "): " . implode(' | ', $out));
		}
		return false;
	}
	if (function_exists('log_error')) {
		log_error("ACCT HISTORY: recorded {$type} x" . count($vals));
	}
	return true;
}

/**
 * 한 계정의 변경 이력 조회 (최신순). 테이블 없으면 같은 배치에서 생성 후 빈 결과.
 *
 * @param string $username 대상 계정명 ([A-Za-z0-9._-] + crewpay- 접두사)
 * @param string $from_dt  UTC 'YYYY-MM-DD HH:MM:SS'
 * @param string $to_dt    UTC 'YYYY-MM-DD HH:MM:SS'
 * @param int    $limit    최대 행수 (1~5000)
 * @return array|false [['id','timestamp','change_type','change_description'], ...] — 실패 시 false
 */
function cp_account_history_fetch($username, $from_dt, $to_dt, $limit = 1000) {
	if (!function_exists('cp_gmt_history_exec_sql') || !function_exists('cp_gmt_history_sql_str')) {
		return false;
	}
	$username = trim((string)$username);
	// username 형식 방어 (SQL 은 escape 하지만 예상 밖 입력 조기 차단)
	if ($username === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
		return false;
	}
	$re = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
	if (!preg_match($re, (string)$from_dt) || !preg_match($re, (string)$to_dt)) {
		return false;
	}
	$limit = (int)$limit;
	if ($limit < 1) { $limit = 1; }
	if ($limit > 5000) { $limit = 5000; }

	$sql = cp_account_history_create_table_sql()
		. 'SELECT `id`,`timestamp`,`change_type`,`change_description` FROM `' . CP_ACCT_HISTORY_TABLE . '`'
		. " WHERE `username` = '" . cp_gmt_history_sql_str($username) . "'"
		. " AND `timestamp` >= '" . cp_gmt_history_sql_str($from_dt) . "'"
		. " AND `timestamp` <= '" . cp_gmt_history_sql_str($to_dt) . "'"
		. " ORDER BY `timestamp` DESC, `id` DESC LIMIT {$limit};\n";

	$out = array();
	// -N(헤더 생략) -B(탭 구분 배치 출력). description 은 record 가 \t 제거하므로 안전.
	$ret = cp_gmt_history_exec_sql($sql, '-N -B', $out);
	if ($ret !== 0) {
		if (function_exists('log_error')) {
			log_error('ACCT HISTORY: fetch failed (' . $username . '): ' . implode(' | ', $out));
		}
		return false;
	}
	$rows = array();
	foreach ($out as $line) {
		$parts = explode("\t", $line, 4);
		if (count($parts) < 4) { continue; }   // 빈/불완전 행 방어
		$rows[] = array(
			'id'                 => (int)$parts[0],
			'timestamp'          => $parts[1],
			'change_type'        => $parts[2],
			'change_description' => $parts[3],
		);
	}
	return $rows;
}
