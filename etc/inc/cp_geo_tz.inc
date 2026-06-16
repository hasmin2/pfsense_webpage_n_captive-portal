<?php
/*
 * cp_geo_tz.inc (#29)
 *
 * GPS 위경도 -> 로컬 타임존 오프셋(시간) 오프라인 판정.
 *
 * 배경: 기존엔 외부 시스템이 REST API(APIStatusSetTimeOffset)로 time_offset 을
 * 밀어넣었으나 효용 저하로 폐기 예정 → 박스 내장 판정으로 전환. 시차 테이블은
 * 생성 시점에 인터넷에서 최신판(IANA timezone-boundary-builder, 해상 nautical
 * zone 포함)을 받아 cp_tz_grid.inc 로 리포에 박제 → 런타임 인터넷/위성통신 사용 0.
 *
 * 동작:
 *   1) cp_tz_grid.inc (0.5° 격자, RLE) 에서 위경도 -> IANA zone 이름
 *   2) PHP DateTimeZone(OS/PHP 내장 tzdata) 으로 현재 시점 오프셋 계산 — DST 자동
 *   3) 격자 미배포(버전 섞임)/미지 zone 이름(구 tzdata) 등 모든 실패는
 *      nautical 폴백(경도/15 반올림) 으로 우아하게 강등 — fatal 없음
 *
 * 한계(수용): PHP 내장 tzdata 가 빌드 시점(pfSense 2.5.2 = ~2021) 고정이라
 * 그 후 DST 규칙이 바뀐 일부 국가(이란 2022 폐지, 멕시코 2022, 이집트 2023,
 * 카자흐스탄 2024 등)는 해당 해역에서 1시간 오차 가능. EU/미주/아시아 주요
 * 항로의 규칙은 2007년 이후 불변이라 영향 없음.
 */

/**
 * 격자 테이블 로드 (1회 캐시). 미배포/손상 시 null.
 */
function cp_tz_grid_load() {
    static $grid = null;
    static $tried = false;
    if ($tried) {
        return $grid;
    }
    $tried = true;
    $file = dirname(__FILE__) . '/cp_tz_grid.inc';
    if (!file_exists($file)) {
        return null;
    }
    $g = @include($file);
    if (!is_array($g) || empty($g['res']) || empty($g['zones']) || empty($g['rows']) ||
        !is_array($g['zones']) || !is_array($g['rows'])) {
        return null;
    }
    $grid = $g;
    return $grid;
}

/**
 * 위경도 -> IANA zone 이름. 격자 미배포/범위 밖이면 null.
 */
function cp_tz_zone_for_position($lat, $lon) {
    if (!is_numeric($lat) || !is_numeric($lon)) {
        return null;
    }
    $grid = cp_tz_grid_load();
    if ($grid === null) {
        return null;
    }
    $lat = (float)$lat;
    $lon = (float)$lon;
    // 경도 정규화 [-180, 180)
    while ($lon >= 180) { $lon -= 360; }
    while ($lon < -180) { $lon += 360; }
    if ($lat > 89.999) { $lat = 89.999; }
    if ($lat < -89.999) { $lat = -89.999; }

    $res = (float)$grid['res'];
    $nrows = count($grid['rows']);
    $ncols = (int)round(360 / $res);
    $r = (int)floor((90 - $lat) / $res);
    $c = (int)floor(($lon + 180) / $res);
    if ($r < 0) { $r = 0; }
    if ($r >= $nrows) { $r = $nrows - 1; }
    if ($c < 0) { $c = 0; }
    if ($c >= $ncols) { $c = $ncols - 1; }

    // RLE 행 디코드: "zoneIdx(base36):run(base36),..." 누적 run 으로 열 위치 탐색
    $acc = 0;
    foreach (explode(',', $grid['rows'][$r]) as $token) {
        $p = explode(':', $token);
        if (count($p) !== 2) {
            return null;   // 손상 행
        }
        $acc += intval($p[1], 36);
        if ($c < $acc) {
            $zi = intval($p[0], 36);
            return isset($grid['zones'][$zi]) ? $grid['zones'][$zi] : null;
        }
    }
    return null;
}

/**
 * IANA zone 이름 -> 현재 시점 UTC 오프셋(시간, float — 5.5/5.75 지원, DST 반영).
 * 박스의 구버전 tzdata 가 모르는 신규/개명 zone 은 별칭으로 재시도. 실패 시 null.
 */
function cp_tz_zone_offset_hours($zone, $when = null) {
    if (!is_string($zone) || $zone === '') {
        return null;
    }
    // 2021년 이후 개명/신설 zone -> pfSense 2.5.2(PHP 7.4) tzdata 가 아는 옛 이름
    $aliases = array(
        'Europe/Kyiv'           => 'Europe/Kiev',
        'Pacific/Kanton'        => 'Pacific/Enderbury',
        'America/Ciudad_Juarez' => 'America/Ojinaga',
        'America/Nuuk'          => 'America/Godthab',
        'Asia/Qostanay'         => 'Asia/Almaty',
    );
    $candidates = array($zone);
    if (isset($aliases[$zone])) {
        $candidates[] = $aliases[$zone];
    }
    foreach ($candidates as $cand) {
        try {
            $tz = new DateTimeZone($cand);
            $at = ($when !== null) ? new DateTime('@' . (int)$when) : new DateTime('now');
            return $tz->getOffset($at) / 3600.0;
        } catch (Exception $e) {
            // 다음 후보
        }
    }
    return null;
}

/**
 * nautical(항해) 타임존 폴백: 경도 15° 밴드 반올림. 항상 성공.
 */
function cp_tz_nautical_offset_hours($lon) {
    $lon = (float)$lon;
    while ($lon >= 180) { $lon -= 360; }
    while ($lon < -180) { $lon += 360; }
    return (float)round($lon / 15);
}

/**
 * 위경도 -> 현재 UTC 오프셋(시간). 격자→DateTimeZone 실패 시 nautical 폴백.
 * 반환은 항상 float (예: 9.0, 5.5, -3.0).
 */
function cp_tz_offset_hours($lat, $lon, $when = null) {
    $zone = cp_tz_zone_for_position($lat, $lon);
    if ($zone !== null) {
        $h = cp_tz_zone_offset_hours($zone, $when);
        if ($h !== null) {
            return $h;
        }
    }
    return cp_tz_nautical_offset_hours($lon);
}

/**
 * 오프셋(시간) -> config 저장 문자열. 기존 수동/외부 API 값과 같은 표기
 * (정수는 "9"/"-3", 반시간대만 소수 "5.5" — 표시부 무수정 호환).
 */
function cp_tz_format_offset($hours) {
    $s = number_format((float)$hours, 2, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    if ($s === '' || $s === '-0') {
        return '0';
    }
    return $s;
}
