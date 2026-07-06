<?php
/*
 * cp_account_history.inc (#49, #54 확장) — crew wifi 계정 변경/로그인·로그아웃/세션 사용량 이력을
 * MariaDB 에 기록
 *
 * 대상 DB: mariadb://192.168.209.210:3306 (radius/radius, MariaDB 5.5)
 * 테이블 : radius.radacct_changehistory — 없으면 자동 생성(멱등)
 *   id                 INT AUTO_INCREMENT PK
 *   timestamp          DATETIME  (박스 UTC — #41/#48 과 동일 기준)
 *   username           VARCHAR(64)   대상 계정 (per-user 조회용 인덱스; change_type 계열은
 *                                    description 선두 "user=<X> " 에서 자동 추출, login/logout
 *                                    계열은 cp_account_history_record_event() 가 명시 인자로 저장)
 *   change_type        VARCHAR(64)   password_reset / password_change / usage_reset /
 *                                    user_create / user_delete / user_modify /
 *                                    description_change / schedule_change / quota_topup /
 *                                    **login / logout** (#54)
 *   change_description VARCHAR(1024) 세부 내용(사용자·값·수행자). **실제 비밀번호 금지** —
 *                                    PW 는 "(changed)"/"initial"/"random" 등으로만 표기.
 *   client_ip          VARCHAR(45)   login/logout 전용 (그 외 change_type 은 '')
 *   client_mac         VARCHAR(20)   login/logout 전용
 *   session_id         VARCHAR(64)   login/logout 전용 (로그인·로그아웃 쌍 상관용)
 *   session_duration   INT           logout 전용(초), 그 외 0
 *   input_octets       BIGINT        logout 전용(RADIUS Input-Octets 합계), 그 외 0
 *   output_octets      BIGINT        logout 전용(RADIUS Output-Octets 합계), 그 외 0
 *
 * 조회: cp_account_history_fetch($username,$from,$to,$limit,$tab) — crew_account.php 의 per-user
 *       "History" 버튼 모달(#50, #54 로 Change/Login/Usage 3탭)이 crew_account_history_data.php 를
 *       통해 사용. $tab='change'(기본, login/logout 제외) | 'login'(login+logout) | 'usage'(logout 만).
 *
 * 호출처(#49 시점 계정 변경 writer 전수):
 *   - etc/inc/manage_crew_wifi_account.inc  (crew/prepaid 웹 + API bulk-create/delete 공용)
 *   - usr/local/www/widgets/widgets/manage_freeradiususer.widget.php (대시보드 위젯 4분기)
 *   - etc/inc/api/models/APIFreeRadiusUser{Create,Update,Topup}.inc (원격 REST)
 *   - etc/inc/captiveportal.inc commit_change_pw (포털 자가 비번변경)
 *   (주기 리셋 크론(daily/weekly/…/selfheal)은 관리자 행위가 아니라 **의도적으로 제외** —
 *    유저수×주기만큼 매일 쌓여 노이즈. 필요 시 같은 helper 로 한 줄 추가 가능.)
 *
 * 호출처(#54 login/logout, cp_account_history_record_event 경유 — captiveportal.inc 의
 * cp_log_account_login()/cp_log_account_logout() 참고):
 *   - portal_allow()            — 신규 세션 로그인 시 (동시로그인 재사용/음성 경로는 자동 제외)
 *   - captiveportal_disconnect()        — 모든 개별 세션 종료(로그아웃/타임아웃/쿼터초과/재인증실패/
 *                                          동시로그인 킥/데이터리셋 등)의 단일 관문
 *   - captiveportal_radius_stop_all()   — 대량 disconnect_all 경로
 *   (게스트 passthrough 'unauthenticated' 는 계정이 없어 기록 대상에서 제외.)
 *
 * 실행부는 cp_gmt_history.inc(#48)의 mysql CLI 헬퍼(cp_gmt_history_exec_sql)를 재사용
 * — 같은 DB/자격증명, defaults-extra-file(비번 argv 미노출) + connect-timeout + timeout 바운드.
 * 실패해도 throw 하지 않고 false + log_error (계정 변경 흐름 불가침).
 *
 * 스키마 self-heal(#54): 이 테이블이 #49 시절 5컬럼(신규 6개 컬럼 없음)으로 이미 배포된 박스가
 * 실사용에서 확인됨 — CREATE TABLE IF NOT EXISTS 는 테이블이 이미 있으면 무효과라 그대로 두면
 * login/logout/usage 기록이 "컬럼 없음" 에러로 조용히 실패한다. record_event()/fetch() 호출
 * 시작부에서 cp_account_history_ensure_schema()가 information_schema 로 누락 컬럼을 확인해
 * 자동 ALTER TABLE ADD COLUMN(MariaDB 5.5 는 ADD COLUMN IF NOT EXISTS 미지원이라 이 방식 필요).
 * 최초 1회만 DB 왕복하고 이후엔 `/var/run/cp_account_history_schema_ok` 마커로 즉시 스킵.
 */

// #48 헬퍼 재사용 (버전섞임 가드 — 미배포면 record 가 조용히 false)
if (file_exists('/etc/inc/cp_gmt_history.inc')) {
	require_once('/etc/inc/cp_gmt_history.inc');
}

define('CP_ACCT_HISTORY_TABLE', 'radacct_changehistory');
// #54 self-heal: 스키마 확인 성공 시 마커 터치 — 이후 호출은 DB 왕복 없이 즉시 스킵.
// /var/run(tmpfs) 라 재부팅 시 소실 → 1회 재확인(무해·멱등).
define('CP_ACCT_HISTORY_SCHEMA_MARKER', '/var/run/cp_account_history_schema_ok');

/**
 * #54 스키마 self-heal: radacct_changehistory 가 #49 시절 5컬럼(client_ip 등 6개 컬럼 없음)으로
 * 이미 배포된 박스에서 login/logout/usage 기록이 "컬럼 없음" SQL 에러로 조용히 실패하는 것을 방지.
 * CREATE TABLE IF NOT EXISTS 는 테이블이 이미 존재하면 아무 효과가 없으므로(#48 과 동일 한계),
 * information_schema 로 실제 컬럼 목록을 확인해 **누락분만** ALTER TABLE ADD COLUMN 한다.
 * MariaDB 5.5 는 `ALTER ... ADD COLUMN IF NOT EXISTS` 미지원(10.0.2+ 전용)이라 이 방식이 필요.
 * 신규(테이블 없음) 박스에서는 이 함수의 ALTER 가 "Unknown table" 로 실패하지만 무해 — 호출부가
 * 이어서 실행하는 CREATE TABLE IF NOT EXISTS + INSERT/SELECT 가 정상적으로 전체 스키마를 만든다.
 * 실패해도 throw 없이 false(호출측 흐름 불가침, #48/#49 패턴과 동일).
 */
function cp_account_history_ensure_schema() {
	if (@file_exists(CP_ACCT_HISTORY_SCHEMA_MARKER)) {
		return true;
	}
	if (!function_exists('cp_gmt_history_exec_sql') || !function_exists('cp_gmt_history_sql_str')) {
		return false;
	}

	// #54 신규 컬럼 6개 (인덱스 idx_session 은 성능용이라 self-heal 대상에서 제외 — 없어도 기능 정상)
	$required = array(
		'client_ip'        => "VARCHAR(45) NOT NULL DEFAULT ''",
		'client_mac'       => "VARCHAR(20) NOT NULL DEFAULT ''",
		'session_id'       => "VARCHAR(64) NOT NULL DEFAULT ''",
		'session_duration' => 'INT NOT NULL DEFAULT 0',
		'input_octets'     => 'BIGINT NOT NULL DEFAULT 0',
		'output_octets'    => 'BIGINT NOT NULL DEFAULT 0',
	);

	$sql = "SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS`"
		. ' WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = \''
		. cp_gmt_history_sql_str(CP_ACCT_HISTORY_TABLE) . "';\n";
	$out = array();
	$ret = cp_gmt_history_exec_sql($sql, '-N -B', $out);
	if ($ret !== 0) {
		if (function_exists('log_error')) {
			log_error('ACCT HISTORY: schema check failed: ' . implode(' | ', $out));
		}
		return false;
	}
	$existing = array_map('trim', $out);

	$missingCols = array();
	$alterClauses = array();
	foreach ($required as $col => $def) {
		if (!in_array($col, $existing, true)) {
			$missingCols[] = $col;
			$alterClauses[] = "ADD COLUMN `{$col}` {$def}";
		}
	}
	if (empty($alterClauses)) {
		@touch(CP_ACCT_HISTORY_SCHEMA_MARKER);
		return true;
	}

	$alter = 'ALTER TABLE `' . CP_ACCT_HISTORY_TABLE . '` ' . implode(', ', $alterClauses) . ";\n";
	$out2 = array();
	$ret2 = cp_gmt_history_exec_sql($alter, '', $out2);
	if ($ret2 !== 0) {
		if (function_exists('log_error')) {
			log_error('ACCT HISTORY: schema self-heal ALTER failed (missing=' . implode(',', $missingCols) . '): ' . implode(' | ', $out2));
		}
		return false;
	}
	if (function_exists('log_error')) {
		log_error('ACCT HISTORY: schema self-heal added columns: ' . implode(',', $missingCols));
	}
	@touch(CP_ACCT_HISTORY_SCHEMA_MARKER);
	return true;
}

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
	// #54: login/logout + 세션 사용량 탭을 위한 6컬럼 추가(전부 기본값 있음 — 기존
	// change_type 이벤트(cp_account_history_record)는 그대로 기본값으로 남아 하위호환).
	// 아직 어떤 선박에도 이 테이블이 배포된 적이 없어(CLAUDE.md #49/#50) 마이그레이션 불필요 —
	// CREATE TABLE 단계에서 바로 최종 스키마로 생성한다.
	return 'CREATE TABLE IF NOT EXISTS `' . CP_ACCT_HISTORY_TABLE . "` (\n"
		. "  `id` INT NOT NULL AUTO_INCREMENT,\n"
		. "  `timestamp` DATETIME NOT NULL,\n"
		. "  `username` VARCHAR(64) NOT NULL DEFAULT '',\n"
		. "  `change_type` VARCHAR(64) NOT NULL DEFAULT '',\n"
		. "  `change_description` VARCHAR(1024) NOT NULL DEFAULT '',\n"
		. "  `client_ip` VARCHAR(45) NOT NULL DEFAULT '',\n"
		. "  `client_mac` VARCHAR(20) NOT NULL DEFAULT '',\n"
		. "  `session_id` VARCHAR(64) NOT NULL DEFAULT '',\n"
		. "  `session_duration` INT NOT NULL DEFAULT 0,\n"
		. "  `input_octets` BIGINT NOT NULL DEFAULT 0,\n"
		. "  `output_octets` BIGINT NOT NULL DEFAULT 0,\n"
		. "  PRIMARY KEY (`id`),\n"
		. "  KEY `idx_username` (`username`),\n"
		. "  KEY `idx_session` (`session_id`)\n"
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
 * #54: 로그인/로그아웃 + 세션 사용량 단일 이벤트 기록 (실시간 1건, 배치 아님).
 * cp_account_history_record() 와 달리 username/ip/mac/session_id/duration/bytes 를
 * 명시적 컬럼으로 받는다 — "user=<name> ..." 접두사 파싱에 의존하지 않는다.
 *
 * @param string $change_type  'login' 또는 'logout'
 * @param string $username     대상 계정명
 * @param string $ip           클라이언트 IP (없으면 '')
 * @param string $mac          클라이언트 MAC (없으면 '')
 * @param string $sessionid    캡티브포털 세션ID (없으면 '')
 * @param int    $duration     세션 길이(초). login 행은 0.
 * @param int    $bytesIn      RADIUS Input-Octets 합계. login 행은 0.
 * @param int    $bytesOut     RADIUS Output-Octets 합계. login 행은 0.
 * @param string $description  사람이 읽는 요약(실제 비밀번호 금지 규칙은 여기 무관)
 * @return bool 성공 여부
 */
function cp_account_history_record_event($change_type, $username, $ip, $mac, $sessionid,
	$duration, $bytesIn, $bytesOut, $description) {
	if (!function_exists('cp_gmt_history_exec_sql') || !function_exists('cp_gmt_history_sql_str')) {
		return false;
	}
	// #54: 구버전(5컬럼) 테이블이 이미 배포된 박스 self-heal(최초 1회만 DB 왕복, 이후 마커로 스킵)
	if (function_exists('cp_account_history_ensure_schema')) {
		cp_account_history_ensure_schema();
	}
	$type = substr(trim((string)$change_type), 0, 64);
	$username = substr(trim((string)$username), 0, 64);
	if ($type === '' || $username === '') {
		return false;
	}
	$ip       = substr(trim((string)$ip), 0, 45);
	$mac      = substr(trim((string)$mac), 0, 20);
	$sessionid = substr(trim((string)$sessionid), 0, 64);
	$duration = max(0, (int)$duration);
	$bytesIn  = max(0, (int)$bytesIn);
	$bytesOut = max(0, (int)$bytesOut);
	$desc = trim(str_replace(array("\r", "\n", "\t"), ' ', (string)$description));
	$desc = substr($desc, 0, 1024);
	$ts = gmdate('Y-m-d H:i:s');

	$sql = cp_account_history_create_table_sql()
		. 'INSERT INTO `' . CP_ACCT_HISTORY_TABLE . '` '
		. '(`timestamp`,`username`,`change_type`,`change_description`,`client_ip`,`client_mac`,`session_id`,`session_duration`,`input_octets`,`output_octets`) VALUES ('
		. "'{$ts}','" . cp_gmt_history_sql_str($username) . "','" . cp_gmt_history_sql_str($type) . "',"
		. "'" . cp_gmt_history_sql_str($desc) . "','" . cp_gmt_history_sql_str($ip) . "',"
		. "'" . cp_gmt_history_sql_str($mac) . "','" . cp_gmt_history_sql_str($sessionid) . "',"
		. "{$duration},{$bytesIn},{$bytesOut});\n";

	$out = array();
	$ret = cp_gmt_history_exec_sql($sql, '', $out);
	if ($ret !== 0) {
		if (function_exists('log_error')) {
			log_error("ACCT HISTORY: record_event failed ({$type} {$username}): " . implode(' | ', $out));
		}
		return false;
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
 * @param string $tab      'change'(기본, login/logout 제외) | 'login'(login+logout) | 'usage'(logout 만)
 * @return array|false [['id','timestamp','change_type','change_description','client_ip','client_mac',
 *                       'session_id','session_duration','input_octets','output_octets'], ...] — 실패 시 false
 */
function cp_account_history_fetch($username, $from_dt, $to_dt, $limit = 1000, $tab = 'change') {
	if (!function_exists('cp_gmt_history_exec_sql') || !function_exists('cp_gmt_history_sql_str')) {
		return false;
	}
	// #54: fetch 도 신규 컬럼을 SELECT 하므로 동일하게 self-heal
	if (function_exists('cp_account_history_ensure_schema')) {
		cp_account_history_ensure_schema();
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

	if ($tab === 'login') {
		$typeFilter = " AND `change_type` IN ('login','logout')";
	} elseif ($tab === 'usage') {
		$typeFilter = " AND `change_type` = 'logout'";
	} else {
		$typeFilter = " AND `change_type` NOT IN ('login','logout')";
	}

	$sql = cp_account_history_create_table_sql()
		. 'SELECT `id`,`timestamp`,`change_type`,`change_description`,`client_ip`,`client_mac`,'
		. '`session_id`,`session_duration`,`input_octets`,`output_octets` FROM `' . CP_ACCT_HISTORY_TABLE . '`'
		. " WHERE `username` = '" . cp_gmt_history_sql_str($username) . "'"
		. " AND `timestamp` >= '" . cp_gmt_history_sql_str($from_dt) . "'"
		. " AND `timestamp` <= '" . cp_gmt_history_sql_str($to_dt) . "'"
		. $typeFilter
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
		$parts = explode("\t", $line, 10);
		if (count($parts) < 10) { continue; }   // 빈/불완전 행 방어
		$rows[] = array(
			'id'                 => (int)$parts[0],
			'timestamp'          => $parts[1],
			'change_type'        => $parts[2],
			'change_description' => $parts[3],
			'client_ip'          => $parts[4],
			'client_mac'         => $parts[5],
			'session_id'         => $parts[6],
			'session_duration'   => (int)$parts[7],
			'input_octets'       => (int)$parts[8],
			'output_octets'      => (int)$parts[9],
		);
	}
	return $rows;
}
