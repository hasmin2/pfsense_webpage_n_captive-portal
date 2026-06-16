<?php
require_once("openvpn.inc");

function gps_degree($value){
    $deg = intval($value);
    $min = ($value - $deg) * 60;
    $min = round($min, 3);
    return $deg."&deg;".$min."'";
}
function calculateHeading($lat1, $lon1, $lat2, $lon2) {
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    $dLon = $lon2 - $lon1;
    $y = sin($dLon) * cos($lat2);
    $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);
    $initial_bearing = atan2($y, $x);
    $initial_bearing = rad2deg($initial_bearing);
    $compass_bearing = (fmod(($initial_bearing + 360), 360));
    return $compass_bearing;
}

function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000){
    // convert from degrees to radians
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}

function check_vsat_status_influxdb(){
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20*%20from%20vesselposition%20where%20time%20%3E%20now()%20-60m%20order%20by%20time%20desc&db=acustatus',
        CURLOPT_TIMEOUT => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => GET,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        )
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    if(!$response){
        return array ("-1");
    }
    else {
        $decoded = json_decode($response, true);
        $resultcount = count($decoded['results'][0]['series'][0]['values']);
        $headingIdx = 0;
        $latIdx = 0;
        $lonIdx = 0;
        $columncount= count($decoded['results'][0]['series'][0]['columns']);
        if($resultcount <= 0){
            return array ("-2");
        }
        else if ($resultcount > 0 && $columncount <= 2){
            return array("-3");
        }
        else{
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20Longitude,"AGC/Signal",TX_Mode%20from%20satstatus%20where%20time%20%3E%20now()%20-10m%20order%20by%20time%20desc&db=acustatus',
                CURLOPT_TIMEOUT => 1,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_CUSTOMREQUEST => GET,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                )
            ));
            $satelliteresponse = curl_exec($ch);
            curl_close($ch);
            $satellite_decoded = json_decode($satelliteresponse, true);
            $satelliteid=0;
            $satellitesignal=0;
            $satellitetx=0;
            if($satellite_decoded['results'][0]['series'][0]['values'][0][1]){
                $satelliteid=$satellite_decoded['results'][0]['series'][0]['values'][0][1];
                $satellitesignal=$satellite_decoded['results'][0]['series'][0]['values'][0][2];
                $satellitetx=$satellite_decoded['results'][0]['series'][0]['values'][0][3];
            }
            for ($i = 0; $i < $columncount; $i++){
                switch($decoded['results'][0]['series'][0]['columns'][$i]){
                    case "Heading":
                        $headingIdx = $i;
                        break;
                    case "Latitude":
                        $latIdx = $i;
                        break;
                    case "Longitude":
                        $lonIdx = $i;
                        break;
                }
            }
            $headingstring = $decoded['results'][0]['series'][0]['values'][0][$headingIdx];
            if(is_numeric($headingstring)){
                $heading = round($headingstring, 1);
            }
            else{
                $heading = 0;
            }
            $lat = $decoded['results'][0]['series'][0]['values'][0][$latIdx];
            $lon = $decoded['results'][0]['series'][0]['values'][0][$lonIdx];
            $lat_last = end($decoded['results'][0]['series'][0]['values'])[$latIdx];
            $lon_last = end($decoded['results'][0]['series'][0]['values'])[$lonIdx];
            if($heading==0){
                $heading = round(calculateHeading($lat_last, $lon_last, $lat, $lon),1);
            }
            $distance = haversineGreatCircleDistance($lat_last, $lon_last, $lat, $lon, 6371);
            if($lat<0){
                $latDir = "S";
                $lat_current = abs($lat);
            }
            else{
                $latDir = "N";
                $lat_current = $lat;
            }
            if($lon<0){
                $lonDir = "W";
                $lon_current = abs($lon);
            }
            else{
                $lonDir = "E";
                $lon_current = $lon;
            }
            $current_time= $decoded['results'][0]['series'][0]['values'][0][0];
            $last_time= end($decoded['results'][0]['series'][0]['values'])[0];
            $timegap= strtotime($current_time) - strtotime($last_time);
            $avrhrspeed= round($distance/$timegap*3600/1.852, 2);
            $lat_deg = gps_degree($lat_current);
            $lon_deg = gps_degree($lon_current);

            // 궤도경도 표시 규약(음수=W/양수=E) 적용. 부가 효과: 포맷된 값("1W" 등)은
            // 호출측(terminal_status.inc)의 에러 센티널("-1"/"-2") 비교와 충돌하지 않음.
            return array(cp_format_satlon($satelliteid),$satellitesignal,$satellitetx,"{$lat_deg}{$latDir}<br>{$lon_deg}{$lonDir}<br>{$heading}deg. {$avrhrspeed}kts");
        }
    }
}
function check_fbb_status_influxdb(){
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20*%20from%20satstatus%20where%20time%20%3E%20now()%20-10m%20order%20by%20time%20desc&db=fbbstatus',
        CURLOPT_TIMEOUT => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => GET,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        )
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    if(!$response){
        return array ("-1");
    }
    else {
        $decoded = json_decode($response, true);
        $resultcount = count($decoded['results'][0]['series'][0]['values']);
        $headingIdx = 0;
        $latIdx = 0;
        $lonIdx = 0;
        $latDirIdx = 0;
        $lonDirIdx = 0;
        $columncount= count($decoded['results'][0]['series'][0]['columns']);
        if($resultcount <= 0){
            return array ("-2");
        }
        else if ($resultcount > 0 && $columncount <= 2){
            return array("-3");
        }
        else{
            for ($i = 0; $i < $columncount; $i++){
                switch($decoded['results'][0]['series'][0]['columns'][$i]){
                    case "Heading":
                        $headingIdx = $i;
                        break;
                    case "Latitude":
                        $latIdx = $i;
                        break;
                    case "Longitude":
                        $lonIdx = $i;
                        break;
                    case "lat-direction":
                        $latDirIdx = $i;
                        break;
                    case "lon-direction":
                        $lonDirIdx = $i;
                        break;
                    case "Signal":
                        $signalIdx = $i;
                        break;
                    case "Satellite":
                        $satelliteIdx = $i;
                        break;
                }
            }
            $headingstring = $decoded['results'][0]['series'][0]['values'][0][$headingIdx];
            if(is_numeric($headingstring)){
                $heading = round($headingstring,1);
            }
            else{
                $heading = 0;
            }
            $satelliteString = $decoded['results'][0]['series'][0]['values'][0][$satelliteIdx];
            $signalString = round($decoded['results'][0]['series'][0]['values'][0][$signalIdx], 2);

            return array($satelliteString,$signalString);
        }
    }
}
function send_api($url, $method, $postdata) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_POSTFIELDS => $postdata,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-requested-by: sdc',
            'x-sdc-application-id: servercommand',
            'Authorization: Basic YWRtaW46YWRtaW4='
        )
    ));
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return array($response, $code);
}

function reboot_svr(){ send_api('http://192.168.209.210:8999/shellcommand', 'POST', '{"command": "sudo reboot"}'); }
function reset_core(){ send_api('http://192.168.209.210:8999/shellcommand', 'POST', '{"command": "pkill -9 -ef streamsets"}'); }
function reset_fw(){send_api('http://192.168.209.210:8999/shellcommand', 'POST', '{"command": "sudo virsh reboot vessel-firewall"}'); }
function get_vpnstatus(){
$vpn_clients = openvpn_get_active_clients();
    foreach($vpn_clients as $vpn){
        if($vpn['status'] == "down"){ return "Offline"; }
    }
    return "Online";
}
// ---------------------------------------------------------------------------
// ACU 안테나 지향(트래킹) 시각화 데이터 — Main Panel(index.php) 나침반용.
//
// vesselposition(선수방위/위경도) + satstatus(위성 궤도경도/신호) 최신 1건을 읽어
// 정지궤도 위성 look-angle(방위각/앙각)을 계산해 돌려준다. influx 불통/데이터
// 부재 시엔 status=nodata 로 degrade 하며 호출측(UI)은 이를 "데이터 없음" 표시로
// 처리한다(페이지 동작에는 영향 없음).
// ---------------------------------------------------------------------------

// 측정치(measurement)의 최신 1행을 컬럼명=>값 배열로 반환. 실패 시 false.
function acu_influx_latest($measurement, $db = 'acustatus', $timeout = 1) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => 'http://192.168.209.210:8086/query?q=' .
            rawurlencode("select * from {$measurement} where time > now() - 60m order by time desc limit 1") .
            '&db=' . rawurlencode($db),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        )
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
    $cols = $decoded['results'][0]['series'][0]['columns'];
    $vals = $decoded['results'][0]['series'][0]['values'][0];
    $row = array();
    foreach ($cols as $i => $col) {
        $row[$col] = isset($vals[$i]) ? $vals[$i] : null;
    }
    return $row;
}

// 위성 궤도경도 표시 규약: 음수 = 서경(W), 양수 = 동경(E), 소수점 1자리 고정.
// (acureader 가 부호·소수점을 보존해 보내는 확정 규약 — 예: -55.0 → "55.0W",
//  62.6 → "62.6E". 0/비수치는 그대로 반환: "0"은 미추적 표시로 기존 동작 유지.)
function cp_format_satlon($satlon) {
    if (!is_numeric($satlon)) {
        return (string)$satlon;
    }
    $v = (float)$satlon;
    if ($v == 0) {
        return '0';
    }
    return number_format(abs($v), 1, '.', '') . ($v < 0 ? 'W' : 'E');
}

// 정지궤도 look-angle: 본선(lat,lon)에서 궤도경도 satlon 위성의 방위각/앙각(도).
// Az = 180° + atan2(tan Δλ, sin φ) (남/북반구 공용), tan el = (cosβ - k)/sinβ.
function cp_geo_look_angle($lat, $lon, $satlon) {
    $phi = deg2rad($lat);
    $dl  = deg2rad($lon - $satlon);
    $az = fmod(rad2deg(atan2(tan($dl), sin($phi))) + 180.0 + 360.0, 360.0);
    $cosb = cos($phi) * cos($dl);
    $sinb = sqrt(max(1e-9, 1.0 - $cosb * $cosb));
    $el = rad2deg(atan2($cosb - 0.15127, $sinb));
    return array('az' => $az, 'el' => $el);
}

function get_acu_pointing_info() {
    $out = array(
        'ok'          => false,
        'status'      => 'nodata',   // tracking | searching | blocked | nodata
        'satellite'   => '',
        'signal'      => null,
        'heading'     => null,
        'azimuth'     => null,
        'rel_azimuth' => null,
        'elevation'   => null,
        'lat'         => null,       // #28 항구 미니맵용 수치형 본선 위치
        'lon'         => null
    );

    $vp = acu_influx_latest('vesselposition');
    $ss = acu_influx_latest('satstatus');
    if ($vp === false && $ss === false) {
        return $out;
    }
    $out['ok'] = true;

    $heading = null; $lat = null; $lon = null;
    if (is_array($vp)) {
        if (isset($vp['Heading']) && is_numeric($vp['Heading'])) {
            $heading = fmod((float)$vp['Heading'] + 360, 360);
        }
        if (isset($vp['Latitude']) && is_numeric($vp['Latitude'])) {
            $lat = (float)$vp['Latitude'];
        }
        if (isset($vp['Longitude']) && is_numeric($vp['Longitude'])) {
            $lon = (float)$vp['Longitude'];
        }
    }

    // satstatus 의 Longitude = 위성 궤도경도(acureader 의 satellite 필드).
    // 추적 위성 없음은 "0" 으로 들어온다 (기존 Tracking 표시와 동일 규약).
    $satlon = null; $signal = null; $antstatus = null;
    if (is_array($ss)) {
        if (isset($ss['Longitude']) && is_numeric($ss['Longitude'])) {
            $satlon = (float)$ss['Longitude'];
        }
        if (isset($ss['AGC/Signal']) && is_numeric($ss['AGC/Signal'])) {
            $signal = round((float)$ss['AGC/Signal'], 1);
        }
        // 안테나 상태 컬럼은 파이프라인 구성에 따라 이름이 다를 수 있어 패턴 매칭
        // (1=TRACKING, 0=SEARCHING, 2=BLOCKING, -1=오류 — IntellianACUReader 규약)
        foreach ($ss as $col => $val) {
            if (preg_match('/ant.*status/i', (string)$col)) {
                $antstatus = trim((string)$val);
                break;
            }
        }
    }

    $out['heading'] = ($heading !== null) ? round($heading, 1) : null;
    $out['signal']  = $signal;
    // (0,0) 은 GPS 미수신/파싱실패 기본값으로 간주해 미니맵에 노출하지 않음
    if ($lat !== null && $lon !== null && !($lat == 0 && $lon == 0)) {
        $out['lat'] = round($lat, 5);
        $out['lon'] = round($lon, 5);
    }

    $tracked = ($satlon !== null && $satlon != 0);
    if ($tracked) {
        $out['satellite'] = cp_format_satlon($satlon);
    }

    // 상태 판정: 안테나 상태 컬럼이 있으면 그걸 우선, 없으면 위성/신호로 추정
    if ($antstatus === '2') {
        $out['status'] = 'blocked';
    } else if ($antstatus === '1') {
        $out['status'] = 'tracking';
    } else if ($antstatus === '0' || $antstatus === '-1') {
        $out['status'] = 'searching';
    } else if ($tracked && $signal !== null && $signal >= 1) {
        $out['status'] = 'tracking';
    } else if (is_array($ss)) {
        $out['status'] = 'searching';
    }

    // 정지궤도 look-angle: 위성 경도 + 본선 위경도가 모두 있어야 계산 가능
    if ($tracked && $lat !== null && $lon !== null) {
        $pickLon = $satlon;
        $look = cp_geo_look_angle($lat, $lon, $satlon);

        // 수신 부호(음수=W, 양수=E)가 1차 기준이다 — acureader 가 부호를 보존해
        // 보내는 확정 규약이므로 정상 데이터에선 아래 분기는 절대 발동하지 않는다.
        // 아래 플립은 "구버전 acureader(부호 소실: 55°W → +55)" 데이터에 대한
        // 안전망: 추적 중인 위성은 물리적으로 수평선 위(el >= 0)여야 하므로,
        // 계산 결과가 수평선 아래일 때만 반대 반구(-satlon) 후보로 복구한다.
        // (실측 검증: 중미 태평양/위성 +55 수신 → 오인 시 Az 256/el -61,
        //  -55 복원 시 Az 101/el 45 = ACU 자체 표시값과 일치.)
        // 함대 전체 acureader 갱신 완료 후에는 이 분기를 제거해도 된다.
        if ($look['el'] < 0) {
            $alt = cp_geo_look_angle($lat, $lon, -$satlon);
            if ($alt['el'] > $look['el']) {
                $pickLon = -$satlon;
                $look = $alt;
            }
        }

        // -5° 톨러런스: 수평선 부근 GPS/표본 시점 노이즈 허용.
        // 두 후보 모두 그 아래면 입력 데이터 이상 → az/el 미표시(오표시 방지).
        if ($look['el'] >= -5) {
            $out['azimuth']   = round($look['az'], 1);
            $out['elevation'] = round($look['el'], 1);
            if ($heading !== null) {
                $out['rel_azimuth'] = round(fmod($look['az'] - $heading + 360.0, 360.0), 1);
            }
            // 라벨도 채택된 반구로 표시 (음수=W, 양수=E)
            $out['satellite'] = cp_format_satlon($pickLon);
        }
    }

    return $out;
}

// ---------------------------------------------------------------------------
// FBB 안테나 지향 데이터 — VSAT 나침반에 보조 니들로 표시.
//
// FBB 단말은 궤도경도를 숫자로 보고하지 않고 "위성/해역 이름"만 보고하며,
// 그 표기가 단말기(Sailor 웹콘솔/JRC/FURUNO AT/Intellian)마다 제각각이다.
// 그래서 ① 이름 안에 명시 경도가 있으면 그걸 우선 파싱(예: "EMEA 25.0E"),
// ② 없으면 해역명 키워드 매핑(I-4/Alphasat 고정 슬롯)으로 결정하고,
// ③ 둘 다 실패하면 az/el 미산출(니들 미표시)로 우아하게 강등한다.
// 새 표기를 만나면 아래 $map 에 키워드 한 줄만 추가하면 된다.
// ---------------------------------------------------------------------------

function cp_fbb_satlon_from_name($name) {
    $n = strtoupper(trim((string)$name));
    if ($n === '' || $n === 'NONE' || $n === '0') {
        return null;
    }
    // ① 이름에 경도가 직접 포함된 표기 (예: "EMEA 25.0E", "143.5 E", "98W")
    if (preg_match('/(\d+(?:\.\d+)?)\s*(?:DEG)?\s*([EW])\b/', $n, $m)) {
        $v = (float)$m[1];
        if ($v > 0 && $v <= 180) {
            return ($m[2] === 'W') ? -$v : $v;
        }
    }
    // ② 해역명/위성명 키워드 매핑 — Inmarsat I-4/Alphasat FBB 슬롯 (2026-06 검증).
    //    부분 일치라 "I-4 Americas"/"ASIA-PACIFIC" 등 변형도 흡수.
    //    순서 중요: "Middle East & Asia"(MEAS)가 'ASIA'에 먼저 걸리지 않도록
    //    MEAS/MIDDLE 을 ASIA 보다 앞에 둔다.
    //    (I-6 등 신위성 이름이 관측되면 실제 슬롯 확인 후 여기에 추가할 것.)
    $map = array(
        'MEAS'     => 63.9,    // I-4 F2, Middle East & Asia
        'MIDDLE'   => 63.9,
        'APAC'     => 143.5,   // I-4 F1, Asia-Pacific
        'ASIA'     => 143.5,
        'AMER'     => -98.0,   // I-4 F3, Americas (98W)
        'EMEA'     => 24.9,    // Alphasat (I-4A F4)
        'ALPHASAT' => 24.9,
    );
    foreach ($map as $kw => $lonv) {
        if (strpos($n, $kw) !== false) {
            return $lonv;
        }
    }
    return null;
}

function get_fbb_pointing_info($heading = null) {
    $out = array(
        'ok'          => false,
        'status'      => 'nodata',   // tracking | searching | nodata
        'name'        => '',
        'satellite'   => '',
        'signal'      => null,
        'azimuth'     => null,
        'rel_azimuth' => null,
        'elevation'   => null,
        'lat'         => null,       // #28 항구 미니맵 폴백 위치(FBB 단독 선박)
        'lon'         => null
    );

    $ss = acu_influx_latest('satstatus', 'fbbstatus');
    if ($ss === false) {
        return $out;
    }
    $out['ok'] = true;
    $out['status'] = 'searching';

    $name = isset($ss['Satellite']) ? trim((string)$ss['Satellite']) : '';
    $out['name'] = $name;
    if (isset($ss['Signal']) && is_numeric($ss['Signal'])) {
        $out['signal'] = round((float)$ss['Signal'], 1);
    }

    $satlon = cp_fbb_satlon_from_name($name);
    if ($satlon === null) {
        return $out;   // 이름 미매칭/미추적 — 니들 없이 기존 FBB 텍스트만
    }
    $out['satellite'] = cp_format_satlon($satlon);
    if ($out['signal'] !== null && $out['signal'] >= 1) {
        $out['status'] = 'tracking';
    }

    // FBB GPS 는 부호 없는 값 + 별도 방향 컬럼(컬럼명도 단말따라 -/_ 혼재) →
    // 방향 문자로 부호 복원. (0,0)은 리더의 파싱실패 기본값이므로 제외.
    $lat = (isset($ss['Latitude']) && is_numeric($ss['Latitude'])) ? (float)$ss['Latitude'] : null;
    $lon = (isset($ss['Longitude']) && is_numeric($ss['Longitude'])) ? (float)$ss['Longitude'] : null;
    $latdir = ''; $londir = '';
    foreach (array('lat-direction', 'lat_direction') as $k) {
        if (isset($ss[$k])) { $latdir = strtoupper(trim((string)$ss[$k])); break; }
    }
    foreach (array('lon-direction', 'lon_direction') as $k) {
        if (isset($ss[$k])) { $londir = strtoupper(trim((string)$ss[$k])); break; }
    }
    if ($lat !== null && $lat > 0 && $latdir === 'S') { $lat = -$lat; }
    if ($lon !== null && $lon > 0 && $londir === 'W') { $lon = -$lon; }
    if ($lat === null || $lon === null || ($lat == 0 && $lon == 0)) {
        return $out;
    }
    $out['lat'] = round($lat, 5);
    $out['lon'] = round($lon, 5);

    $look = cp_geo_look_angle($lat, $lon, $satlon);
    // 매핑 슬롯이 틀렸거나 GPS 이상이면 수평선 아래로 나옴 → 표시 억제(-5° 톨러런스)
    if ($look['el'] >= -5) {
        $out['azimuth']   = round($look['az'], 1);
        $out['elevation'] = round($look['el'], 1);
        if ($heading !== null && is_numeric($heading)) {
            $out['rel_azimuth'] = round(fmod($look['az'] - (float)$heading + 360.0, 360.0), 1);
        }
    }
    return $out;
}

function get_core_status(){
    $pipelines_result = send_api('http://192.168.209.210:18630/rest/v1/pipelines', 'GET', '');
    $pipelines_status_result = send_api('http://192.168.209.210:18630/rest/v1/pipelines/status', 'GET', '');
    $update_result = json_decode(send_api('http://192.168.209.210:8999/getversion', 'GET', '')[0], true);
    $update_version = $update_result['data'][0]['update_version'];

    if($pipelines_result[1] === 200 && $pipelines_status_result[1] === 200) {
        $core_module_status = 'Online';
        $core_status = 'OK';
    }
    else{
        $core_status = 'Offline';
        $core_module_status = 'N/A';
    }
    $pipelines = json_decode($pipelines_result[0], true);
    $status = json_decode($pipelines_status_result[0], true);
    foreach($pipelines as $item){
        if(substr($item['title'],0,16)=== '[System Pipeline'){
            foreach($status as $statusitem){
                if($statusitem['pipelineId'] === $item['pipelineId']&&
                    $statusitem['status'] === 'EDITED' ||
                    $statusitem['status'] === 'RUN_ERROR' ||
                    $statusitem['status'] === 'STOPPED' ||
                    $statusitem['status'] === 'START_ERROR' ||
                    $statusitem['status'] === 'STOP_ERROR' ||
                    $statusitem['status'] === 'DISCONNECTED' ||
                    $statusitem['status'] === 'RUNNING_ERROR' ||
                    $statusitem['status'] === 'STARTING_ERROR' ||
                    $statusitem['status'] === 'STOPPING' ||
                    $statusitem['status'] === 'STOPPING_ERROR'
                ) {
                    $core_module_status = 'NOT OK';
                    break;
                }
            }
            if($core_module_status === 'NOT OK'){
                break;
            }
        }
    }
    if($core_module_status === "Offline"){
        $color = 'red';
    }
    else if($core_module_status === 'Online' && $core_status === 'NOT OK'){
        $color = 'yellow';
    }
    else if($core_module_status === 'Online' && $core_status === 'OK'){
        $color = 'green';
    }
    $rtnstr = $core_module_status.'<br>'.$core_status.'-'.$update_version;
    return array($rtnstr, $color);
}
?>