<?
//require_once("api/framework/APIModel.inc");
//require_once("api/framework/APIResponse.inc");
require_once("openvpn.inc");
global $config;

$vpnclients = openvpn_get_active_clients();
foreach($vpnclients as $vpnclient){
    $pingresult = mwexec('ping -c1 -t5 -S '.$vpnclient['virtual_addr'].' vpn-server.synersat.noc > /dev/null')==0 ? "online": "offline";
}

if ($pingresult == "offline"){
	$config['openvpn']['openvpnrestart']="";
}

if(isset($config['openvpn']['openvpnrestart'])){
	$clients = openvpn_get_active_clients();

	foreach($clients as $client){
		openvpn_restart_by_vpnid('client', $client['vpnid']);
	}
	unset($config['openvpn']['openvpnrestart']);
	write_config("openvpn_restart");
}
?>