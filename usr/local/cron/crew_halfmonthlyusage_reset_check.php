<?php
require_once("captiveportal.inc");

global $config;

/*
 * FreeRADIUS users config 확인
 */
if (
    !isset($config['installedpackages']['freeradius']['config']) ||
    !is_array($config['installedpackages']['freeradius']['config']) ||
    empty($config['installedpackages']['freeradius']['config'])
) {
    cp_wireless_log("Reset half monthly: FreeRADIUS config not found or empty");
    exit;
}

/*
 * $config 원본을 직접 수정하기 위해 reference 사용
 */
$radiusUsers =& $config['installedpackages']['freeradius']['config'];

$changed = false;

foreach (array_keys($radiusUsers) as $idx) {

    if (!isset($radiusUsers[$idx]) || !is_array($radiusUsers[$idx])) {
        continue;
    }

    $userEntry =& $radiusUsers[$idx];

    /*
     * 값 안전하게 읽기
     */
    $pointOfTime = strtolower(trim((string)(
    isset($userEntry['varuserspointoftime'])
        ? $userEntry['varuserspointoftime']
        : ''
    )));

    $halfPeriod = strtolower(trim((string)(
    isset($userEntry['varusershalftimeperiod'])
        ? $userEntry['varusershalftimeperiod']
        : ''
    )));

    /*
     * monthly + half 계정만 처리
     */
    if ($pointOfTime !== 'monthly' || $halfPeriod !== 'half') {
        unset($userEntry);
        continue;
    }

    $resetQuota = strtolower(trim((string)(
    isset($userEntry['varusersresetquota'])
        ? $userEntry['varusersresetquota']
        : 'true'
    )));

    $modified = strtolower(trim((string)(
    isset($userEntry['varusersmodified'])
        ? $userEntry['varusersmodified']
        : 'Update'
    )));

    unset($userEntry);
}


// 변경이 있을 때만 반영 (lost-update 방지)
if ($changed) {
    if (function_exists('freeradius_users_resync')) {
        freeradius_users_resync();
    }
    write_config("Reset half monthly datausage Wifi user");
    cp_wireless_log("Reset half monthly datausage Wifi user (updated)");
} else {
    cp_wireless_log("Reset half monthly datausage Wifi user: no changes");
}

?>