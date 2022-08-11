<?
require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");
if(isset($config['gateways']['manualroutetimestamp']) && isset($config['gateways']['manualrouteduration'])){
	$date = new DateTime();
	if((round($date->getTimestamp()/60,0) - $config['gateways']['manualroutetimestamp']) >= $config['gateways']['manualrouteduration']){
		unset($config['gateways']['manualroutetimestamp']);
		unset($config['gateways']['manualrouteduration']);
		write_config("Modified gateway via API");
		//echo('back to auto routing due to duration is expire');
	}
	else {echo('still manual routing activated');}
}
else if (!isset($config['gateways']['manualroutetimestamp']) && !isset($config['gateways']['manualrouteduration'])){
	//echo('auto routing enabled, no action performed.');
}
else {
	if(isset($config['gateways']['manualroutetimestamp']) && !isset($config['gateways']['manualrouteduration'])){
		unset($config['gateways']['manualroutetimestamp']);
		write_config("Modified gateway via API");

	}
	else{
		unset($config['gateways']['manualrouteduration']);
		write_config("Modified gateway via API");

	}
	//echo('uncecessary setting for time duration, recovering back to auto-routing');

}
?>