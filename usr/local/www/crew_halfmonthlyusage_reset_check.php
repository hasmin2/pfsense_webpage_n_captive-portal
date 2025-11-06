<?php
require_once("captiveportal.inc");

global $config;
foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
    if(strtolower($config["installedpackages"]["freeradius"]["config"][$item]['varuserspointoftime']) === 'halfmonthly'){
        $config['installedpackages']['freeradius']['config'][$item]['varusersresetquota'] = "true";
        $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";

    }
}
freeradius_users_resync();
captiveportal_syslog("Reset half monthly datausage Wifi user");
write_config("Reset half monthly datausage Wifi user");
?>