<?php
/*
 * gateways.widget.php
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
require_once("openvpn.inc");
require_once("/usr/local/www/widgets/include/manual_routing.inc");

if (!function_exists('compose_manual_routing_contents')) {
	function compose_manual_routing_contents($widgetkey) {
		global $user_settings;
		global $config;
		$rtnstr = '';

		$a_gateways = return_gateways_array();
		$gateways_status = array();
		$gateways_status = return_gateways_status(true);

		if (isset($user_settings["widgets"][$widgetkey]["display_type"])) {
			$display_type = $user_settings["widgets"][$widgetkey]["display_type"];
		} else {
			$display_type = "gw_ip";
		}

		$hiddengateways = explode(",", $user_settings["widgets"][$widgetkey]["gatewaysfilter"]);
		$gw_displayed = false;

		foreach ($a_gateways as $gname => $gateway) {
			if ($gateway['monitor_disable']) {
				continue;
			}
			if (isset($gateway['inactive'])) {
				$title = gettext("Gateway inactive, interface is missing");
				$icon = 'fa-times-circle-o';
			} elseif (isset($gateway['disabled'])) {
				$icon = 'fa-ban';
				$title = gettext("Gateway disabled");
			} else {
				$icon = 'fa-check-circle-o';
				$title = gettext("Gateway enabled");
			}
			if (isset($gateway['isdefaultgw'])) {
				//$gtitle = gettext("Default gateway");
			}

			$gw_displayed = true;
			$rtnstr .= "<tr>\n";
			$rtnstr .= 	"<td title='{$title}'><i class='fa {$icon}'></i></td>\n";
			$rtnstr .= 	"<td title='{$gtitle}'>\n";
			$rtnstr .= htmlspecialchars($gateway['name']);
			if (isset($gateway['isdefaultgw'])) {
				$rtnstr .= ' <i class="fa fa-globe"></i>';
			}
			$rtnstr .= "<br />";
			$rtnstr .= '<div id="gateway' . $counter . '" style="display:inline"><b>';

			$monitor_address = "";
			$monitor_address_disp = "";
			if ($display_type == "monitor_ip" || $display_type == "both_ip") {
				$monitor_address = $gateway['monitor'];
				if ($monitor_address != "" && $display_type == "both_ip") {
					$monitor_address_disp = " (" . $monitor_address . ")";
				} else {
					$monitor_address_disp = $monitor_address;
				}
			}
			global $config;
			$if_gw = '';
			// If the user asked to display Gateway IP or both IPs, or asked for just monitor IP but the monitor IP is blank
			// then find the gateway IP (which is also the monitor IP if the monitor IP was not explicitly set).
			if ($display_type == "gw_ip" || $display_type == "both_ip" || ($display_type == "monitor_ip" && $monitor_address == "")) {
				if (is_ipaddr($gateway['gateway'])) {
					$if_gw = htmlspecialchars($gateway['gateway']);
				} else {
					if ($gateway['ipprotocol'] == "inet") {
						$if_gw = htmlspecialchars(get_interface_gateway($gateway['friendlyiface']));
					}
					if ($gateway['ipprotocol'] == "inet6") {
						$if_gw = htmlspecialchars(get_interface_gateway_v6($gateway['friendlyiface']));
					}
				}
				if ($if_gw == "") {
					$if_gw = "No Connection";
				}
			}

			if ($monitor_address == $if_gw) {
				$monitor_address_disp = "";
			}

			$rtnstr .= $if_gw . $monitor_address_disp;
			unset ($if_gw);
			unset ($monitor_address);
			unset ($monitor_address_disp);
			$counter++;

			$rtnstr .= 		"</b>";
			$rtnstr .= 		"</div>\n";
			$rtnstr .= 	"</td>\n";

			if ($gateways_status[$gname]) {
				if (stristr($gateways_status[$gname]['status'], "online")) {
					switch ($gateways_status[$gname]['substatus']) {
						case "highloss":
							$online = gettext("Danger, Packetloss");
							$bgcolor = "danger";
							break;
						case "highdelay":
							$online = gettext("Danger, Latency");
							$bgcolor = "danger";
							break;
						case "loss":
							$online = gettext("Warning, Packetloss");
							$bgcolor = "warning";
							break;
						case "delay":
							$online = gettext("Warning, Latency");
							$bgcolor = "warning";
							break;
						default:
							if ($status['monitor_disable'] || ($status['monitorip'] == "none")) {
								$online = gettext("Online <br/>(unmonitored)");
							} else {
								$online = gettext("Online");
							}
							$bgcolor = "success";
					}
				} elseif (stristr($gateways_status[$gname]['status'], "down")) {
					$bgcolor = "danger";
					switch ($gateways_status[$gname]['substatus']) {
						case "force_down":
							$online = gettext("Offline (forced)");
							break;
						case "highloss":
							$online = gettext("Offline, Packetloss");
							break;
						case "highdelay":
							$online = gettext("Offline, Latency");
							break;
						default:
							$online = gettext("Offline");
					}
				} else {
					$online = gettext("Pending");
					$bgcolor = "info";  // lightgray
				}
			} else {
				$online = gettext("Unknown");
				$bgcolor = "info";  // lightblue
			}
			if ($gateways_status[$gname]) {
				$pingresult = $config['gatewaystatus'][strtolower($gname)]['pingresult'];
				if($pingresult == 'online'){
					$pingresult = "Online";
					$pingcolor = "success";
				}
				else{
					$pingcolor = "danger";
					$pingresult = "Offline";

				}

			}

			$date = new DateTime();
			$curDate = round($date->getTimestamp(),0);
			$timeleft = ($config['gateways']['manualroutetimestamp']+$config['gateways']['manualrouteduration'])*60 - $curDate;
			if($timeleft>60){
				$timeRemain= round($timeleft/60, 0) ." minutes";
			}
			else{
				if($timeleft >0 ){
					$timeRemain = ($timeleft). " seconds";
				}
				else {
					 $timeRemain = "Auto";

				}
			}
			$rtnstr .= 	"<td>" . ($config['gateways']['defaultgw4']==$gateways_status[$gname]['name'] ? $timeRemain:"") . "</td>\n";
			$rtnstr .= '<td class="bg-' . $bgcolor . '">' . $online . "</td>\n";
			$rtnstr .= '<td class="bg-' . $pingcolor . '">' . $pingresult . "</td>\n";

			$rtnstr .= "</tr>\n";
		}

		if (!$gw_displayed) {
			$rtnstr .= '<tr>';
			$rtnstr .= 	'<td colspan="5" class="text-center">';
			if (count($a_gateways)) {
				$rtnstr .= gettext('All gateways are hidden.');
			} else {
				$rtnstr .= gettext('No gateways found.');
			}
			$rtnstr .= '</td>';
			$rtnstr .= '</tr>';
		}
		return($rtnstr);
	}
}

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {
	print(compose_manual_routing_contents($_REQUEST['widgetkey']));
	exit;
}
if ($_POST['widgetkey']) {//변경할때이므로
	//set_customwidgettitle($user_settings);
	if($_POST['routing_radiobutton']){
		if($_POST['routing_radiobutton']!="Automatic"){
		    $config['gateways']['defaultgw4']=$_POST['routing_radiobutton'];
		    $config['gateways'] ['manualrouteduration']= $_POST['routeduration'];
		    $date = new DateTime();
		    $config['gateways']['manualroutetimestamp']= round($date->getTimestamp()/60,0);
		}
		else{
    			//unset($config['gateways'] ['manualrouteduration']);
			//unset($config['gateways']['manualroutetimestamp']);
			$config['gateways'] ['manualrouteduration']= 0;
	   	 	$date = new DateTime();
	    		$config['gateways']['manualroutetimestamp']= round($date->getTimestamp()/60,0);
		}

	 	write_config("manual routing");
	             system_routing_configure();
             		system_resolvconf_generate();
             		filter_configure();
	             setup_gateways_monitor();
             		send_event("service reload dyndnsall");
		clear_subsystem_dirty("staticroutes");
		if($_POST['routing_radiobutton']!="Automatic"){
		$clients = openvpn_get_active_clients();
		    foreach($clients as $client){
			    openvpn_restart_by_vpnid('client', $client['vpnid']);
		    }
		}
	}

	
	if (!is_array($user_settings["widgets"][$_POST['widgetkey']])) {
		$user_settings["widgets"][$_POST['widgetkey']] = array();
	}

	if (isset($_POST["display_type"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["display_type"] = $_POST["display_type"];
	}

	$validNames = array();
	$a_gateways = return_gateways_array();

	foreach ($a_gateways as $gname => $gateway) {
		array_push($validNames, $gname);
	}

	if (is_array($_POST['show'])) {
		$user_settings["widgets"][$_POST['widgetkey']]["gatewaysfilter"] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings["widgets"][$_POST['widgetkey']]["gatewaysfilter"] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Updated gateways widget settings via dashboard."));
	header("Location: /");
	exit(0);
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);

?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th></th>
				<th><?=gettext("Name")?></th>
				<th>GW</th>
				<th>NET</th>
				<th>INT</th>
			</tr>
		</thead>
		<tbody id="<?=htmlspecialchars($widgetkey)?>-gwtblbody">
<?php
		print(compose_manual_routing_contents($widgetkey));
?>
		</tbody>
	</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">
<form action="/widgets/widgets/manual_routing.widget.php" method="post" class="form-horizontal">
	<//?=gen_customwidgettitle_div($widgetconfig['title']);?>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Manual Override')?></label>
		<div class="col-sm-6">
			<div class="radio">
				<label><input name="routing_radiobutton" type="radio" value="Automatic">Automatic</label>
			</div>

<?php
		$gateways = return_gateways_array();
		foreach ($gateways as $gname => $gateway):
		if (!$gateway['monitor_disable']):
?>
			<div class="radio">
				<label><input name="routing_radiobutton" type="radio" value=<?echo($gname);?> <?//echo($config['gateways']['defaultgw4']==$gname) ? 'checked':'';?>><?echo($gname);?></label>
			</div>
<?php
				
		endif;
		endforeach;
?>
		</div>
		<label class="col-sm-4 control-label"><?=gettext('Time duration')?></label>
		<div class="col-sm-6">
			<div class="radio">
				<select name="routeduration" size="1">
					<option value="5">5 minutes </option>
					<option value="30">30 minutes </option>
					<option value="60">60 minutes </option>
					<option value="300">5 hours </option>
				</select>
			</div>
		</div>

<br/>

		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
		<div>
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Apply')?></button>
		</div>
	</div>
</form>
<script>
//<![CDATA[

events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function manual_routing_callback(s) {
		$(<?= json_encode('#' . $widgetkey . '-gwtblbody')?>).html(s);
	}
	
	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		widgetkey : <?=json_encode($widgetkey)?>
	 };
	// Create an object defining the widget refresh AJAX call
	var manual_routingObject= new Object();
	manual_routingObject.name = "manual_routing";
	manual_routingObject.url = "/widgets/widgets/manual_routing.widget.php";
	manual_routingObject.callback = manual_routing_callback;
	manual_routingObject.parms = postdata;
	manual_routingObject.freq = 1;

	// Register the AJAX object
	register_ajax(manual_routingObject);

	// ---------------------------------------------------------------------------------------------------
});

//]]>
</script>
