<?php

include_once("auth.inc");
include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("lan_status.inc");

function print_crewwifi_timeduration(){
    global $config;
    $rtnstr = "";
    if(isset($config['captiveportal']['crew']['terminate_duration'])){
        $date = new DateTime();
        $terminate_timeleft = $config['captiveportal']['crew']['terminate_duration']-(round($date->getTimestamp()/60,0) -$config['captiveportal']['crew']['terminate_timestamp']);
        if($terminate_timeleft>1440){ $rtnstr .= "<p class='text'>". Fixed."</p>";}
        else{ $rtnstr .= "<p class='text'>". $terminate_timeleft." minutes left</p>";}
    }
    else{
        $rtnstr .= "<select name='terminate_duration' id='terminate_duration' class='select v1'>
                                                <option value='5'>5 minutes</option>
                                                <option value='30'>30 minutes</option>
                                                <option value='60'>60 minutes</option>
                                                <option value='300'>5 hours</option>
                                                <option value='1440'>24 hours</option>
                                                <option value='999999999'>Permanent</option></select>";
    }
    return $rtnstr;
}

global $config, $g;
$totaldata = "N/A";
foreach ($config['interfaces'] as $ifname => $ifcfg) {
    if ($ifcfg['descr']==="BUSINESS") {
        $biztotaldata = "Business:".read_month_data($ifcfg['if']);
    }
    if ($ifcfg['descr']==="IOTLAN") {
        $iottotaldata = "IoT:".read_month_data($ifcfg['if']);
    }
}
$a_terminal_state = return_terminal_state();
$a_terminal_label = return_gateways_label();
$a_core_status_string = get_core_status();
$vpnstatus = get_vpnstatus();
$vpncolor = $vpnstatus == 'Online' ? 'green' : 'red';

init_config_arr(array('captiveportal'));
if(isset($_POST['crewcheckboxvalue'])){toggle_crew_wifi($_POST['crewcheckboxvalue']);}
if(isset($_POST['terminate_crewinternetvalue'])){
    terminate_crew_internet($_POST['terminate_crewinternetvalue'], $_POST['terminate_duration']);
}
if(isset($_POST['terminate_bizinternetvalue'])){
    terminate_biz_internet($_POST['terminate_bizinternetvalue'], $_POST['ipaddr']);
}

$gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);

$wan_status ="";
$drawing_table_label = "<th>Core/Version</th><th>NOC</th>";
$drawing_table_content = '<td id="core_status_string" data-th="Version" data-th-width="90" data-width="100" class="txt-'.$a_core_status_string[1].'">'.$a_core_status_string[0].'</td>';
$drawing_table_content .='<td id="vpnstatus" data-th="NOC" data-th-width="90" data-width="100" class="txt-'. $vpncolor .'">'.$vpnstatus.'</td>';
$antenna_columncount = 0;
foreach ($gateways as $gname => $gateway){
    if (!startswith($gateway['terminal_type'], 'vpn')){
        $extnet_status = get_extnet_status($gateways_status[$gname]);
        $extnet_status[1]=="Online"? $extnet_color = "txt-green" : $extnet_color = "txt-red";
        $wan_status .= $gname;
        $wan_status .= get_speed($gateway);
        $wan_status .= "&nbsp&nbsp&nbsp";
        $wan_status .= get_datausage($gateway);
        $wan_status .= "<br>";
        $drawing_table_label .= "<th>$gname</th>";
        $drawing_table_content .= '<td data-th="'.$gname.'" data-th-width="90" data-width="100" class="'.$extnet_color.'">'.$extnet_status[1].'</td>';
        $antenna_columncount++;
    }
    $wan_status .= "<br>";
}
$drawing_table_ratio = '<col style="width: calc(100% / '.($antenna_columncount+2).');">';




////////////////////SIMPLE SELF API//////////////////////////

if(isset($_POST['resetfw'])){reset_fw(); exit(0);}
if(isset($_POST['resetcore'])){reset_core(); exit(0);}
if(isset($_POST['rebootsvr'])){reboot_svr(); exit(0);}

$terminate_biz_internet = isset($config['ban_all'])? "true" : "false";
if($_POST['data_update']){
    echo json_encode(array(
        'biztotaldata' => $biztotaldata,
        'iottotaldata' => $iottotaldata,
        'drawing_table_label' => $drawing_table_label,
        'drawing_table_content' => $drawing_table_content,
        'drawing_table_ratio' => $drawing_table_ratio,
        'vsat_tracking_info' => $a_terminal_state[0],
        'vsat_signal_info' => $a_terminal_state[1],
        'gps_info' => $a_terminal_state[2],
        'fbb_tracking_info' => $a_terminal_state[3],
        'fbb_signal_info' => $a_terminal_state[4],
        'toggle_crew_wifi' => isset($config['captiveportal']['crew']['enable'])? "true" : "false",
        'terminate_crew_internet' => isset($config['captiveportal']['crew']['terminate_duration'])? "true" : "false",
        'print_crewwifi_duration' => print_crewwifi_timeduration(),
        'terminate_biz_internet' => $terminate_biz_internet,
        'ban_all_ip' => $config['ban_all_ip'],
        'wan_status' => $wan_status
        ));
    exit(0);
}
////////////////////SIMPLE SELF API//////////////////////////
?>
<!DOCTYPE HTML>
<html lang="ko">

<head>
    <?php echo print_css_n_head(); ?>
</head>
<body>
<div id="wrapper">
    <?php echo print_sidebar( basename($_SERVER['PHP_SELF']));?>
    <div id="content">
        <div class="contents">
            <div class="container" id="terminal_label">
                <div class="private-wrap" >
                    <div id="terminal_label_color" class="<?php echo $a_terminal_label[0];?>" >
                        <dl class="system-status-area" id="terminal_label_content">
                            <?php echo $a_terminal_label[1];?>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="contents">
                <div class="title-area">
                    <div class="private-wrap" >
                        <div class="tile-wrap">
                            <dl class="tile-area">
                                <dt>
                                    <img src="../img/sat_ant.png" alt="">
                                    <p>Satellite</p>
                                </dt>
                                <dd>
                                    <p class="text" id="vsat_tracking_info"><?php echo $a_terminal_state[0];?></p>
                                    <p class="text" id="vsat_signal_info"><?php echo $a_terminal_state[1];?></p>
                                    <p class="text" id="fbb_tracking_info"><?php echo $a_terminal_state[3];?></p>
                                    <p class="text" id="fbb_signal_info"> <?php echo $a_terminal_state[4];?> </p>

                                </dd>

                            </dl>
                            <dl class="tile-area">
                                <dt>
                                    <img src="../img/gps_pos.png" alt="">
                                    <p>Position</p>
                                </dt>
                                <dd>
                                    <p class="text" id="gps_info"><?php echo $a_terminal_state[2];?></p>
                                </dd>
                            </dl>
                            <dl class="tile-area">
                                <dt>
                                    <img src="../img/data_usage.png" alt="">
                                    <p>Internet usage</p>
                                </dt>
                                <dd>
                                    <p id="wan_status" class="text"><?php echo $wan_status;?></p>
                                </dd>
                            </dl>
                            <dl class="tile-area">
                                <dt>
                                    <img src="../img/lan_usage.png" alt="">
                                    <p>LAN usage</p>
                                </dt>
                                <dd>
                                    <p id="biz_total_data" class="text"><?php echo $biztotaldata;?></p>
                                    <p id="iot_total_data" class="text"><?php echo $iottotaldata;?></p>
                                </dd>
                            </dl>

                        </div>
                    </div>
                </div>
                <div class="container">
                    <div class="server-wrap">
                        <div class="tit-wrap v1 mt40">
                            <div class="tit-area">
                                <p class="tit v2">Server Status</p>
                            </div>
                            <div class="etc-area">
                                <button class="btn-setting" onclick="popOpenAndDim('pop-set-server', true)">Setting</button>
                            </div>
                        </div>
                        <div class="list-wrap v1 mt20">
                            <table id="server_status">
                                <colgroup id="table_ration">
                                    <?php echo $drawing_table_ratio;?>
                                </colgroup>
                                <thead>
                                <tr id="table_label">
                                    <?php echo $drawing_table_label;?>
                                </tr>
                                </thead>
                                <tbody>
                                <tr id="table_content">
                                    <?php echo $drawing_table_content;?>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="popup layer pop-set-server">
        <div class="pop-head">
            <p class="title">Server Setting</p>
        </div>
        <div class="pop-cont">
            <button class="btn lg line-mint justify-content-start" onclick="core_open()"><i class="ic-open mint"></i>Open Core console</button>
            <button class="btn lg line-mint justify-content-start" onclick="console_open(<?php echo $config['terminalinfo']['vsat_ip'];?>)"><i class="ic-open mint"></i>Open VSAT console</button>
            <button class="btn lg line-mint justify-content-start" onclick="console_open(<?php echo $config['terminalinfo']['fbb_ip'];?>)"><i class="ic-open mint"></i>Open FBB console</button>
            <button class="btn lg line-red justify-content-start" onclick="confirm_resetfw()"><i class="ic-reset red"></i>Reset Firewall</button>
            <button class="btn lg line-red justify-content-start" onclick="confirm_resetcore()"><i class="ic-reset red"></i>Reset Core</button>
            <button class="btn lg line-red justify-content-start" onclick="confirm_rebootsvr()"><i class="ic-reboot red"></i>Reboot SVR</button>
        </div>
        <div class="pop-foot">
            <button class="btn md fill-dark" onclick="popClose('pop-set-server')"><i class="ic-cancel"></i>CANCEL</button>
        </div>
    </div>

</div>
</body>
</html>
<script>
    function ipAddressCheck(ipAddress){
        var regEx = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        if(ipAddress.match(regEx)||ipAddress.value===""){
            return true;
        }
        else{alert("Please enter a valid ip Address.");return false;}
    }
    function refreshValue() {
        $.ajax({
            url: "./index.php",
            data: {data_update: "true"},
            type: 'POST',
            dataType: 'json',
            success: function (result) {
                $("#table_label").html(result.drawing_table_label);
                $("#table_content").html(result.drawing_table_content);
                $("#table_ratio").html(result.drawing_table_ratio);
                $("#vsat_tracking_info").html(result.vsat_tracking_info);
                $("#vsat_signal_info").html(result.vsat_signal_info);
                $("#gps_info").html(result.gps_info);
                $("#fbb_tracking_info").html(result.fbb_tracking_info);
                $("#fbb_signal_info").html(result.fbb_signal_info);
                $("#biz_total_data").html(result.biztotaldata);
                $("#iot_total_data").html(result.iottotaldata);
                if(result.toggle_crew_wifi === "true") { $("#crew").prop('checked', true); }
                else { $("#crew").prop('checked', false); }
                if(result.terminate_crew_internet === "true") { $("#terminate_crewinternet").prop('checked', true); }
                else { $("#terminate_crewinternet").prop('checked', false); }
                if(result.terminate_biz_internet === "true") { $("#terminate_bizinternet").prop('checked', true); }
                else { $("#terminate_bizinternet").prop('checked', false); }
                $("#ipaddr").val(result.ban_all_ip);
                $("#wan_status").html(result.wan_status);
                $("#terminate_remaintime").html(result.print_crewwifi_duration);
            },
            error: function (request, status, error) {
                alert(error);
            }
        })
    }
    setInterval(refreshValue, 10000); // 밀리초 단위이므로 5초는 5000밀리초
    function core_open(){
        var ip = "192.168.209.210";
        /*if(location.host.startsWith("10")){
            ip = location.host;
        }*/
        var form = document.createElement("form");
        form.setAttribute("method", "post");
        form.setAttribute("target", "_blank");
        form.setAttribute("action", "http://"+ip+":18630/j_security_check");
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = "j_username";
        input.value = "admin";
        form.appendChild(input);
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = "j_password";
        input.value = "admin";
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function console_open(ipaddr){
        window.open(`http://${ipaddr}`);
    }
    function confirm_resetfw(){
        var confirm = window.confirm(`Are you sure you want to reset firewall?\nIt takes 2~3 mins to restore internet.`);
        if(confirm){
            $.ajax({
                url: "./index.php",
                data: {resetfw: "true"},
                type: 'POST',
                success: function (result) {
                }
            })
        }
    }
    function confirm_resetcore(){
        var confirm = window.confirm(`Are you sure you want to reset core module?\nDuring reboot, reset buttons won't work for 2~3 mins.`);
        if(confirm){
            $.ajax({
                url: "./index.php",
                data: {resetcore: "true"},
                type: 'POST',
                success: function (result) {
                }
            })
        }
    }
    function confirm_rebootsvr(){
        var confirm = window.confirm(`Are you sure you want to reboot the whole system?\nIt takes about 5~10 mins to restore intenet.`);
        if(confirm){
            $.ajax({
                url: "./index.php",
                data: {rebootsvr: "true"},
                type: 'POST',
                success: function (result) {
                }
            })
        }
    }
</script>


