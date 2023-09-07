<?
require_once("captiveportal.inc");
init_config_arr(array('captiveportal'));
$cpzone="crew";
$cpdb = captiveportal_read_db();
foreach ($cpdb as $eachuser){
	Portal_reply_page("/","connected","Refreshing",$eachuser[3],$eachuser[2],$eachuser[4]);
}
?>