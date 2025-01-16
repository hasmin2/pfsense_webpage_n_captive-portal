<?php
require_once("openvpn.inc");

function gps_degree($value){
    $deg = intval($value);
    $min = ($value - $deg) * 60;
    $min = round($min, 3);
    return $deg."&deg;".$min."'";
}
function calculateHeading($lat1, $lon1, $lat2, $lon2) {
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    $dLon = $lon2 - $lon1;
    $y = sin($dLon) * cos($lat2);
    $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);
    $initial_bearing = atan2($y, $x);
    $initial_bearing = rad2deg($initial_bearing);
    $compass_bearing = (fmod(($initial_bearing + 360), 360));
    return $compass_bearing;
}

function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000){
    // convert from degrees to radians
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}

function check_vsat_status_influxdb(){
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20*%20from%20vesselposition%20where%20time%20%3E%20now()%20-60m%20order%20by%20time%20desc&db=acustatus',
        CURLOPT_TIMEOUT => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => GET,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        )
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    if(!$response){
        return array ("-1");
    }
    else {
        $decoded = json_decode($response, true);
        $resultcount = count($decoded['results'][0]['series'][0]['values']);
        $headingIdx = 0;
        $latIdx = 0;
        $lonIdx = 0;
        $columncount= count($decoded['results'][0]['series'][0]['columns']);
        if($resultcount <= 0){
            return array ("-2");
        }
        else if ($resultcount > 0 && $columncount <= 2){
            return array("-3");
        }
        else{
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20Longitude,AGC/Signal,TX_Mode%20from%20satstatus%20where%20time%20%3E%20now()%20-10m%20order%20by%20time%20desc&db=acustatus',
                CURLOPT_TIMEOUT => 1,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_CUSTOMREQUEST => GET,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                )
            ));
            $satelliteresponse = curl_exec($ch);
            curl_close($ch);
            $satellite_decoded = json_decode($satelliteresponse, true);
            $satelliteid=0;
            $satellitesignal=0;
            $satellitetx=0;
            if($decoded['results'][0]['series'][0]['values'][0][1]){
                $satelliteid=$satellite_decoded['results'][0]['series'][0]['values'][0][1];
                $satellitesignal=$satellite_decoded['results'][0]['series'][0]['values'][0][2];
                $satellitetx=$satellite_decoded['results'][0]['series'][0]['values'][0][3];
            }
            for ($i = 0; $i < $columncount; $i++){
                switch($decoded['results'][0]['series'][0]['columns'][$i]){
                    case "Heading":
                        $headingIdx = $i;
                        break;
                    case "Latitude":
                        $latIdx = $i;
                        break;
                    case "Longitude":
                        $lonIdx = $i;
                        break;
                    case "lat_direction":
                        $latDirIdx = $i;
                        break;
                    case "lon_direction":
                        $lonDirIdx = $i;
                        break;
                }
            }
            $headingstring = $decoded['results'][0]['series'][0]['values'][0][$headingIdx];
            if(is_numeric($headingstring)){
                $heading = round($headingstring, 1);
            }
            else{
                $heading = 0;
            }

            $lat = $decoded['results'][0]['series'][0]['values'][0][$latIdx];
            $lon = $decoded['results'][0]['series'][0]['values'][0][$lonIdx];
            $lat_last = end($decoded['results'][0]['series'][0]['values'])[$latIdx];
            $lon_last = end($decoded['results'][0]['series'][0]['values'])[$lonIdx];
            if($heading==0){
                $heading = round(calculateHeading($lat_last, $lon_last, $lat, $lon),1);
            }
            $distance = haversineGreatCircleDistance($lat_last, $lon_last, $lat, $lon, 6371);
            if($lat < 0){
                $lat_current=$lat*-1;
                $latDir="S";
            }
            else {
                $lat_current = $lat;
                $latDir="N";
            }
            if($lon < 0){
                $lon_current=$lon*-1;
                $lonDir="W";
            }
            else {
                $lon_current = $lon;
                $lonDir="E";
            }
            if($lat_last < 0){ $lat_last*=-1; }
            if($lon_last < 0){ $lon_last*=-1; }
            $current_time= $decoded['results'][0]['series'][0]['values'][0][0];
            $last_time= end($decoded['results'][0]['series'][0]['values'])[0];
            $timegap= strtotime($current_time) - strtotime($last_time);
            $avrhrspeed= round($distance/$timegap*3600/1.852, 2);
            $lat_deg = gps_degree($lat_current);
            $lon_deg = gps_degree($lon_current);

            return array("{$satelliteid}","{$satellitesignal}","{$satellitetx}","{$lat_deg}{$latDir}<br>{$lon_deg}{$lonDir}<br>{$heading}deg. {$avrhrspeed}kts");
        }
    }
}
function check_fbb_status_influxdb(){
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20*%20from%20satstatus%20where%20time%20%3E%20now()%20-10m%20order%20by%20time%20desc&db=fbbstatus',
        CURLOPT_TIMEOUT => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => GET,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        )
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    if(!$response){
        return array ("-1");
    }
    else {
        $decoded = json_decode($response, true);
        $resultcount = count($decoded['results'][0]['series'][0]['values']);
        $headingIdx = 0;
        $latIdx = 0;
        $lonIdx = 0;
        $latDirIdx = 0;
        $lonDirIdx = 0;
        $columncount= count($decoded['results'][0]['series'][0]['columns']);
        if($resultcount <= 0){
            return array ("-2");
        }
        else if ($resultcount > 0 && $columncount <= 2){
            return array("-3");
        }
        else{
            for ($i = 0; $i < $columncount; $i++){
                switch($decoded['results'][0]['series'][0]['columns'][$i]){
                    case "Heading":
                        $headingIdx = $i;
                        break;
                    case "Latitude":
                        $latIdx = $i;
                        break;
                    case "Longitude":
                        $lonIdx = $i;
                        break;
                    case "lat-direction":
                        $latDirIdx = $i;
                        break;
                    case "lon-direction":
                        $lonDirIdx = $i;
                        break;
                    case "Signal":
                        $signalIdx = $i;
                        break;
                    case "Satellite":
                        $satelliteIdx = $i;
                        break;
                }
            }
            $headingstring = $decoded['results'][0]['series'][0]['values'][0][$headingIdx];
            if(is_numeric($headingstring)){
                $heading = round($headingstring,1);
            }
            else{
                $heading = 0;
            }
            $satelliteString = $decoded['results'][0]['series'][0]['values'][0][$satelliteIdx];
            $signalString = round($decoded['results'][0]['series'][0]['values'][0][$signalIdx], 2);
            $lat = $decoded['results'][0]['series'][0]['values'][0][$latIdx];
            $lon = $decoded['results'][0]['series'][0]['values'][0][$lonIdx];
            $latDir = $decoded['results'][0]['series'][0]['values'][0][$latDirIdx];
            $lonDir = $decoded['results'][0]['series'][0]['values'][0][$lonDirIdx];

            $lat_last = $decoded['results'][0]['series'][0]['values'][1][$latIdx];
            $lon_last = $decoded['results'][0]['series'][0]['values'][1][$lonIdx];
            if($lat < 0){
                $lat_current=$lat*-1;
                $latDir="S";
            }
            else {
                $lat_current = $lat;
                $latDir="N";
            }
            if($lon < 0){
                $lon_current=$lon*-1;
                $lonDir="W";
            }
            else {
                $lon_current = $lon;
                $lonDir="E";
            }
            if($lat_last < 0){ $lat_last*=-1; }
            if($lon_last < 0){ $lon_last*=-1; }
            $current_time= $decoded['results'][0]['series'][0]['values'][0][0];
            $last_time= $decoded['results'][0]['series'][0]['values'][1][0];
            $timegap= strtotime($current_time) - strtotime($last_time);
            $distance = haversineGreatCircleDistance($lat_current, $lon_current, $lat_last, $lon_last, 6371);
            $avrhrspeed= round($distance/$timegap*3600/1.852, 2);
            $lat_deg = gps_degree($lat_current);
            $lon_deg = gps_degree($lon_current);
            $color="green";
            if($satelliteString=='0'){
                $color="red";
            }
            return array("ID:{$satelliteString}<br>Sig: {$signalString}</font>","{$lat_deg}{$latDir}<br>{$lon_deg}{$lonDir}<br>{$heading}deg. {$avrhrspeed}kts");
        }
    }
}
function send_api($url, $method, $postdata) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 5,
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

function reboot_svr(){ send_api('http://192.168.209.210:8999/shellcommand', 'POST', '{"command": "sudo reboot"}'); }
function reset_core(){ send_api('http://192.168.209.210:8999/shellcommand', 'POST', '{"command": "pkill -9 -ef streamsets"}'); }
function reset_fw(){send_api('http://192.168.209.210:8999/shellcommand', 'POST', '{"command": "sudo virsh reboot vessel-firewall"}'); }
function get_vpnstatus(){
$vpn_clients = openvpn_get_active_clients();
    foreach($vpn_clients as $vpn){
        if($vpn['status'] == "down"){ return "Offline"; }
    }
    return "Online";
}
function get_core_status(){
    $pipelines_result = send_api('http://192.168.209.210:18630/rest/v1/pipelines', 'GET', '');
    $pipelines_status_result = send_api('http://192.168.209.210:18630/rest/v1/pipelines/status', 'GET', '');
    $update_result = json_decode(send_api('http://192.168.209.210:8999/getversion', 'GET', '')[0], true);
    $update_version = $update_result['data'][0]['update_version'];

    if($pipelines_result[1] === 200 && $pipelines_status_result[1] === 200) {
        $core_module_status = 'Online';
        $core_status = 'OK';
    }
    else{
        $core_status = 'Offline';
        $core_module_status = 'N/A';
    }
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
                    $core_module_status = 'NOT OK';
                    break;
                }
            }
            if($core_module_status === 'NOT OK'){
                break;
            }
        }
    }
    if($core_module_status === "Offline"){
        $color = 'red';
    }
    else if($core_module_status === 'Online' && $core_status === 'NOT OK'){
        $color = 'yellow';
    }
    else if($core_module_status === 'Online' && $core_status === 'OK'){
        $color = 'green';
    }
    $rtnstr = $core_module_status.'<br>'.$core_status.'-'.$update_version;
    return array($rtnstr, $color);
}
?>