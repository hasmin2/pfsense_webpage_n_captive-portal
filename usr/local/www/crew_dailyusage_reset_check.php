<?php
require_once("captiveportal.inc");

global $config;
foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
    if(strtolower($config["installedpackages"]["freeradius"]["config"][$item]['varuserspointoftime']) === 'daily'){
        $config['installedpackages']['freeradius']['config'][$item]['varusersresetquota'] = "true";
        $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
        //captiveportal_syslog("Reset Datausage for".$userentry['varusersusername']);
    }
}
freeradius_users_resync();
write_config("Reset Daily datausage Wifi user");
?>