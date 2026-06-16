<?php
/*
 * crew_usage_reset_selfheal.php
 *
 * CREW WIFI 사용량 리셋 "자가복구" 크론.
 *
 * 주기 경계 크론(daily/weekly/halfmonthly/monthly)이 NTP 시각 점프/재부팅/고부하로
 * 발화를 놓쳐 리셋이 누락된 경우, 이 크론이 다음 틱에서 날짜키 비교로 보충 리셋한다.
 * (cp_usage_reset.inc 참고.)
 *
 * 동작:
 *   - 각 유저의 (pointoftime, halftimeperiod)로 현재 주기키를 계산.
 *   - 저장된 마지막 리셋 키보다 "엄격히 최신"인데 마킹 안 됨 → 경계 크론이 놓침 → 즉시 리셋.
 *   - config.xml 을 쓰지 않아 매분 writer 크론과 동시 실행해도 안전.
 *
 * 스케줄(권장): 분 15,45 (매 30분) — 경계 크론(0~10분)과 겹치지 않게 오프셋.
 */
require_once("captiveportal.inc");
require_once("cp_usage_reset.inc");

init_config_arr(['captiveportal']);
global $config, $cpzone;
$cpzone = "crew";

if (!function_exists('cp_usage_reset_run_selfheal')) {
    // 버전 섞임(구버전 배포)에서 헬퍼 부재 시 조용히 종료.
    exit;
}

$done = cp_usage_reset_run_selfheal();

if (!empty($done)) {
    cp_wireless_log("SELF-HEAL reset applied to " . count($done) . " user(s): " . implode(',', $done));
}
