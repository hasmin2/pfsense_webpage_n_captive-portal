<?
//require_once("api/framework/APIModel.inc");
//require_once("api/framework/APIResponse.inc");
// ── 단일 인스턴스 가드 (#26) ──────────────────────────────────────────────────
// 이전 실행이 1주기 안에 안 끝났으면(디스크풀/느린 I/O 등) 즉시 종료 → 프로세스 누적/OOM 방지.
// 의존성 없는 self-contained(버전 섞임 안전). 락 fd 는 프로세스 종료 시 자동 해제.
$__cron_singleton_fp = @fopen('/tmp/cron_' . basename(__FILE__, '.php') . '.lock', 'c');
if ($__cron_singleton_fp === false || !@flock($__cron_singleton_fp, LOCK_EX | LOCK_NB)) {
    exit(0);
}
require_once("openvpn.inc");
global $config;

$pingresult = "online";
$vpnclients = openvpn_get_active_clients();
foreach($vpnclients as $vpnclient){
	$pingresult = mwexec('ping -c1 -t5 -S '.$vpnclient['virtual_addr'].' vpn-server.synersat.noc > /dev/null')==0 ? "online": "offline";
}

// restart 요청 여부 결정: 관리자가 openvpnrestart 플래그를 세웠거나, VPN ping 이 offline.
// (느린 ping/restart 는 락 밖에서 처리. config 정리만 락 안에서.)
$do_restart = ($pingresult == "offline") || isset($config['openvpn']['openvpnrestart']);

if($do_restart){
	$clients = openvpn_get_active_clients();
	foreach($clients as $client){
		openvpn_restart_by_vpnid('client', $client['vpnid']);
	}

	// lost-update 방지: 락 안에서 최신본(parse_config(true)) 재로딩 후 openvpnrestart 플래그만
	// 정리한다. 스냅샷 전체를 저장하지 않으므로 동시 PW 변경 등을 덮지 않는다.
	// (PW writer 와 같은 lock('freeradius_user_config') 공유.) 플래그가 실제로 있을 때만
	// write → VPN 끊김 중 매분 불필요한 write_config(=clobber 창) 도 제거.
	$cnf_lock = lock('freeradius_user_config', LOCK_EX);
	try {
		$config = parse_config(true);
		if(isset($config['openvpn']['openvpnrestart'])){
			unset($config['openvpn']['openvpnrestart']);
			write_config("openvpn_restart");
		}
	} finally {
		unlock($cnf_lock);
	}
}
?>
