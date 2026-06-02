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

require_once("terminal_status.inc");
require_once("captiveportal.inc");
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
$filepath="/etc/inc/";
$timestamp = intdiv(time(), 60);          // epoch minutes
$timestamp = intdiv($timestamp, 5) * 5;   // 5분 경계로 내림 (분 단위)
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
    }
}

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
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_POSTFIELDS => $datastring,
    CURLOPT_HTTPHEADER => array('Content-Type: text/plain')
));
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);
$isModified=false;

$isModified = false;

$interfaces = $config['interfaces'];

$isModified = false;

foreach ($gateways as &$gateway) {
    $gatewayInterface = $gateway['interface'] ?? '';
    $terminalType     = $gateway['terminal_type'] ?? '';
    $gatewayName      = $gateway['name'] ?? '';

    echo "GATEWAYS: " . $gatewayInterface . "\n";

    if (strpos($terminalType, 'vpn') === 0) {
        echo "Skip VPN gateway: " . $gatewayName . "\n";
        continue;
    }

    if ($gatewayInterface === '' || !isset($interfaces[$gatewayInterface])) {
        echo "No matched interface: " . $gatewayInterface . "\n";
        continue;
    }

    $details = $interfaces[$gatewayInterface];

    echo "INTERFACES: " . $gatewayInterface . "\n";

    if (empty($details['if'])) {
        echo "No root interface for: " . $gatewayInterface . "\n";
        continue;
    }

    $rootIf = $details['if'];
    $gateway['rootinterface'] = $rootIf;

    if (!isset($gateway['allowance']) || $gateway['allowance'] === '') {
        echo "No allowance set for gateway: " . $gatewayName . "\n";
        continue;
    }

    $usage     = get_datausage_from_db($rootIf);
    $allowance = (float)$gateway['allowance'];

    $needShutdown = ((float)$usage >= $allowance);

    $cutoff_enabled = !empty($gateway['cutoff_enable']);

    if ($needShutdown) {
        if ($cutoff_enabled) {
            echo "gateway shutdown start: " . $gatewayName . "\n";

            if (captiveportal_add_shutdown_gateway($gatewayName)) {
                echo "shutdown gateway added: " . $gatewayName . "\n";
                $isModified = true;
            } else {
                echo "shutdown gateway already exists: " . $gatewayName . "\n";
            }
        } else {
            // cutoff_enable 이 꺼져 있으면 차단하지 않음.
            // 혹시 이전에 shutdown_gateways 에 등재되어 있다면 제거해서 정리.
            echo "allowance exceeded but cutoff disabled: " . $gatewayName
                . " (usage=" . $usage . "/" . $allowance . "GB)\n";

            if (captiveportal_remove_shutdown_gateway($gatewayName)) {
                echo "shutdown gateway removed (cutoff disabled): " . $gatewayName . "\n";
                $isModified = true;
            }
        }
    } else {
        echo "gateway turnon start: " . $gatewayName . "\n";

        if (captiveportal_remove_shutdown_gateway($gatewayName)) {
            echo "shutdown gateway removed: " . $gatewayName . "\n";
            $isModified = true;
        } else {
            echo "shutdown gateway not found: " . $gatewayName . "\n";
        }
    }
}

unset($gateway);

if ($isModified) {
    $cpzone='crew';
    $cpzoneid = $config['captiveportal']['crew']['zoneid'];
    echo "shutdown gateway applied: " . $gatewayName . "\n";
    captiveportal_disconnect_all($term_cause = 6, $logoutReason = "DISCONNECT", $carp_loop = false);
    write_config("Update captiveportal shutdown gateway list");
}
function captiveportal_add_shutdown_gateway(string $gatewayName): bool
{
    global $config;

    $gatewayName = trim($gatewayName);

    if ($gatewayName === '') {
        return false;
    }

    // $config['captiveportal']['shutdown_gateways'] 는 zone 배열에 비-zone 키를 주입해
    // phantom CP zone 을 만든다 (#8 prepaid_enabled 동일 패턴). $config['system'] 에 보관.
    if (!isset($config['system']['cp_shutdown_gateways']) ||
        !is_string($config['system']['cp_shutdown_gateways'])) {
        $config['system']['cp_shutdown_gateways'] = '';
    }

    $gatewayNameString = $config['system']['cp_shutdown_gateways'];

    if (strpos($gatewayNameString, $gatewayName . "||") !== false) {
        return false;
    }

    $gatewayNameString .= $gatewayName . "||";

    $config['system']['cp_shutdown_gateways'] = $gatewayNameString;

    return true;
}

function captiveportal_remove_shutdown_gateway(string $gatewayName): bool
{
    global $config;

    $gatewayName = trim($gatewayName);

    if ($gatewayName === '') {
        return false;
    }

    if (!isset($config['system']['cp_shutdown_gateways']) ||
        !is_string($config['system']['cp_shutdown_gateways'])) {
        return false;
    }

    $gatewayList = explode('||', $config['system']['cp_shutdown_gateways']);

    // 빈 값 제거
    $gatewayList = array_filter($gatewayList, function ($value) {
        return trim($value) !== '';
    });

    if (!in_array($gatewayName, $gatewayList, true)) {
        return false;
    }

    $gatewayList = array_filter($gatewayList, function ($value) use ($gatewayName) {
        return $value !== $gatewayName;
    });

    $config['system']['cp_shutdown_gateways'] = empty($gatewayList)
        ? ''
        : implode('||', $gatewayList) . '||';

    return true;
}
/*function send_api($url, $method, $postdata) {
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
}*/

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