<?php
/*
 * cp_tz_offset_update.php (#29) — 매시 GPS 위치 기반 time_offset 자동 갱신
 *
 * 기존: 외부 시스템이 REST API(APIStatusSetTimeOffset)로 time_offset 푸시(효용 저하).
 * 변경: 박스가 직접 — influx(선내 LAN)에서 현재 위경도 → 오프라인 시차 격자
 *       (cp_tz_grid.inc, 생성 시 인터넷에서 받아 박제) → 현재 오프셋 산출.
 *       런타임 인터넷/위성통신 사용 0. 표시부(사이드바/미니맵 시계/CP)는 기존
 *       $config['time_offset_enabled']['time_offset'] 을 그대로 읽으므로 무수정.
 *
 * 안전:
 *   - gmtcheck='1'(Manual Timezone Enable) 이면 절대 덮지 않음 (API 와 동일 규약)
 *   - 변경 시에만 write_config — lock('freeradius_user_config') + parse_config(true)
 *     재로딩 + delta 만 재적용 (#10/#22 lost-update 패턴)
 *   - GPS 미수신(influx 불통/(0,0))이면 아무것도 안 함 (마지막 오프셋 유지)
 *   - cp_geo_tz.inc 미배포(버전 섞임)면 조용히 종료 — fatal 없음
 */

// ── 단일 인스턴스 가드 (#26) ──────────────────────────────────────────────────
$__cron_singleton_fp = @fopen('/tmp/cron_' . basename(__FILE__, '.php') . '.lock', 'c');
if ($__cron_singleton_fp === false || !@flock($__cron_singleton_fp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

// 시차 격자 라이브러리 (버전 섞임 방어: 미배포면 종료)
$__tzlib = '/etc/inc/cp_geo_tz.inc';
if (!file_exists($__tzlib)) {
    exit(0);
}
require_once($__tzlib);
if (!function_exists('cp_tz_offset_hours') || !function_exists('cp_tz_format_offset')) {
    exit(0);
}

global $config;

// 수동 타임존 모드면 자동 갱신 금지.
//   가드는 사이드바 표시(!empty)와 동일한 truthy 의미로 판정한다. gmtcheck 가
//   '1' 이 아닌 다른 truthy 값(레거시/API 저장)이어도 "Manual" 로 취급해 자동
//   갱신을 막아야 수동 0.5(반시간대) 선택이 정수로 덮어써지지 않는다.
//   ('0'/''/미설정 = 자동 모드 → 진행)
if (!empty($config['time_offset_enabled']['gmtcheck'])) {
    echo "manual timezone enabled, no action\n";
    exit(0);
}

/**
 * influx 측정치 최신 1행을 컬럼명=>값 으로 (gps_update.php 와 동일한 로컬 influx).
 * 크론 전용 self-contained — server_module.inc(openvpn.inc 연쇄) 비의존.
 */
function cp_tzcron_influx_latest($measurement, $db) {
    $ch = curl_init();
    if ($ch === false) {
        return false;
    }
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
    if (!$response) {
        return false;
    }
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

// ── 현재 위치: VSAT(vesselposition) 우선, FBB(fbbstatus) 폴백 — 미니맵과 동일 정책
$lat = null;
$lon = null;
$src = '';

$vp = cp_tzcron_influx_latest('vesselposition', 'acustatus');
if (is_array($vp) &&
    isset($vp['Latitude']) && is_numeric($vp['Latitude']) &&
    isset($vp['Longitude']) && is_numeric($vp['Longitude'])) {
    $vlat = (float)$vp['Latitude'];
    $vlon = (float)$vp['Longitude'];
    // (0,0) = GPS 미수신/파싱실패 기본값
    if (!($vlat == 0 && $vlon == 0)) {
        $lat = $vlat;
        $lon = $vlon;
        $src = 'vsat';
    }
}

if ($lat === null) {
    $ss = cp_tzcron_influx_latest('satstatus', 'fbbstatus');
    if (is_array($ss) &&
        isset($ss['Latitude']) && is_numeric($ss['Latitude']) &&
        isset($ss['Longitude']) && is_numeric($ss['Longitude'])) {
        $flat = (float)$ss['Latitude'];
        $flon = (float)$ss['Longitude'];
        // FBB GPS 는 부호 없는 값 + 방향 컬럼(-/_ 표기 혼재) → 부호 복원
        $latdir = '';
        $londir = '';
        foreach (array('lat-direction', 'lat_direction') as $k) {
            if (isset($ss[$k])) { $latdir = strtoupper(trim((string)$ss[$k])); break; }
        }
        foreach (array('lon-direction', 'lon_direction') as $k) {
            if (isset($ss[$k])) { $londir = strtoupper(trim((string)$ss[$k])); break; }
        }
        if ($flat > 0 && $latdir === 'S') { $flat = -$flat; }
        if ($flon > 0 && $londir === 'W') { $flon = -$flon; }
        if (!($flat == 0 && $flon == 0)) {
            $lat = $flat;
            $lon = $flon;
            $src = 'fbb';
        }
    }
}

if ($lat === null || $lon === null) {
    echo "no GPS position available, keeping last offset\n";
    exit(0);
}

// ── 오프셋 산출 (격자 → DateTimeZone, 실패 시 nautical 폴백 — 항상 성공)
$zone = function_exists('cp_tz_zone_for_position') ? cp_tz_zone_for_position($lat, $lon) : null;
$hours = cp_tz_offset_hours($lat, $lon);
$new_offset = cp_tz_format_offset($hours);

$cur_offset = isset($config['time_offset_enabled']['time_offset'])
    ? (string)$config['time_offset_enabled']['time_offset'] : '';

if ($cur_offset !== '' && is_numeric($cur_offset) && (float)$cur_offset == $hours) {
    echo "offset unchanged ({$new_offset}), no action\n";
    exit(0);
}

/*
 * 변경 반영 — lost-update 방지(#10/#22): PW writer 와 같은 락 공유,
 * 락 안에서 최신본 재로딩 후 이 크론의 delta(time_offset 한 키)만 재적용.
 */
$applied = false;
$cnf_lock = lock('freeradius_user_config', LOCK_EX);
try {
    $config = parse_config(true);
    // 락 대기 중 수동 모드로 바뀌었을 수 있음 — 재확인 (truthy = 수동 → 적용 금지)
    // (exit 는 finally 를 타지 않으므로 락 안에서는 플래그만 세우고 빠져나온다)
    if (empty($config['time_offset_enabled']['gmtcheck'])) {
        if (!isset($config['time_offset_enabled']) || !is_array($config['time_offset_enabled'])) {
            $config['time_offset_enabled'] = array();
        }
        $config['time_offset_enabled']['time_offset'] = $new_offset;
        write_config("timezone offset auto-update (GPS): {$cur_offset} -> {$new_offset}");
        $applied = true;
    }
} finally {
    unlock($cnf_lock);
}
if (!$applied) {
    echo "manual timezone enabled (recheck), no action\n";
    exit(0);
}

// #48: GMT 변경 이력 → radius.gmt_history (락 밖 느린 I/O — #22 패턴, 버전섞임 가드)
if (!function_exists('cp_gmt_history_record') && file_exists('/etc/inc/cp_gmt_history.inc')) {
    require_once('/etc/inc/cp_gmt_history.inc');
}
if (function_exists('cp_gmt_history_record')) {
    cp_gmt_history_record($cur_offset, $new_offset, 'auto-gps');
}

$zinfo = ($zone !== null) ? $zone : 'nautical-fallback';
if (function_exists('log_error')) {
    log_error(sprintf(
        "TZ AUTO: offset %s -> %s (lat=%.3f lon=%.3f src=%s zone=%s)",
        ($cur_offset === '' ? 'unset' : $cur_offset), $new_offset, $lat, $lon, $src, $zinfo
    ));
}
echo "offset updated: {$cur_offset} -> {$new_offset} (zone={$zinfo})\n";
exit(0);
