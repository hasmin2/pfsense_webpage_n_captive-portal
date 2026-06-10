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
foreach ($cpdb as $eachuser) {
    // 안전하게 인덱스 존재 확인
    $username = isset($eachuser[4]) ? strtolower(trim($eachuser[4])) : '';
    $clientId = $eachuser[5] ?? null; // captiveportal_disconnect_client에 넘기던 값

    if ($username === '' || $clientId === null) {
        continue;
    }
    /*$mappedGateway = $userterminalMap[$clientId] ?? '';

    if (!in_array($mappedGateway, $gatewaylist, true)) {
        captiveportal_disconnect_client($clientId);
    }*/

    // 1) 스케줄 suspend이면 바로 disconnect
    if (get_suspend_timeschedule($username)) {
        captiveportal_disconnect_client($clientId);
        //write_cause($clientId, 'client_suspended', 'Currently your ID has been forced logged out due to suspension policy. You may login again.');
        continue; // 이미 끊었으면 다음 유저로
    }

    // 2) FreeRADIUS modified=update면 disconnect
    //    (config에 해당 username이 없으면 무시)
    if (($radiusMap[$username] ?? '') === 'update') {
        //write_cause($clientId, 'update', 'Currently your ID has been forced logged out due to suspension policy. You may login again.');
        captiveportal_disconnect_client($clientId);
    }
}

?>