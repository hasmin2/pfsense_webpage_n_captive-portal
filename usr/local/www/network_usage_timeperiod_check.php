<?php
/*
 * status_traffic_totals.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

//require_once("ipsec.inc");
require_once("status_traffic_totals.inc");
$json_string = '';
$fd = popen("/usr/local/bin/vnstat --json f 1", "r");
$error = "";

$json_string = str_replace("\n", ' ', fgets($fd));
if(substr($json_string, 0, 5) === "Error") {
    throw new Exception(substr($json_string, 7));
}

while (!feof($fd)) {
    $json_string .= fgets($fd);

    if(substr($json_string, 0, 5) === "Error") {
        throw new Exception(str_replace("\n", ' ', substr($json_string, 7)));
        break;
    }
}
#sleep (10);
pclose($fd);
$datastring="traffic ";
global $config;
$interface = $config['gateways']['gateway_item'];
$filepath="/etc/inc/";
////////////////////
/// VLAN state write
////////////////////
$vlandevices=$config['vlan_device']['item'];
if ($config['vlan_device']['item'] && $vlandevices[0]!==""){
    $newstate = [];
    foreach($vlandevices as $vlandevice){
        mwexec("sh $filepath/vlanstate.sh ".$vlandevice);
        sleep (1);
        $handle = fopen($filepath.$vlandevice.".log", "r");
        if ($handle) {
            $vlan_state='';
            $vlan_id='';
            while (($line = fgets($handle)) !== false) {
                if(strpos($line,"Ethernet")!==false){
                    if(strpos($line, 'up')!==false){
                        $vlan_state.="UP||";
                    }
                    else{
                        $vlan_state.="DN||";
                    }
                    $pvidarray = explode( " ", fgets($handle));//next line
                    if(trim(preg_replace('/\s\s+/', ' ', $pvidarray[4]) ==='')){
                        $vlan_id.='1||';
                    }
                    else{
                        $vlan_id.=trim(preg_replace('/\s\s+/', ' ', $pvidarray[4])).'||';
                    }
                }
            }
            fclose($handle);
        }
        array_push($newstate, ['id'=>$vlan_id, 'state'=>$vlan_state,'ipaddr'=>$vlandevice]);
    }
    if(count($config['vlan_device']['item'])<count($config['vlan_device']['config'])){
        echo "vlan device removed from shoreside\n";
        $config['vlan_device']['config']=[""];
        write_config('vlan_config');
    }
    $ischanged=false;
    foreach($newstate as $eachstate){
        $devicechanged=true;
        foreach($config['vlan_device']['config'] as $vlan_device){
            if($vlan_device['ipaddr']===$eachstate['ipaddr']){
                $devicechanged=false;
                if($eachstate['state']!==$vlan_device['state'] || $eachstate['id']!==$vlan_device['id']) {
                    $ischanged = true;
                    break;
                }
            }
        }
        if($ischanged||$devicechanged){
            break;
        }
    }
    if($ischanged||$devicechanged){
        $config['vlan_device']['config']=$newstate;
        echo "vlan state changed\n";
        write_config('vlan_config');
    }
    else {
        echo "vlan state Not changed\n";
    }
}
else{
    if(($config['vlan_device']['config'])){
        echo "No vlan device Found, restting.\n";
        unset ($config['vlan_device']['config']);
        write_config('vlan_config');
    }
}
///////////////////
$timestamp = (floor(time()/300))*5;
foreach (json_decode($json_string, true)["interfaces"] as $value) {
    if(strpos($value['name'], "vtnet")!== false || strpos($value['name'], "ovpn")!==false){
        $alias = $value['alias']==='' ? "none" : $value['alias'];

        if($value["traffic"]["fiveminute"][0]["rx"]){
            $rxdata = $value["traffic"]["fiveminute"][0]["rx"];
        }
        else {
            $rxdata = 0;
        }
        if($value["traffic"]["fiveminute"][0]["tx"]){
            $txdata = $value["traffic"]["fiveminute"][0]["tx"];
        }
        else {
            $txdata = 0;
        }
        $datastring .= $value['name']. "_rx=" . $rxdata.",".$value['name']. "_tx=" .$txdata.",";
        $crew_interface = $config['captiveportal']['crew']['interface'];
        /*foreach ($config['interfaces'] as $crew_key => $crew_value){
            if($crew_key === $crew_interface){
                $crew_rootinterface = $crew_value['if'];
                break;
            }
        }
        if($value['name']=== $crew_rootinterface){
            if(file_exists($filepath."crew_tx") && ($crew_file = fopen($filepath."crew_tx", "w"))!==false ){
                $fivemintx = $value['traffic']['fiveminute'][0]['tx'];
                fwrite ($crew_file, round ($fivemintx/38400,0));
            }
            else {
                touch($filepath."crew_tx");
                fwrite ($crew_file, 0);
            }
            fclose($crew_file);
            if(file_exists($filepath."crew_rx") && ($crew_file = fopen($filepath."crew_rx", "w"))!==false ){
                $fiveminrx = $value['traffic']['fiveminute'][0]['rx'];
                fwrite ($crew_file, round ($fiveminrx/38400,0));
            }
            else {
                touch($filepath."crew_rx");
                fwrite ($crew_file, 0);
            }
            fclose($crew_file);
            echo "crew : tx".$fivemintx.",      rx".$fiveminrx."\n";
        }
    }
    foreach ($interface as $key => $item) {
        //if($value['name'] === $item['rootinterface']){
            if(file_exists($filepath.$item['rootinterface']."_cumulative") && ($cumulative_file = fopen($filepath.$item['rootinterface']."_cumulative", "r"))!==false ){
                $cur_usage = fgets($cumulative_file);
            }
            else {
                touch($filepath.$item['rootinterface']."_cumulative");
                $cur_usage=0;
            }
            fclose($cumulative_file);
            if(file_exists($filepath.$item['rootinterface']."_tx") && ($use_file = fopen($filepath.$item['rootinterface']."_tx", "w"))!==false ){
                $fivemintx = $value['traffic']['fiveminute'][0]['tx'];
                fwrite ($use_file, round ($fivemintx/38400,0));
            }
            else {
                $fivemintx=0;
                touch($filepath.$item['rootinterface']."_tx");
                fwrite ($use_file, 0);
            }
            fclose($use_file);
            if(file_exists($filepath.$item['rootinterface']."_rx") && ($use_file = fopen($filepath.$item['rootinterface']."_rx", "w"))!==false ){
                $fiveminrx = $value['traffic']['fiveminute'][0]['rx'];
                fwrite ($use_file, round ($value['traffic']['fiveminute'][0]['rx']/38400,0));
            }
            else {
                $fiveminrx=0;
                touch($filepath.$item['rootinterface']."_rx");
                fwrite ($use_file, 0);
            }
            fclose($use_file);

            $currentusagegb = floatval($cur_usage) + round(($fivemintx + $fiveminrx)/1000000000, 6);
            $cumulative_file = fopen($filepath.$item['rootinterface']."_cumulative", "w");
            fwrite($cumulative_file, sprintf('%.6f',$currentusagegb));
            fclose($cumulative_file);
            echo $item['rootinterface'].":".$currentusagegb.",   tx".$fivemintx.",      rx".$fiveminrx."\n";*/
        //}
    }
}
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*$fx_total=0;
$isfx=false;
foreach ($interface as $key => $item) {
    if($item['terminal_type']=='vsat_sec'){
        $isfx=true;
        break;
    }
}
foreach ($interface as $key => $item) {
    if($item['terminal_type']=='vsat_pri' || $item['terminal_type']=='vsat_sec'){
        $cumulative_file = fopen($filepath.$item['rootinterface']."_cumulative", "r");
        $fx_total = $fx_total + fgets($cumulative_file);
        fclose($cumulative_file);
    }
}
if($isfx){
    $fx_totalfile = fopen($filepath."fx_total", "w");
    fwrite($fx_totalfile, sprintf('%.6f',$fx_total));
    fclose($fx_totalfile);
}
else {
    unlink_if_exists($filepath."fx_total");
}*/
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$defaultgw4 = $config['gateways']['defaultgw4'];
$gateways = $config['gateways']['gateway_item'];
foreach ($gateways as $eachgw){
    if($eachgw['name'] === $defaultgw4){
        $currentroute = $eachgw['interface'];
        break;
    }
}
if($currentroute!=""){
    $routeinterface="";
    foreach ($config['interfaces'] as $key => $item) {
        if($key === $currentroute){
            $routeinterface = str_replace(".", "_", $item['if']);
            break;
        }
    }
    $datastring .=  "currentroute=\"" .$routeinterface."\",";
}
$datastring .= 'core_status='.get_module_status();
$datastring = rtrim($datastring, ',');
$datastring .= " " .$timestamp;
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => "http://192.168.209.210:8086/write?db=acustatus&precision=m",
    CURLOPT_TIMEOUT => 1,
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_CUSTOMREQUEST => POST,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_POSTFIELDS => $datastring,
    CURLOPT_HTTPHEADER => array('Content-Type: text/plain')
));
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

//sleep (1);
//write_config("networkusage update");


function send_api($url, $method, $postdata) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 10,
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
        $core_status = 0;
    }
    else{
        $core_status = 1;
    }
    $noc_status = '<font color=green>ONLINE';
    $pipelines = json_decode($pipelines_result[0], true);
    $status = json_decode($pipelines_status_result[0], true);
    foreach($pipelines as $item){
        if(substr($item['title'],0,16)=== '[System Pipeline'){
            foreach($status as $statusitem){
                if($statusitem['pipelineId'] === $item['pipelineId']&&
                    $statusitem['status'] === 'EDITED' ||
                    $statusitem['status'] === 'RUN_ERROR' ||
                    $statusitem['status'] === 'STOPPED' ||
                    $statusitem['status'] === 'START_ERROR' ||
                    $statusitem['status'] === 'STOP_ERROR' ||
                    $statusitem['status'] === 'DISCONNECTED' ||
                    $statusitem['status'] === 'RUNNING_ERROR' ||
                    $statusitem['status'] === 'STARTING_ERROR' ||
                    $statusitem['status'] === 'STOPPING' ||
                    $statusitem['status'] === 'STOPPING_ERROR'
                ) {
                    $core_status = 2;
                    break;
                }
            }
            if($core_status === 2){
                break;
            }
        }
    }

    return $core_status;
}
?>