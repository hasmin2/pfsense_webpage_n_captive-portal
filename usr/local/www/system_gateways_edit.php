<?php
/*
 * system_gateways_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|+PRIV
##|*IDENT=page-system-gateways-editgateway
##|*NAME=System: Gateways: Edit Gateway
##|*DESCR=Allow access to the 'System: Gateways: Edit Gateway' page.
##|*MATCH=system_gateways_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

if (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_gateways.php');
}

$a_gateways = return_gateways_array(true, false, true, true);

init_config_arr(array('gateways', 'gateway_item'));
$a_gateway_item = &$config['gateways']['gateway_item'];
$dpinger_default = return_dpinger_defaults();

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	$id = $_REQUEST['dup'];
}

if (isset($id) && $a_gateways[$id]) {
	$pconfig = array();
	$pconfig['name'] = $a_gateways[$id]['name'];
	$pconfig['weight'] = $a_gateways[$id]['weight'];
	$pconfig['interval'] = $a_gateways[$id]['interval'];
	$pconfig['loss_interval'] = $a_gateways[$id]['loss_interval'];
	$pconfig['alert_interval'] = $a_gateways[$id]['alert_interval'];
	$pconfig['time_period'] = $a_gateways[$id]['time_period'];
	$pconfig['interface'] = $a_gateways[$id]['interface'];
	$pconfig['friendlyiface'] = $a_gateways[$id]['friendlyiface'];
	$pconfig['terminal_type'] = $a_gateways[$id]['terminal_type'];
	$pconfig['check_method'] = $a_gateways[$id]['check_method'];
	$pconfig['rootinterface'] = $a_gateways[$id]['rootinterface'];
    $pconfig['disablecrewinternet'] = $a_gateways[$id]['disablecrewinternet'];
    $pconfig['blockall_bydefault'] = $a_gateways[$id]['blockall_bydefault'];
    $pconfig['sourceaddresses'] = $a_gateways[$id]['sourceaddresses'];
    $pconfig['destaddresses'] = $a_gateways[$id]['destaddresses'];
    $pconfig['portsfrom'] = $a_gateways[$id]['portsfrom'];
    $pconfig['portsto'] = $a_gateways[$id]['portsto'];
    $pconfig['protos'] = $a_gateways[$id]['protos'];
    $pconfig['currentusage'] = $a_gateways[$id]['currentusage'];
	$pconfig['allowance'] = $a_gateways[$id]['allowance'];
	$pconfig['destinationip'] = $a_gateways[$id]['destinationip'];
	$pconfig['check_timeout'] = $a_gateways[$id]['check_timeout'];
	$pconfig['ipprotocol'] = $a_gateways[$id]['ipprotocol'];
	if (isset($a_gateways[$id]['dynamic'])) {
		$pconfig['dynamic'] = true;
	}
	$pconfig['gateway'] = $a_gateways[$id]['gateway'];
	$pconfig['force_down'] = isset($a_gateways[$id]['force_down']);
	$pconfig['latencylow'] = $a_gateways[$id]['latencylow'];
	$pconfig['latencyhigh'] = $a_gateways[$id]['latencyhigh'];
	$pconfig['losslow'] = $a_gateways[$id]['losslow'];
	$pconfig['losshigh'] = $a_gateways[$id]['losshigh'];
	$pconfig['monitor'] = $a_gateways[$id]['monitor'];
	$pconfig['monitor_disable'] = isset($a_gateways[$id]['monitor_disable']);
	$pconfig['action_disable'] = isset($a_gateways[$id]['action_disable']);
	$pconfig['data_payload'] = $a_gateways[$id]['data_payload'];
	$pconfig['nonlocalgateway'] = isset($a_gateways[$id]['nonlocalgateway']);
	$pconfig['descr'] = $a_gateways[$id]['descr'];
	$pconfig['attribute'] = $a_gateways[$id]['attribute'];
	$pconfig['disabled'] = isset($a_gateways[$id]['disabled']);
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	unset($id);
	unset($pconfig['attribute']);
}

if (isset($id) && $a_gateways[$id]) {
	$realid = $a_gateways[$id]['attribute'];
}

if ($_POST['save']) {

	$input_errors = validate_gateway($_POST, $id);

	if (count($input_errors) == 0) {
		save_gateway($_POST, $realid);
		header("Location: system_gateways.php");
		exit;
	} else {
		$pconfig = $_POST;
		if (empty($_POST['friendlyiface'])) {
			$pconfig['friendlyiface'] = $_POST['interface'];
		}
	}
}

$pgtitle = array(gettext("System"), gettext("Routing"), gettext("Gateways"), gettext("Edit"));
$pglinks = array("", "system_gateways.php", "system_gateways.php", "@self");
$shortcut_section = "gateways";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

/* If this is a system gateway we need this var */
if (($pconfig['attribute'] == "system") || is_numeric($pconfig['attribute'])) {
	$form->addGlobal(new Form_Input(
		'attribute',
		null,
		'hidden',
		$pconfig['attribute']
	));
}

if (isset($id) && $a_gateways[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->addGlobal(new Form_Input(
	'friendlyiface',
	null,
	'hidden',
	$pconfig['friendlyiface']
));

$section = new Form_Section('Edit Gateway');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this gateway',
	$pconfig['disabled']
))->setHelp('Set this option to disable this gateway without removing it from the list.');

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['friendlyiface'],
	get_configured_interface_with_descr(true)
))->setHelp('Choose which interface this gateway applies to.');

$section->addInput(new Form_Select(
	'ipprotocol',
	'*Address Family',
	$pconfig['ipprotocol'],
	array(
		"inet" => "IPv4",
		"inet6" => "IPv6"
	)
))->setHelp('Choose the Internet Protocol this gateway uses.');

$section->addInput(new Form_Input(
	'name',
	'*Name',
	'text',
	$pconfig['name']
))->setHelp('Gateway name');

$egw = new Form_Input(
	'gateway',
	'Gateway',
	'text',
	($pconfig['dynamic'] ? 'dynamic' : $pconfig['gateway'])
);

$egw->setHelp('Gateway IP address');

if ($pconfig['dynamic']) {
	$egw->setReadonly();
}

$section->addInput($egw);

$section->addInput(new Form_Checkbox(
	'monitor_disable',
	'Gateway Monitoring',
	'Disable Gateway Monitoring',
	$pconfig['monitor_disable']
))->toggles('.toggle-monitor-ip')->setHelp('This will consider this gateway as always being up.');

$section->addInput(new Form_Checkbox(
	'action_disable',
	'Gateway Action',
	'Disable Gateway Monitoring Action',
	$pconfig['action_disable']
))->setHelp('No action will be taken on gateway events. The gateway is always considered up.');

$group = new Form_Group('Monitor IP');
$group->addClass('toggle-monitor-ip', 'collapse');

if (!$pconfig['monitor_disable'])
	$group->addClass('in');

$group->add(new Form_Input(
	'monitor',
	null,
	'text',
	($pconfig['gateway'] == $pconfig['monitor'] ? '' : $pconfig['monitor'])
))->setHelp('Enter an alternative address here to be '.
	'used to monitor the link. This is used for the quality RRD graphs as well as the '.
	'load balancer entries. Use this if the gateway does not respond to ICMP echo '.
	'requests (pings).');
$section->add($group);

$section->addInput(new Form_Checkbox(
	'force_down',
	'Force state',
	'Mark Gateway as Down',
	$pconfig['force_down']
))->setHelp('This will force this gateway to be considered down.');
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$group=new Form_Group("Terminal Type");
$group->add(new Form_Select(
	'terminal_type',
	'*Terminal Type',
	$pconfig['terminal_type'],
	 array(
		"vsat_pri" => "1st VSAT(or FX CORP)",
		"vsat_sec" => "FX_CREW",
		"vsat_thi" => "2nd VSAT (or any after second VSAT terminal)",
		"tcp_other" => "Internet",
		"nexuswave_pri" => "NexusWave CORP",
        "nexuswave_sec" => "NexusWave legacy CORP",
        "nexuswave_thi" => "NexusWave CREW",
        "nexuswave_fth" => "NexusWave IOT",
		"tcp_starlink" => "STARLink",
        "tcp_kuiper" => "Amazon Kuiper",
		"fbb_satlink"=> "SATLink FleetBroadband",
		"fbb_jrc" => "JRC FleetBroadband",
		"fbb_furuno" => "FURUNO FleetBroadband",
		"fbb_sailor" => "SAILOR FleetBroadband",
		"iridium_other"=> "Iridium",
		"metered_other"=> "Metered terminial",
        "vpn"=> "VPN or Internal network"
     )
))->setHelp('Choose terminal type, ***IMPORTANT *** Note that the Gateway priority is "Internet"-> "VSAT(N)"->"any FBB"->"Iridium"->"Metered"');
//$section->add($group);

//$group=new Form_Group("Monthly data limit");

$section->addInput(new Form_Input(
	'allowance',
	'*Monthly Data Allowance',
	'text',
	$pconfig['allowance']
))->setHelp('Enter an monthly data usage limit here in GB, -1 or blank for unlimited');

$section->addInput(new Form_Input(
	'rootinterface',
	'*Root Interface',
	'text',
	$pconfig['rootinterface']
))->setHelp('Enter the root interface name here, e.g. "vtnet0" or "vtnet2.1000"');

$section->addInput(new Form_Input(
	'currentusage',
	'*Current Usage',
	'text',
	$pconfig['currentusage']
))->setHelp('Current usage in GB, In case of adjustment, manually input value in GB unit.');
$section->add($group);

$group=new Form_Group("Online Check Method");
$group->add(new Form_Select(
	'check_method',
	'*Check Method',
	$pconfig['check_method'],
	 array(
		 "nmap" => "Specific check port (Port scan) can be banned in certain case",
	 	"none" => "no Monitor (Always Online once terminal is up)",
		"ping"=> "Ping"
	)
))->setHelp('Choose terminal online check method, **IMPORTANT** NOTE that port scan can be banned by site policy');
$section->add($group);
$section->addInput(new Form_Input(
	'destinationip',
	'*Destionation IP',
	'text',
	$pconfig['destinationip'],
	['placeholder' => 'Default: ping for 8.8.8.8,8.8.8,4 for nmap 52.78.7.68:11111']
))->setHelp('**IMPORTANT** wrong input may unusable the gateway, usage: ping [destination IP], Port Scan : [destination IP]:[port], User can input URL instead of IP address., use semicolon ";" to separate two or more addresses');

$group=new Form_Group("Check Timeout in seconds");
$group->add(new Form_Select(
	'check_timeout',
	'*Check Timeout',
	$pconfig['check_timeout'],
	 array(
	 	"3" => "3 (three) seconds (low latency) sutable for VSAT based connection",
	 	"5" => "5 (five) seconds (medium latency) sutable for FBB/Iridium based connection",
	 	"10" => "10 (ten) seconds (high latency) sutable for slower than FBB connection"
	 		)
))->setHelp('Choose terminal online check Timeout');
$section->add($group);

$section->addInput(new Form_Checkbox(
    'disablecrewinternet',
    'Disable Crew Internet',
    'Disable Crew Internet',
    $pconfig['disablecrewinternet']
))->setHelp('Disable Crew Internet when check during this gateway is selected.');

$section->addInput(new Form_Checkbox(
    'blockall_bydefault',
    'Traffic Control',
    'Block all traffic by default if checked',
    $pconfig['blockall_bydefault']
))->setHelp('Block all terminal by default if this gateway is selected, in case of limited usage, under FBB/Iridium. <br> ** NOTE : once this option unchecked, all firewall preset WILL NOT be applied');



$counter=0;

$source_addresses = explode("||", $pconfig['sourceaddresses']);
$dest_addresses = explode("||", $pconfig['destaddresses']);
$port_from = explode("||", $pconfig['portsfrom']);
$port_to = explode("||", $pconfig['portsto']);
$proto = explode("||", $pconfig['protos']);

while ($counter < count($source_addresses)) {
    $group = new Form_Group('Firewall Preset-'.$counter);
    $group->addClass('repeatable');
    $group->add(new Form_Input(
        'source_address'.$counter,
        'Src, IP only',
        'text',
        $source_addresses[$counter]
    ))->setWidth(2);
    $group->add(new Form_Input(
        'dest_address' . $counter,
        'Dest, IP only',
        'text',
        $dest_addresses[$counter]
    ))->setWidth(2);
    $group->add(new Form_Input(
        'port_from' . $counter,
        'Port from',
        'text',
        $port_from [$counter]
    ))->setWidth(1);
    $group->add(new Form_Input(
        'port_to' . $counter,
        'Port to',
        'text',
        $port_to [$counter]
    ))->setWidth(1);
    $group->add(new Form_Select(
        'proto'.$counter,
        'Protocol',
        $proto [$counter],
        array(
            'any' => gettext('Any'),
            'tcp' => 'TCP',
            'udp' => 'UDP',
            'tcp/udp' => 'TCP/UDP'
        )
    ))->setWidth(2);

    $group->add(new Form_Button(
        'deleterow' . $counter,
        'Delete',
        null,
        'fa-trash'
    ))->addClass('btn-warning');
    $section->add($group);
    $counter++;
}
//$form->add($section);

$form->addGlobal(new Form_Button(
    'addrow',
    'Add rule',
    null,
    'fa-plus'
))->addClass('btn-success addbtn');




//////////////////////////////////////////////////////////////////////////////////////////////////////////////
$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for reference (not parsed).');

// Add a button to provide access to the advanced fields
$btnadv = new Form_Button(
	'btnadvopts',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	null,
	$btnadv
));

$form->add($section);
$section = new Form_Section('Advanced');

$section->addClass('adnlopts');

$section->addInput(new Form_Select(
	'weight',
	'Weight',
	$pconfig['weight'],
	array_combine(range(1, 30), range(1, 30))
))->setHelp('Weight for this gateway when used in a Gateway Group.');

$section->addInput(new Form_Input(
	'data_payload',
	'Data Payload',
	'number',
	$pconfig['data_payload'],
	['placeholder' => $dpinger_default['data_payload'], 'min' => 0]
))->setHelp('Define data payload to send on ICMP packets to gateway monitor IP.');

$group = new Form_Group('Latency thresholds');
$group->add(new Form_Input(
	'latencylow',
	'From',
	'number',
	$pconfig['latencylow'],
	['placeholder' => $dpinger_default['latencylow']]
));
$group->add(new Form_Input(
	'latencyhigh',
	'To',
	'number',
	$pconfig['latencyhigh'],
	['placeholder' => $dpinger_default['latencyhigh']]
));
$group->setHelp('Low and high thresholds for latency in milliseconds. ' .
	'Default is %1$d/%2$d.', $dpinger_default['latencylow'], $dpinger_default['latencyhigh']);

$section->add($group);

$group = new Form_Group('Packet Loss thresholds');
$group->add(new Form_Input(
	'losslow',
	'From',
	'number',
	$pconfig['losslow'],
	['placeholder' => $dpinger_default['losslow']]
));
$group->add(new Form_Input(
	'losshigh',
	'To',
	'number',
	$pconfig['losshigh'],
	['placeholder' => $dpinger_default['losshigh']]
));
$group->setHelp('Low and high thresholds for packet loss in %%. ' .
	'Default is %1$d/%2$d.', $dpinger_default['losslow'], $dpinger_default['losshigh']);
$section->add($group);

$section->addInput(new Form_Input(
	'interval',
	'Probe Interval',
	'number',
	$pconfig['interval'],
	[
		'placeholder' => $dpinger_default['interval'],
		'max' => 3600000
	]
))->setHelp('How often an ICMP probe will be sent in milliseconds. Default is %d.', $dpinger_default['interval']);

$section->addInput(new Form_Input(
	'loss_interval',
	'Loss Interval',
	'number',
	$pconfig['loss_interval'],
	['placeholder' => $dpinger_default['loss_interval']]
))->setHelp('Time interval in milliseconds before packets are treated as lost. '.
	'Default is %d.', $dpinger_default['loss_interval']);

$group = new Form_Group('Time Period');
$group->add(new Form_Input(
	'time_period',
	null,
	'number',
	$pconfig['time_period'],
	[
		'placeholder' => $dpinger_default['time_period']
	]
));
$group->setHelp('Time period in milliseconds over which results are averaged. Default is %d.',
	$dpinger_default['time_period']);
$section->add($group);

$group = new Form_Group('Alert interval');
$group->add(new Form_Input(
	'alert_interval',
	null,
	'number',
	$pconfig['alert_interval'],
	[
		'placeholder' => $dpinger_default['alert_interval']
	]
));
$group->setHelp('Time interval in milliseconds between checking for an alert condition. Default is %d.',
	$dpinger_default['alert_interval']);
$section->add($group);

$section->addInput(new Form_StaticText(
	gettext('Additional information'),
	'<span class="help-block">'.
	gettext('The time period, probe interval and loss interval are closely related. The ' .
		'ratio between these values control the accuracy of the numbers reported and ' .
		'the timeliness of alerts.') .
	'<br/><br/>' .
	gettext('A longer time period will provide smoother results for round trip time ' .
		'and loss, but will increase the time before a latency or loss alert is triggered.') .
	'<br/><br/>' .
	gettext('A shorter probe interval will decrease the time required before a latency ' .
		'or loss alert is triggered, but will use more network resource. Longer ' .
		'probe intervals will degrade the accuracy of the quality graphs.') .
	'<br/><br/>' .
	gettext('The ratio of the probe interval to the time period (minus the loss interval) ' .
		'also controls the resolution of loss reporting. To determine the resolution, ' .
		'the following formula can be used:') .
	'<br/><br/>' .
	gettext('&nbsp;&nbsp;&nbsp;&nbsp;100 * probe interval / (time period - loss interval)') .
	'<br/><br/>' .
	gettext('Rounding up to the nearest whole number will yield the resolution of loss ' .
		'reporting in percent. The default values provide a resolution of 1%.') .
	'<br/><br/>' .
	gettext('The default settings are recommended for most use cases. However if ' .
		'changing the settings, please observe the following restrictions:') .
	'<br/><br/>' .
	gettext('- The time period must be greater than twice the probe interval plus the loss ' .
		'interval. This guarantees there is at least one completed probe at all times. ') .
	'<br/><br/>' .
	gettext('- The alert interval must be greater than or equal to the probe interval. There ' .
		'is no point checking for alerts more often than probes are done.') .
	'<br/><br/>' .
	gettext('- The loss interval must be greater than or equal to the high latency threshold.') .
	'</span>'
));

$section->addInput(new Form_Checkbox(
	'nonlocalgateway',
	'Use non-local gateway',
	'Use non-local gateway through interface specific route.',
	$pconfig['nonlocalgateway']
))->setHelp('This will allow use of a gateway outside of this interface\'s subnet. This is usually indicative of a configuration error, but is required for some scenarios.');

$form->add($section);

print $form;
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Show advanced additional opts options ===========================================================================
	var showadvopts = false;

	function show_advopts(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (!(!empty($pconfig['latencylow']) || !empty($pconfig['latencyhigh']) ||
			    !empty($pconfig['losslow']) || !empty($pconfig['losshigh']) ||
			    (isset($pconfig['data_payload']) && is_numeric($pconfig['data_payload']) &&  intval($pconfig['data_payload']) >= 0) ||
			    (!empty($pconfig['weight']) && $pconfig['weight'] > 1) ||
			    (!empty($pconfig['interval']) && !($pconfig['interval'] == $dpinger_default['interval'])) ||
			    (!empty($pconfig['loss_interval']) && !($pconfig['loss_interval'] == $dpinger_default['loss_interval'])) ||
			    (!empty($pconfig['time_period']) && !($pconfig['time_period'] == $dpinger_default['time_period'])) ||
			    (!empty($pconfig['alert_interval']) && !($pconfig['alert_interval'] == $dpinger_default['alert_interval'])) ||
			    (!empty($pconfig['nonlocalgateway']) && $pconfig['nonlocalgateway']))) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvopts = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvopts = !showadvopts;
		}

		hideClass('adnlopts', !showadvopts);

		if (showadvopts) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvopts').html('<i class="fa fa-cog"></i> ' + text);
	}

	let click = $('#btnadvopts').click(function(event) {
		show_advopts();
	});

	// ---------- On initial page load ------------------------------------------------------------

	show_advopts(true);
});
//]]>
</script>

<?php include("foot.inc");?>
