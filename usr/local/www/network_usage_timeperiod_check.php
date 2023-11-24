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
sleep (10);
pclose($fd);
$datastring="traffic ";
global $config;
$interface = $config['gateways']['gateway_item'];
foreach (json_decode($json_string, true)["interfaces"] as $value) {
	if(strpos($value['name'], "vtnet")!== false || strpos($value['name'], "ovpn")!==false){
        //$datestring = $value["traffic"]["fiveminute"][0]["date"]["year"] . "-" . $value["traffic"]["fiveminute"][0]["date"]["month"] . "-" . $value["traffic"]["fiveminute"][0]["date"]["day"] . " " . $value["traffic"]["fiveminute"][0]["time"]["hour"] . ":" . $value["traffic"]["fiveminute"][0]["time"]["minute"] . ":00";
        //$timestamp = strtotime($datestring)/60;
        $timestamp = (floor(time()/300))*5;
        $alias = $value['alias']==='' ? "none" : $value['alias'];
        $datastring .= $value['name']. "_rx=" . $value["traffic"]["fiveminute"][0]["rx"].",".$value['name']. "_tx=" . $value["traffic"]["fiveminute"][0]["tx"].",";
	}
	foreach ($interface as $key => $item) {
	    if($value['name'] == $item['rootinterface']){
	        $config['gateways']['gateway_item'][$key]['speedtx']=round ($value['traffic']['fiveminute'][0]['tx']/38400,0);
            $config['gateways']['gateway_item'][$key]['speedrx']=round ($value['traffic']['fiveminute'][0]['rx']/38400,0);
		    $currentusagegb = floatval($config['gateways']['gateway_item'][$key]['currentusage']);
            $currentusagegb += floatval(round(($value['traffic']['fiveminute'][0]['rx'] + $value['traffic']['fiveminute'][0]['tx'])/1000000000, 6));
	        echo ("time:".$timestamp."  CurrentUsage : ".$currentusagegb."  Usage: ".round(($value['traffic']['fiveminute'][0]['rx'] + $value['traffic']['fiveminute'][0]['tx'])/1000000000, 6)." LastUsage:".$config['gateways']['gateway_item'][$key]['currentusage']."\n");
	        $config['gateways']['gateway_item'][$key]['currentusage'] = $currentusagegb;
        }
	}
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

sleep (1);
write_config("networkusage update");


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
					$core_module_status = 2;
					break;
				}
			}
			if($core_module_status === 2){
				break;
			}
		}
	}

	return $core_status;
}
?>