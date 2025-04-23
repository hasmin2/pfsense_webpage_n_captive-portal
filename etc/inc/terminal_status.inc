<?php
require_once("openvpn.inc");
include_once("auth.inc");
include_once("terminal_status.inc");
require_once("server_module.inc");
require_once("crew_internet_control.inc");
require_once("globals.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("firewallpreset.inc");

$vsattx="";
function return_terminal_state(){
    $vsat_status =check_vsat_status_influxdb();
    if($vsat_status[1] <=50){ $vsat_drawing_color = "red";}
    else if ($vsat_status[1] <=120 && $vsat_status[1] > 50){$vsat_drawing_color = "yellow";}
    else if ($vsat_status[1] > 120 && $vsat_status[1] <= 200){$vsat_drawing_color = "yellowgreen";}
    else if($vsat_status[1] > 200){$vsat_drawing_color = "blue";}
    else {$vsat_drawing_color = "";}

    if($vsat_status[0]=="-1"){
        $vsatstring = "DB read error";
    } else if($vsat_status[0]=="-2"){
        $vsatstring = "ACU info not available";
    } else {
        $vsat_signal_string="";
        if ($vsat_status[1]==0 || $vsat_status[1]==""){$vsat_signal_string="No Signal";}
        else{$vsat_signal_string= "Signal: ".$vsat_status[1];}
        if($vsat_drawing_color!==""){$vsatstring = "<span style=\"color: ".$vsat_drawing_color."; \">Tracking : ".$vsat_status[0]."<br>".$vsat_signal_string."</span>";}
        else{$vsatstring = "Tracking: ".$vsat_status[0]."<br>".$vsat_signal_string;}
    }
    if($vsat_status[3]==""){ $vsatgpsstring = "GPS info not available";}
    else {$vsatgpsstring = $vsat_status[3];}
    global $vsattx;
    $vsattx = $vsat_status[2];
    $fbb_status = check_fbb_status_influxdb();
    if($fbb_status[1] <=40){ $fbb_drawing_color = "red";}
    else if ($fbb_status[1] <=55 && $fbb_status[1] > 40){$fbb_drawing_color = "yellow";}
    else if ($fbb_status[1] > 55 && $fbb_status[1] <= 70){$fbb_drawing_color = "yellowgreen";}
    else if($fbb_status[1] > 70){$fbb_drawing_color = "blue";}
    else {$fbb_drawing_color = "";}
    if($fbb_status[0]=="-1"){
        $fbbstring = "DB read error";

    } else if($fbb_status[0] =="-2"){
        $fbbstring = "FBB info not available";
    } else {
        $fbb_signal_string="";
        if ($vsat_status[1]==0 || $vsat_status==""){$fbb_signal_string="No Signal";}
        else{$fbb_signal_string= "Signal: ".$fbb_status[1];}
        if($fbb_drawing_color!==""){$fbbstring = "<span style=\"color: ".$fbb_drawing_color."; \">Tracking: ".$fbb_status[0]."<br>".$fbb_signal_string."</span>";}
        else{$fbbstring = "Tracking: ".$fbb_status[0]."<br>".$fbb_signal_string;}
    }
    return array($vsatstring, $vsatgpsstring, $fbbstring);
}
function return_gateways_label(){
    $a_gateways=return_gateways_array();
    $gateways_status = return_gateways_status(true);
    $system_status_color="gray-label-wrap";
    $vsat_online="";
    $metered_online="";
    $fbb_online="";
    $iridium_online="";
    $tcp_online="";
    $res=check_vsat_status_influxdb();
    $vsattx=$res[2];
    $isfx = false;
    foreach ($a_gateways as $gname => $gateway) {
        if ($gateways_status[$gname] && stristr($gateways_status[$gname]['status'], "online") && !stristr($gateway['terminal_type'],"vpn")) {

            $system_status_color="red-label-wrap";
            $pingresult = get_extnet_status($gateways_status[$gname])[1];
            if(stristr($gateway['terminal_type'],"metered")) {
                if($pingresult==="Online"){ $metered_online=true; }
                else{ $metered_online=false; }
            }
            if(stristr($gateway['terminal_type'],"iridium")) {
                if($pingresult==="Online"){ $iridium_online=true; }
                else{ $iridium_online=false; }
            }
            if(stristr($gateway['terminal_type'],"fbb") && !$isfx) {
                if($pingresult==="Online"){ $fbb_online=true; }
                else{ $fbb_online=false; }
            }
            if(stristr($gateway['terminal_type'],"vsat")) {
                if($vsattx=="1" && $pingresult==='Online'){
                    $vsat_online=true;
                }
                else if(($vsattx=="0") && $pingresult==="Online"){
                    $vsat_online=false;
                    $fbb_online=true;
                    $isfx=true;
                }
                else{
                    $vsat_online=false;
                }
            }
            if(stristr($gateway['terminal_type'],"tcp")) {
                if($pingresult==="Online"){ $tcp_online=true; }
                else{ $tcp_online=false; }
            }
        }
    }
    $system_status_string="";
    if($metered_online===true){
        $system_status_color="yellow-label-wrap";
        $system_status_string.="Metered ";
    }
    if($iridium_online===true){
        $system_status_color="blue-label-wrap";
        $system_status_string.="Iridium ";
    }
    if($fbb_online===true){
        $system_status_color="blue-label-wrap";
        $system_status_string.="FBB ";
    }
    if($vsat_online===true){
        $system_status_color="green-label-wrap";
        $system_status_string.="VSAT ";
    }
    if($tcp_online===true){
        $system_status_color="purple-label-wrap";
        $system_status_string.="LEO/4G ";
    }
    $system_status_string .='available';
    if($system_status_string==='available'){
        $system_status_string = 'Vessel network offline';
    }
    return array($system_status_color, $system_status_string);
}


function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}
function get_routingduration(){
    $date = new DateTime();
    global $config;
    $curDate = round($date->getTimestamp(),0);
    $timeleft = ($config['gateways']['manualroutetimestamp']+$config['gateways']['manualrouteduration'])*60 - $curDate;
    if($timeleft>36000){
        $timeRemain= "Fixed";
    }
    else if($timeleft>60){
        $timeRemain= round($timeleft/60, 0) ." minutes";
    }
    else{
        if($timeleft >0 ){ $timeRemain = ($timeleft). " seconds";}
        else {$timeRemain = "Auto";}
    }
    return $timeRemain;
}
function get_extnet_status($gateway_status){
    $online_status="txt-offline";
    if (stristr($gateway_status['status'], "Online")) {
        if($gateway_status['check_method'] == 'none') {
            $pingresult="Online";
            $online_status="txt-online";
        }
        else{
            if(file_exists("/etc/inc/".$gateway_status['srcip'].".log")) {
                $fp = fopen("/etc/inc/" . $gateway_status['srcip'] . ".log", "r");
                $online_status = preg_replace('/\r\n|\r|\n/', '', fgets($fp));
                fclose($fp);
                $pingresult = ucfirst($online_status);
                if($pingresult=='Online'){
                    $online_status = 'txt-online';
                }
                else {
                    $online_status = 'txt-offline';
                }
            }
            else {
                $pingresult = 'Init';
                $online_status = 'txt-offline';
            }
        }
    }
    else {
        $pingresult="N/A";
        $online_status="txt-warning";
    }
    return array ($online_status, $pingresult);
}
function get_defaultgw($gateway){
    if (isset($gateway['isdefaultgw'])) {
        $isselected = true;
    }
    return $isselected;
}
function get_net_status($gateway){
    $online_icon="txt_online";
    $online_text="Online";
    if (stristr($gateway['status'], "online")) {
        switch ($gateway['substatus']) {
            case "highloss":
                $online_text = "Packetloss";
                $online_icon = "txt-warning";
                break;
            case "highdelay":
                $online_text = "Latency";
                $online_icon = "txt-warning";
                break;
            case "loss":
                $online_text = "Packetloss";
                $online_icon = "txt-warning";
                break;
            case "delay":
                $online_text = "Latency";
                $online_icon = "txt-warning";
                break;
            default:
                $online_text = "Online";
                $online_icon = "txt-online";
        }
    }
    elseif (stristr($gateway['status'], "down")) {
        switch ($gateway['substatus']) {
            case "force_down":
                $online_text = "Offline(Forced)";
                $online_icon = "txt-offline";
                break;
            case "highloss":
                $online_text = "Offline(Packetloss)";
                $online_icon = "txt-offline";
                break;
            case "highdelay":
                $online_text = "Offline(Latency)";
                $online_icon = "txt-offline";
                break;
            default:
                $online_icon = "Offline";
                $online_icon = "txt-offline";
        }
    } else {
        $online_icon="txt_offline";
        $online_text="Pending";
    }
    return array($online_icon, $online_text);
}

function set_routing($radiobutton, $routeduration){
    global $config;
    if($radiobutton!="automatic"){
        $config['gateways']['defaultgw4']=$radiobutton;
        $config['gateways']['manualrouteduration']= $routeduration;
    }
    else{
        unset ($config['gateways']['manualrouteduration']);
        unset ($config['gateways']['manualroutetimestamp']);
    }
    destroy_firewall_preset();
    build_firewall_preset($radiobutton);
    $date = new DateTime();
    $config['gateways']['manualroutetimestamp']= round($date->getTimestamp()/60,0);
    write_config("manual routing");
    system_routing_configure();
    system_resolvconf_generate();
    filter_configure();
    setup_gateways_monitor();
    send_event("service reload dyndnsall");
    clear_subsystem_dirty("staticroutes");
    if($radiobutton!="automatic"){
        openvpn_get_active_clients();
        if(isset($config['openvpn']['openvpnrestart'])){
            unset($config['openvpn']['openvpnrestart']);
        }
    }
    else{
        $config['openvpn']['openvpnrestart']='';
    }
}

function get_datausage($gateway){
    $filepath = "/etc/inc/";
    if ($gateway['allowance'] && $gateway['allowance'] != '') {//once we have that metered gateway
        if (file_exists($filepath . $gateway['rootinterface'] . "_cumulative") && ($cumulative_file = fopen($filepath . $gateway['rootinterface'] . "_cumulative", "r")) !== false) {
            $cur_usage = fgets($cumulative_file);
            fclose($cumulative_file);
        } else {
            $cur_usage = 0;
        }
        /*if($gateway['terminal_type']=='vsat_pri' && file_exists($filepath.'fx_total')){
            $cumulative_file = fopen($filepath.'fx_total', "r");
            $cur_usage = fgets($cumulative_file);
            fclose($cumulative_file);
        }*/

        if (file_exists($filepath . $gateway['rootinterface'] . "_cumulative") && ($cumulative_file = fopen($filepath . $gateway['rootinterface'] . "_cumulative", "r")) !== false) {
            $cur_usage = fgets($cumulative_file);
            fclose($cumulative_file);
        }

        $quotausage = round($cur_usage, 2) . '/' . $gateway['allowance'] . 'GB';
        if ($gateway['terminal_type'] == 'vsat_sec') {
            $quotausage = round($cur_usage, 2) . 'GB';
        }
    } else {
        $quotausage = '<font color="gray">Unlimited</font>';
    }
    return $quotausage;
}
function get_speed($gateway){
    $filepath = "/etc/inc/";
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
    return $speedstring;
}

function get_datausage_from_db($dataMetric) {
    global $config;
    $ch = curl_init();
    $dataMetric_rx = $dataMetric."_rx";
    $dataMetric_tx = $dataMetric."_tx";
    $currentDateTime = new DateTime();
    $firstDayOfMonth = new DateTime($currentDateTime->format('Y-m-01'));
    $interval = $currentDateTime->diff($firstDayOfMonth);
    $elaspedminutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    curl_setopt_array($ch, array(
        CURLOPT_URL => "http://192.168.209.210:8086/query?q=select%20sum%28%22".$dataMetric_rx."%22%29,sum%28%22".$dataMetric_tx."%22%29from%20traffic%20where%20time%20%3E%20now()%20-".$elaspedminutes."m&db=acustatus",
        CURLOPT_TIMEOUT => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array('Content-Type: text/plain')
    ));
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $responsejson = json_decode($response, true);
    $totaldata = $responsejson['results'][0]['series'][0]['values'][0][1]+$responsejson['results'][0]['series'][0]['values'][0][2];
    $totaldata = round($totaldata/1073741824, 2);
    $basedata=0;
    foreach($config['gateways']['gateway_item'] as $gateway){
        if($gateway['rootinterface'] == $dataMetric){
            $basedata=intval($gateway['currentusage']);
            break;
        }
    }
    $totaldata = $totaldata + $basedata;

    return $totaldata;
}

function get_speed_from_db($dataMetric){
    $ch = curl_init();
    $dataMetric_rx = $dataMetric."_rx";
    $dataMetric_tx = $dataMetric."_tx";
    $currentDateTime = new DateTime();
    $firstDayOfMonth = new DateTime($currentDateTime->format('Y-m-01'));
    $interval = $currentDateTime->diff($firstDayOfMonth);
    $elaspedminutes = 10;
    curl_setopt_array($ch, array(
        CURLOPT_URL => "http://192.168.209.210:8086/query?q=select%20sum%28%22".$dataMetric_rx."%22%29,sum%28%22".$dataMetric_tx."%22%29from%20traffic%20where%20time%20%3E%20now()%20-".$elaspedminutes."m&db=acustatus",
        CURLOPT_TIMEOUT => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array('Content-Type: text/plain')
    ));
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $responsejson = json_decode($response, true);
    $dn_data = $responsejson['results'][0]['series'][0]['values'][0][1];
    $up_data = $responsejson['results'][0]['series'][0]['values'][0][2];
    $up_speed = $up_data/300*8;
    $dn_speed = $dn_data/300*8;
    if ($dn_speed >= 1024 && $dn_speed<=1048576){
        $avg_speed = round($up_speed/1024, 2).'&nbsp/&nbsp'.round($dn_speed/1024, 2)."Kb/s";
    }
    else if($dn_speed > 1048576){
        $avg_speed = round($up_speed/1048576, 2).'&nbsp/&nbsp'.round($dn_speed/1048576, 2)."Mb/s";
    }
    else {
        $avg_speed = "0&nbsp/&nbsp0Kbps";
    }
    return $avg_speed;
}


?>