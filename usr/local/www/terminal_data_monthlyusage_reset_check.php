<?
require_once("status_traffic_totals.inc");
$filepath = "/etc/inc/";
global $config;
/*$terminalcount = count ($config['gateways']['gateway_item']);
for ($i=0; $i < $terminalcount; $i++){
	$config['gateways']['gateway_item'][$i]['currentusage']=0;
}
sleep(3);
write_config("Reset terminal usage");*/
$gateways = $config['gateways']['gateway_item'];
foreach ($gateways as $item) {
    if(file_exists($filepath.$item['rootinterface']."_cumulative") && ($cumulative_file = fopen($filepath.$item['rootinterface']."_cumulative", "w"))!==false ){
        $cur_usage = fwrite($cumulative_file, 0);
        fclose($cumulative_file);
    }
}
?>