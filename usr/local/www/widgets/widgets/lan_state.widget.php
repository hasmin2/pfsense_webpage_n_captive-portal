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


if (!function_exists('compose_lan_state_contents')) {
	function compose_lan_state_contents($widgetkey) {
		$theadstr = '<thead><tr>';
		$tbodystr = '';
		global $config;
		if(isset($config['interface']['lanstate'])){
			$lanstate = &$config['interface']['lanstate'];
			foreach ($lanstate as $key => $item) {
				$theadstr .= "<th center> ${key} </center></th>";
				if($item==1){
					$itemstate="UP";
				}
				else{
					$itemstate="Down";
				}
				$tbodystr .= "<td><center>$itemstate</center></td>";
			}
		}
		else{

		}
		$theadstr .= "</tr></thead>";
		return($theadstr.$tbodystr);
	}
}
if ($_REQUEST && $_REQUEST['ajax']) {
	print(compose_lan_state_contents($_REQUEST['widgetkey']));
	exit;
}


$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);

?>
<style>

.btn-square-little-rich {
  position: relative;
  display: inline-block;
  padding: 0.25em 0.5em;
  text-decoration: none;
  color: #FFF;
  background: #03A9F4;/*��*/
  border: solid 1px #0f9ada;/*����*/
  border-radius: 4px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
  text-shadow: 0 1px 0 rgba(0,0,0,0.2);
}

.btn-square-little-rich:active {
  /*�㪷���Ȫ�*/
  border: solid 1px #03A9F4;
  box-shadow: none;
  text-shadow: none;
}
</style>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">

		<tbody id="<?=htmlspecialchars($widgetkey)?>-lan_state">
<?php
		print(compose_lan_state_contents($widgetkey));
?>
		</tbody>
	</table>
</div>

<script type="text/javascript">
/*events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function lan_state_callback(s) {
		$(<?= json_encode('#' . $widgetkey .'-lan_state')?>).html(s);
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		widgetkey : <?=json_encode($widgetkey)?>
	 };
	// Create an object defining the widget refresh AJAX call
	var lan_stateObject= new Object();
	lan_stateObject.name = "lan_state";
	lan_stateObject.url = "/widgets/widgets/lan_state.widget.php";
	lan_stateObject.callback = lan_state_callback;
	lan_stateObject.parms = postdata;
	lan_stateObject.freq = 1;

	// Register the AJAX object
	register_ajax(lan_StateObject);

	// ---------------------------------------------------------------------------------------------------
});*/
</script>