<?
require_once("captiveportal.inc");
init_config_arr(array('captiveportal'));
$cpzone="crew";
global $config;

$cpdb = captiveportal_read_db();
captiveportal_disconnect_all();
$usercount = count ($config["installedpackages"]["freeradius"]["config"]);
for ($i=0; $i < $usercount; $i++){
	$config["installedpackages"]["freeradius"]["config"][$i]["varusersmodified"]="update";
    $used_quota=check_quota($config["installedpackages"]["freeradius"]["config"][$i]['varusersusername'],
        $config["installedpackages"]["freeradius"]["config"][$i]['varusersmaxtotaloctetstimerange']);
    $createdate = strtotime($config["installedpackages"]["freeradius"]["config"][$i]['varuserscreatedate']);
    $currentdate = strtotime(date("Y-m-d H:i:s"));
    $timegapday = intval(($currentdate - $createdate)/86400);
    if (($timegapday >=547 || $used_quota >= $config["installedpackages"]["freeradius"]["config"][$i]['varusersmaxtotaloctets']) &&
        strtolower($config["installedpackages"]["freeradius"]["config"][$i]['varuserspointoftime']) === 'forever') {
        $user = $config["installedpackages"]["freeradius"]["config"][$i]["varusersusername"];
        unlink_if_exists("/var/log/radacct/datacounter/{$config["installedpackages"]["freeradius"]["config"][$i]['varusersmaxtotaloctetstimerange']}/used-octets-{$config["installedpackages"]["freeradius"]["config"][$i]['varusersusername']}*");
        unset($config["installedpackages"]["freeradius"]["config"][$i]);  // flag for remove DB for when anyone who is in site is open webpage.
        captiveportal_syslog("Deleted user: ".$user);
    }
}
foreach ($config['gateways']['gateway_item'] as $index => $item) {
    echo $item['currentusage'];
    if(isset($item['currentusage'])) {
        $config['gateways']['gateway_item'][$index]['currentusage'] = 0;
    }
}
write_config("Reset Crew wifi usage");
?>