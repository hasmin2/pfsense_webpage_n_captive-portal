<?
require_once("status_traffic_totals.inc");

global $config;
foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
    $config['installedpackages']['freeradius']['config'][$item]['varusersresetquota']="true";
    $config['installedpackages']['freeradius']['config'][$item]['varusersmodified']="update";
}
write_config("Reset terminal usage");
?>