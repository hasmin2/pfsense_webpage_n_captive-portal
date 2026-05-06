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
    cp_wireless_log("Reset Weekly datausage Wifi user: FreeRADIUS config not found or empty");
    exit;
}

/*
 * $config 원본을 직접 수정하기 위해 reference 사용
 */
$radiusUsers =& $config['installedpackages']['freeradius']['config'];

$changed = false;

foreach (array_keys($radiusUsers) as $item) {

    if (!isset($radiusUsers[$item]) || !is_array($radiusUsers[$item])) {
        continue;
    }

    $userEntry =& $radiusUsers[$item];

    $pointOfTime = strtolower(trim((string)(
    isset($userEntry['varuserspointoftime'])
        ? $userEntry['varuserspointoftime']
        : ''
    )));

    /*
     * weekly 계정만 처리
     */
    if ($pointOfTime !== 'weekly') {
        unset($userEntry);
        continue;
    }

    $resetQuota = strtolower(trim((string)(
    isset($userEntry['varusersresetquota'])
        ? $userEntry['varusersresetquota']
        : ''
    )));

    $modified = strtolower(trim((string)(
    isset($userEntry['varusersmodified'])
        ? $userEntry['varusersmodified']
        : ''
    )));

    /*
     * 이미 원하는 값이면 불필요한 update 방지
     */
    if ($resetQuota !== 'true' || $modified !== 'update') {
        $userEntry['varusersresetquota'] = "true";
        $userEntry['varusersmodified'] = "update";
    }

    unset($userEntry);
}

/*
 * 변경이 있을 때만 반영
 */

    if (function_exists('freeradius_users_resync')) {
        freeradius_users_resync();
        cp_wireless_log("Reset Weekly datausage Wifi user");
    } else {
        cp_wireless_log("Reset Weekly datausage Wifi user: freeradius_users_resync() function not found");
    }

    write_config("Reset Weekly datausage Wifi user");


?>