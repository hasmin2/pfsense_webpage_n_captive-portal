<?php

// ── 단일 인스턴스 가드 (#26) ──────────────────────────────────────────────────
// 이전 실행이 1주기 안에 안 끝났으면(디스크풀/느린 I/O 등) 즉시 종료 → 프로세스 누적/OOM 방지.
// 의존성 없는 self-contained(버전 섞임 안전). 락 fd 는 프로세스 종료 시 자동 해제.
$__cron_singleton_fp = @fopen('/tmp/cron_' . basename(__FILE__, '.php') . '.lock', 'c');
if ($__cron_singleton_fp === false || !@flock($__cron_singleton_fp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

global $config;

function haversineGreatCircleDistance(
    $latitudeFrom,
    $longitudeFrom,
    $latitudeTo,
    $longitudeTo,
    $earthRadius = 6371000
) {
    $latFrom = deg2rad((float)$latitudeFrom);
    $lonFrom = deg2rad((float)$longitudeFrom);
    $latTo   = deg2rad((float)$latitudeTo);
    $lonTo   = deg2rad((float)$longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

    return $angle * $earthRadius;
}

/*
 * InfluxDB query
 */
$url = 'http://192.168.209.210:8086/query?q=' .
    rawurlencode('select * from vesselposition where time > now() -10m order by time desc') .
    '&db=acustatus';

$ch = curl_init();

if ($ch === false) {
    exit;
}

curl_setopt_array($ch, array(
    CURLOPT_URL            => $url,
    CURLOPT_TIMEOUT        => 1,
    CURLOPT_CONNECTTIMEOUT => 1,
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_CUSTOMREQUEST  => 'GET',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => array(
        'Content-Type: application/json'
    )
));

$response = curl_exec($ch);
$curlErr  = curl_errno($ch);
curl_close($ch);

if ($response === false || $curlErr !== 0 || trim((string)$response) === '') {
    exit;
}

$decoded = json_decode($response, true);

if (!is_array($decoded)) {
    exit;
}

/*
 * InfluxDB response 구조 확인
 */
if (
    !isset($decoded['results'][0]['series'][0]['columns']) ||
    !isset($decoded['results'][0]['series'][0]['values']) ||
    !is_array($decoded['results'][0]['series'][0]['columns']) ||
    !is_array($decoded['results'][0]['series'][0]['values'])
) {
    exit;
}

$columns = $decoded['results'][0]['series'][0]['columns'];
$values  = $decoded['results'][0]['series'][0]['values'];

/*
 * 현재값 + 이전값 최소 2개 필요
 */
if (count($values) < 2) {
    exit;
}

$headingIdx = null;
$latIdx     = null;
$lonIdx     = null;

/*
 * column index 찾기
 */
foreach ($columns as $i => $columnName) {
    switch ($columnName) {
        case 'Heading':
            $headingIdx = $i;
            break;

        case 'Latitude':
            $latIdx = $i;
            break;

        case 'Longitude':
            $lonIdx = $i;
            break;
    }
}

if ($headingIdx === null || $latIdx === null || $lonIdx === null) {
    exit;
}

/*
 * 필요한 값 존재 확인
 */
if (
    !isset($values[0][0]) ||
    !isset($values[1][0]) ||
    !isset($values[0][$headingIdx]) ||
    !isset($values[0][$latIdx]) ||
    !isset($values[0][$lonIdx]) ||
    !isset($values[1][$latIdx]) ||
    !isset($values[1][$lonIdx])
) {
    exit;
}

$headingstring = $values[0][$headingIdx];

/*
 * 기존 출력 유지
 */
echo $headingstring;

$heading = is_numeric($headingstring)
    ? $headingstring
    : 0;

$lat      = is_numeric($values[0][$latIdx]) ? (float)$values[0][$latIdx] : 0;
$lon      = is_numeric($values[0][$lonIdx]) ? (float)$values[0][$lonIdx] : 0;
$lat_last = is_numeric($values[1][$latIdx]) ? (float)$values[1][$latIdx] : 0;
$lon_last = is_numeric($values[1][$lonIdx]) ? (float)$values[1][$lonIdx] : 0;

/*
 * 방향 및 절대값 처리
 * 기존 코드에서는 양수일 때 lat_current/lon_current가 미정의될 수 있었음
 */
if ($lat < 0) {
    $latDir = 'S';
    $lat_current = abs($lat);
} else {
    $latDir = 'N';
    $lat_current = $lat;
}

if ($lon < 0) {
    $lonDir = 'W';
    $lon_current = abs($lon);
} else {
    $lonDir = 'E';
    $lon_current = $lon;
}

$current_time = $values[0][0];
$last_time    = $values[1][0];

$currentTimestamp = strtotime($current_time);
$lastTimestamp    = strtotime($last_time);

if ($currentTimestamp === false || $lastTimestamp === false) {
    exit;
}

$timegap = $currentTimestamp - $lastTimestamp;

if ($timegap <= 0) {
    $avrhrspeed = 0;
} else {
    /*
     * 기존 코드와 동일하게 earthRadius=6371 사용
     * 결과 단위는 km 기준
     */
    $distance = haversineGreatCircleDistance(
        $lat_current,
        $lon_current,
        $lat_last,
        $lon_last,
        6371
    );

    $avrhrspeed = round($distance / $timegap * 3600 / 1.852, 2);
}

$time = date("His.00", $currentTimestamp);
$date = date("ymd", $currentTimestamp);

/*
 * 기존 GPRMC 문자열 포맷 유지
 */
$current_gpsdata = "GPRMC,{$time},A,{$lat_current},{$latDir},{$lon_current},{$lonDir},{$avrhrspeed},{$heading},{$date},0.0,E,M";

/*
 * NMEA checksum 계산
 */
$checksum = 0;
$gpsdataLength = strlen($current_gpsdata);

for ($i = 0; $i < $gpsdataLength; $i++) {
    $checksum ^= ord($current_gpsdata[$i]);
}

$checksumHex = strtoupper(str_pad(dechex($checksum), 2, '0', STR_PAD_LEFT));

$current_gpsdata = "\$" . $current_gpsdata . "*" . $checksumHex;

/*
 * 기존 config 저장 로직은 주석 유지
 */
/*
if ($current_gpsdata !== $config['gpsdata']) {
    $config['gpsdata'] = $current_gpsdata;
    write_config("GPS_WRITE");
}
*/

/*
 * GPS position file write
 */
$filepath = "/etc/inc";
$gpsPositionFile = $filepath . "/gps_position.txt";

if (!is_dir($filepath)) {
    exit;
}

$gps_file = fopen($gpsPositionFile, "w");

if ($gps_file === false) {
    exit;
}

fwrite($gps_file, $current_gpsdata);
fclose($gps_file);

?>