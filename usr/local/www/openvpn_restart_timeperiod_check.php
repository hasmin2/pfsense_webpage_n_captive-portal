<?
//require_once("api/framework/APIModel.inc");
//require_once("api/framework/APIResponse.inc");
require_once("openvpn.inc");
global $config;
if(isset($config['openvpn']['openvpnrestart'])){
	$clients = openvpn_get_active_clients();

	foreach($clients as $client){
		openvpn_restart_by_vpnid('client', $client['vpnid']);
	}
	unset($config['openvpn']['openvpnrestart']);
	write_config("openvpn_restart");
}
?>