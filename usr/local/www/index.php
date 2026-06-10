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
    echo '<script> location.replace("processing.php?to=index.php");</script>';
}
if(isset($_POST['gmtcheck'])){
    $config['time_offset_enabled']['gmtcheck'] = $_POST['gmtcheck'];
    write_config("GMT has been manually checked");
}
$a_terminal_state = return_terminal_state();
$a_terminal_label = return_gateways_label();
// ACU 안테나 지향 시각화 데이터 (server_module.inc 구버전 배포 대비 가드 — 버전섞임 시 fatal 방지)
$acu_view = function_exists('get_acu_pointing_info')
    ? get_acu_pointing_info()
    : array('ok' => false, 'status' => 'nodata');
// FBB 보조 니들 데이터 (선수방위는 ACU 쪽 값을 공유)
$fbb_view = function_exists('get_fbb_pointing_info')
    ? get_fbb_pointing_info(isset($acu_view['heading']) ? $acu_view['heading'] : null)
    : array('ok' => false, 'status' => 'nodata');
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
        'wan_status' => $wan_status,
        'acu_view' => $acu_view,
        'fbb_view' => $fbb_view
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
                                    <!-- ACU 안테나 지향(트래킹) 시각화 — ACU SIGNAL(vsat_info) 위 -->
                                    <div class="acu-trk" id="acu_trk" data-status="nodata">
                                        <p class="acu-trk-status"><span class="acu-dot"></span><span id="acu_trk_label">--</span></p>
                                        <p class="acu-trk-status acu-fbb-status" id="acu_fbb_status"><span class="acu-dot"></span><span id="acu_fbb_label">FBB : --</span></p>
                                        <svg id="acu_trk_svg" viewBox="0 0 252 196" xmlns="http://www.w3.org/2000/svg" aria-label="Antenna tracking view">
                                            <g transform="translate(92,100)">
                                                <line class="acu-cross" x1="-64" y1="0" x2="64" y2="0"/>
                                                <line class="acu-cross" x1="0" y1="-64" x2="0" y2="64"/>
                                                <circle class="acu-ring-outer" r="72"/>
                                                <circle class="acu-ring-inner" r="64"/>
                                                <g id="acu_ticks"></g>
                                                <g id="acu_dial_rot"></g>
                                                <g id="acu_sweep">
                                                    <path class="acu-sweep-wedge" d="M0 0 L0 -64 A64 64 0 0 1 32 -55.4 Z">
                                                        <animateTransform attributeName="transform" type="rotate" from="0" to="360" dur="4s" repeatCount="indefinite"/>
                                                    </path>
                                                </g>
                                                <g id="acu_fbb_az_rot">
                                                    <line class="acu-fbb-az-line" x1="0" y1="-8" x2="0" y2="-56"/>
                                                    <circle class="acu-fbb-dot" cx="0" cy="-58" r="2.6"/>
                                                </g>
                                                <g id="acu_az_rot">
                                                    <line class="acu-az-line" x1="0" y1="-8" x2="0" y2="-61"/>
                                                    <circle class="acu-sat-dot" cx="0" cy="-64" r="3.4"/>
                                                </g>
                                                <g id="acu_ship_rot">
                                                    <g>
                                                        <animateTransform attributeName="transform" type="rotate" values="-1.6;1.6;-1.6" keyTimes="0;0.5;1" calcMode="spline" keySplines="0.45 0 0.55 1;0.45 0 0.55 1" dur="9s" repeatCount="indefinite"/>
                                                        <line class="acu-bow" x1="0" y1="-38" x2="0" y2="-54"/>
                                                        <path class="acu-ship" d="M0,-32 C7,-22 9,-12 9,0 L9,20 C9,25 5,28 0,28 C-5,28 -9,25 -9,20 L-9,0 C-9,-12 -7,-22 0,-32 Z"/>
                                                        <circle class="acu-deck" cx="0" cy="-2" r="6.5"/>
                                                        <circle class="acu-deck-dot" cx="0" cy="-2" r="1.6"/>
                                                        <circle class="acu-deck" cx="0" cy="13" r="4"/>
                                                    </g>
                                                </g>
                                            </g>
                                            <g transform="translate(196,174)">
                                                <path class="acu-el-arc" d="M48 0 A48 48 0 0 0 0 -48"/>
                                                <g id="acu_el_ticks"></g>
                                                <text class="acu-el-lbl" x="44" y="12">0&#176;</text>
                                                <text class="acu-el-lbl" x="0" y="-54">90&#176;</text>
                                                <g id="acu_fbb_el_rot">
                                                    <line class="acu-fbb-el-needle" x1="7" y1="0" x2="33" y2="0"/>
                                                </g>
                                                <g id="acu_el_rot">
                                                    <line class="acu-el-needle" x1="7" y1="0" x2="40" y2="0"/>
                                                </g>
                                                <circle class="acu-el-pivot" r="3"/>
                                            </g>
                                        </svg>
                                        <ul class="acu-trk-metrics">
                                            <li><span>HEADING</span><strong id="acu_m_hdg">--</strong></li>
                                            <li><span>AZIMUTH</span><strong id="acu_m_az">--</strong></li>
                                            <li><span>R.AZIMUTH</span><strong id="acu_m_raz">--</strong></li>
                                            <li><span>ELEVATION</span><strong id="acu_m_el">--</strong></li>
                                        </ul>
                                    </div>
                                    <!-- 기존 VSAT/FBB 텍스트는 위 나침반 상태줄 2개(VSAT/FBB)가 대체 -->
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

    /* === ACU 안테나 트래킹 시각화 (Satellite 타일) === */
    .acu-trk {max-width: 300px; margin: 0 auto 8px;}
    .acu-trk-status {display:flex; align-items:center; justify-content:center; gap:6px; font-size:13px; font-weight:600; color:#868E96; line-height:1;}
    .acu-trk .acu-dot {width:8px; height:8px; border-radius:50%; background:#ADB5BD; flex:0 0 auto;}
    /* VSAT 상태색은 첫 상태줄에만 (FBB 줄은 .acu-fbb-status 가 별도 관리) */
    .acu-trk[data-status="tracking"] .acu-trk-status:not(.acu-fbb-status) {color:#12B886;}
    .acu-trk[data-status="tracking"] .acu-trk-status:not(.acu-fbb-status) .acu-dot {background:#12B886; animation: acuDotPulse 2.2s ease-out infinite;}
    .acu-trk[data-status="searching"] .acu-trk-status:not(.acu-fbb-status) {color:#FAB005;}
    .acu-trk[data-status="searching"] .acu-trk-status:not(.acu-fbb-status) .acu-dot {background:#FAB005;}
    .acu-trk[data-status="blocked"] .acu-trk-status:not(.acu-fbb-status) {color:#FA5252;}
    .acu-trk[data-status="blocked"] .acu-trk-status:not(.acu-fbb-status) .acu-dot {background:#FA5252;}
    .acu-fbb-status {margin-top:5px;}
    .acu-trk .acu-fbb-status.on {color:#4C6EF5;}
    .acu-trk .acu-fbb-status.on .acu-dot {background:#4C6EF5;}
    .acu-trk svg {display:block; width:100%; height:auto; margin-top:10px;}
    .acu-trk[data-status="nodata"]:not(.has-fbb) svg {opacity:.45;}
    .acu-ring-outer {fill:none; stroke:#868E96; stroke-width:1.6;}
    .acu-ring-inner {fill:none; stroke:#DEE2E6; stroke-width:1;}
    .acu-cross {stroke:#F1F3F5; stroke-width:1;}
    .acu-tick {stroke:#CED4DA; stroke-width:1;}
    .acu-tick-main {stroke:#868E96; stroke-width:1.4;}
    .acu-lbl-card {font-size:11px; font-weight:700; fill:#495057; text-anchor:middle; dominant-baseline:middle;}
    .acu-lbl-num {font-size:8px; fill:#ADB5BD; text-anchor:middle; dominant-baseline:middle;}
    .acu-ship {fill:#E9ECEF; stroke:#495057; stroke-width:1.4; stroke-linejoin:round;}
    .acu-deck {fill:#FFFFFF; stroke:#495057; stroke-width:1.2;}
    .acu-deck-dot {fill:#495057;}
    .acu-bow {stroke:#343A40; stroke-width:3.4; stroke-linecap:round;}
    /* VSAT 지향선: FBB 와 동일 스타일(실선+도트), 색만 녹색 */
    .acu-az-line {stroke:#12B886; stroke-width:2; stroke-linecap:round;}
    .acu-sat-dot {fill:#12B886;}
    #acu_az_rot {opacity:0; transition:opacity .6s;}
    .acu-trk.has-az[data-status="tracking"] #acu_az_rot,
    .acu-trk.has-az[data-status="blocked"] #acu_az_rot {opacity:1;}
    .acu-trk[data-status="blocked"] .acu-az-line {stroke:#FA5252;}
    .acu-trk[data-status="blocked"] .acu-sat-dot {fill:#FA5252;}
    #acu_sweep {opacity:0; transition:opacity .6s;}
    .acu-trk[data-status="searching"] #acu_sweep {opacity:1;}
    .acu-sweep-wedge {fill:rgba(250,176,5,.16); stroke:#FAB005; stroke-width:1;}
    .acu-el-arc {fill:none; stroke:#868E96; stroke-width:1.4;}
    .acu-el-tick {stroke:#CED4DA; stroke-width:1;}
    .acu-el-needle {stroke:#12B886; stroke-width:2.4; stroke-linecap:round;}
    .acu-trk[data-status="blocked"] .acu-el-needle,
    .acu-trk[data-status="searching"] .acu-el-needle,
    .acu-trk[data-status="nodata"] .acu-el-needle {stroke:#ADB5BD;}
    .acu-el-pivot {fill:#495057;}
    .acu-el-lbl {font-size:7px; fill:#ADB5BD; text-anchor:middle;}
    /* FBB 보조 니들 (파랑) — has-fbb 일 때만 표시 */
    .acu-fbb-az-line {stroke:#4C6EF5; stroke-width:1.6; stroke-linecap:round;}
    .acu-fbb-dot {fill:#4C6EF5;}
    .acu-fbb-el-needle {stroke:#4C6EF5; stroke-width:1.8; stroke-linecap:round;}
    #acu_fbb_az_rot, #acu_fbb_el_rot {opacity:0; transition:opacity .6s;}
    .acu-trk.has-fbb #acu_fbb_az_rot,
    .acu-trk.has-fbb #acu_fbb_el_rot {opacity:1;}
    .acu-trk-metrics {display:flex; gap:6px; margin-top:10px;}
    .acu-trk-metrics li {flex:1 1 0; min-width:0; border:1px solid #DEE2E6; padding:6px 2px 5px; text-align:center; list-style:none;}
    .acu-trk-metrics li span {display:block; font-size:9px; font-weight:600; letter-spacing:.4px; color:#868E96;}
    .acu-trk-metrics li strong {display:block; font-size:14px; font-weight:700; color:#212529; margin-top:2px; transition: color .4s;}
    .acu-trk-metrics li strong.src-vsat {color:#12B886;}
    .acu-trk-metrics li strong.src-fbb {color:#4C6EF5;}
    @keyframes acuDotPulse {
        0% {box-shadow:0 0 0 0 rgba(18,184,134,.45);}
        70% {box-shadow:0 0 0 7px rgba(18,184,134,0);}
        100% {box-shadow:0 0 0 0 rgba(18,184,134,0);}
    }

    /* === FULL HD(1080) 세로 맞춤 — Main Panel 한정 압축 오버라이드 ===
       전역 style.css 는 그대로 두고 이 페이지에서만 여백/크기를 줄인다.
       (인라인 style 블록이 외부 css 보다 뒤에 로드되므로 동일 선택자로 덮임) */
    .private-wrap .tile-wrap .tile-area {padding: 22px 24px;}
    .private-wrap .tile-wrap .tile-area dt img {width: 40px;}
    .private-wrap .tile-wrap .tile-area dt p {margin-top: 10px;}
    .private-wrap .tile-wrap .tile-area dd {margin-top: 18px; padding-top: 18px;}
    .private-wrap .tile-wrap .tile-area dd .text {margin-top: 16px;}
    .acu-trk {max-width: 260px;}
    /* utility.css 의 .mt40/.mt20 이 !important 라 동일하게 !important 로 덮는다 */
    .server-wrap .tit-wrap.v1.mt40 {margin-top: 14px !important;}
    .server-wrap .list-wrap.v1.mt20 {margin-top: 8px !important;}
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
                $("#biz_total_data").html(result.biztotaldata);
                $("#iot_total_data").html(result.iottotaldata);
                $("#wan_status").html(result.wan_status);
                if (result.acu_view) { updateAcuCompass(result.acu_view); }
                if (result.fbb_view) { updateFbbCompass(result.fbb_view); }
                //$("#terminate_remaintime").html(result.print_crewwifi_duration);
            },
            error: function (request, status, error) {
            }
        })
    }
    setInterval(refreshValue, 10000); // 밀리초 단위이므로 5초는 5000밀리초

    // ===================== ACU 안테나 트래킹 시각화 =====================
    // 눈금/라벨 1회 생성 (나침반 + 앙각 게이지)
    (function buildAcuScales() {
        var ns = 'http://www.w3.org/2000/svg';
        var g = document.getElementById('acu_ticks');
        if (g) {
            var names = {0: 'N', 90: 'E', 180: 'S', 270: 'W'};
            // 눈금은 외륜(r72) 바깥쪽으로 — 내측 환형부(64~72)는 회전 다이얼 숫자 전용
            for (var a = 0; a < 360; a += 15) {
                var rad = a * Math.PI / 180, s = Math.sin(rad), c = Math.cos(rad);
                var len = (a % 45 === 0) ? 4.5 : 2.5;
                var l = document.createElementNS(ns, 'line');
                l.setAttribute('x1', (72 * s).toFixed(2));
                l.setAttribute('y1', (-72 * c).toFixed(2));
                l.setAttribute('x2', ((72 + len) * s).toFixed(2));
                l.setAttribute('y2', (-(72 + len) * c).toFixed(2));
                l.setAttribute('class', (a % 45 === 0) ? 'acu-tick-main' : 'acu-tick');
                g.appendChild(l);
            }
            for (var b = 0; b < 360; b += 90) {
                var rad2 = b * Math.PI / 180;
                var t = document.createElementNS(ns, 'text');
                t.setAttribute('x', (84 * Math.sin(rad2)).toFixed(2));
                t.setAttribute('y', (-84 * Math.cos(rad2)).toFixed(2));
                t.setAttribute('class', 'acu-lbl-card');
                t.textContent = names[b];
                g.appendChild(t);
            }
        }
        // 내측 숫자 = 선수 기준 상대방위 다이얼 — 선수방위와 함께 회전(레퍼런스 ACU UI 동일).
        // 각 숫자는 rotate(각도)로 접선 방향으로 눕혀 배치(아래쪽 숫자는 뒤집혀 보이는 게 정상).
        var dial = document.getElementById('acu_dial_rot');
        if (dial) {
            for (var dnum = 45; dnum < 360; dnum += 45) {
                var dt = document.createElementNS(ns, 'text');
                dt.setAttribute('x', '0');
                dt.setAttribute('y', '-68');
                dt.setAttribute('transform', 'rotate(' + dnum + ')');
                dt.setAttribute('class', 'acu-lbl-num');
                dt.textContent = String(dnum);
                dial.appendChild(dt);
            }
        }
        var eg = document.getElementById('acu_el_ticks');
        if (eg) {
            for (var e = 0; e <= 90; e += 30) {
                var er = e * Math.PI / 180, ec = Math.cos(er), es = Math.sin(er);
                var el = document.createElementNS(ns, 'line');
                el.setAttribute('x1', (48 * ec).toFixed(2));
                el.setAttribute('y1', (-48 * es).toFixed(2));
                el.setAttribute('x2', (43 * ec).toFixed(2));
                el.setAttribute('y2', (-43 * es).toFixed(2));
                el.setAttribute('class', 'acu-el-tick');
                eg.appendChild(el);
            }
        }
    })();

    // 회전 트윈: rotate() 속성 직접 갱신(로컬 원점 회전 — transform-origin 비의존).
    // 359→1 같은 wrap-around 에서 최단 경로로 회전.
    var acuTween = {};
    function acuRotateTo(id, deg) {
        var node = document.getElementById(id);
        if (!node || deg === null || deg === undefined || isNaN(deg)) { return; }
        var st = acuTween[id];
        if (!st) {
            acuTween[id] = {cur: deg, raf: null};
            node.setAttribute('transform', 'rotate(' + deg + ')');
            return;
        }
        var from = st.cur;
        var delta = (((deg - from) % 360) + 540) % 360 - 180;
        var to = from + delta;
        if (st.raf) { cancelAnimationFrame(st.raf); st.raf = null; }
        if (Math.abs(delta) < 0.25) {
            st.cur = to;
            node.setAttribute('transform', 'rotate(' + to + ')');
            return;
        }
        var t0 = null, dur = 1600;
        function step(ts) {
            if (t0 === null) { t0 = ts; }
            var p = Math.min(1, (ts - t0) / dur);
            var ease = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
            var v = from + delta * ease;
            node.setAttribute('transform', 'rotate(' + v + ')');
            st.cur = v;
            if (p < 1) { st.raf = requestAnimationFrame(step); }
            else { st.raf = null; st.cur = to; }
        }
        st.raf = requestAnimationFrame(step);
    }

    // AZ/R.AZ/EL 메트릭 로테이션: 5초마다 VSAT(녹색) ↔ FBB(파랑) 교대 표시.
    // 한쪽 데이터만 있으면 그쪽에 고정, 둘 다 없으면 '--'. HEADING 은 공용(기본색).
    var acuLastVsat = null, acuLastFbb = null, acuMetricsSrc = 'vsat';
    function acuHasPointing(d) {
        return !!(d && d.azimuth !== null && d.azimuth !== undefined && !isNaN(d.azimuth));
    }
    function renderAcuMetrics() {
        function setVal(id, v, cls) {
            var n = document.getElementById(id);
            if (!n) { return; }
            n.textContent = (v === null || v === undefined || isNaN(v)) ? '--' : (Math.round(v) + '°');
            n.className = cls || '';
        }
        var hdg = (acuLastVsat && acuLastVsat.heading !== null && acuLastVsat.heading !== undefined)
            ? acuLastVsat.heading : null;
        setVal('acu_m_hdg', hdg, '');

        var vsatOk = acuHasPointing(acuLastVsat);
        var fbbOk = acuHasPointing(acuLastFbb);
        var src = acuMetricsSrc;
        if (src === 'fbb' && !fbbOk) { src = 'vsat'; }
        if (src === 'vsat' && !vsatOk && fbbOk) { src = 'fbb'; }
        var d = ((src === 'fbb') ? acuLastFbb : acuLastVsat) || {};
        var cls = (src === 'fbb') ? 'src-fbb' : (vsatOk ? 'src-vsat' : '');
        setVal('acu_m_az', d.azimuth, cls);
        setVal('acu_m_raz', d.rel_azimuth, cls);
        setVal('acu_m_el', d.elevation, cls);
    }
    setInterval(function () {
        if (acuHasPointing(acuLastVsat) && acuHasPointing(acuLastFbb)) {
            acuMetricsSrc = (acuMetricsSrc === 'vsat') ? 'fbb' : 'vsat';
            renderAcuMetrics();
        }
    }, 5000);

    function updateAcuCompass(d) {
        var wrap = document.getElementById('acu_trk');
        if (!wrap) { return; }
        d = d || {};
        var status = d.status || 'nodata';
        wrap.setAttribute('data-status', status);
        var hasAz = (d.azimuth !== null && d.azimuth !== undefined && !isNaN(d.azimuth));
        if (wrap.classList) { wrap.classList.toggle('has-az', hasAz); }

        var labels = {
            searching: 'VSAT : Searching satellite…',
            blocked: 'VSAT : Blockage / TX off',
            nodata: 'VSAT : ACU data unavailable'
        };
        var lbl;
        if (status === 'tracking') {
            lbl = 'VSAT : ' + (d.satellite ? d.satellite : 'Tracking');
            if (d.signal !== null && d.signal !== undefined && !isNaN(d.signal)) {
                lbl += ' (Signal : ' + d.signal + ')';
            }
        } else {
            lbl = labels[status] || '--';
        }
        var lblNode = document.getElementById('acu_trk_label');
        if (lblNode) { lblNode.textContent = lbl; }

        acuLastVsat = d;
        renderAcuMetrics();

        if (d.heading !== null && d.heading !== undefined && !isNaN(d.heading)) {
            acuRotateTo('acu_ship_rot', d.heading);
            acuRotateTo('acu_dial_rot', d.heading);
        }
        if (hasAz) { acuRotateTo('acu_az_rot', d.azimuth); }
        var elv = d.elevation;
        if (elv !== null && elv !== undefined && !isNaN(elv)) {
            elv = Math.max(0, Math.min(90, elv));
            acuRotateTo('acu_el_rot', -elv);
        }
    }

    // FBB 보조 표시: VSAT 아래 상태줄(같은 디자인, 파랑) + 파란 보조 니들.
    // Az/El 수치는 표시하지 않음(상대적으로 덜 중요 — 니들 방향으로만 전달).
    function updateFbbCompass(d) {
        var wrap = document.getElementById('acu_trk');
        if (!wrap) { return; }
        d = d || {};
        var hasAz = (d.azimuth !== null && d.azimuth !== undefined && !isNaN(d.azimuth));
        if (wrap.classList) { wrap.classList.toggle('has-fbb', hasAz); }
        acuLastFbb = d;
        renderAcuMetrics();

        var line = document.getElementById('acu_fbb_status');
        var lbl = document.getElementById('acu_fbb_label');
        if (line && lbl) {
            var label = d.satellite ? d.satellite : (d.name ? d.name : '');
            var txt;
            if (d.status === 'tracking' && label) {
                txt = 'FBB : ' + label;
                if (d.signal !== null && d.signal !== undefined && !isNaN(d.signal)) {
                    txt += ' (Signal : ' + d.signal + ')';
                }
            } else if (d.status === 'searching') {
                txt = 'FBB : ' + (label ? label + ' (No Signal)' : 'Searching satellite…');
            } else {
                txt = 'FBB : info unavailable';
            }
            lbl.textContent = txt;
            if (line.classList) { line.classList.toggle('on', d.status === 'tracking'); }
        }

        if (!hasAz) { return; }
        acuRotateTo('acu_fbb_az_rot', d.azimuth);
        var elv = d.elevation;
        if (elv !== null && elv !== undefined && !isNaN(elv)) {
            acuRotateTo('acu_fbb_el_rot', -Math.max(0, Math.min(90, elv)));
        }
    }

    // 초기 1회 렌더 (이후엔 refreshValue 의 10초 AJAX 가 갱신)
    updateAcuCompass(<?php echo json_encode($acu_view); ?>);
    updateFbbCompass(<?php echo json_encode($fbb_view); ?>);
    // ===================================================================
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


