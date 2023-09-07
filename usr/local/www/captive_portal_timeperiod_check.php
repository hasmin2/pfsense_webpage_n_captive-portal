<?
require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");
$a_cp = &$config['captiveportal'];

foreach ($a_cp as $cpzone => $cp) {		
if(isset($config['captiveportal'][$cpzone]['terminate_duration']) && isset($config['captiveportal'][$cpzone]['terminate_timestamp'])){
	$date = new DateTime();
	if((round($date->getTimestamp()/60,0) - $config['captiveportal'][$cpzone]['terminate_timestamp']) >= $config['captiveportal'][$cpzone]['terminate_duration']){
		unset($config['captiveportal'][$cpzone]['terminate_timestamp']);
		unset($config['captiveportal'][$cpzone]['terminate_duration']);
		write_config("Modified Captive portal via API");
		echo('Turn on Captive portal due to duration is expire');
	}
	else {echo('still Terminated due to in timeduration ');}
}
else if (!isset($config['captiveportal'][$cpzone]['terminate_timestamp']) && !isset($config['captiveportal'][$cpzone]['terminate_duration'])){
	echo('Terminate captive portal is disabled, no action performed.');
}
else {
	if(isset($config['captiveportal'][$cpzone]['terminate_timestamp']) && !isset($config['captiveportal'][$cpzone]['terminate_duration'])){
		unset($config['captiveportal'][$cpzone]['terminate_timestamp']);
		write_config("Modified gateway via API");
	}
	else{
		unset($config['captiveportal'][$cpzone]['terminate_duration']);
		write_config("Modified gateway via API");

	}
	echo('uncecessary setting for time duration, recovering back');

}
}
?>