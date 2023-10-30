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

pclose($fd);
$datastring="traffic ";
global $config;
$interface = $config['gateways']['gateway_item'];

foreach (json_decode($json_string, true)["interfaces"] as $value) {
	if(strpos($value['name'], "vtnet")!== false || strpos($value['name'], "ovpn")!==false){
        $datestring = $value["traffic"]["fiveminute"][0]["date"]["year"] . "-" . $value["traffic"]["fiveminute"][0]["date"]["month"] . "-" . $value["traffic"]["fiveminute"][0]["date"]["day"] . " " . $value["traffic"]["fiveminute"][0]["time"]["hour"] . ":" . $value["traffic"]["fiveminute"][0]["time"]["minute"] . ":00";
        $timestamp = strtotime($datestring)/60;
        $alias = $value['alias']==='' ? "none" : $value['alias'];
        $datastring .= $value['name']. "_rx=" . $value["traffic"]["fiveminute"][0]["rx"].",".$value['name']. "_tx=" . $value["traffic"]["fiveminute"][0]["tx"].",";
	}
	foreach ($interface as $key => $item) {
	    if(strpos ($value['name'], $item['rootinterface']) !== false && $item['allowance'] !== ''||$item['allowance']=='-1'){
	        $config['gateways']['gateway_item'][$key]['currentusage'] += round(($value['traffic']['fiveminute'][0]['rx'] + $value['traffic']['fiveminute'][0]['tx'])/1000000000, 6);
    	    $config['gateways']['gateway_item'][$key]['speedtx']=round ($value['traffic']['fiveminute'][0]['tx']/38400,0);
    	    $config['gateways']['gateway_item'][$key]['speedrx']=round ($value['traffic']['fiveminute'][0]['rx']/38400,0);
	    }
	}
}
write_config("networkusage update");

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

?>