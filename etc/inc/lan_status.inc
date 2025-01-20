<?php
require_once("globals.inc");
require_once("pfsense-utils.inc");
require_once("server_module.inc");
require_once("functions.inc");

global $config;
function draw_servertable_contents()
{
    $core_status_string = get_core_status();
}
function get_vlan_status(){
    global $config;
    $vlanlist = array();
    $eachvlan = $config['vlan_device']['config'];
    foreach($eachvlan as $index => $vlan){
        $vlanlist[$index]=$vlan;
    }
    return($vlanlist);
}

function draw_vlantable_contents(){

    $vlanlist = get_vlan_status();
    $rtnstr= "";
    foreach($vlanlist as $index => $vlan){
        $rtnstr.= "<tr>";
        $rtnstr.='<td data-th="IP" data-th-width="40" data-width="100" class="ta-c">'.$vlan["ipaddr"].'</td>';
        $vlanidarray= explode ("||", $vlan['id']);
        array_pop($vlanidarray);
        $vlanstatearray = explode ("||", $vlan['state']);
        foreach($vlanidarray as $portindex => $eachid) {
            $rtnstr .= '<td data-th="#' . $portindex . '" data-th-width="40" data-width="50" class="ta-c">';
            $rtnstr .= $eachid .'<br>';
            if($vlanstatearray[$portindex] == "UP"){ $rtnstr .='<i class="ic-up sm" ></i >'; }
            else if($vlanstatearray[$portindex] == "DN"){ $rtnstr .='<i class="ic-down sm" ></i >'; }
            $rtnstr .='</td>';
        }
        $rtnstr.='</tr>';
    }

   return ($rtnstr);
}

function draw_lantable_contents(){
    global $config;
    $lanstate = $config['interface']['lanstate'];
    $rtnstr = "<thead><tr>";
    foreach ($lanstate as $portname => $portstate) {
        $rtnstr .= "<th>" . $portname . "</th>";
    }
    $rtnstr .= "</thead><tbody><tr>";
    foreach ($lanstate as $portname => $portstate) {
        $rtnstr.='<td data-th="'.$portname.'" data-th-width="60" data-width="50">';
        if($portstate['status'] == "1"){ $rtnstr .= '<i class="ic-up md"></i>'; }
        else{ $rtnstr .= '<i class="ic-down md"></i></td>'; }
    }
    $rtnstr .="</tr></tbody>";
    return ($rtnstr);
}



function read_month_data($dataMetric) {
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
    if($totaldata<1048576){
        $totaldata = round($totaldata/1024, 2)."KB";
    }
    else if ($totaldata<1073741824){
        $totaldata = round($totaldata/1048576, 2)."MB";
    } else{
        $totaldata = round($totaldata/1073741824, 2)."GB";
    }
    return $totaldata;
}
?>