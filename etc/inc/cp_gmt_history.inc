<?php
/*
 * cp_gmt_history.inc (#48) — GMT time_offset 변경 이력을 MariaDB 에 기록
 *
 * 대상 DB: mariadb://192.168.209.210:3306 (radius/radius, MariaDB 5.5)
 * 테이블 : radius.gmt_history — 없으면 자동 생성(CREATE TABLE IF NOT EXISTS, 멱등)
 *   id        INT AUTO_INCREMENT PK
 *   timestamp DATETIME  (박스 UTC 기준 이벤트 시각 — #41 박스 UTC 권위 원칙)
 *   timefrom  VARCHAR(10)  변경 전 오프셋 문자열 ("9"/"-3.5"/"" = 미설정)
 *   timeto    VARCHAR(10)  변경 후 오프셋 문자열
 *
 * 호출처(= time_offset 의 모든 writer, #48 시점 전수):
 *   - usr/local/www/index.php               수동 GMT 저장 (source=manual-web)
 *   - usr/local/cron/cp_tz_offset_update.php GPS 자동 갱신 (source=auto-gps)
 *   - api/models/APIStatusSetTimeOffset.inc  외부 REST 푸시 (source=api-push)
 *
 * 설계:
 *   - mysql CLI + defaults-extra-file(비번 argv 노출 방지) + --connect-timeout
 *     — freeradius_radcheck_exec_sql(#23 step3-A) 과 동일 패턴. freeradius.inc
 *     비의존(self-contained — 크론/API 에서 무거운 패키지 include 회피).
 *   - 실패해도 throw 하지 않고 false + log_error (degrade) — GMT 저장 흐름 불가침.
 *   - /usr/bin/timeout 존재 시 전체 실행을 하드 바운드 (#40 패턴; 없으면
 *     --connect-timeout 만으로도 불통 호스트는 커버).
 *   - source 는 시스템 로그 가시화용(테이블 스키마는 사용자 지정 4컬럼 고정).
 */

define('CP_GMT_HISTORY_DB_HOST', '192.168.209.210');
define('CP_GMT_HISTORY_DB_PORT', '3306');
define('CP_GMT_HISTORY_DB_USER', 'radius');
define('CP_GMT_HISTORY_DB_PASS', 'radius');
define('CP_GMT_HISTORY_DB_NAME', 'radius');
define('CP_GMT_HISTORY_TABLE',   'gmt_history');

function cp_gmt_history_mysql_bin() {
	foreach (array('mysql', 'mariadb') as $name) {
		$out = array(); $ret = 1;
		@exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $out, $ret);
		if ($ret === 0 && !empty($out[0]) && is_executable(trim($out[0]))) {
			return trim($out[0]);
		}
	}
	foreach (array('/usr/local/bin/mysql', '/usr/local/bin/mariadb') as $p) {
		if (is_executable($p)) { return $p; }
	}
	return '';
}

function cp_gmt_history_sql_str($s) {
	return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string)$s);
}

/* 테이블 생성 DDL (CREATE TABLE IF NOT EXISTS — record/fetch 배치 앞에 항상 포함).
 * 컬럼 마이그레이션 없음: #48 배포 전까지 이 테이블이 존재하는 박스가 없어(사용자 확인)
 * 6컬럼 스키마로 바로 생성하면 됨. */
function cp_gmt_history_create_table_sql() {
	return 'CREATE TABLE IF NOT EXISTS `' . CP_GMT_HISTORY_TABLE . "` (\n"
		. "  `id` INT NOT NULL AUTO_INCREMENT,\n"
		. "  `timestamp` DATETIME NOT NULL,\n"
		. "  `timefrom` VARCHAR(10) NOT NULL DEFAULT '',\n"
		. "  `timeto` VARCHAR(10) NOT NULL DEFAULT '',\n"
		. "  `description` VARCHAR(255) NOT NULL DEFAULT '',\n"
		. "  `gps` VARCHAR(32) NOT NULL DEFAULT 'N/A',\n"
		. "  PRIMARY KEY (`id`)\n"
		. ") ENGINE=InnoDB DEFAULT CHARSET=utf8;\n";
}

/**
 * influx 측정치 최신 1행 (컬럼명=>값). cp_tz_offset_update.php 와 동일한 선내 LAN
 * influx — 2초 타임아웃, 실패 시 false (record 의 GPS 자동 채움용, self-contained).
 */
function cp_gmt_history_influx_latest($measurement, $db) {
	if (!function_exists('curl_init')) { return false; }
	$ch = curl_init();
	if ($ch === false) { return false; }
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://192.168.209.210:8086/query?q=' .
			rawurlencode("select * from {$measurement} where time > now() - 60m order by time desc limit 1") .
			'&db=' . rawurlencode($db),
		CURLOPT_TIMEOUT        => 2,
		CURLOPT_CONNECTTIMEOUT => 2,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
	));
	$response = curl_exec($ch);
	curl_close($ch);
	if (!$response) { return false; }
	$decoded = json_decode($response, true);
	if (!isset($decoded['results'][0]['series'][0]['columns']) ||
	    !isset($decoded['results'][0]['series'][0]['values'][0])) {
		return false;
	}
	$row = array();
	foreach ($decoded['results'][0]['series'][0]['columns'] as $i => $col) {
		$row[$col] = isset($decoded['results'][0]['series'][0]['values'][0][$i])
			? $decoded['results'][0]['series'][0]['values'][0][$i] : null;
	}
	return $row;
}

/**
 * 현재 GPS 좌표 문자열 "lat,lon"(소수 5자리) — VSAT(vesselposition) 우선, FBB 폴백
 * (부호 복원 포함, #29 크론과 동일 정책). 미수신/(0,0)/influx 불통이면 'N/A'.
 */
function cp_gmt_history_current_gps() {
	$vp = cp_gmt_history_influx_latest('vesselposition', 'acustatus');
	if (is_array($vp) &&
	    isset($vp['Latitude']) && is_numeric($vp['Latitude']) &&
	    isset($vp['Longitude']) && is_numeric($vp['Longitude'])) {
		$lat = (float)$vp['Latitude'];
		$lon = (float)$vp['Longitude'];
		if (!($lat == 0 && $lon == 0)) {
			return sprintf('%.5f,%.5f', $lat, $lon);
		}
	}
	$ss = cp_gmt_history_influx_latest('satstatus', 'fbbstatus');
	if (is_array($ss) &&
	    isset($ss['Latitude']) && is_numeric($ss['Latitude']) &&
	    isset($ss['Longitude']) && is_numeric($ss['Longitude'])) {
		$lat = (float)$ss['Latitude'];
		$lon = (float)$ss['Longitude'];
		// FBB GPS 는 부호 없는 값 + 방향 컬럼(-/_ 표기 혼재) → 부호 복원
		$latdir = '';
		$londir = '';
		foreach (array('lat-direction', 'lat_direction') as $k) {
			if (isset($ss[$k])) { $latdir = strtoupper(trim((string)$ss[$k])); break; }
		}
		foreach (array('lon-direction', 'lon_direction') as $k) {
			if (isset($ss[$k])) { $londir = strtoupper(trim((string)$ss[$k])); break; }
		}
		if ($lat > 0 && $latdir === 'S') { $lat = -$lat; }
		if ($lon > 0 && $londir === 'W') { $lon = -$lon; }
		if (!($lat == 0 && $lon == 0)) {
			return sprintf('%.5f,%.5f', $lat, $lon);
		}
	}
	return 'N/A';
}

/**
 * mysql CLI 실행 공통부. $flags 는 추가 mysql 옵션('-N -B' 등), $out 에 stdout 캡처.
 * @return int exit code (0=성공; mysql 미존재=127)
 */
function cp_gmt_history_exec_sql($sql, $flags = '', &$out = null) {
	$out = array();
	$mysql = cp_gmt_history_mysql_bin();
	if ($mysql === '') {
		$out[] = 'mysql client not found';
		return 127;
	}

	$cnf = @tempnam('/tmp', 'gmth');
	$sqlfile = @tempnam('/tmp', 'gmthsql');
	if ($cnf === false || $sqlfile === false) {
		if ($cnf !== false) { @unlink($cnf); }
		if ($sqlfile !== false) { @unlink($sqlfile); }
		$out[] = 'tempnam failed';
		return 1;
	}

	@file_put_contents($cnf,
		"[client]\nhost=" . CP_GMT_HISTORY_DB_HOST . "\nport=" . CP_GMT_HISTORY_DB_PORT
		. "\nuser=" . CP_GMT_HISTORY_DB_USER . "\n"
		. 'password="' . str_replace('"', '\\"', CP_GMT_HISTORY_DB_PASS) . "\"\n");
	@chmod($cnf, 0600);
	@file_put_contents($sqlfile, $sql);
	@chmod($sqlfile, 0600);

	$cmd = escapeshellarg($mysql)
		. ' --defaults-extra-file=' . escapeshellarg($cnf)
		. ' --connect-timeout=3 '
		. ($flags !== '' ? $flags . ' ' : '')
		. escapeshellarg(CP_GMT_HISTORY_DB_NAME)
		. ' < ' . escapeshellarg($sqlfile) . ' 2>&1';
	// 전체 실행 하드 바운드 (#40 패턴) — timeout 부재 시 connect-timeout 만으로 진행
	if (is_executable('/usr/bin/timeout')) {
		$cmd = '/usr/bin/timeout 8 sh -c ' . escapeshellarg($cmd);
	}
	$ret = 1;
	@exec($cmd, $out, $ret);
	@unlink($cnf);
	@unlink($sqlfile);
	return $ret;
}

/**
 * GMT time_offset 변경 1건 기록. 테이블 없으면 같은 배치에서 생성(멱등).
 *
 * @param string $timefrom    변경 전 오프셋 (미설정이면 '')
 * @param string $timeto      변경 후 오프셋
 * @param string $source      로그 가시화용 출처 태그 (manual-web/auto-gps/api-push)
 * @param string $description 세부 내용 ("Manual change from 1.2.3.4" 등, 255자 클램프)
 * @param string $gps         당시 GPS "lat,lon" — '' 이면 influx 에서 자동 취득(최대
 *                            ~4초, LAN), 미수신이면 'N/A'. 호출측이 이미 알면 전달(크론).
 * @return bool 성공 여부 (실패는 log_error 만 — 호출측 흐름에 영향 없음)
 */
function cp_gmt_history_record($timefrom, $timeto, $source = '', $description = '', $gps = '') {
	// VARCHAR 클램프 + 공백 정리
	$from = substr(trim((string)$timefrom), 0, 10);
	$to   = substr(trim((string)$timeto), 0, 10);
	$desc = substr(trim((string)$description), 0, 255);
	$gps  = trim((string)$gps);
	if ($gps === '') { $gps = cp_gmt_history_current_gps(); }
	$gps  = substr($gps, 0, 32);

	$sql = cp_gmt_history_create_table_sql()
		. 'INSERT INTO `' . CP_GMT_HISTORY_TABLE . '` (`timestamp`,`timefrom`,`timeto`,`description`,`gps`) VALUES ('
		. "'" . gmdate('Y-m-d H:i:s') . "',"
		. "'" . cp_gmt_history_sql_str($from) . "',"
		. "'" . cp_gmt_history_sql_str($to) . "',"
		. "'" . cp_gmt_history_sql_str($desc) . "',"
		. "'" . cp_gmt_history_sql_str($gps) . "');\n";

	$out = array();
	$ret = cp_gmt_history_exec_sql($sql, '', $out);
	if ($ret !== 0) {
		if (function_exists('log_error')) {
			log_error("GMT HISTORY: record failed ({$from} -> {$to}, src={$source}): "
				. implode(' | ', $out));
		}
		return false;
	}
	if (function_exists('log_error')) {
		log_error("GMT HISTORY: recorded {$from} -> {$to} (src={$source})");
	}
	return true;
}

/**
 * 기간 내 변경 이력 조회 (최신순). 테이블 없으면 같은 배치에서 생성 후 빈 결과.
 *
 * @param string $from_dt UTC 'YYYY-MM-DD HH:MM:SS'
 * @param string $to_dt   UTC 'YYYY-MM-DD HH:MM:SS'
 * @param int    $limit   최대 행수 (1~5000)
 * @return array|false [['id','timestamp','timefrom','timeto','description','gps'], ...] — 실패 시 false
 */
function cp_gmt_history_fetch($from_dt, $to_dt, $limit = 1000) {
	$re = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
	if (!preg_match($re, (string)$from_dt) || !preg_match($re, (string)$to_dt)) {
		return false;
	}
	$limit = (int)$limit;
	if ($limit < 1) { $limit = 1; }
	if ($limit > 5000) { $limit = 5000; }

	$sql = cp_gmt_history_create_table_sql()
		. 'SELECT `id`,`timestamp`,`timefrom`,`timeto`,`description`,`gps` FROM `' . CP_GMT_HISTORY_TABLE . '`'
		. " WHERE `timestamp` >= '" . cp_gmt_history_sql_str($from_dt) . "'"
		. " AND `timestamp` <= '" . cp_gmt_history_sql_str($to_dt) . "'"
		. " ORDER BY `timestamp` DESC, `id` DESC LIMIT {$limit};\n";

	$out = array();
	// -N(헤더 생략) -B(탭 구분 배치 출력)
	$ret = cp_gmt_history_exec_sql($sql, '-N -B', $out);
	if ($ret !== 0) {
		if (function_exists('log_error')) {
			log_error('GMT HISTORY: fetch failed: ' . implode(' | ', $out));
		}
		return false;
	}
	$rows = array();
	foreach ($out as $line) {
		$parts = explode("\t", $line, 6);
		if (count($parts) < 4) { continue; }
		$rows[] = array(
			'id'          => (int)$parts[0],
			'timestamp'   => $parts[1],
			'timefrom'    => $parts[2],
			'timeto'      => $parts[3],
			'description' => isset($parts[4]) ? $parts[4] : '',
			'gps'         => isset($parts[5]) ? $parts[5] : 'N/A',
		);
	}
	return $rows;
}
