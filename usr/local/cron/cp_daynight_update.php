<?php
/*
 * cp_daynight_update.php — GPS 위치 기반 일출/일몰(civil twilight) 자동 갱신
 *
 * 다크모드 "GPS 연동" 모드용. influx(선내 LAN)에서 현재 위경도 → 오프라인
 * date_sun_info()(cp_daynight.inc) 로 civil twilight begin/end 산출 →
 * $config['daytimecheck'] 에 캐시. 런타임 인터넷/위성통신 0 (#29 패턴).
 *
 * 기존: 외부 시스템이 day/night API(/api/v1/services/daytimecheck)로 daynight/sunrise/
 *       sunset 를 푸시했으나 레포 내 소비처가 없었다. → 그 API(엔드포인트/모델/URL 핸들러)
 *       는 삭제했고, 박스가 직접 GPS 로 계산하도록 전환. (중앙서버 푸시도 중지할 것 —
 *       남아 있으면 옛 형식이 begin/end 키를 덮어써 GPS 모드가 시스템 폴백으로 강등됨)
 *
 * 안전:
 *   - 단일 인스턴스 flock 가드 (#26)
 *   - GPS 미수신(influx 불통/(0,0))이면 아무것도 안 함 (마지막 캐시 유지 → 클라가
 *     GPS 모드라도 시스템 폴백). cp_daynight.inc 미배포면 조용히 종료(fatal 없음).
 *   - 변경 시에만 write_config — lock('freeradius_user_config') + parse_config(true)
 *     + delta(daytimecheck 키)만 재적용 (#10/#22 lost-update 패턴)
 *   - 위치 이동에 따른 초단위 변동으로 매번 쓰지 않도록 begin/end 는 분 단위 비교
 */

// ── 단일 인스턴스 가드 (#26) ──────────────────────────────────────────────────
$__cron_singleton_fp = @fopen('/tmp/cron_' . basename(__FILE__, '.php') . '.lock', 'c');
if ($__cron_singleton_fp === false || !@flock($__cron_singleton_fp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

// 일출/일몰 라이브러리 (버전 섞임 방어: 미배포면 종료)
$__dnlib = '/etc/inc/cp_daynight.inc';
if (!file_exists($__dnlib)) {
    exit(0);
}
require_once($__dnlib);
if (!function_exists('cp_daynight_civil_times')) {
    exit(0);
}

global $config;

/**
 * influx 측정치 최신 1행을 컬럼명=>값 으로 (cp_tz_offset_update.php 와 동일한 로컬 influx).
 * 크론 전용 self-contained — server_module.inc(openvpn.inc 연쇄) 비의존.
 */
function cp_dncron_influx_latest($measurement, $db) {
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

// ── 현재 위치: VSAT(vesselposition) 우선, FBB(fbbstatus) 폴백 — 미니맵/TZ 와 동일 정책
$lat = null;
$lon = null;
$src = '';

$vp = cp_dncron_influx_latest('vesselposition', 'acustatus');
if (is_array($vp) &&
    isset($vp['Latitude']) && is_numeric($vp['Latitude']) &&
    isset($vp['Longitude']) && is_numeric($vp['Longitude'])) {
    $vlat = (float)$vp['Latitude'];
    $vlon = (float)$vp['Longitude'];
    if (!($vlat == 0 && $vlon == 0)) {
        $lat = $vlat;
        $lon = $vlon;
        $src = 'vsat';
    }
}

if ($lat === null) {
    $ss = cp_dncron_influx_latest('satstatus', 'fbbstatus');
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
    echo "no GPS position available, keeping last daytimecheck\n";
    exit(0);
}

// ── civil twilight 산출 (오프라인) ────────────────────────────────────────────
$civil = cp_daynight_civil_times($lat, $lon);
if ($civil === false) {
    echo "civil time computation failed\n";
    exit(0);
}
$polar = isset($civil['polar']) ? $civil['polar'] : '';
$begin = isset($civil['begin']) ? (int)$civil['begin'] : 0;
$end   = isset($civil['end'])   ? (int)$civil['end']   : 0;

// 다음날 새벽(next civil dawn) — 일몰 이후~다음 일출 구간을 정확히 판정하기 위함.
// (긴 시간 열려 있는 페이지가 새벽을 넘겨도 캐시만으로 올바르게 라이트 전환)
$nbegin = 0;
$civilNext = cp_daynight_civil_times($lat, $lon, time() + 86400);
if (is_array($civilNext) && isset($civilNext['begin'])) {
    $nbegin = (int)$civilNext['begin'];
}

// 변경 감지(분 단위) — 위치 이동에 따른 초단위 변동으로 매번 write 하지 않도록
$old = isset($config['daytimecheck']) && is_array($config['daytimecheck'])
    ? $config['daytimecheck'] : array();
$old_polar  = isset($old['polar'])  ? (string)$old['polar'] : '';
$old_begin  = isset($old['begin'])  ? (int)$old['begin'] : 0;
$old_end    = isset($old['end'])    ? (int)$old['end']   : 0;
$old_nbegin = isset($old['nbegin']) ? (int)$old['nbegin'] : 0;
$same = ($old_polar === $polar)
    && (intdiv($old_begin, 60)  === intdiv($begin, 60))
    && (intdiv($old_end, 60)    === intdiv($end, 60))
    && (intdiv($old_nbegin, 60) === intdiv($nbegin, 60));
if ($same) {
    echo "daytimecheck unchanged, no action\n";
    exit(0);
}

/*
 * 변경 반영 — lost-update 방지(#10/#22): PW writer 와 같은 락 공유,
 * 락 안에서 최신본 재로딩 후 daytimecheck delta 만 재적용.
 */
$applied = false;
$cnf_lock = lock('freeradius_user_config', LOCK_EX);
try {
    $config = parse_config(true);
    $config['daytimecheck'] = array(
        'polar'    => $polar,
        'begin'    => $begin,
        'end'      => $end,
        'nbegin'   => $nbegin,
        'lat'      => round($lat, 4),
        'lon'      => round($lon, 4),
        'computed' => time(),
    );
    write_config("daynight civil times auto-update (GPS)");
    $applied = true;
} finally {
    unlock($cnf_lock);
}

if ($applied && function_exists('log_error')) {
    if ($polar !== '') {
        log_error(sprintf("DAYNIGHT AUTO: polar=%s (lat=%.3f lon=%.3f src=%s)", $polar, $lat, $lon, $src));
    } else {
        log_error(sprintf(
            "DAYNIGHT AUTO: civil dawn=%s dusk=%s UTC (lat=%.3f lon=%.3f src=%s)",
            gmdate('H:i', $begin), gmdate('H:i', $end), $lat, $lon, $src
        ));
    }
}
echo "daytimecheck updated\n";
exit(0);
