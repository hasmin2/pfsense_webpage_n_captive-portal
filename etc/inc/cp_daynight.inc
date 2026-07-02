<?php
/*
 * cp_daynight.inc — GPS 위경도 기반 오프라인 일출/일몰(civil twilight) 판정.
 *
 * 다크모드의 "GPS 연동" 모드용. 현재 위치의 civil twilight(태양 -6°, "밖이
 * 어둑해지는" 경계) 시각을 PHP 7.4 내장 date_sun_info() 로 계산한다.
 * 인터넷/위성통신 0 (#29 time_offset 오프라인화와 동일 철학).
 *
 * 소비: cp_daynight_update.php(크론)가 결과를 $config['daytimecheck'] 에 캐시 →
 *       common_ui.inc print_css_n_head() 가 읽어 CP_SUN 으로 주입 →
 *       클라이언트(common.js)가 now(UTC) 와 begin/end(UTC epoch) 비교로 다크 판정.
 * day/night 판정은 절대시각(UTC epoch) 비교라 타임존 계산 불필요.
 */

/**
 * 현재 위치의 civil twilight 경계(다크↔라이트)를 구한다.
 *
 * 반환:
 *   array('polar'=>'',      'begin'=>epoch, 'end'=>epoch)  // 정상(일반 위도)
 *   array('polar'=>'day')                                  // 백야: 항상 밝음
 *   array('polar'=>'night')                                // 극야: 항상 어두움
 *   false                                                  // lat/lon 무효/계산 불가
 *
 * begin = civil dawn(라이트 시작), end = civil dusk(다크 시작).
 */
function cp_daynight_civil_times($lat, $lon, $when = null) {
    if (!is_numeric($lat) || !is_numeric($lon)) {
        return false;
    }
    if (!function_exists('date_sun_info')) {
        return false;
    }
    if ($when === null) {
        $when = time();
    }
    $info = date_sun_info((int)$when, (float)$lat, (float)$lon);
    if (!is_array($info)) {
        return false;
    }
    $b = isset($info['civil_twilight_begin']) ? $info['civil_twilight_begin'] : null;
    $e = isset($info['civil_twilight_end'])   ? $info['civil_twilight_end']   : null;

    // 극지방: date_sun_info 는 epoch 대신 true(하루 종일 그 고도 위)/
    // false(하루 종일 아래)를 반환한다. true=항상 밝음(낮), false=항상 어두움(밤).
    if ($b === true || $e === true) {
        return array('polar' => 'day');
    }
    if ($b === false || $e === false) {
        return array('polar' => 'night');
    }
    if (!is_numeric($b) || !is_numeric($e)) {
        return false;
    }
    return array('polar' => '', 'begin' => (int)$b, 'end' => (int)$e);
}

/**
 * 주어진 시각(UTC epoch)이 '밤'(다크여야 함)인지. 정상 케이스 전용.
 * begin <= now < end 이면 낮(라이트), 아니면 밤(다크).
 */
function cp_daynight_is_night($begin, $end, $now = null) {
    if ($now === null) {
        $now = time();
    }
    return !($now >= $begin && $now < $end);
}
?>
