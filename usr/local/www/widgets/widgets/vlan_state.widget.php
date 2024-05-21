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


if (!function_exists('compose_vlan_state_contents')) {
	function compose_vlan_state_contents($widgetkey) {
        global $config;
        if(is_array($config['vlan_device']['config'])){
            $tvlanbodystr = '';
            foreach($config['vlan_device']['config'] as $vlan){
                $tvlanbodystr .= '<tr>';
                $vlanidarray= explode ("||", $vlan['id']);
                $tvlanbodystr .= "<td><center>{$vlan['ipaddr']}</center></td>";
                foreach($vlanidarray as $vlanid){
                    $tvlanbodystr .= "//<td><center>{$vlanid}</center></td>";
                }
                $tvlanbodystr .= '</tr>';
                $tvlanbodystr .= "<td><center>-</center></td>";
                $vlanstatearray = explode ("||", $vlan['state']);
                foreach($vlanstatearray as $vlanstate){
                    if($vlanstate==="UP"){
                        $tvlanbodystr .= "//<td><font color=green><center>{$vlanstate}</center></font></td>";
                    }
                    else{
                        $tvlanbodystr .= "//<td><center>{$vlanstate}</center></td>";
                    }
                }
                $tvlanbodystr .= '</tr>';
            }
        }
        else {
            $tvlanbodystr .= '<tr>';
            $tvlanbodystr .= "<td><center>No device found</center></td>";
            $tvlanbodystr .= '<tr>';
        }
        return($tvlanbodystr);
	}
}
if ($_REQUEST && $_REQUEST['ajax']) {
	print(compose_vlan_state_contents($_REQUEST['widgetkey']));
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
		background: #03A9F4;/*占쏙옙*/
		border: solid 1px #0f9ada;/*占쏙옙占쏙옙*/
		border-radius: 4px;
		box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
		text-shadow: 0 1px 0 rgba(0,0,0,0.2);
	}

	.btn-square-little-rich:active {
		/*占썬し占쏙옙占싫わ옙*/
		border: solid 1px #03A9F4;
		box-shadow: none;
		text-shadow: none;
	}
</style>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">

		<tbody id="<?=htmlspecialchars($widgetkey)?>-vlan_state">
        <thead><tr>
        <?
            $tvlanheadstr .= "<td><center>IP</center></td>";
            for($i=1; $i<=12; $i++){
                $tvlanheadstr .= "<td><center>#{$i}</center></td>";
            }
            echo $tvlanheadstr;
        ?>
        </thead></tr>
		</tbody>
	</table>
</div>

<script type="text/javascript">
	events.push(function(){
		// --------------------- Centralized widget refresh system ------------------------------

		// Callback function called by refresh system when data is retrieved
		function vlan_state_callback(s) {
			$(<?= json_encode('#' . $widgetkey .'-vlan_state')?>).html(s);
		}

		// POST data to send via AJAX
		var postdata = {
			ajax: "ajax",
			widgetkey : <?=json_encode($widgetkey)?>
		};
		// Create an object defining the widget refresh AJAX call
		var vlan_stateObject= new Object();
		vlan_stateObject.name = "lan_state";
		vlan_stateObject.url = "/widgets/widgets/vlan_state.widget.php";
		vlan_stateObject.callback = vlan_state_callback;
		vlan_stateObject.parms = postdata;
		vlan_stateObject.freq = 60;

		// Register the AJAX object
		register_ajax(vlan_stateObject);

		// ---------------------------------------------------------------------------------------------------
	});
</script>