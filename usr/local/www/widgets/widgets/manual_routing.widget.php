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

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("openvpn.inc");
require_once("captiveportal.inc");
require_once("firewallpreset.inc");
require_once("/usr/local/www/widgets/include/manual_routing.inc");


if (!function_exists('compose_manual_routing_contents')) {
    function compose_manual_routing_contents($widgetkey) {
        global $user_settings;
        global $config;
        $filepath = "/etc/inc/";
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
            if ($gateway['monitor_disable'] || $gateway['terminal_type']==="vpn") {
                continue;
            }
            $gw_displayed = true;
            $rtnstr .= "<tr>\n";
            $rtnstr .= 	"<td class='text-center' title='{$title}'>\n";
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
                            $online = gettext("Danger, <br/>Packetloss");
                            $bgcolor = "danger";
                            break;
                        case "highdelay":
                            $online = gettext("Danger, <br/>Latency");
                            $bgcolor = "danger";
                            break;
                        case "loss":
                            $online = gettext("Warning <br/>Packetloss");
                            $bgcolor = "warning";
                            break;
                        case "delay":
                            $online = gettext("Warning, <br/>Latency");
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
                            $online = gettext("Offline <br/>(forced)");
                            break;
                        case "highloss":
                            $online = gettext("Offline, <br/>Packetloss");
                            break;
                        case "highdelay":
                            $online = gettext("Offline, <br/>Latency");
                            break;
                        default:
                            $online = gettext("Offline");
                    }
                } else {
                    $online = gettext("Pending");
                    $bgcolor = "info";  // lightgray
                }

            } else {
                $online = gettext("No Conn");
                $bgcolor = "info";  // lightblue
            }
            if ($gateways_status[$gname] && stristr($gateways_status[$gname]['status'], "online")) {
                if($gateways_status[$gname]['check_method'] == 'none') {
                    $pingcolor="success";
                    $pingresult="Online";
                }
                else{
                    if(file_exists("/etc/inc/".$gateways_status[$gname]['srcip'].".log")) {
                        $fp = fopen("/etc/inc/" . $gateways_status[$gname]['srcip'] . ".log", "r");
                        $online_status = preg_replace('/\r\n|\r|\n/', '', fgets($fp));
                        fclose($fp);
                        $pingresult = $online_status;
                        if ($pingresult == 'online') {
                            $pingresult = "Online";
                            $pingcolor = "success";
                        } else {
                            $pingcolor = "danger";
                            $pingresult = "Offline";
                        }
                    }
                    else {
                        $pingcolor="info";
                        $pingresult = 'Init';
                    }
                }
            }
            else {
                $pingcolor="";
                $pingresult="N/A";
            }

            $date = new DateTime();
            $curDate = round($date->getTimestamp(),0);
            $timeleft = ($config['gateways']['manualroutetimestamp']+$config['gateways']['manualrouteduration'])*60 - $curDate;
            if($timeleft>36000){
                $timeRemain= "Fixed";
            }
            else if($timeleft>60){
                $timeRemain= round($timeleft/60, 0) ." minutes";
            }
            else{
                if($timeleft >0 ){
                    $timeRemain = ($timeleft). " seconds";
                }
                else {
                    if($config['gateways']['defaultgw4']==''){
                        $timeRemain= '';
                    }
                    else {
                        $timeRemain = "Auto";
                    }
                }
            }
//////////////////////////////////////
            if(startswith($gateway['terminal_type'], 'vsat')){
                $ch = curl_init();
                curl_setopt_array($ch, array(
                    CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20*%20from%20satstatus%20where%20time%20%3E%20now()%20-30m%20order%20by%20time%20desc%20limit%201&db=acustatus',
                    CURLOPT_TIMEOUT => 1,
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_CUSTOMREQUEST => GET,
                    CURLOPT_RETURNTRANSFER => TRUE,
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json',
                        'Content-Type: application/vnd.flux'
                    )
                ));
                $response = curl_exec($ch);
                curl_close($ch);
                if(!$response){
                    $signal = '<font color="red">Offline</font>';
                }
                else{
                    $response = json_decode($response, true);
                    $signal = $response['results'][0]['series'][0]['values'][0][1];
                    if($signal < 1){
                        $signal = '<font color="red">No Signal</font>';
                    }
                    else{
                        if($signal < 70){
                            $signal = '<font color="red">'.$signal.'</font>';
                        }
                        else if ($signal >=70 && $signal < 120){
                            $signal = '<font color="yellow">'.$signal.'</font>';
                        }
                        else if ($signal >=120 && $signal < 220){
                            $signal = '<font color="green">'.$signal.'</font>';
                        }
                        else if ($signal >=220 && $signal < 400){
                            $signal = '<font color="blue">'.$signal.'</font>';
                        }
                        else {
                            $signal = '<font color="gray">'.$signal.'</font>';
                        }
                    }
                }
            }
            else if(startswith($gateway['terminal_type'], 'fbb')){
                $signal = '<font color="gray">N/I</font>';
            }
            else{
                $signal = '<font color="gray">N/A</font>';
            }
            $signal_description = "title='Signal Level, below 70 is normally unable to make internet connection.&#10;70~120 may able to internet with similar speed with FB250/500.&#10;110~170 may surfing with average speed, Datausage can be calculated by sum of each terminal TX/RX usage'";
            if($gateway['allowance'] && $gateway['allowance'] != ''){//once we have that metered gateway

                if(file_exists($filepath.$gateway['rootinterface']."_cumulative") && ($cumulative_file = fopen($filepath.$gateway['rootinterface']."_cumulative", "r"))!==false ){
                    $cur_usage = fgets($cumulative_file);
                    fclose($cumulative_file);
                }
                else {
                    $cur_usage = 0;
                }
                /*if($gateway['terminal_type']=='vsat_pri' && file_exists($filepath.'fx_total')){
                    $cumulative_file = fopen($filepath.'fx_total', "r");
                    $cur_usage = fgets($cumulative_file);
                    fclose($cumulative_file);
                }*/

                if(file_exists($filepath.$gateway['rootinterface']."_cumulative") && ($cumulative_file = fopen($filepath.$gateway['rootinterface']."_cumulative", "r"))!==false ){
                    $cur_usage = fgets($cumulative_file);
                    fclose($cumulative_file);
                }

                $quotausage = round($cur_usage, 2). '/'.$gateway['allowance'].'GB';
                if($gateway['terminal_type']=='vsat_sec'){
                    $quotausage = round($cur_usage, 2).'GB';
                }
            }
            else{
                $quotausage = '<font color="gray">Unlimited</font>';
            }
            if ($gateway['rootinterface'] && $gateway['rootinterface'] != ''){
                if(file_exists($filepath.$gateway['rootinterface']."_tx") && ($open_file = fopen($filepath.$gateway['rootinterface']."_tx", "r"))!==false ){
                    $txspeed = fgets($open_file);
                    fclose($open_file);
                }
                else {
                    $txspeed = 0;
                }
                if(file_exists($filepath.$gateway['rootinterface']."_rx") && ($open_file = fopen($filepath.$gateway['rootinterface']."_rx", "r"))!==false ){
                    $rxspeed = fgets($open_file);
                    fclose($open_file);
                }
                else {
                    $rxspeed = 0;
                }
                if($txspeed>=1024 || $rxspeed >=1024){
                    $speedstring = '<br>'.round($txspeed/1024, 1).'/'.round($rxspeed/1024, 1).'Mbps';
                }
                else{
                    $speedstring = '<br>'.$txspeed.'/'.$rxspeed.'Kbps';
                }

            }
            else {
                $speedstring = '<br>setLAN';
            }
            $gw_description = "title='GW is normally for set to Auto, or Fixed to manage manually terminal switch, up/down in Kbps last 5(five) minutes speed'";
            $rtnstr .= 	"<td {$signal_description} class='text-center'>$signal<br> $quotausage</td>\n";
///////////////////////////////////////
            $rtnstr .= 	"<td {$gw_description}class='text-center'>" . ($config['gateways']['defaultgw4']==$gateways_status[$gname]['name'] ? $timeRemain:"").$speedstring."</td>\n";
            $rtnstr .= '<td class="bg-' . $bgcolor . '" style="text-align:center">' . $online . "</td>\n";
            $rtnstr .= '<td class="bg-' . $pingcolor . '" style="text-align:center">' . $pingresult . "</td>\n";

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
if ($_POST['widgetkey']) {
    global $config;
    if($_POST['routing_radiobutton']){
        if($_POST['routing_radiobutton']!="Automatic"){
            $config['gateways']['defaultgw4']=$_POST['routing_radiobutton'];
            $config['gateways']['manualrouteduration']= $_POST['routeduration'];
        }
        else{
            unset ($config['gateways']['manualrouteduration']);
            unset ($config['gateways']['manualroutetimestamp']);
        }
        destroy_firewall_preset();
        build_firewall_preset($_POST['routing_radiobutton']);
        $date = new DateTime();
        $config['gateways']['manualroutetimestamp']= round($date->getTimestamp()/60,0);
        write_config("manual routing");
        system_routing_configure();
        system_resolvconf_generate();
        filter_configure();
        setup_gateways_monitor();
        send_event("service reload dyndnsall");
        clear_subsystem_dirty("staticroutes");
        if($_POST['routing_radiobutton']!="Automatic"){
            $clients = openvpn_get_active_clients();
            if(isset($config['openvpn']['openvpnrestart'])){
                unset($config['openvpn']['openvpnrestart']);
            }
            foreach($clients as $client){
            }
        }
        else{
            $config['openvpn']['openvpnrestart']='';
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
            <th class="text-center"><?=gettext("Name")?></th>
            <th class="text-center">Info</th>
            <th class="text-center">GW</th>
            <th class="text-center">Net</th>
            <th class="text-center">Ext-Net</th>
        </tr>
        </thead>
        <tbody id="<?=htmlspecialchars($widgetkey)?>-manual-route">
        <?php
        print(compose_manual_routing_contents($widgetkey));
        ?>
        </tbody>
    </table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">
    <form action="/widgets/widgets/manual_routing.widget.php" method="post" class="form-horizontal">
        <?//=gen_customwidgettitle_div($widgetconfig['title']);?>
        <div class="form-group">
            <label title= "Manually force to select current internet connection.&#10;Operator may choose this by manually for certain time duration. all network flow will be routed to designated internet connection.&#10;The manual selection will be restored certain time period even if it was powered off." class="col-sm-4 control-label"><?=gettext('Manual Override')?></label>
            <div class="col-sm-6">
                <div class="radio">
                    <label>
                        <input name="routing_radiobutton" type="radio" value="Automatic">Automatic</label>
                </div>

                <?php
                $gateways = return_gateways_array();
                foreach ($gateways as $gname => $gateway):
                    if (!startswith($gateway['terminal_type'], 'vpn') ):
                        ?>
                        <div class="radio">
                            <label><input name="routing_radiobutton" type="radio" value=<?echo($gname);?> <?//echo($config['gateways']['defaultgw4']==$gname) ? 'checked':'';?>><?echo($gname);?></label>
                        </div>
                    <?php
                    endif;
                endforeach;
                ?>
            </div>
            <label title="Time duration for manual selection.&#10;After the time goes to 0 the system is going back to auto routing mode." name="routing_radiobutton" class="col-sm-4 control-label"><?=gettext('Time duration')?></label>
            <div class="col-sm-6">
                <div class="radio">
                    <select name="routeduration" size="1">
                        <option value="5">5 minutes </option>
                        <option value="30">30 minutes </option>
                        <option value="60">60 minutes </option>
                        <option value="300">5 hours </option>
                        <option value="864000000">Permanent</option>
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
                $(<?= json_encode('#' . $widgetkey .'-manual-route')?>).html(s);
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