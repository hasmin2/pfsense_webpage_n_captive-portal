<?php
require_once("captiveportal.inc");

global $config;

// FreeRADIUS users config가 없으면 아무 것도 하지 않음
$radiusUsers = $config['installedpackages']['freeradius']['config'] ?? null;
if (!is_array($radiusUsers) || empty($radiusUsers)) {
    captiveportal_syslog("Reset half monthly: FreeRADIUS config not found or empty");
    exit;
}

$changed = false;

foreach ($radiusUsers as $idx => $userEntry) {
    // 배열인지 확인 (방어)
    if (!is_array($userEntry)) {
        continue;
    }

    // 값 안전하게 가져오기 (없으면 ''), trim + strtolower
    $pointOfTime = strtolower(trim((string)($userEntry['varuserspointoftime'] ?? '')));
    $halfPeriod  = strtolower(trim((string)($userEntry['varusershalftimeperiod'] ?? '')));

    // 조건 매칭
    if ($pointOfTime === 'monthly' && $halfPeriod === 'half') {
        // 이미 원하는 값이면 불필요한 update를 피함
        $resetQuota = strtolower(trim((string)($userEntry['varusersresetquota'] ?? '')));
        $modified   = strtolower(trim((string)($userEntry['varusersmodified'] ?? '')));

        if ($resetQuota !== 'true' || $modified !== 'update') {
            $config['installedpackages']['freeradius']['config'][$idx]['varusersresetquota'] = 'true';
            $config['installedpackages']['freeradius']['config'][$idx]['varusersmodified']  = 'update';
            $changed = true;
        }
    }
}

// 변경이 있을 때만 반영 작업 수행
if ($changed) {
    freeradius_users_resync();
    captiveportal_syslog("Reset half monthly datausage Wifi user (updated)");
    write_config("Reset half monthly datausage Wifi user");
} else {
    captiveportal_syslog("Reset half monthly datausage Wifi user (no changes)");
}
/*require_once("captiveportal.inc");

global $config;
foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
    if(strtolower($config["installedpackages"]["freeradius"]["config"][$item]['varuserspointoftime']) === 'monthly'  &&
        strtolower($config["installedpackages"]["freeradius"]["config"][$item]['varusershalftimeperiod']) === 'half'){
        $config['installedpackages']['freeradius']['config'][$item]['varusersresetquota'] = "true";
        $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";

    }
}
freeradius_users_resync();
captiveportal_syslog("Reset half monthly datausage Wifi user");
write_config("Reset half monthly datausage Wifi user");*/
?>