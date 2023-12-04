<?
require_once("status_traffic_totals.inc");

global $config;
$terminalcount = count ($config['gateways']['gateway_item']);
for ($i=0; $i < $terminalcount; $i++){
	$config['gateways']['gateway_item'][$i]['currentusage']=0;
}
sleep(3);
write_config("Reset terminal usage");
?>