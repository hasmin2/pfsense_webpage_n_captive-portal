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
require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

if (!function_exists('compose_manage_server_module_contents')) {
	function compose_manage_server_module_contents($widgetkey) {
		$core_status = get_module_status();
		$widgetkey_html = htmlspecialchars($widgetkey);
		$rtnstr = '';
		$rtnstr .= "<tr>";
		$rtnstr .= "<td><center>{$core_status[0]}</center></td>";
		$rtnstr .="<td><center>{$core_status[1]}</center></td>";
		if($core_status[0] == "ONLINE"){
			$isdisabled="";
		}
		else {
			$isdisabled="diasbled=true";
		}
		$vsat_status = check_vsat_status_influxdb();
		$vpn_clients = openvpn_get_active_clients();
		$vpn_status = '<font color=green>ONLINE</font>';
		foreach($vpn_clients as $vpn){
			if($vpn['status'] == "down"){
				$vpn_status = "<font color=red>OFFLINE</font>";
			}
			break;
		}
		$rtnstr .="<td><center>{$vsat_status}</center></td>";
		$rtnstr .="<td><center>N/I</center></td>";
		$rtnstr .="<td><center>N/I</center></td>";
		$rtnstr .="<td><center>{$vpn_status}</center></td>";
		$rtnstr .="<td><center><font color=green>OK</font></center></td>";
		return($rtnstr);
	}
}

if ($_POST['widgetkey']) {//
	if($_POST['configdef']){
		global $config;
		$config['terminalinfo']['vsat_ip'] = $_POST['vsatip'];
		$config['terminalinfo']['fbb_ip'] = $_POST['fbbip'];
		write_config("update terminal info");
				header("Location: /");
	}

	if($_POST['rebootpc']){
		$postdata = '{"command": "sudo reboot"}';
	}
	else if($_POST['resetcore']){
		$postdata = '{"command": "pkill -9 -ef streamsets"}';
			//	$postdata = '{"command": "ls -altr"}';
	}
	else if($_POST['resetfw']){
		$postdata = '{"command": "sudo virsh reboot vessel-firewall"}';
	}
	else{
		print(compose_manage_server_module_contents($widgetkey));//아작스 호출 시에 사용되는 (부팅코어리셋 등이 아니면 여기 걸림)
	}

	if($_POST['rebootpc'] || $_POST['resetcore'] ||$_POST['resetfw']){
		send_api('http://192.168.209.210:8999', 'POST', $postdata);
		header("Location: /");
	}
	exit(0);

}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);
function check_vsat_status_influxdb(){
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20*%20from%20satstatus%20where%20time%20%3E%20now()%20-10m%20order%20by%20time%20desc&db=acustatus',
		CURLOPT_TIMEOUT => 1,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_CUSTOMREQUEST => GET,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json'
		)
	));
	$response = curl_exec($ch);
	curl_close($ch);
	if(!$response){
		return "<font color=red>OFFLINE</font>";
	}
	else {
		$decoded = json_decode($response, true);
		$resultcount = count($decoded['results'][0]['series'][0]['values']);
		if($resultcount > 1){
			return "<font color=green>ONLINE</font>";
		}
		else {
			return "<font color=red>OFFLINE</font>";
		}
	}
}

function send_api($url, $method, $postdata) {
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => $url,
		CURLOPT_TIMEOUT => 1,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_POSTFIELDS => $postdata,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-requested-by: sdc',
			'x-sdc-application-id: servercommand',
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
	$pipelines_result = send_api('http://192.168.209.210:18630/rest/v1/pipelines', 'GET', '');
	$pipelines_status_result = send_api('http://192.168.209.210:18630/rest/v1/pipelines/status', 'GET', '');

    if($pipelines_result[1] === 200 && $pipelines_status_result[1] === 200) {
		$core_module_status = '<font color=green>ALL OK';
		$core_status = '<font color=green>ONLINE';
	}
	else{
		$core_status = '<font color=green>OFFLINE';
		$core_module_status = '<font color=grey>N/A';
	}
	$noc_status = '<font color=green>ONLINE';
	$pipelines = json_decode($pipelines_result[0], true);
	$status = json_decode($pipelines_status_result[0], true);
	foreach($pipelines as $item){
		if(substr($item['title'],0,16)=== '[System Pipeline'){
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
  background: #03A9F4;/*色*/
  border: solid 1px #0f9ada;/*線色*/
  border-radius: 4px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
  text-shadow: 0 1px 0 rgba(0,0,0,0.2);
}

.btn-square-little-rich:active {
  /*押したとき*/
  border: solid 1px #03A9F4;
  box-shadow: none;
  text-shadow: none;
}
</style>
<script>
function core_open(){
	window.open("http://192.168.209.210:18630");
}

function console_open(ipaddr){
	window.open(`http://${ipaddr}`);
}
function confirm_resetfw(){
   return window.confirm(`Are you sure you want to reset firewall?\nIt takes 2~3 mins to restore internet.`);
}
function confirm_resetcore(){
   return window.confirm(`Are you sure you want to reset core module?\nDuring reboot, reset buttons won't work for 2~3 mins.`);
}
function confirm_rebootsvr(){
   return window.confirm(`Are you sure you want to reboot the whole system?\nIt takes about 5~10 mins to restore intenet.`);
}
</script>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
		<tr>
			<th title='Core module status.&#10;If this is offline, the manage panel(this) should not be working.&#10;If problem persists, operator may physically push the smartBOX button to restart.'>
			<center><?=gettext("Core");?></center></th>
			<th title='Core Module logic status.&#10;If NOT OK, network switching, crew internet may not be working propely swithing&#10;By clicking "Reset Core" to reset core module to solve this issue.'>
			<center><?=gettext("Core Logic");?></center></th>
			<th title = 'VSAT management connection status.&#10;If it is offline, VSAT terminal status may not be monitored.&#10;To recover the issue, operator may re-input VSAT ACU ip address both core and smartbox to recover it'>
			<center><?=gettext("VSAT");?></center></th>
			<th title = 'Fleet Broadband management connection status.&#10;If it is offline, FBB terminal status may not be monitored.&#10;To recover the issue, operator may re-input FBB ip address both core and smartbox to recover it'>
			<center><?=gettext("FBB");?></center></th>
			<th><center><?=gettext("GPS");?></center></th>
			<th title = 'Network Operation Center(NOC) status.&#10;If it is offline, Internet connection may unstable or shoreside server was offline.&#10;If vessel's internet connection was stable,leave it as offline'>
			<center><?=gettext("NOC");?></center></th>
			<th><center><?=gettext("Server");?></center></th>

		</tr>
		</thead>

		<tbody id="<?=htmlspecialchars($widgetkey)?>-manage-server-module">
<?php
		print(compose_manage_server_module_contents($widgetkey));
?>
		</tbody>
	</table>
</div>

	<!-- close the body we're wrapped in and add a configuration-panel -->
</div>
	<div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">
		<table class="table table-striped table-hover table-condensed">
    		<tr >
				<td title = 'Opens Core console web page, the all pipeline begins with System pipeline should always running.'>
				<a><center>
					<input type=button value="Open Core console" class='btn-square-little-rich' onclick='core_open()'>
					</center></a>
				</td>
    			<td title = 'Opens VSAT console web page&#10;Standard VSAT troubleshooting may begins here.'>
    			<a><center>
					<input type=button value="Open VSAT console" class='btn-square-little-rich' onclick=console_open('<?echo $config["terminalinfo"]["vsat_ip"];?>')>
					</center></a>
				</td>
    			<td title = 'Opens FleetBroadband console web page&#10;Standard FBB troubleshooting may begins here.'>
    			<a><center>
					<input type=button value="Open FBB console" class='btn-square-little-rich' onclick='console_open('<?echo$config["terminalinfo"]["fbb_ip"];?>')'>
					</center></a>
				</td>
			</tr>
			<tr>
				<td>
					<a><center>
						<form action='/widgets/widgets/manage_server_module.widget.php' method='post' class='form-horizontal' onSubmit='return confirm_resetfw()'>
							<input type='hidden' value={$widgetkey_html} name=widgetkey>
							<input type='hidden' name=resetfw value = resetfw>
							<input type=submit  class='btn-square-little-rich' value='Reset firewall' title='resetfirewall'>
						</form>
					</center></a>
				</td>
				<td>
					<a><center>
						<form action='/widgets/widgets/manage_server_module.widget.php' method='post' class='form-horizontal' onSubmit='return confirm_resetcore()'>
							<input type='hidden' value={$widgetkey_html} name=widgetkey>
							<input type='hidden' name=resetcore value = resetcore>
							<input type=submit  class='btn-square-little-rich' value='Reset Core' title='resetcore'>
						</form>
					</center></a>
				</td>
    			<td>
    				<a><center>
    					<form action='/widgets/widgets/manage_server_module.widget.php' method='post' class='form-horizontal' onSubmit='return confirm_rebootsvr()'>
    						<input type='hidden' value={$widgetkey_html} name=widgetkey>
    						<input type='hidden' name=rebootpc value = rebootpc>
    						<input type=submit  class='btn-square-little-rich' value='Reboot SVR' title='rebootsvr'>
    					</form>
    				</center></a>
    			</td>
    		</tr>
				<form action='/widgets/widgets/manage_server_module.widget.php' method='post' class='form-horizontal'>
				<td>
					<a><center><font color=white>VSAT IP:
							<input type='text' value=
							<?
								if($config['terminalinfo']['vsat_ip'])
									echo $config['terminalinfo']['vsat_ip'];
								else
									echo "192.168.2.1";
							?> name=vsatip>
					</center></a>
				</td>
				<td>
					<a><center><font color=white>FBB IP:
							<input type='text' value=
							<?
								if($config['terminalinfo']['fbb_ip'])
									echo $config['terminalinfo']['fbb_ip'];
								else
									echo "192.168.1.35";
							?> name=fbbip>
					</center></a>
				</td>
				<td>
					<input type='hidden' value={$widgetkey_html} name=widgetkey>
					<input type='hidden' value=configdef name=configdef>
					<button type="submit" class="btn btn-primary">
						<i class="fa fa-save icon-embed-btn"></i>
						<?=gettext("Apply")?>
					</button>
				</td>
				</form>
    		</tr>


    		</div>
    	</table>



<script type="text/javascript">
events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function manage_server_module_callback(s) {
		$(<?= json_encode('#' . $widgetkey .'-manage-server-module')?>).html(s);
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		widgetkey : <?=json_encode($widgetkey)?>
	 };
	// Create an object defining the widget refresh AJAX call
	var manage_server_moduleObject= new Object();
	manage_server_moduleObject.name = "manage_server_module";
	manage_server_moduleObject.url = "/widgets/widgets/manage_server_module.widget.php";
	manage_server_moduleObject.callback = manage_server_module_callback;
	manage_server_moduleObject.parms = postdata;
	manage_server_moduleObject.freq = 1;

	// Register the AJAX object
	register_ajax(manage_server_moduleObject);

	// ---------------------------------------------------------------------------------------------------
});
</script>