<?php

require_once('guiconfig.inc');
require_once("auth.inc");
require_once('functions.inc');
require_once('notices.inc');
require_once("pkg-utils.inc");

include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("lan_status.inc");
if (isset($_REQUEST['logout'])) {

    // 1) pfSense 쪽 logout 함수가 있으면 우선 호출
    if (function_exists('log_out')) {
        // 일부 pfSense 계열에서 사용
        @log_out();
    }
    if (function_exists('logout')) {
        // 환경에 따라 있을 수 있음
        @logout();
    }

    // 2) PHP 세션 강제 파기
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $_SESSION = [];

    // 3) 세션 쿠키 제거
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }

    @session_destroy();

    header("Location: /index.php");
    exit;
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
            <div class="d-grid gap-2">
                <!--button type="button" class="btn lg line-mint justify-content-start d-flex align-items-center"
                        onclick="core_open()">
                    <i class="ic-open mint"></i>Open Core console
                </button>

                <button type="button" class="btn lg line-mint justify-content-start d-flex align-items-center"
                        onclick="console_open(<?//php echo (int)$config['terminalinfo']['vsat_ip']; ?>)">
                    <i class="ic-open mint"></i>Open VSAT console
                </button--->

                <form method="post" action="/restart_radiusd.php" class="m-0 w-100">
                    <input type="hidden" name="do" value="restart_radiusd">
                    <button type="submit"
                            class="btn lg line-mint justify-content-start d-flex align-items-center w-100"
                            onclick="return confirm('Restart Wifi Module?');">
                        <i class="ic-open mint"></i>Restart WIFI Module
                    </button>
                </form>
                <br>
            <button class="btn lg line-red justify-content-start" onclick="confirm_resetfw()"><i class="ic-reset red"></i>Reset Firewall</button>
            <button class="btn lg line-red justify-content-start" onclick="confirm_resetcore()"><i class="ic-reset red"></i>Reset Core</button>
            <button class="btn lg line-red justify-content-start" onclick="confirm_rebootsvr()"><i class="ic-reboot red"></i>Reboot SVR</button>
        </div>
        <div class="pop-foot">
            <button class="btn md fill-dark" onclick="popClose('pop-set-server')"><i class="ic-cancel"></i>CANCEL</button>
        </div>
    </div>

</div>
<div id="rebootSplash" style="display:none; position:fixed; inset:0; z-index:999999; background:#0f172a; color:#fff;">
    <div style="height:100%; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:14px; padding:24px; text-align:center;">
        <div style="width:48px; height:48px; border:4px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin 1s linear infinite;"></div>

        <div id="rebootSplashTitle" style="font-size:18px; font-weight:700;">Working…</div>
        <div id="rebootSplashDesc" style="opacity:.85; max-width:520px;">Please wait.</div>
    </div>
</div>

<style>
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
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
        const ok = window.confirm(`Are you sure you want to reset firewall?\nIt takes 2~3 mins to restore internet.`);
        if(!ok) return;

        showSplash('Resetting Firewall…', 'Please wait. This screen will stay until the system is ready.');

        // ✅ 요청은 던지고, 결과는 신경쓰지 말고(끊겨도 정상)
        // ✅ 복귀는 healthz 폴링이 담당
        waitUntilBackOnline({ intervalMs: 3000, graceMs: 3000 });

        $.ajax({
            url: "./index.php",
            data: {resetfw: "true"},
            type: 'POST',
            timeout: 3000 // 짧게: 끊기면 끊기는대로 OK
        });
    }

    function confirm_resetcore(){
        const ok = window.confirm(`Are you sure you want to reset core module?\nDuring reboot, reset buttons won't work for 2~3 mins.`);
        if(!ok) return;

        showSplash('Resetting Core Module…', 'Please wait. This screen will stay until the system is ready.');
        waitUntilBackOnline({ intervalMs: 3000, graceMs: 3000 });

        $.ajax({
            url: "./index.php",
            data: {resetcore: "true"},
            type: 'POST',
            timeout: 3000
        });
    }

    function confirm_rebootsvr(){
        const ok = window.confirm(`Are you sure you want to reboot the whole system?\nIt takes about 5~10 mins to restore intenet.`);
        if(!ok) return;

        showSplash('Rebooting System…', 'Please wait. This screen will stay until the server is back online.');
        waitUntilBackOnline({ intervalMs: 3000, graceMs: 3000 });

        $.ajax({
            url: "./index.php",
            data: {rebootsvr: "true"},
            type: 'POST',
            timeout: 3000
        });
    }



    function showSplash(title, desc) {
        const wrap = document.getElementById('rebootSplash');
        const t = document.getElementById('rebootSplashTitle');
        const d = document.getElementById('rebootSplashDesc');

        if (t) t.textContent = title || 'Working…';
        if (d) d.textContent = desc || 'Please wait.';
        if (wrap) wrap.style.display = 'block';
    }

    function hideSplash() {
        const wrap = document.getElementById('rebootSplash');
        if (wrap) wrap.style.display = 'none';
    }

    // 서버가 다시 살아날 때까지 대기 후 index로 복귀
    function waitUntilBackOnline(opts) {
        const checkUrl = (opts && opts.checkUrl) ? opts.checkUrl : '/healthz.php';
        const intervalMs = (opts && opts.intervalMs) ? opts.intervalMs : 3000;

        const timer = setInterval(() => {
            fetch(checkUrl + '?_=' + Date.now(), { cache: 'no-store' })
                .then(r => r.ok ? r.text() : Promise.reject())
                .then(() => {
                    clearInterval(timer);
                    location.replace('/index.php');
                })
                .catch(() => {});
        }, intervalMs);

        return () => clearInterval(timer);
    }



</script>


