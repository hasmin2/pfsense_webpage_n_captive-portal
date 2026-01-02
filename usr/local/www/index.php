<?php

include_once("auth.inc");
include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("lan_status.inc");
require_once('guiconfig.inc');
require_once('functions.inc');
require_once('notices.inc');
require_once("pkg-utils.inc");

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
if(isset($_POST['gmt'])){
    $config['time_offset_enabled']['time_offset'] = $_POST['gmt'];
    write_config("time_offset changed to ", $config['time_offset_enabled']['time_offset']);
    echo '<script> location.replace("index_processing.php");</script>';
}
if(isset($_POST['gmtcheck'])){
    $config['time_offset_enabled']['gmtcheck'] = $_POST['gmtcheck'];
    write_config("GMT has been manually checked");
}
$a_terminal_state = return_terminal_state();
$a_terminal_label = return_gateways_label();
$a_core_status_string = get_core_status();
$vpnstatus = get_vpnstatus();
$vpncolor = $vpnstatus == 'Online' ? 'green' : 'red';

$gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);

$drawing_table_label = "<th>Core/Version</th><th>NOC</th>";
$drawing_table_content = '<td id="core_status_string" data-th="Version" data-th-width="90" data-width="100" class="txt-'.$a_core_status_string[1].'">'.$a_core_status_string[0].'</td>';
$drawing_table_content .='<td id="vpnstatus" data-th="NOC" data-th-width="90" data-width="100" class="txt-'. $vpncolor .'">'.$vpnstatus.'</td>';
$antenna_columncount = 0;
$defaultgw = $config['gateways']['defaultgw4'];
$wan_status ="";
foreach ($gateways as $gname => $gateway){
    if (!startswith($gateway['terminal_type'], 'vpn')){
        $extnet_status = get_extnet_status($gateways_status[$gname]);
        $extnet_status[1]=="Online"? $extnet_color = "txt-green" : $extnet_color = "txt-red";
        foreach ($config['interfaces'] as $ifname => $ifcfg) {
            if ($gateways[$gname]['interface']===$ifcfg['if']) {
                $wan_status .= $gname."";

                if($gateway['allowance']=="" || $gateway['allowance']=="0"||$gateway['terminal_type']==='vsat_sec'){
                    $wan_status .= '<br>'.get_datausage_from_db($ifcfg['if']).'GB';
                } else {
                    $wan_status .= '<br>'.get_datausage_from_db($ifcfg['if']).'/'.$gateway['allowance']."GB";
                }

                break;
            }
        }
        $wan_status .= "<br>";
        $isselected ="";
        if ($gateway['name']===$defaultgw){
            $isselected ="Selected";
        }
        $drawing_table_label .= "<th>$gname</th>";
        $drawing_table_content .= '<td data-th="'.$gname.'" data-th-width="90" data-width="100" class="'.$extnet_color.'">'.$extnet_status[1].'<br><strong>'.$isselected.'</strong></td>';
        $antenna_columncount++;
    }
    $wan_status .= "<br>";
}
$drawing_table_ratio = '<col style="width: calc(100% / '.($antenna_columncount+2).');">';
$drawing_vsat_info=$a_terminal_state[0];
$drawing_fbb_info=$a_terminal_state[2];
$drawing_gps_info=$a_terminal_state[1];
////////////////////SIMPLE SELF API//////////////////////////

if(isset($_POST['resetfw'])){reset_fw(); exit(0);}
if(isset($_POST['resetcore'])){reset_core(); exit(0);}
if(isset($_POST['rebootsvr'])){reboot_svr(); exit(0);}

//$terminate_biz_internet = isset($config['ban_all'])? "true" : "false";
if($_POST['data_update']){
    echo json_encode(array(
        'biztotaldata' => $biztotaldata,
        'iottotaldata' => $iottotaldata,
        'drawing_table_label' => $drawing_table_label,
        'drawing_table_content' => $drawing_table_content,
        'drawing_table_ratio' => $drawing_table_ratio,
        'drawing_gps_info' => $drawing_gps_info,
        'drawing_vsat_info' => $drawing_vsat_info,
        'drawing_fbb_info' => $drawing_fbb_info,
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
            &nbsp;<strong><?php echo $a_terminal_label[2];?></strong>
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
                                    <p class="text" id="vsat_info"><?= $drawing_vsat_info;?></p>
                                    <p class="text" id="fbb_info"><?= $drawing_fbb_info;?></p>
                                </dd>
                            </dl>
                            <dl class="tile-area">
                                <dt>
                                    <img src="../img/gps_pos.png" alt="">
                                    <p>Position</p>
                                </dt>
                                <dd>
                                    <p class="text" id="gps_info"><?= $drawing_gps_info;?></p>
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
                $("#gps_info").html(result.drawing_gps_info);
                $("#vsat_info").html(result.drawing_vsat_info);
                $("#fbb_info").html(result.drawing_fbb_info);
                $("#biz_total_data").html(result.biztotaldata);
                $("#iot_total_data").html(result.iottotaldata);
                /*if(result.toggle_crew_wifi === "true") { $("#crew").prop('checked', true); }
                else { $("#crew").prop('checked', false); }
                if(result.terminate_crew_internet === "true") { $("#terminate_crewinternet").prop('checked', true); }
                else { $("#terminate_crewinternet").prop('checked', false); }
                if(result.terminate_biz_internet === "true") { $("#terminate_bizinternet").prop('checked', true); }
                else { $("#terminate_bizinternet").prop('checked', false); }
                $("#ipaddr").val(result.ban_all_ip);*/
                $("#wan_status").html(result.wan_status);
                //$("#terminate_remaintime").html(result.print_crewwifi_duration);
            },
            error: function (request, status, error) {
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


