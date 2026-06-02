<?php
require_once("captiveportal.inc");

init_config_arr(array('captiveportal'));

$cpzone = "crew";
global $config;

/*
 * FreeRADIUS user config 안전 확인
 */
$radiusUsers =& $config['installedpackages']['freeradius']['config'];

$currentTime = time();
$changed = false;
$reset_targets = array();

if (is_array($radiusUsers) && !empty($radiusUsers)) {

    foreach (array_keys($radiusUsers) as $item) {

        if (!isset($radiusUsers[$item]) || !is_array($radiusUsers[$item])) {
            continue;
        }

        $userEntry =& $radiusUsers[$item];

        $username = trim((string)(
        isset($userEntry['varusersusername'])
            ? $userEntry['varusersusername']
            : ''
        ));

        if ($username === '') {
            unset($userEntry);
            continue;
        }

        $timerange = trim((string)(
        isset($userEntry['varusersmaxtotaloctetstimerange'])
            ? $userEntry['varusersmaxtotaloctetstimerange']
            : ''
        ));

        $pointOfTime = strtolower(trim((string)(
        isset($userEntry['varuserspointoftime'])
            ? $userEntry['varuserspointoftime']
            : ''
        )));

        $halfTimePeriod = strtolower(trim((string)(
        isset($userEntry['varusershalftimeperiod'])
            ? $userEntry['varusershalftimeperiod']
            : ''
        )));

        $maxTotalOctetsRaw = isset($userEntry['varusersmaxtotaloctets'])
            ? $userEntry['varusersmaxtotaloctets']
            : '';

        $maxTotalOctets = is_numeric($maxTotalOctetsRaw)
            ? (float)$maxTotalOctetsRaw
            : 0;

        /*
         * quota 확인
         * 기존 함수 입출력 유지
         */
        $usedQuota = check_quota($username, $timerange);
        $usedQuota = is_numeric($usedQuota) ? (float)$usedQuota : 0;

        /*
         * 생성일 확인
         * 날짜가 비어있거나 파싱 실패하면 365일 초과 조건으로 삭제하지 않음
         */
        $createdateRaw = isset($userEntry['varuserscreatedate'])
            ? trim((string)$userEntry['varuserscreatedate'])
            : '';

        $createdTime = $createdateRaw !== '' ? strtotime($createdateRaw) : false;

        $isOlderThan365Days = false;
        if ($createdTime !== false) {
            $timegapday = intval(($currentTime - $createdTime) / 86400);
            $isOlderThan365Days = ($timegapday >= 365);
        }

        /*
         * quota 삭제 조건
         * maxTotalOctets가 0보다 클 때만 quota 초과 삭제 판단
         */
        $isQuotaExceeded = false;
        if ($maxTotalOctets > 0 && $usedQuota >= $maxTotalOctets) {
            $isQuotaExceeded = true;
        }

        /*
         * Forever 계정 삭제 조건
         */
        if (
            $pointOfTime === 'forever'
            && ($isOlderThan365Days || $isQuotaExceeded)
        ) {
            $user = $username;

            /*
             * datacounter used-octets 파일 삭제
             * 기존 의도였던 wildcard 삭제를 실제 glob 처리로 안정화
             */
            if ($timerange !== '') {
                $usedOctetsPattern = "/var/log/radacct/datacounter/"
                    . $timerange
                    . "/used-octets-"
                    . $username
                    . "*";

                $usedOctetsFiles = glob($usedOctetsPattern);

                if (is_array($usedOctetsFiles)) {
                    foreach ($usedOctetsFiles as $usedOctetsFile) {
                        unlink_if_exists($usedOctetsFile);
                    }
                }
            }

            unset($radiusUsers[$item]);
            cp_wireless_log("Deleted user: " . $user);

            $changed = true;

            /*
             * unset 후 같은 index 재접근 방지
             */
            unset($userEntry);
            continue;
        }

        /*
         * Monthly 계정 중 half period 없는 경우 reset flag 설정
         */
        if ($pointOfTime === 'monthly' && $halfTimePeriod === '') {
            if (
                !isset($userEntry['varusersresetquota'])
                || $userEntry['varusersresetquota'] !== "true"
            ) {
                $userEntry['varusersresetquota'] = "true";
                $changed = true;
            }

            if (
                !isset($userEntry['varusersmodified'])
                || $userEntry['varusersmodified'] !== "update"
            ) {
                $userEntry['varusersmodified'] = "update";
                $changed = true;
            }

            // 즉시 리셋 대상(monthly, non-half)으로 수집
            $reset_targets[] = (string)($userEntry['varusersusername'] ?? '');
        }

        unset($userEntry);
    }

    /*
     * unset으로 생긴 numeric key gap 정리
     */
    $radiusUsers = array_values($radiusUsers);
}

/*
 * Gateway currentusage 초기화
 * 기존 echo 출력은 유지하되 undefined index warning 방지
 */
if (
    isset($config['gateways']['gateway_item'])
    && is_array($config['gateways']['gateway_item'])
) {
    foreach ($config['gateways']['gateway_item'] as $index => $gatewayItem) {
        if (isset($gatewayItem['currentusage'])) {
            echo $gatewayItem['currentusage'];
            $config['gateways']['gateway_item'][$index]['currentusage'] = 0;
            $changed = true;
        }
    }
}

// 변경이 있을 때만 반영 (lost-update 방지)
if ($changed) {
    cp_wireless_log("Reset Monthly Crew wifi usage, delete all unused onetime id more 360days, initialize gateway usage offset");
    write_config("Reset Monthly Crew wifi usage, delete all unused onetime id more 360days, initialize gateway usage offset");

    // 차후 로그인이 아니라 "이때 바로": 활성 세션 로그아웃 + 사용량 0
    if (function_exists('captiveportal_reset_user_usage')) {
        foreach (array_unique($reset_targets) as $u) {
            if (is_string($u) && $u !== '') { captiveportal_reset_user_usage($u); }
        }
    }
} else {
    cp_wireless_log("Reset Monthly Crew wifi usage: no changes");
}
?>