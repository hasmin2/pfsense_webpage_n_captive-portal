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
    cp_wireless_log("Reset Daily datausage Wifi user: FreeRADIUS config not found or empty");
    exit;
}

/*
 * $config 원본을 직접 수정하기 위해 reference 사용
 */
$radiusUsers =& $config['installedpackages']['freeradius']['config'];

$changed = false;
$reset_targets = array();

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
     * daily 계정만 처리
     */
    if ($pointOfTime !== 'daily') {
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

    // (복원) gutted 였던 reset 플래그 설정 — daily 사용자가 실제로 리셋되도록.
    if ($resetQuota !== 'true' || $modified !== 'update') {
        $userEntry['varusersresetquota'] = "true";
        $userEntry['varusersmodified'] = "update";
        $changed = true;
        $reset_targets[] = (string)($userEntry['varusersusername'] ?? '');
    }

    unset($userEntry);
}

// 변경이 있을 때만 반영. stale 스냅샷으로 write_config/resync 하여 동시 변경(예: 비밀번호)을
// 덮어쓰는 lost-update 를 막는다.
if ($changed) {
    if (function_exists('freeradius_users_resync')) {
        freeradius_users_resync();
    }
    write_config("Reset Daily datausage Wifi user");
    cp_wireless_log("Reset Daily datausage Wifi user successfully");

    // 차후 로그인이 아니라 "이때 바로": 활성 세션 로그아웃 + 사용량 0
    if (function_exists('captiveportal_reset_user_usage')) {
        foreach (array_unique($reset_targets) as $u) {
            if (is_string($u) && $u !== '') { captiveportal_reset_user_usage($u); }
        }
    }
} else {
    cp_wireless_log("Reset Daily datausage Wifi user: no changes");
}

?>