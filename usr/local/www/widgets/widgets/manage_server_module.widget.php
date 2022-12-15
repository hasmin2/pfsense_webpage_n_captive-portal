<?php
/*
 * manage_server_module.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Seth Mos
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once ("auth.inc");

if (!function_exists('manage_server_module_contents')) {
	function manage_server_module_contents($widgetkey) {
		$core_status = get_module_status();
		$widgetkey_html = htmlspecialchars($widgetkey);
		$rtnstr = '';
		$rtnstr .= "<tr>";
		$rtnstr .= "<td><center>{$core_status[0]}</center></td>";
		$rtnstr .="<td><center>{$core_status[1]}</center></td>";
		$rtnstr .="<td><center>N/I</center></td>";
		$rtnstr .="<td><a> <input type=button value=Open class='btn-square-little-rich' onClick='return core_open()''></a></td>";
		$rtnstr .="<td><a> <form action='/widgets/widgets/manage_server_module.widget.php' method='post' class='form-horizontal' onSubmit='return confirm_resetfw()'> <input type='hidden' value={$widgetkey_html} name=widgetkey>";
		$rtnstr .="<input type='hidden' name=resetfw value = resetfw><input type=submit class='btn-square-little-rich' value=Restart title='Reset firewall'></form></a></td>";
		$rtnstr .="<td><a> <form action='/widgets/widgets/manage_server_module.widget.php' method='post' class='form-horizontal' onSubmit='return confirm_resetcore()'> <input type='hidden' value={$widgetkey_html} name=widgetkey>";
		$rtnstr .="<input type='hidden' name=resetcore value =resetcore><input type=submit class='btn-square-little-rich' value=Restart title='Reset Core module'></form></a></td>";
		$rtnstr .="<td><a> <form action='/widgets/widgets/manage_server_module.widget.php' method='post' class='form-horizontal' onSubmit='return confirm_reboot()'> <input type='hidden' value={$widgetkey_html} name=widgetkey>";
		$rtnstr .="<input type='hidden' name=rebootpc value = rebootpc><input type=submit class='btn-square-little-rich' value=Reboot title='Reboot PC'></form></a></td>";
		return($rtnstr);
	}
}
get_module_status();
if ($_POST['widgetkey']) {//º¯°æÇÒ¶§ÀÌ¹Ç·Î
	if($_POST['rebootpc']){
		$postdata = '{"command": "sudo reboot"}';
	}
	if($_POST['resetcore']){
			$postdata = '{"command": "pkill -9 -ef streamsets"}';
		//$url = 'http://192.168.209.210:18630/rest/v1/system/restart';
	}
	if($_POST['resetfw']){
		$postdata = '{"command": "sudo virsh reboot vessel-firewall"}';
	}
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://192.168.209.210:8999',
		CURLOPT_TIMEOUT => 10,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => $postdata,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'x-sdc-application-id: servercommand',
			'X-requested-by: sdc',
			'Authorization: Basic YWRtaW46YWRtaW4='
		)
	));
	$response = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$error = curl_error($ch);
	curl_close($ch);
	header("Location: /");
	exit(0);
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);

function send_post_api($url){
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => $url,
		CURLOPT_TIMEOUT => 1,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_CUSTOMREQUEST => 'GET',
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-requested-by: sdc',
			'Authorization: Basic YWRtaW46YWRtaW4='
		)
	));
	$response = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$error = curl_error($ch);
	curl_close($ch);
	return array($response, $code);
}

function get_module_status(){
	$pipelines_result = send_post_api('http://192.168.209.210:18630/rest/v1/pipelines');
	$pipelines_status_result = send_post_api('http://192.168.209.210:18630/rest/v1/pipelines/status');

    if($pipelines_result[1] === 200 && $pipelines_status_result[1] === 200) {
		$core_module_status = '<font color=green>ALL OK';
		$core_status = '<font color=green>ONLINE';
	}
	else{
		$core_status = '<font color=green>OFFLINE';
		$core_module_status = '<font color=grey>N/A';
	}
	curl_close($ch);
	$pipelines = json_decode($pipelines_result[0], true);
	$status = json_decode($pipelines_status_result[0], true);
	foreach($pipelines as $item){

		if(substr($item['title'],0,17)=== '[System Pipeline]'){
			//echo $item['pipelineId'];
			foreach($status as $statusitem){
				if($statusitem['pipelineId'] === $item['pipelineId']&& $statusitem['status'] !== 'RUNNING'){
					$core_module_status = '<font color=red>NOT OK';
					break;
				}
			}
			if($core_module_status === '<font color=red>NOT OK'){
				break;
			}
		}
	}

	return array($core_status, $core_module_status);
}
?>
<style>

.btn-square-little-rich {
  position: relative;
  display: inline-block;
  padding: 0.25em 0.5em;
  text-decoration: none;
  color: #FFF;
  background: #03A9F4;/*ßä*/
  border: solid 1px #0f9ada;/*àÊßä*/
  border-radius: 4px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
  text-shadow: 0 1px 0 rgba(0,0,0,0.2);
}

.btn-square-little-rich:active {
  /*äãª·ª¿ªÈª­*/
  border: solid 1px #03A9F4;
  box-shadow: none;
  text-shadow: none;
}
</style>
<script>
function core_open(){
	window.open("http://192.168.209.210:18630", "_self");
}
function confirm_resetfw(){
   return window.confirm(`Are you sure you want to reset firewall?\nIt takes 2~3 mins to restore internet.`);
}
function confirm_resetcore(){
   return window.confirm(`Are you sure you want to reset core module?\nDuring reboot, reset buttons won't work for 2~3 mins.`);
}
function confirm_reboot(){
   return window.confirm(`Are you sure you want to reboot the whole system?\nIt takes about 5~10 mins to restore intenet.`);
}
</script>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
		<tr>
			<th center><center><?=gettext("Core status");?></center></th>
			<th><center><?=gettext("Core module status");?></center></th>
			<th><center><?=gettext("GPS status");?></center></th>
			<th><center><?=gettext("Core Web");?></center></th>
			<th><center><?=gettext("Firewall");?></center></th>
			<th><center><?=gettext("Core");?></center></th>
			<th><center><?=gettext("System");?></center></th>

		</tr>
		</thead>

		<tbody id="<?=htmlspecialchars($widgetkey)?>-manage-server-module">
<?php
		print(manage_server_module_contents($widgetkey));
?>
		</tbody>
	</table>
</div>