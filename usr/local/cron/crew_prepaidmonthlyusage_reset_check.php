<?php
require_once("captiveportal.inc");

global $config;

// FreeRADIUS users config가 없으면 아무 것도 하지 않음
$radiusUsers = $config['installedpackages']['freeradius']['config'] ?? null;
if (!is_array($radiusUsers) || empty($radiusUsers)) {
    cp_wireless_log("crewpay - Reset half monthly: FreeRADIUS config not found or empty");
    exit;
}

$changed = false;

foreach ($radiusUsers as $idx => $userEntry) {
    // 배열인지 확인 (방어)
    if (!is_array($userEntry)) {
        continue;
    }
    $username = trim((string)($userEntry['varusersusername'] ?? ''));
    if (strpos($username, "crewpay-") !== 0) {
        continue;
    }

    // 값 안전하게 가져오기 (없으면 ''), trim + strtolower
    $pointOfTime = strtolower(trim((string)($userEntry['varuserspointoftime'] ?? '')));
    $halfPeriod  = strtolower(trim((string)($userEntry['varusershalftimeperiod'] ?? '')));

    // 조건 매칭
    if ($pointOfTime === 'monthly') {
        $config['installedpackages']['freeradius']['config'][$idx]['varusersresetquota'] = 'true';
        $config['installedpackages']['freeradius']['config'][$idx]['varusersmodified']  = 'update';
        $config['installedpackages']['freeradius']['config'][$idx]['varusersmaxtotaloctets']  = '0';
        $changed = true;
    }
}

// 변경이 있을 때만 반영 작업 수행
if ($changed) {
    freeradius_users_resync();
    cp_wireless_log("crewpay - Reset PREPAID datausage / allocation (Updated)");
    write_config("Reset half monthly datausage Wifi user");
} else {
    cp_wireless_log("crewpay - Reset PREPAID datausage / allocation (no changes)");
}
