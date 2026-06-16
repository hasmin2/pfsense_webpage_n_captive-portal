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

/*
 * gateways 배열이 없으면 생성
 */
if (!isset($config['gateways']) || !is_array($config['gateways'])) {
    $config['gateways'] = array();
}

$hasTimestamp = isset($config['gateways']['manualroutetimestamp']);
$hasDuration  = isset($config['gateways']['manualrouteduration']);

$changed = false;
// 락 안에서 최신 config 에 재적용할 변경(delta). 스냅샷을 통째로 저장하지 않고
// 이 키들만 unset 하므로, 동시 PW 변경 등 타 writer 의 변경을 덮지 않는다.
$unset_keys = array();

/*
 * manualroutetimestamp + manualrouteduration 둘 다 있는 경우
 */
if ($hasTimestamp && $hasDuration) {

    $manualRouteTimestamp = $config['gateways']['manualroutetimestamp'];
    $manualRouteDuration  = $config['gateways']['manualrouteduration'];

    /*
     * 값이 숫자가 아니면 설정 이상으로 보고 자동 복구
     */
    if (!is_numeric($manualRouteTimestamp) || !is_numeric($manualRouteDuration)) {

        $unset_keys[] = 'manualroutetimestamp';
        $unset_keys[] = 'manualrouteduration';

        $changed = true;

        echo "uncecessary setting for time duration, recovering back to auto-routing";

    } else {

        $date = new DateTime();

        /*
         * 기존 코드와 동일하게 분 단위 timestamp 비교
         */
        $currentMinuteTimestamp = round($date->getTimestamp() / 60, 0);
        $elapsedMinutes = $currentMinuteTimestamp - (float)$manualRouteTimestamp;

        if ($elapsedMinutes >= (float)$manualRouteDuration) {

            $unset_keys[] = 'manualroutetimestamp';
            $unset_keys[] = 'manualrouteduration';

            $changed = true;

            echo "back to auto routing due to duration is expire\n";

        } else {
            echo "still manual routing activated";
        }
    }

    /*
     * 둘 다 없는 경우
     */
} elseif (!$hasTimestamp && !$hasDuration) {

    echo "auto routing enabled, no action performed.";

    /*
     * 둘 중 하나만 있는 비정상 상태
     */
} else {

    if ($hasTimestamp && !$hasDuration) {
        $unset_keys[] = 'manualroutetimestamp';
        $changed = true;
    } elseif (!$hasTimestamp && $hasDuration) {
        $unset_keys[] = 'manualrouteduration';
        $changed = true;
    }

    echo "uncecessary setting for time duration, recovering back to auto-routing";
}

/*
 * 변경이 있을 때만 config 저장.
 * lost-update 방지: 느린 sleep 은 락 밖, 락 안에서 최신본(parse_config(true)) 재로딩 후
 * 이 크론의 변경(delta=unset 키)만 재적용한다. PW writer 와 같은
 * lock('freeradius_user_config') 를 공유해야 둘의 변경이 서로를 덮지 않는다.
 */
if ($changed) {
    sleep(2);
    $cnf_lock = lock('freeradius_user_config', LOCK_EX);
    try {
        $config = parse_config(true);
        if (!isset($config['gateways']) || !is_array($config['gateways'])) {
            $config['gateways'] = array();
        }
        foreach ($unset_keys as $k) {
            unset($config['gateways'][$k]);
        }
        write_config("Modified gateway via API");
    } finally {
        unlock($cnf_lock);
    }
}

?>
