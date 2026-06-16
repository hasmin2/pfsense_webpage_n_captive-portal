<?php
require_once("captiveportal.inc");
require_once("cp_usage_reset.inc");

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
        $changed = true;
        $reset_targets[] = (string)($userEntry['varusersusername'] ?? '');
    }

    unset($userEntry);
}

/*
 * 변경이 있을 때만 반영
 */

    if ($changed) {
        if (function_exists('freeradius_users_resync')) {
            freeradius_users_resync();
        }
        write_config("Reset Weekly datausage Wifi user");
        cp_wireless_log("Reset Weekly datausage Wifi user");

        // 차후 로그인이 아니라 "이때 바로": 활성 세션 로그아웃 + 사용량 0
        if (function_exists('captiveportal_reset_user_usage')) {
            foreach (array_unique($reset_targets) as $u) {
                if (is_string($u) && $u !== '') {
                    captiveportal_reset_user_usage($u);
                    // 자가복구 크론이 이번 주기를 중복 리셋하지 않도록 날짜키 마킹.
                    if (function_exists('cp_reset_mark_user')) { cp_reset_mark_user($u, 'weekly'); }
                }
            }
        }
    } else {
        cp_wireless_log("Reset Weekly datausage Wifi user: no changes");
    }


?>