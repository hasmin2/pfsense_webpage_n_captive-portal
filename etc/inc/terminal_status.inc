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
        // 표시 규약: "FBB : 143.5E (Signal : 61)" — 위성 이름은 매핑된 궤도경도로
        // 표시(미매핑 시 단말 보고 이름 그대로), 신호는 정수 반올림.
        $fbb_label = $fbb_status[0];
        if (function_exists('cp_fbb_satlon_from_name') && function_exists('cp_format_satlon')) {
            $mapped = cp_fbb_satlon_from_name($fbb_status[0]);
            if ($mapped !== null) {
                $fbb_label = cp_format_satlon($mapped);
            }
        }
        // (버그 교정: 기존엔 No Signal 판정이 $vsat_status[1] 를 보고 있었음)
        if ($fbb_status[1]==0 || $fbb_status[1]==""){$fbb_signal_string="No Signal";}
        else{$fbb_signal_string= "Signal : ".$fbb_status[1];}
        $fbb_core = "FBB : ".$fbb_label." (".$fbb_signal_string.")";
        if($fbb_drawing_color!==""){$fbbstring = "<span style=\"color: ".$fbb_drawing_color."; \">".$fbb_core."</span>";}
        else{$fbbstring = $fbb_core;}
    }
    return array($vsatstring, $vsatgpsstring, $fbbstring);
}
$vsat_sec_online = "";
function return_gateways_label(){
    $a_gateways=return_gateways_array();
    $gateways_status = return_gateways_status(true);
    $system_status_color="gray-label-wrap";
    $res=check_vsat_status_influxdb();
    $vsattx=$res[2];
    foreach ($a_gateways as $gname => $gateway) {
        if ($gateways_status[$gname] && stristr($gateways_status[$gname]['status'], "online") && !stristr($gateway['terminal_type'],"vpn")) {

            $system_status_color="red-label-wrap";
            $pingresult = get_extnet_status($gateways_status[$gname])[1];
            if(stristr($gateway['terminal_type'],"metered") && !$metered_online) {
                if($pingresult==="Online"){ $metered_online=true; }
                else{ $metered_online=false; }
            }
            if(stristr($gateway['terminal_type'],"iridium")&& !$iridium_online) {
                if($pingresult==="Online"){ $iridium_online=true; }
                else{ $iridium_online=false; }
            }
            if(stristr($gateway['terminal_type'],"fbb") && !$fbb_online) {
                if($pingresult==="Online"){ $fbb_online=true; }
                else{ $fbb_online=false; }
            }
            if(stristr($gateway['terminal_type'],"vsat_pri") && !$vsat_pri_online) {
                if($pingresult==="Online"){ $vsat_pri_online=true; }
                else{ $vsat_pri_online=false; }
            }
            if(stristr($gateway['terminal_type'],"vsat_sec") && !$vsat_sec_online) {
                if($pingresult==="Online"){ $vsat_sec_online=true; }
                else{ $vsat_sec_online=false; }
            }
            if(stristr($gateway['terminal_type'],"vsat_thi") && !$vsat_thi_online) {
                if($pingresult==="Online"){ $vsat_thi_online=true; }
                else{ $vsat_thi_online=false; }
            }
            if(stristr($gateway['terminal_type'],"nexuswave_pri") && !$nexuswave_pri_online) {
                if($pingresult==="Online"){ $nexuswave_pri_online=true; }
                else{ $nexuswave_pri_online=false; }
            }
            if(stristr($gateway['terminal_type'],"nexuswave_sec") && !$nexuswave_sec_online) {
                if($pingresult==="Online"){ $nexuswave_sec_online=true; }
                else{ $nexuswave_sec_online=false; }
            }
            if(stristr($gateway['terminal_type'],"nexuswave_thi") && !$nexuswave_thi_online) {
                if($pingresult==="Online"){ $nexuswave_thi_online=true; }
                else{ $nexuswave_thi_online=false; }
            }
            if(stristr($gateway['terminal_type'],"nexuswave_fth") && !$nexuswave_fth_online) {
                if($pingresult==="Online"){ $nexuswave_fth_online=true; }
                else{ $nexuswave_fth_online=false; }
            }
            if(stristr($gateway['terminal_type'],"tcp_other") && !$tcp_online) {
                if($pingresult==="Online"){ $tcp_online=true; }
                else{ $tcp_online=false; }
            }
            if(stristr($gateway['terminal_type'],"tcp_oneweb") && !$tcp_oneweb_online) {
                if($pingresult==="Online"){ $tcp_oneweb_online=true; }
                else{ $tcp_oneweb_online=false; }
            }
            if(stristr($gateway['terminal_type'],"tcp_starlink") && !$tcp_starlink_online) {
                if($pingresult==="Online"){ $tcp_starlink_online=true; }
                else{ $tcp_starlink_online=false; }
            }
            if(stristr($gateway['terminal_type'],"tcp_kuiper") && !$tcp_kuiper_online) {
                if($pingresult==="Online"){ $tcp_kuiper_online=true; }
                else{ $tcp_kuiper_online=false; }
            }

        }
    }

    $system_status_string="";

    if($vsat_pri_online) {
        $vsat_online=true;
        if (isset($vsat_sec_online)) {
            $vsat_online=false;
            if ($vsattx === 1 || $vsattx === '') {
                $fx_online = true;
            }
            else {
                $fx_backup_online = true;
            }
        }
    }
    if($nexuswave_pri_online ||$nexuswave_sec_online|| $nexuswave_fth_online|| $nexuswave_thi_online) {
        $nexuswave_online=true;
    }
    $system_status_descrtpion = "<br>";
    if($metered_online===true){
        $system_status_color="yellow-label-wrap";
        $system_status_string.="Metered ";
        $system_status_descrtpion .= 'Vessel network is running on metered network, please be aware of data usage<br>';
    }
    if($iridium_online===true){
        $system_status_color="blue-label-wrap";
        $system_status_string.="Iridium ";
        $system_status_descrtpion .= 'Vessel network is running on Iridium network, Pleae be patient that the connection is only up to 700Kbps<br>';
    }
    if($fbb_online===true){
        $system_status_color="blue-label-wrap";
        $system_status_string.="FBB ";
        $system_status_descrtpion .= 'Vessel network is running on FBB network, please be patient that the connection is only up to 500Kbps<br>';
    }
    if($fx_backup_online===true){
        $system_status_color="blue-label-wrap";
        $system_status_string.="FX Backup ";
        $system_status_descrtpion .= 'FX Backup is currently active, please be patient that the connection is only up to 500kbps<br>';
    }

    if($vsat_online===true){
        $system_status_color="green-label-wrap";
        $system_status_string.="VSAT ";
    }
    if($fx_online===true){
        $system_status_color="green-label-wrap";
        $system_status_string.="Fleet Xpress ";
    }
    if($tcp_online===true){
        $system_status_color="purple-label-wrap";
        $system_status_string.="4G ";
    }
    if($tcp_oneweb_online===true){
        $system_status_color="purple-label-wrap";
        $system_status_string.="OneWEB ";
    }

    if($nexuswave_online===true) {
        $system_status_color="purple-label-wrap";
        $system_status_string.="NEXUSWAVE ";
    }
    if($tcp_kuiper_online===true){
        $system_status_color="purple-label-wrap";
        $system_status_string.="Kuiper ";
    }
    if($tcp_starlink_online===true){
        $system_status_color="purple-label-wrap";
        $system_status_string.="Starlink ";
    }
    $system_status_string .='available';
    if($system_status_string==='available'){
        $system_status_string = 'Vessel network offline';
        $system_status_descrtpion .= 'Currently all available network connection found "OFFLINE" please check your antenna is online';
    }
    if($system_status_color==="gray-label-wrap"){
        $system_status_string="No network defined";
        $system_status_descrtpion .= 'System cannot find any network connectivity, please verify that the antenna/internet is connected and configured correctly';
    }
    global $config;
    if (!$config['gateways']['manualrouteduration'] && $system_status_descrtpion=='<br>'){
        $system_status_descrtpion .= 'Vessel antenna is configured to automatically select for best available network.<br>If you want to set a specific antenna, go to "Antenna" and choose an best available antenna.<br>';
    }
    else if(!$config['gateways']['manualrouteduration'] && $system_status_descrtpion=='<br>'){
        $system_status_descrtpion .= 'Vessel antenna is configured manual set as per user request.<br> If you want to set "Auto Switching", go to "Antenna" and choose "Automatic" in antenna selection menu.<br>';
    }
    return array($system_status_color, $system_status_string, $system_status_descrtpion);
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

function get_datausage_from_db($dataMetric, $timeout = 1) {
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
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array('Content-Type: text/plain')
    ));
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlerr = curl_error($ch);
    curl_close($ch);

    // InfluxDB 하드 실패(타임아웃/연결오류/비200)는 false 를 반환한다.
    // 실패를 0(사용량 미달)으로 오독하면 게이트웨이 shutdown 목록이 토글되어
    // crew zone 이 강제 disconnect 되는 flapping(전원 로그아웃 + ipfw 카운터 리셋
    // → 계측 오차)이 발생하기 때문이다. 호출측은 false 를 받으면 상태를 변경하지
    // 말고 이전 상태를 유지해야 한다.
    if ($curlerr !== '' || (int)$code !== 200) {
        return false;
    }

    $responsejson = json_decode($response, true);
    // 쿼리는 성공했으나 데이터 포인트가 없는 경우(예: 월초)는 influx 사용량 0 으로
    // 간주하고 basedata(currentusage)만 가산한다. (이는 정상이며 실패가 아니다.)
    $influxbytes = 0;
    if (isset($responsejson['results'][0]['series'][0]['values'][0][1]) &&
        isset($responsejson['results'][0]['series'][0]['values'][0][2])) {
        $influxbytes = $responsejson['results'][0]['series'][0]['values'][0][1] +
                       $responsejson['results'][0]['series'][0]['values'][0][2];
    }
    $totaldata = round($influxbytes/1073741824, 2);
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

// ---------------------------------------------------------------------------
// 일별 사용량(막대그래프용) — InfluxDB traffic 을 GROUP BY time(1d) 로 질의.
//
// get_datausage_from_db() 와 동일한 원격 InfluxDB(192.168.209.210:8086,
// db=acustatus, measurement=traffic, 필드 {metric}_rx/{metric}_tx)를 쓰되,
// 월합산 대신 "최근 N일"을 하루 버킷으로 나눠 각 일자의 rx+tx 합을 반환한다.
//
// 여러 게이트웨이(metric)를 한 번의 질의로 가져오기 위해 SELECT 에 모든
// metric 의 sum(rx),sum(tx) 를 순서대로 나열한다. InfluxDB 는 같은 함수가
// 반복되면 컬럼명을 sum,sum_1,sum_2... 로 충돌시키므로 이름이 아니라
// "SELECT 순서 = 컬럼 위치"로 매핑한다(time=0, metric i 의 rx=1+2*i, tx=2+2*i).
//
// 하루 경계는 선박 GMT 오프셋(local midnight)에 맞춘다. InfluxDB 의
// GROUP BY time(1d, <offset>) 으로 버킷 경계를 옮기고, 반환 ts(epoch s)는
// 그 경계 시각(UTC)이므로 라벨은 ts + 오프셋초 를 gmdate 로 포맷한다.
//
// $metrics: 인터페이스 device 명(예: vtnet4, vtnet4.10) 배열.
// 반환: array('labels'=>['06-18',...], 'series'=>array(metric=>[gb,...]))
//       하드 실패(타임아웃/연결오류/비200)면 false.
// ---------------------------------------------------------------------------
function read_daily_usage_multi($metrics, $days = 14, $timeout = 4, $monthMode = false) {
    global $config;
    if (!is_array($metrics) || count($metrics) === 0) {
        return array('labels' => array(), 'series' => array());
    }

    // 선박 GMT 오프셋(시간, 반시간대 허용) → InfluxDB GROUP BY 오프셋(분)
    $offsetHours = 0.0;
    if (isset($config['time_offset_enabled']['time_offset']) &&
        $config['time_offset_enabled']['time_offset'] !== '') {
        $offsetHours = floatval($config['time_offset_enabled']['time_offset']);
    }
    $offsetSeconds = (int)round($offsetHours * 3600);
    $groupOffsetMin = -(int)round($offsetHours * 60); // local midnight 경계

    // "이번 달"(현지시각 1일 00:00 ~ 현재) 모드: 조회창 = 현지 날짜(오늘 일자)
    //   + 1일 여유. 이후 라벨의 월(MM)이 현재 현지 월과 같은 버킷만 남긴다.
    $curMonth = '';
    if ($monthMode) {
        $nowLocal   = time() + $offsetSeconds;
        $curMonth   = gmdate('m', $nowLocal);
        $dayOfMonth = (int)gmdate('j', $nowLocal);
        $days       = $dayOfMonth + 1;
    }
    $days = max(1, min(62, intval($days)));

    // SELECT 절: metric 순서대로 sum(rx),sum(tx)
    $sel = array();
    foreach ($metrics as $m) {
        $sel[] = 'sum("' . $m . '_rx")';
        $sel[] = 'sum("' . $m . '_tx")';
    }
    $q = 'SELECT ' . implode(',', $sel) .
         ' FROM traffic WHERE time > now() - ' . $days . 'd' .
         ' GROUP BY time(1d,' . $groupOffsetMin . 'm) fill(0)';

    $url = "http://192.168.209.210:8086/query?db=acustatus&epoch=s&q=" . rawurlencode($q);
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array('Content-Type: text/plain')
    ));
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlerr = curl_error($ch);
    curl_close($ch);
    if ($curlerr !== '' || (int)$code !== 200) {
        return false;
    }
    $j = json_decode($response, true);
    $rows = isset($j['results'][0]['series'][0]['values'])
        ? $j['results'][0]['series'][0]['values'] : array();

    $labels = array();
    $series = array();
    foreach ($metrics as $m) { $series[$m] = array(); }

    foreach ($rows as $row) {
        $ts = isset($row[0]) ? intval($row[0]) : 0;
        $labels[] = gmdate('m-d', $ts + $offsetSeconds);
        foreach ($metrics as $i => $m) {
            $rxIdx = 1 + 2 * $i;
            $txIdx = 2 + 2 * $i;
            $bytes = (isset($row[$rxIdx]) ? floatval($row[$rxIdx]) : 0) +
                     (isset($row[$txIdx]) ? floatval($row[$txIdx]) : 0);
            $series[$m][] = round($bytes / 1073741824, 3); // GB, 소수 3자리
        }
    }

    // month 모드: 현지 월(MM)이 현재 월과 일치하는 버킷만 유지(전월 말일 등 절삭)
    if ($monthMode && $curMonth !== '') {
        $fl = array();
        $fs = array();
        foreach ($metrics as $m) { $fs[$m] = array(); }
        foreach ($labels as $i => $lbl) {
            if (substr($lbl, 0, 2) === $curMonth) {
                $fl[] = $lbl;
                foreach ($metrics as $m) {
                    $fs[$m][] = isset($series[$m][$i]) ? $series[$m][$i] : 0;
                }
            }
        }
        $labels = $fl;
        $series = $fs;
    }

    return array('labels' => $labels, 'series' => $series);
}

// ---------------------------------------------------------------------------
// 게이트웨이 월 사용량 소스(로컬 vnstat) — "불씨" 제거용.
//
// 기존 get_datausage_from_db() 는 원격 InfluxDB 에 "월초~현재" 한 달치 sum 을
// 매번 질의했다. 이 쿼리는 (1) 논리 LAN 의 원격 호출이라 지연/실패 가능,
// (2) 조회창이 월 진행에 따라 단조 증가해 월말로 갈수록 무거워져 1~수초를
// 넘김 → 실패를 0(미달)으로 오독 → shutdown flapping → 전원 로그아웃의 발화점.
//
// 그런데 InfluxDB 의 traffic 데이터는 애초에 이 박스의 vnstat 5분 표본을
// 이 크론이 밀어넣은 "원격 사본"이다. 따라서 로컬 vnstat 의 이번달 누계를
// 직접 읽으면 원격 의존이 사라지고(불씨 제거) 정확도도 더 높다(틱 누락 무관).
// ---------------------------------------------------------------------------

// vnstat 의 "이번 달" 누계를 인터페이스명 => bytes(rx+tx) 맵으로 1회 조회한다.
// 루프에서 게이트웨이마다 vnstat 를 호출하지 않도록 호출측이 1회만 부르고 재사용.
// 실패(미설치/오류/파싱불가)면 false 를 반환한다.
// vnstat --json 을 재시도 포함으로 실행한다.
// vnstat SQLite DB 가 vnstatd 쓰기와 겹치면 "database is locked"(SQLITE_BUSY) 나
// "Database version 0 ..." 등으로 일시 실패한다(선상에서 하루 수십 회 관측). lock 은
// 보통 수십~수백 ms 만 유지되므로 즉시 skip 하지 말고 짧게 대기 후 재시도해 대부분의
// 일시적 실패를 성공으로 흡수한다. 성공 시 JSON 문자열, 끝까지 실패하면 false.
// (stderr 는 버리고, 유효한 interfaces JSON 인지로 성공을 판정 → Error/빈/부분출력 모두 재시도.)
if (!function_exists('cp_vnstat_json_exec')) {
    function cp_vnstat_json_exec($modeargs = '', $maxtries = 4, $sleep_us = 500000) {
        $args = trim((string)$modeargs);
        $cmd  = '/usr/local/bin/vnstat --json' . ($args !== '' ? ' ' . $args : '') . ' 2>/dev/null';
        for ($i = 0; $i < $maxtries; $i++) {
            $out = shell_exec($cmd);
            if (is_string($out) && $out !== '' && strncmp($out, 'Error', 5) !== 0) {
                $decoded = json_decode($out, true);
                if (is_array($decoded) && isset($decoded['interfaces'])) {
                    return $out;
                }
            }
            if ($i < $maxtries - 1) {
                usleep($sleep_us);
            }
        }
        return false;
    }
}

function get_vnstat_month_to_date_map() {
    $json = cp_vnstat_json_exec('m');
    if ($json === false) {
        return false;
    }
    $data = json_decode($json, true);
    if (!isset($data['interfaces']) || !is_array($data['interfaces'])) {
        return false;
    }

    $now = new DateTime();
    $cy  = (int)$now->format('Y');
    $cm  = (int)$now->format('n');

    $out = array();
    foreach ($data['interfaces'] as $iface) {
        $name = $iface['name'] ?? '';
        if ($name === '' ||
            !isset($iface['traffic']['month']) ||
            !is_array($iface['traffic']['month'])) {
            continue;
        }
        $months = $iface['traffic']['month'];

        // 달력상 이번 달 버킷을 우선 매칭, 없으면 가장 최신(마지막) 버킷.
        $picked = null;
        foreach ($months as $m) {
            if ((int)($m['date']['year'] ?? 0) === $cy &&
                (int)($m['date']['month'] ?? 0) === $cm) {
                $picked = $m;
                break;
            }
        }
        if ($picked === null && !empty($months)) {
            $picked = $months[count($months) - 1];
        }
        if ($picked === null) {
            continue;
        }

        $rx = (float)($picked['rx'] ?? 0);
        $tx = (float)($picked['tx'] ?? 0);
        $out[$name] = $rx + $tx;   // vnstat 2.x JSON 은 bytes 단위
    }

    return $out;
}

// 게이트웨이(루트 인터페이스) 월 사용량(GB) = vnstat 이번달 누계 + currentusage baseline.
// vnstat 맵에서 인터페이스를 찾으면 vnstat GB 를 로컬 캐시에 기록한다.
// 소스가 없거나(맵 false/미발견) 하면 캐시 직전값(stale)을 사용해 항상 안정값을
// 돌려준다. 소스도 캐시도 없을 때만 false → 호출측이 상태를 바꾸지 말고 유지(Fix#1).
// baseline 은 캐시에 넣지 않고 매번 새로 더해 관리자 수정이 즉시 반영되게 한다.
function get_gateway_monthly_usage_local($rootIf, $vnstatMap) {
    global $config;

    $cachefile = "/var/db/gwusage-" .
        preg_replace('/[^0-9a-zA-Z._:-]/', '_', (string)$rootIf);

    $basedata = 0;
    foreach ($config['gateways']['gateway_item'] as $gateway) {
        if (($gateway['rootinterface'] ?? '') == $rootIf) {
            $basedata = intval($gateway['currentusage'] ?? 0);
            break;
        }
    }

    $vnstatGb = null;
    if (is_array($vnstatMap) && isset($vnstatMap[$rootIf])) {
        $vnstatGb = round($vnstatMap[$rootIf] / 1073741824, 2);
        @file_put_contents($cachefile, (string)$vnstatGb, LOCK_EX);
    } elseif (is_file($cachefile)) {
        $cached = trim((string)@file_get_contents($cachefile));
        if ($cached !== '' && is_numeric($cached)) {
            $vnstatGb = (float)$cached;   // stale-on-failure
        }
    }

    if ($vnstatGb === null) {
        return false;
    }
    return $vnstatGb + $basedata;
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