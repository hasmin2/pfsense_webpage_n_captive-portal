<?
require_once("captiveportal.inc");
init_config_arr(array('captiveportal'));
$cpzone="crew";
global $config;

$cpdb = captiveportal_read_db();
$resetdata = array( "varusersresetquota" => "true");
$updateflag = array( "varusersmodified" => "update");
$usercount = count ($config["installedpackages"]["freeradius"]["config"]);
for ($i=0; $i < $usercount; $i++){
	$config["installedpackages"]["freeradius"]["config"][$i]["varusersmodified"]="update";
    $used_quota=check_quota($config["installedpackages"]["freeradius"]["config"][$i]['varusersusername'],
        $config["installedpackages"]["freeradius"]["config"][$i]['varusersmaxtotaloctetstimerange']);
    if ($used_quota >= 	$config["installedpackages"]["freeradius"]["config"][$i]['varusersmaxtotaloctets']
        &&	$config["installedpackages"]["freeradius"]["config"][$i]['varuserspointoftime'] === 'forever') {
        $config["installedpackages"]["freeradius"]["config"][$i]["varusersmodified"]="delete";
    }
}
write_config("Reset Crew wifi usage");
?>