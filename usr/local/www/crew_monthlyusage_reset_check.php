<?
require_once("captiveportal.inc");
init_config_arr(array('captiveportal'));
$cpzone="crew";
global $config;

$cpdb = captiveportal_read_db();

//$usercount = count ($config["installedpackages"]["freeradius"]["config"]);

foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
    $used_quota=check_quota($config["installedpackages"]["freeradius"]["config"][$item]['varusersusername'],
    $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctetstimerange']);
    $createdate = strtotime($config["installedpackages"]["freeradius"]["config"][$item]['varuserscreatedate']);
    $currentdate = strtotime(date("Y-m-d H:i:s"));
    $timegapday = intval(($currentdate - $createdate)/86400);
    if (($timegapday >=365 || $used_quota >= $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctets']) &&
        strtolower($config["installedpackages"]["freeradius"]["config"][$item]['varuserspointoftime']) === 'forever') {
        $user = $config["installedpackages"]["freeradius"]["config"][$item]["varusersusername"];
        unlink_if_exists("/var/log/radacct/datacounter/{$config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctetstimerange']}/used-octets-{$config["installedpackages"]["freeradius"]["config"][$item]['varusersusername']}*");
        unset($config["installedpackages"]["freeradius"]["config"][$item]);  // flag for remove DB for when anyone who is in site is open webpage.
        captiveportal_syslog("Deleted user: ".$user);
    }
    if(strtolower($config["installedpackages"]["freeradius"]["config"][$item]['varuserspointoftime']) === 'monthly'){
        $config['installedpackages']['freeradius']['config'][$item]['varusersresetquota'] = "true";
        $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
        //captiveportal_syslog("Reset Datausage for".$userentry['varusersusername']);
    }
}


foreach ($config['gateways']['gateway_item'] as $index => $item) {
    echo $item['currentusage'];
    if(isset($item['currentusage'])) {
        $config['gateways']['gateway_item'][$index]['currentusage'] = 0;
    }
}
captiveportal_syslog("Reset Monthly Crew wifi usage, delete all unused onetime id more 360days, initialize gateway usage offset");
write_config("Reset Monthly Crew wifi usage, delete all unused onetime id more 360days, initialize gateway usage offset");
?>