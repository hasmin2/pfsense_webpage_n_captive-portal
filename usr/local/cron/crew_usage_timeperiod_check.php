<?

// ── 단일 인스턴스 가드 (#26) ──────────────────────────────────────────────────
// 이전 실행이 1주기 안에 안 끝났으면(디스크풀/느린 I/O 등) 즉시 종료 → 프로세스 누적/OOM 방지.
// 의존성 없는 self-contained(버전 섞임 안전). 락 fd 는 프로세스 종료 시 자동 해제.
$__cron_singleton_fp = @fopen('/tmp/cron_' . basename(__FILE__, '.php') . '.lock', 'c');
if ($__cron_singleton_fp === false || !@flock($__cron_singleton_fp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

require_once("captiveportal.inc");
init_config_arr(['captiveportal']);
global $config;

$cpzone = "crew";

// Captive portal DB: 각 row는 보통 배열 형태(인덱스 기반)
$cpdb = captiveportal_read_db();

// FreeRADIUS config를 username 기준으로 빠르게 찾을 수 있게 맵 구성
$radiusMap = [];
$userterminalMap = [];
$radiusCfg = $config['installedpackages']['freeradius']['config'] ?? [];
foreach ($radiusCfg as $userentry) {
    if (!isset($userentry['varusersusername'])) {
        continue;
    }
    $uname = strtolower(trim($userentry['varusersusername']));
    if ($uname === '') {
        continue;
    }
    // 필요 정보만 저장
    $radiusMap[$uname] = $userentry['varusersmodified'] ?? '';
    $userterminalMap[$uname] = $userentry['varuserterminaltype'] ?? '';

}
//$gatewaylist= available_default_gateways()['v4'];
// CP DB 순회하며 조건에 따라 disconnect
$flagsToClear = [];
foreach ($cpdb as $eachuser) {
    $username = isset($eachuser[4]) ? strtolower(trim($eachuser[4])) : '';
    $clientId = $eachuser[5] ?? null;

    if ($username === '' || $clientId === null) {
        continue;
    }

    // 1) 스케줄 suspend이면 바로 disconnect
    if (get_suspend_timeschedule($username)) {
        captiveportal_disconnect_client($clientId);
        continue;
    }

    // 2) FreeRADIUS modified=update면 disconnect
    if (($radiusMap[$username] ?? '') === 'update') {
        captiveportal_disconnect_client($clientId);
        $flagsToClear[] = $username;
    }
}

// kick 후 varusersmodified 플래그 클리어 (#30 lost-update 방지)
// captiveportal_authenticate_user 가 로그인 시 지우는 것에만 의존하면,
// CP를 통해 로그인하지 않는 계정(synersat 등)은 플래그가 영구히 남아
// 매분 kick 시도가 반복된다. lock+parse_config(true) 로 stale 덮어씀도 방지.
if (!empty($flagsToClear)) {
    $cnf_lock = lock('freeradius_user_config', LOCK_EX);
    try {
        $config = parse_config(true);
        foreach ($config['installedpackages']['freeradius']['config'] as $k => $u) {
            if (in_array(strtolower($u['varusersusername'] ?? ''), $flagsToClear, true)) {
                $config['installedpackages']['freeradius']['config'][$k]['varusersmodified'] = '';
            }
        }
        write_config('crew_usage_timeperiod_check: cleared varusersmodified after kick');
    } finally {
        unlock($cnf_lock);
    }
}

?>