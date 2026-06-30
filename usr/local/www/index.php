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

// === NexusWave gateway 유무: 위성 커버리지 맵 노출 게이트 (terminal_type 기준) ===
//   terminal_type 이 nexuswave(_pri/_sec/_thi/_fth) 인 gateway 가 하나라도 있을 때만
//   Position 미니맵 클릭 → coverage map 모달을 노출한다. 없으면 일반 미니맵으로 유지.
$cp_has_nexuswave_gw = false;
foreach ($gateways as $gw) {
    if (isset($gw['terminal_type']) && stripos($gw['terminal_type'], 'nexuswave') !== false) {
        $cp_has_nexuswave_gw = true;
        break;
    }
}

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

// === 위성 커버리지 DB (10.8.128.1:3306/SynerSAT/coveragemap) ===
$cp_coverage_json = '{}';
(function() use (&$cp_coverage_json) {
    $mysql = '';
    foreach (array('/usr/local/bin/mysql', '/usr/bin/mysql', '/usr/local/bin/mariadb', '/usr/bin/mariadb') as $_p) {
        if (is_executable($_p)) { $mysql = $_p; break; }
    }
    if ($mysql === '') { return; }

    $cnf = @tempnam('/tmp', 'cpdbcov');
    if ($cnf === false) { return; }
    @file_put_contents($cnf, "[client]\nhost=10.8.128.1\nport=3306\nuser=sbox_reader\n"
        . "password=\"readonlyP@ss\"\n");
    @chmod($cnf, 0600);

    $run = function($sql) use ($mysql, $cnf) {
        $out = array(); $ret = 1;
        $cmd = escapeshellarg($mysql)
            . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' --connect-timeout=4 -N -B SynerSAT -e ' . escapeshellarg($sql)
            . ' 2>/dev/null';
        @exec($cmd, $out, $ret);
        return array($ret, $out);
    };

    $coverage = array();
    /* schema: (satellite, positionlist) — positionlist 열이 polygon JSON 배열 */
    list($r2, $rows) = $run('SELECT `satellite`, `positionlist` FROM coveragemap');
    if ($r2 === 0) {
        foreach ($rows as $line) {
            $parts = explode("\t", $line, 2);
            if (count($parts) === 2) {
                $decoded = json_decode(trim($parts[1]), true);
                if (is_array($decoded)) { $coverage[trim($parts[0])] = $decoded; }
            }
        }
    }

    @unlink($cnf);
    if (!empty($coverage)) {
        $cp_coverage_json = json_encode($coverage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
})();
// =============================================================

?>
<!DOCTYPE HTML>
<html lang="ko">

<head>
    <?php echo print_css_n_head(); ?>
</head>
<body>
<div id="wrapper">
    <?php echo print_sidebar( basename($_SERVER['PHP_SELF']));?>
    <!-- 안테나 3D 스카이돔 모달 (Satellite 나침반 클릭 시 표시) -->
    <div id="acu3d-ov" role="dialog" aria-modal="true" aria-label="Antenna 3D sky view">
        <div class="acu3d-modal">
            <div class="acu3d-head">
                <h3>Antenna sky view (3D)</h3>
                <button type="button" class="acu3d-close" id="acu3d-x" aria-label="Close">&times;</button>
            </div>
            <canvas id="acu3d-cv"></canvas>
            <div class="acu3d-legend">
                <span><i style="background:#19c37d"></i>VSAT</span>
                <span><i style="background:#4C6EF5"></i>FBB</span>
                <span><i style="background:#FA5252"></i>Blocked</span>
            </div>
            <p class="acu3d-hint">drag to rotate &middot; auto-orbits when idle</p>
        </div>
    </div>
    <!-- 위성 커버리지: 지도 타일 인터넷 소모 경고 -->
    <div id="covwarn-ov" role="dialog" aria-modal="true" aria-label="Internet data warning">
        <div class="covwarn">
            <h3>Online map tiles required</h3>
            <p>Coverage polygon data is loaded from the local database.<br>
               The <b>background map tiles</b> (OpenStreetMap) require an internet connection and
               <b>may consume satellite data</b>. Continue?</p>
            <div class="row">
                <button type="button" id="covwarn-cancel">Cancel</button>
                <button type="button" id="covwarn-ok" class="primary">Connect &amp; view</button>
            </div>
        </div>
    </div>
    <!-- NexusWave 외 안테나: 커버리지 미지원 안내 팝업 (월드맵은 표시하되 커버리지 오버레이만 게이트) -->
    <div id="covnote-ov" role="dialog" aria-modal="true" aria-label="Coverage map notice">
        <div class="covwarn">
            <h3>Coverage map</h3>
            <p>Currently, <b>only NEXUSWAVE</b> antennas support the satellite coverage map.<br>
               The world map is shown without coverage overlays.</p>
            <div class="row">
                <button type="button" id="covnote-ok" class="primary">OK</button>
            </div>
        </div>
    </div>
    <!-- 위성 커버리지 맵 모달 (DB polygon + 선박 위치) -->
    <div id="cov-ov" role="dialog" aria-modal="true" aria-label="Satellite coverage map">
        <div class="cov-modal">
            <div class="cov-head">
                <h3>Satellite coverage</h3>
                <button type="button" class="cov-close" id="cov-x" aria-label="Close">&times;</button>
            </div>
            <div class="cov-disc" id="cov-disc">&#9432; Coverage footprints provided by SynerSAT Korea &mdash; actual coverage may differ in real-world use.</div>
            <div class="cov-toggles" id="cov-toggles">
                <!-- JS가 CP_COVERAGE_DB 키 기반으로 동적 생성 -->
            </div>
            <div id="cov-map"></div>
            <p class="cov-pos" id="cov-pos">Vessel position: --</p>
        </div>
    </div>
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
                                    <!-- #28 항구 미니맵: 최근접 3개 항구 방위/거리 (GPS 갱신 시 자동 재계산, 오프라인 지도)
                                         GPS 미수신 시 no-gps 클래스 → 회색 디스크 + "NO GPS" (레이아웃 점프 없음) -->
                                    <div class="port-mm no-gps" id="port_mm">
                                        <!-- 상단 존 플레이트: 현재 해역/위치 영문 표시 (WoW 존 이름판) -->
                                        <div class="pm-plate"><span id="port_mm_region">--</span></div>
                                        <div class="pm-stage">
                                        <div class="port-mm-disc">
                                            <div class="port-mm-map" id="port_mm_map"></div>
                                            <p class="pm-nogps">NO GPS</p>
                                        </div>
                                        <svg class="port-mm-svg" viewBox="-124 -124 248 248" xmlns="http://www.w3.org/2000/svg">
                                            <defs>
                                                <radialGradient id="mmGlow" cx="50%" cy="50%" r="50%">
                                                    <stop offset="78%" stop-color="rgba(0,0,0,0)"/>
                                                    <stop offset="100%" stop-color="rgba(20,14,2,0.35)"/>
                                                </radialGradient>
                                            </defs>
                                            <circle r="110" fill="url(#mmGlow)"/>
                                            <circle r="110" fill="none" stroke="#6B5516" stroke-width="7"/>
                                            <circle r="110" fill="none" stroke="#D4AF37" stroke-width="4"/>
                                            <circle r="113.5" fill="none" stroke="#8A6D1F" stroke-width="1.6"/>
                                            <circle r="106.5" fill="none" stroke="#8A6D1F" stroke-width="1.2"/>
                                            <g id="mm_diamonds"></g>
                                            <text x="0" y="-116" text-anchor="middle" font-size="11" font-weight="700" fill="#8A6D1F">N</text>
                                            <g id="mm_arrow_0"><path d="M0,-103 L7,-90 L-7,-90 Z" fill="#FFD75E" stroke="#5C4708" stroke-width="1.4"/></g>
                                            <g id="mm_arrow_1"><path d="M0,-103 L6.2,-91 L-6.2,-91 Z" fill="#E3B341" stroke="#5C4708" stroke-width="1.3"/></g>
                                            <g id="mm_arrow_2"><path d="M0,-103 L5.4,-92 L-5.4,-92 Z" fill="#B98E2F" stroke="#5C4708" stroke-width="1.2"/></g>
                                            <!-- #28 지도 위 항구 점 (on-map 시 JS가 동적 생성) -->
                                            <g id="mm_port_dots"></g>
                                            <g id="mm_ship">
                                                <path d="M0,-9 L6,7 L0,3.4 L-6,7 Z" fill="#FFE08A" stroke="#3A2D05" stroke-width="1.5"/>
                                            </g>
                                        </svg>
                                        <!-- 우상단 시계 배지: 선박 설정 GMT 오프셋 기준 로컬타임 -->
                                        <div class="pm-clock"><strong id="pm_clock_time">--:--</strong><span id="pm_clock_gmt"></span></div>
                                        <!-- 우하단 줌 버튼 -->
                                        <div class="pm-zoom">
                                            <button type="button" class="pm-zoom-btn" id="pm_zoom_in" title="Zoom in">+</button>
                                            <button type="button" class="pm-zoom-btn" id="pm_zoom_out" title="Zoom out">&#8722;</button>
                                        </div>
                                        </div>
                                        <ul class="port-mm-list" id="port_mm_list"></ul>
                                    </div>
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
    .acu-trk-metrics li span {display:block; font-size:8px; font-weight:600; letter-spacing:0; color:#868E96; white-space:nowrap; overflow:hidden;}
    .acu-trk-metrics li strong {display:block; font-size:14px; font-weight:700; color:#212529; margin-top:2px; transition: color .4s;}
    .acu-trk-metrics li strong.src-vsat {color:#12B886;}
    .acu-trk-metrics li strong.src-fbb {color:#4C6EF5;}
    @keyframes acuDotPulse {
        0% {box-shadow:0 0 0 0 rgba(18,184,134,.45);}
        70% {box-shadow:0 0 0 7px rgba(18,184,134,0);}
        100% {box-shadow:0 0 0 0 rgba(18,184,134,0);}
    }

    /* === 안테나 3D 스카이돔 (Satellite 나침반 클릭 → 모달, Canvas 2D 자체투영) === */
    .acu-trk {cursor:pointer; position:relative;}
    .acu-trk::after {content:'\2922 3D'; position:absolute; top:-2px; right:-2px; font-size:10px; font-weight:700;
        color:#12B886; border:1px solid #12B886; border-radius:6px; padding:1px 5px; background:#fff; pointer-events:none;}
    #acu3d-ov {position:fixed; inset:0; background:rgba(8,12,20,.74); z-index:9999; display:none;
        align-items:center; justify-content:center;}
    #acu3d-ov.on {display:flex;}
    .acu3d-modal {width:min(540px,94vw); background:#0d1726; border:1px solid #21344f; border-radius:14px;
        padding:14px 16px 16px;}
    .acu3d-head {display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;}
    .acu3d-head h3 {margin:0; font-size:15px; font-weight:700; color:#e7eefc;}
    .acu3d-close {background:none; border:none; color:#9fb6d4; font-size:24px; line-height:1; cursor:pointer; padding:0 4px;}
    #acu3d-cv {width:100%; height:360px; display:block; cursor:grab; touch-action:none;}
    .acu3d-legend {display:flex; gap:16px; justify-content:center; margin:6px 0 0; font-size:12px; color:#9fb6d4;}
    .acu3d-legend i {display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:5px; vertical-align:-1px;}
    .acu3d-hint {text-align:center; font-size:11px; color:#6f8aab; margin:5px 0 0;}

    /* === 위성 커버리지 맵 (Position 미니맵 클릭 → 인터넷 경고 → 온라인 지도 + 근사 커버리지) === */
    #covwarn-ov, #cov-ov, #covnote-ov {position:fixed; inset:0; background:rgba(8,12,20,.74); z-index:10000;
        display:none; align-items:center; justify-content:center;}
    #covwarn-ov.on, #cov-ov.on, #covnote-ov.on {display:flex;}
    #covnote-ov {z-index:10001;}  /* coverage 모달 위에 표시 */
    .covwarn {width:min(420px,92vw); background:#fff; border-radius:14px; padding:20px 22px;}
    .covwarn h3 {margin:0 0 8px; font-size:16px; font-weight:700; color:#212529;}
    .covwarn p {margin:0 0 16px; font-size:13px; line-height:1.6; color:#495057;}
    .covwarn .row {display:flex; gap:10px; justify-content:flex-end;}
    .covwarn button {height:36px; padding:0 16px; border-radius:8px; font-size:13px; font-weight:700;
        cursor:pointer; border:1px solid #ced4da; background:#fff; color:#495057;}
    .covwarn button.primary {background:#1976d2; border-color:#1976d2; color:#fff;}
    .cov-modal {width:min(840px,96vw); background:#0d1726; border:1px solid #21344f; border-radius:14px; padding:12px 14px 14px;}
    .cov-head {display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;}
    .cov-head h3 {margin:0; font-size:15px; font-weight:700; color:#e7eefc;}
    .cov-close {background:none; border:none; color:#9fb6d4; font-size:24px; line-height:1; cursor:pointer; padding:0 4px;}
    .cov-disc {font-size:12px; font-weight:600; color:#9fb6d4; background:#0a192f; border:1px solid #21344f;
        border-radius:8px; padding:6px 10px; margin-bottom:8px; line-height:1.45;}
    .cov-disc.approx {color:#7a4f1f; background:#fff3e0; border-color:#ffc107;}
    .cov-toggles {display:flex; flex-wrap:wrap; gap:14px; margin-bottom:8px; font-size:12px; color:#cdd9ea;}
    .cov-toggles label {display:flex; align-items:center; gap:6px; cursor:pointer;}
    .cov-toggles .sw {width:11px; height:11px; border-radius:3px; display:inline-block;}
    #cov-map {width:100%; height:440px; border-radius:8px; background:#11203a;}
    .cov-msg {color:#cdd9ea; text-align:center; padding:46px 12px; font-size:14px; line-height:1.6;}
    .cov-pos {font-size:12px; color:#9fb6d4; margin:8px 0 0; text-align:center;}
    /* Position 미니맵 클릭 진입 힌트 */
    .pm-stage::after {content:'\2922 MAP'; position:absolute; left:7%; top:7%; z-index:3; font-size:9px;
        font-weight:700; color:#FFD75E; background:#343A40; border:1px solid #D4AF37; border-radius:6px;
        padding:1px 5px; pointer-events:none; letter-spacing:.5px;}

    /* === #28 항구 미니맵 (Position 타일) === */
    /* 반응형(B): 고정 220px → 컨테이너 비례(최대 248=링 포함 시각폭). 좁은 타일에서도 우측 넘침 없음.
       배경 패닝은 JS 가 디스크 대비 %로 설정하므로 px 크기와 무관하게 스케일된다. */
    .port-mm {width:100%; max-width:248px; margin:16px auto 0; position:relative; cursor:pointer;}
    /* 디스크는 스테이지(248 기준) 안에 5.645%(=14/248) 인셋 → 링(svg)이 가장자리에 정합 */
    .port-mm-disc {position:absolute; left:5.645%; top:5.645%; width:88.71%; height:88.71%;
        border-radius:50%; overflow:hidden; background:#454C54;
        box-shadow: inset 0 0 26px rgba(0,0,0,.55), inset 0 0 4px rgba(0,0,0,.6);}
    .port-mm-map {position:absolute; left:0; top:0; right:0; bottom:0; background-repeat:no-repeat;
        background-image:url('../img/world_minimap.jpg');
        filter:saturate(1.15) brightness(1.04); transition:opacity .6s;}
    .port-mm .pm-nogps {display:none; position:absolute; left:0; top:0; right:0; bottom:0;
        align-items:center; justify-content:center; font-size:13px; font-weight:700;
        letter-spacing:2px; color:#ADB5BD; margin:0;}
    .port-mm.no-gps .port-mm-map {opacity:0;}
    .port-mm.no-gps .pm-nogps {display:flex;}
    .port-mm.no-gps #mm_ship,
    .port-mm.no-gps #mm_arrow_0,
    .port-mm.no-gps #mm_arrow_1,
    .port-mm.no-gps #mm_arrow_2 {opacity:0;}
    .port-mm.no-gps #mm_port_dots {display:none;}
    .pm-stage {position:relative; width:100%; max-width:248px; aspect-ratio:1 / 1; margin:0 auto;}
    .port-mm-svg {position:absolute; left:0; top:0; width:100%; height:100%; pointer-events:none;}
    /* 상단 존 플레이트 (WoW 존 이름판) */
    .pm-plate {width:min(208px, 92%); margin:0 auto 10px; background:#343A40; border:1.5px solid #D4AF37;
        border-radius:11px; padding:3px 12px; text-align:center;}
    .pm-plate span {display:block; font-size:11px; font-weight:700; letter-spacing:1px; color:#FFD75E;
        line-height:1.5; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
    /* 우상단 시계 배지 (로컬타임 + GMT 오프셋) */
    .pm-clock {position:absolute; top:7%; right:7%; z-index:3; background:#343A40;
        border:1.5px solid #D4AF37; border-radius:8px; padding:2px 7px 3px; text-align:center; line-height:1.1;}
    .pm-clock strong {display:block; font-size:12px; font-weight:700; color:#FFD75E;}
    .pm-clock span {display:block; font-size:8px; font-weight:600; color:#E3B341; margin-top:1px;}
    /* 우하단 줌 버튼 */
    .pm-zoom {position:absolute; right:7%; bottom:7%; z-index:3;}
    .pm-zoom-btn {display:block; width:24px; height:24px; margin-top:6px; padding:0; border-radius:50%;
        background:#343A40; border:1.5px solid #D4AF37; color:#FFD75E; font-size:15px; font-weight:700;
        line-height:1; cursor:pointer;}
    .pm-zoom-btn:hover {background:#495057;}
    .port-mm.no-gps .pm-zoom {display:none;}
    .port-mm-list {margin-top:8px; min-height:62px;}
    .port-mm-list li {list-style:none; display:flex; align-items:center; gap:6px;
        font-size:12px; font-weight:600; color:#495057; line-height:1.7; justify-content:center;}
    .port-mm-list li .pm-tri {display:inline-block; transition:transform 1.2s cubic-bezier(.35,.1,.25,1);}
    .port-mm-list li .pm-dot {display:inline-block; line-height:1; font-size:10px;}
    .port-mm-list li .pm-dist {color:#212529; font-weight:700;}
    .port-mm-list li .pm-brg {color:#868E96; font-weight:500;}
    .port-mm-list li .pm-onmap {color:#868E96; font-weight:500; font-size:10px; font-style:italic;}

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
<!-- #28 데이터: 주요 항구(WPI L/M) + 해역 박스(Natural Earth) — tools/ 생성기로 갱신 -->
<script src="js/cp_ports.js"></script>
<script src="js/cp_searegions.js"></script>
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
        updatePortMinimap();
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
        updatePortMinimap();

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

    // ===================== #28 항구 미니맵 =====================
    // 전세계 주요 항구 리스트 [이름, 위도, 경도]
    var PORTS = [
        ['BUSAN',35.10,129.04],['ULSAN',35.50,129.38],['INCHEON',37.46,126.62],['GWANGYANG',34.90,127.70],
        ['SHANGHAI',31.23,121.49],['NINGBO',29.87,121.84],['QINGDAO',36.07,120.32],['TIANJIN',38.98,117.79],
        ['DALIAN',38.92,121.65],['HONG KONG',22.30,114.17],['SHENZHEN',22.55,114.05],['KAOHSIUNG',22.61,120.28],
        ['TOKYO',35.61,139.79],['YOKOHAMA',35.45,139.65],['NAGOYA',35.05,136.85],['KOBE',34.65,135.20],
        ['SINGAPORE',1.26,103.84],['PORT KLANG',3.00,101.40],['TG.PELEPAS',1.36,103.55],['JAKARTA',-6.10,106.88],
        ['SURABAYA',-7.20,112.73],['MANILA',14.58,120.97],['HO CHI MINH',10.77,106.70],['HAIPHONG',20.86,106.68],
        ['LAEM CHABANG',13.08,100.88],['COLOMBO',6.95,79.85],['CHENNAI',13.10,80.30],['NHAVA SHEVA',18.95,72.95],
        ['MUNDRA',22.74,69.70],['KARACHI',24.80,66.97],
        ['JEBEL ALI',25.01,55.06],['ABU DHABI',24.53,54.38],['DAMMAM',26.50,50.20],['JEDDAH',21.48,39.17],
        ['SALALAH',16.94,54.00],['BANDAR ABBAS',27.15,56.21],
        ['ROTTERDAM',51.95,4.14],['ANTWERP',51.28,4.34],['HAMBURG',53.54,9.97],['BREMERHAVEN',53.55,8.58],
        ['FELIXSTOWE',51.95,1.31],['LE HAVRE',49.48,0.12],['ALGECIRAS',36.13,-5.43],['VALENCIA',39.45,-0.32],
        ['BARCELONA',41.35,2.16],['MARSEILLE',43.33,5.33],['GENOA',44.40,8.92],['GIOIA TAURO',38.45,15.90],
        ['PIRAEUS',37.94,23.62],['AMBARLI',40.97,28.69],['GDANSK',54.40,18.66],['ST.PETERSBURG',59.88,30.20],
        ['LOS ANGELES',33.74,-118.26],['LONG BEACH',33.75,-118.20],['OAKLAND',37.80,-122.32],['SEATTLE',47.60,-122.34],
        ['VANCOUVER',49.29,-123.11],['MANZANILLO',19.05,-104.31],['BALBOA',8.95,-79.57],['COLON',9.36,-79.90],
        ['CARTAGENA',10.40,-75.51],['CALLAO',-12.05,-77.14],['VALPARAISO',-33.03,-71.62],['SANTOS',-23.98,-46.30],
        ['BUENOS AIRES',-34.58,-58.37],['NEW YORK',40.67,-74.05],['SAVANNAH',32.08,-81.09],['HOUSTON',29.73,-95.27],
        ['MIAMI',25.77,-80.17],['CHARLESTON',32.78,-79.92],['NORFOLK',36.92,-76.33],
        ['DURBAN',-29.87,31.03],['CAPE TOWN',-33.91,18.44],['LAGOS',6.44,3.36],['TANGER MED',35.88,-5.50],
        ['PORT SAID',31.25,32.31],['SUEZ',29.93,32.55],['MOMBASA',-4.06,39.65],
        ['SYDNEY',-33.96,151.20],['MELBOURNE',-37.83,144.93],['BRISBANE',-27.38,153.17],['AUCKLAND',-36.84,174.78],
        ['FREMANTLE',-32.05,115.74]
    ];
    // js/cp_ports.js(WPI Large/Medium 주요 항구 544개) 가 로드되면 그걸 사용.
    // 미배포(버전 섞임) 시 위 내장 82개로 폴백 — fatal 없음.
    if (typeof CP_PORTS !== 'undefined' && CP_PORTS.length) { PORTS = CP_PORTS; }
    var MM_D = 220;            // 미니맵 표시 지름(px)
    // 줌 레벨 = 세로 표시 범위(도). 값이 작을수록 확대. +/- 버튼으로 전환, 선택은 localStorage 보존.
    var MM_SPANS = [36, 26, 18, 12, 8];
    var mmZoomIdx = 2;
    try {
        var mmZs = parseInt(localStorage.getItem('cp_mm_zoom'), 10);
        if (mmZs >= 0 && mmZs < MM_SPANS.length) { mmZoomIdx = mmZs; }
    } catch (e) {}
    function mmSaveZoom() {
        try { localStorage.setItem('cp_mm_zoom', String(mmZoomIdx)); } catch (e) {}
    }
    var MM_ARROW_COLORS = ['#FFD75E', '#E3B341', '#B98E2F'];

    // 우상단 시계: 선박 설정 GMT 오프셋 기준 로컬타임 (GPS 와 무관하게 항상 동작)
    var MM_GMT_OFFSET = <?php echo json_encode((float)(isset($config['time_offset_enabled']['time_offset']) ? $config['time_offset_enabled']['time_offset'] : 0)); ?>;
    function mmUpdateClock() {
        var tEl = document.getElementById('pm_clock_time');
        if (!tEl) { return; }
        var d = new Date(Date.now() + MM_GMT_OFFSET * 3600000);
        tEl.textContent = ('0' + d.getUTCHours()).slice(-2) + ':' + ('0' + d.getUTCMinutes()).slice(-2);
        var gEl = document.getElementById('pm_clock_gmt');
        if (gEl) { gEl.textContent = 'GMT' + (MM_GMT_OFFSET >= 0 ? '+' : '') + MM_GMT_OFFSET; }
    }
    mmUpdateClock();
    setInterval(mmUpdateClock, 10000);

    function mmDistNm(lat1, lon1, lat2, lon2) {
        var R = 3440.065;
        var p1 = lat1 * Math.PI / 180, p2 = lat2 * Math.PI / 180;
        var dp = (lat2 - lat1) * Math.PI / 180, dl = (lon2 - lon1) * Math.PI / 180;
        var a = Math.sin(dp / 2) * Math.sin(dp / 2) +
                Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) * Math.sin(dl / 2);
        return 2 * R * Math.asin(Math.sqrt(a));
    }
    function mmBearingDeg(lat1, lon1, lat2, lon2) {
        var p1 = lat1 * Math.PI / 180, p2 = lat2 * Math.PI / 180;
        var dl = (lon2 - lon1) * Math.PI / 180;
        var y = Math.sin(dl) * Math.cos(p2);
        var x = Math.cos(p1) * Math.sin(p2) - Math.sin(p1) * Math.cos(p2) * Math.cos(dl);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }

    // 대략적 해역명 박스 (위도min, 위도max, 경도min, 경도max — 위에서부터 첫 매칭).
    // ① 운하/해협(접근 표시) ② 항구 30nm 이내 ③ 해역 박스 ④ 대양 폴백 순.
    var MM_STRAITS = [
        ['NEARBY PANAMA CANAL', 8.4, 9.8, -80.3, -79.0],
        ['NEARBY SUEZ CANAL', 29.5, 31.6, 32.0, 32.9],
        ['NEARBY SINGAPORE STRAIT', 0.9, 1.6, 103.3, 104.4],
        ['MALACCA STRAIT', 1.6, 6.5, 98.0, 103.3],
        ['NEARBY GIBRALTAR', 35.6, 36.3, -6.2, -4.9],
        ['NEARBY HORMUZ STRAIT', 25.5, 27.2, 55.5, 57.5],
        ['NEARBY BAB-EL-MANDEB', 12.0, 13.5, 42.5, 44.2],
        ['KOREA STRAIT', 33.8, 34.9, 128.3, 130.3],
        ['TAIWAN STRAIT', 22.5, 25.5, 117.5, 121.0],
        ['NEARBY DOVER STRAIT', 50.7, 51.3, 0.9, 1.9],
        ['NEARBY BOSPORUS', 40.8, 41.5, 28.7, 29.5]
    ];
    var MM_SEAS = [
        ['YELLOW SEA', 33, 41, 119, 127],
        ['EAST CHINA SEA', 25, 33, 120, 130],
        ['SEA OF JAPAN', 35, 48, 128, 142],
        ['SOUTH CHINA SEA', 0, 23, 105, 120],
        ['PHILIPPINE SEA', 5, 25, 120, 140],
        ['JAVA SEA', -8, -3, 105, 117],
        ['ANDAMAN SEA', 6, 14, 92, 98.5],
        ['GULF OF THAILAND', 6, 13.5, 99, 105],
        ['BAY OF BENGAL', 5, 22, 80, 95],
        ['ARABIAN SEA', 5, 25, 55, 75],
        ['PERSIAN GULF', 23.5, 30, 47, 56.5],
        ['GULF OF ADEN', 10, 15, 44, 52],
        ['RED SEA', 13, 28, 32, 44],
        ['BLACK SEA', 41, 47, 27, 42],
        ['MEDITERRANEAN SEA', 30, 46, -6, 36],
        ['ENGLISH CHANNEL', 48.5, 51, -5.5, 1.5],
        ['NORTH SEA', 51, 61, -4, 9],
        ['BALTIC SEA', 53, 66, 9, 30],
        ['CARIBBEAN SEA', 9, 22, -89, -60],
        ['GULF OF MEXICO', 18, 30, -98, -81],
        ['SEA OF OKHOTSK', 44, 60, 135, 157],
        ['BERING SEA', 52, 66, 162, 180],
        ['BERING SEA', 52, 66, -180, -157],
        ['CORAL SEA', -30, -10, 145, 165],
        ['TASMAN SEA', -45, -30, 150, 170]
    ];
    function mmZoneHit(zones, lat, lon) {
        for (var i = 0; i < zones.length; i++) {
            var z = zones[i];
            if (lat >= z[1] && lat <= z[2] && lon >= z[3] && lon <= z[4]) { return z[0]; }
        }
        return null;
    }
    function mmSeaRegion(lat, lon, nearest) {
        var hit = mmZoneHit(MM_STRAITS, lat, lon);
        if (hit) { return hit; }
        if (nearest && nearest.d < 30) { return 'NEARBY ' + nearest.name; }
        // js/cp_searegions.js(Natural Earth 292개 박스, 면적 오름차순=구체적 우선)
        // 로드 시 그걸 사용 — 미배포면 내장 MM_SEAS(25개)로 폴백.
        hit = mmZoneHit((typeof CP_SEAREGIONS !== 'undefined' && CP_SEAREGIONS.length) ? CP_SEAREGIONS : MM_SEAS, lat, lon);
        if (hit) { return hit; }
        if (lat <= -60) { return 'SOUTHERN OCEAN'; }
        if (lat >= 66.5) { return 'ARCTIC OCEAN'; }
        if (lon >= -70 && lon < 20) { return (lat >= 0 ? 'NORTH' : 'SOUTH') + ' ATLANTIC OCEAN'; }
        if (lon >= 20 && lon < 100) { return 'INDIAN OCEAN'; }
        if (lon >= 100) { return 'WESTERN PACIFIC OCEAN'; }
        return 'EASTERN PACIFIC OCEAN';
    }

    (function buildMmDiamonds() {
        var g = document.getElementById('mm_diamonds');
        if (!g) { return; }
        var ns = 'http://www.w3.org/2000/svg';
        for (var a = 45; a < 360; a += 90) {
            var d = document.createElementNS(ns, 'path');
            d.setAttribute('d', 'M0,-118 L5,-110 L0,-102 L-5,-110 Z');
            d.setAttribute('fill', '#D4AF37');
            d.setAttribute('stroke', '#6B5516');
            d.setAttribute('stroke-width', '1.2');
            d.setAttribute('transform', 'rotate(' + a + ')');
            g.appendChild(d);
        }
    })();

    // acuLastVsat/acuLastFbb 저장값에서 위치를 골라(VSAT GPS 우선, FBB 폴백) 미니맵 갱신.
    // updateAcuCompass/updateFbbCompass 끝에서 호출되므로 10초 AJAX 마다 재계산된다.
    function updatePortMinimap() {
        var box = document.getElementById('port_mm');
        if (!box) { return; }
        function hasPos(d) {
            return !!(d && d.lat !== null && d.lat !== undefined && d.lon !== null && d.lon !== undefined &&
                      !isNaN(d.lat) && !isNaN(d.lon) && !(d.lat === 0 && d.lon === 0));
        }
        var src = hasPos(acuLastVsat) ? acuLastVsat : (hasPos(acuLastFbb) ? acuLastFbb : null);
        // GPS 미수신: 숨기지 않고 회색 디스크 + "NO GPS" (타일 높이 유지 → 레이아웃 점프 없음)
        if (box.classList) { box.classList.toggle('no-gps', !src); }
        if (!src) {
            // 인라인 display 오버라이드 해제 → CSS .no-gps 규칙이 화살표를 숨길 수 있도록
            for (var ai = 0; ai < 3; ai++) {
                var ae = document.getElementById('mm_arrow_' + ai);
                if (ae) { ae.style.display = ''; }
            }
            var emptyDots = document.getElementById('mm_port_dots');
            if (emptyDots) { emptyDots.innerHTML = ''; }
            var emptyList = document.getElementById('port_mm_list');
            if (emptyList) { emptyList.innerHTML = ''; }
            var emptyRegion = document.getElementById('port_mm_region');
            if (emptyRegion) { emptyRegion.textContent = '--'; }
            return;
        }
        var lat = src.lat, lon = src.lon;

        // 지도 패닝 (등장방형: 위경도 -> 픽셀 선형 매핑, north-up)
        // 디스크 대비 "백분율" 패닝 → 디스크 px 크기와 무관(반응형). 기존 px 식과 수학적으로 동일:
        //   kx=지도폭/디스크폭=360/span, ky=지도높이/디스크높이=180/span (등장방형)
        //   size% = k*100,  pos% = (0.5 - r*k)/(1 - k)*100,  r_x=(lon+180)/360, r_y=(90-lat)/180
        var span = MM_SPANS[mmZoomIdx];
        var kx = 360 / span, ky = 180 / span;
        var rx = (lon + 180) / 360, ry = (90 - lat) / 180;
        var mapEl = document.getElementById('port_mm_map');
        if (mapEl) {
            mapEl.style.backgroundSize = (kx * 100) + '% ' + (ky * 100) + '%';
            mapEl.style.backgroundPosition = ((0.5 - rx * kx) / (1 - kx) * 100) + '% ' +
                                             ((0.5 - ry * ky) / (1 - ky) * 100) + '%';
        }

        // 중앙 본선 마커 (선수방위)
        var hdg = (acuLastVsat && acuLastVsat.heading !== null && acuLastVsat.heading !== undefined &&
                   !isNaN(acuLastVsat.heading)) ? acuLastVsat.heading : null;
        if (hdg !== null) { acuRotateTo('mm_ship', hdg); }

        // 최근접 항구 3개 계산 — plat/plon 포함(on-map 판정에 필요)
        var ranked = PORTS.map(function (p) {
            return {name: p[0], plat: p[1], plon: p[2],
                    d: mmDistNm(lat, lon, p[1], p[2]),
                    b: mmBearingDeg(lat, lon, p[1], p[2])};
        }).sort(function (a, b) { return a.d - b.d; }).slice(0, 3);

        // 줌 스케일: 배경 픽셀/도 = SVG 유닛/도 (등장방형 1:1)
        // SVG 디스크 반경 110에서 8px 마진 → 점이 링 안에 완전히 들어오도록
        var scale = MM_D / MM_SPANS[mmZoomIdx];
        var R_VIS = MM_D / 2 - 8;   // = 102

        // on-map 항구 점 초기화
        var ns = 'http://www.w3.org/2000/svg';
        var dotsG = document.getElementById('mm_port_dots');
        if (dotsG) { dotsG.innerHTML = ''; }

        var html = '';
        for (var i = 0; i < 3; i++) {
            var r = ranked[i];
            var arrowEl = document.getElementById('mm_arrow_' + i);

            // 항구의 SVG 좌표 (ship = 원점, 경도 wrap 처리)
            var dlon = r.plon - lon;
            if (dlon > 180) { dlon -= 360; }
            if (dlon < -180) { dlon += 360; }
            var svgX = dlon * scale;
            var svgY = -(r.plat - lat) * scale;
            var onMap = (svgX * svgX + svgY * svgY) <= R_VIS * R_VIS;

            if (onMap) {
                // 림 화살표 숨기고 지도 위 점 표시
                if (arrowEl) { arrowEl.style.display = 'none'; }
                if (dotsG) {
                    var gEl = document.createElementNS(ns, 'g');
                    // 외곽 글로우 (인지성 향상)
                    var glow = document.createElementNS(ns, 'circle');
                    glow.setAttribute('cx', svgX.toFixed(1));
                    glow.setAttribute('cy', svgY.toFixed(1));
                    glow.setAttribute('r', '7');
                    glow.setAttribute('fill', MM_ARROW_COLORS[i]);
                    glow.setAttribute('opacity', '0.22');
                    gEl.appendChild(glow);
                    // 항구 점
                    var dot = document.createElementNS(ns, 'circle');
                    dot.setAttribute('cx', svgX.toFixed(1));
                    dot.setAttribute('cy', svgY.toFixed(1));
                    dot.setAttribute('r', '3.5');
                    dot.setAttribute('fill', MM_ARROW_COLORS[i]);
                    dot.setAttribute('stroke', '#2A1F00');
                    dot.setAttribute('stroke-width', '1');
                    gEl.appendChild(dot);
                    // 이름 레이블 — 점 왼쪽/오른쪽 자동 선택
                    var lx = svgX + (svgX >= 0 ? -6 : 6);
                    var anchor = svgX >= 0 ? 'end' : 'start';
                    var lbl = document.createElementNS(ns, 'text');
                    lbl.setAttribute('x', lx.toFixed(1));
                    lbl.setAttribute('y', (svgY - 5).toFixed(1));
                    lbl.setAttribute('font-size', '8');
                    lbl.setAttribute('fill', MM_ARROW_COLORS[i]);
                    lbl.setAttribute('font-weight', '700');
                    lbl.setAttribute('text-anchor', anchor);
                    lbl.setAttribute('stroke', '#1A1000');
                    lbl.setAttribute('stroke-width', '2.5');
                    lbl.setAttribute('paint-order', 'stroke');
                    lbl.textContent = r.name;
                    gEl.appendChild(lbl);
                    dotsG.appendChild(gEl);
                }
                // 리스트: 점 아이콘으로 표시 (on-map 강조)
                html += '<li><span class="pm-dot" style="color:' + MM_ARROW_COLORS[i] + '">&#9679;</span>'
                     + '<span>' + r.name + '</span>'
                     + '<span class="pm-dist">' + (r.d < 100 ? r.d.toFixed(1) : Math.round(r.d)) + ' nm</span>'
                     + '<span class="pm-onmap">on map</span></li>';
            } else {
                // 림 화살표 표시 (기존 동작)
                if (arrowEl) { arrowEl.style.display = ''; }
                acuRotateTo('mm_arrow_' + i, r.b);
                html += '<li><span class="pm-tri" style="color:' + MM_ARROW_COLORS[i] + ';text-shadow:0 0 1px #5C4708;transform:rotate(' + r.b + 'deg)">&#9650;</span>'
                     + '<span>' + r.name + '</span>'
                     + '<span class="pm-dist">' + (r.d < 100 ? r.d.toFixed(1) : Math.round(r.d)) + ' nm</span>'
                     + '<span class="pm-brg">' + Math.round(r.b) + '&#176;</span></li>';
            }
        }
        var list = document.getElementById('port_mm_list');
        if (list) { list.innerHTML = html; }

        // 대략적 해역명 -> 상단 존 플레이트 (예: EASTERN PACIFIC OCEAN / NEARBY PANAMA CANAL)
        var regionEl = document.getElementById('port_mm_region');
        if (regionEl) { regionEl.textContent = mmSeaRegion(lat, lon, ranked[0]); }
    }

    // 줌 버튼: + 확대(표시범위 축소) / - 축소
    (function initMmZoomButtons() {
        var zi = document.getElementById('pm_zoom_in');
        var zo = document.getElementById('pm_zoom_out');
        if (zi) { zi.onclick = function () {
            if (mmZoomIdx < MM_SPANS.length - 1) { mmZoomIdx++; mmSaveZoom(); updatePortMinimap(); }
        }; }
        if (zo) { zo.onclick = function () {
            if (mmZoomIdx > 0) { mmZoomIdx--; mmSaveZoom(); updatePortMinimap(); }
        }; }
    })();
    // ===========================================================

    // 초기 1회 렌더 (이후엔 refreshValue 의 10초 AJAX 가 갱신)
    updateAcuCompass(<?php echo json_encode($acu_view); ?>);
    updateFbbCompass(<?php echo json_encode($fbb_view); ?>);
    var CP_COVERAGE_DB = <?php echo $cp_coverage_json; ?>;
    var CP_HAS_NEXUSWAVE = <?php echo $cp_has_nexuswave_gw ? 'true' : 'false'; ?>;

    // === 안테나 3D 스카이돔 (Satellite 나침반 클릭 → 모달; Canvas 2D 자체 투영, 외부 라이브러리 0) ===
    //   데이터는 acuLastVsat/acuLastFbb(az·el·heading·status) 그대로 사용 → 10초 AJAX 갱신 자동 반영.
    //   세계좌표 E=east,N=north,U=up. 카메라 yaw(드래그/자동회전)+pitch(부감). 정사영.
    (function () {
        var cv = document.getElementById('acu3d-cv');
        var ov = document.getElementById('acu3d-ov');
        if (!cv || !ov) { return; }
        var ctx = cv.getContext('2d');
        var W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
        var yaw = 0, pitch = -0.74, dragging = false, lastX = 0, raf = null, isOpen = false, orbitT = 0;
        function size() {
            var r = cv.getBoundingClientRect();
            W = r.width || 480; H = r.height || 360;
            cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        function C() { return { cx: W / 2, cy: H * 0.57, R: Math.min(W * 0.40, H * 0.46) }; }
        function P(az, el) {
            var a = az * Math.PI / 180, e = el * Math.PI / 180;
            var E = Math.cos(e) * Math.sin(a), N = Math.cos(e) * Math.cos(a), U = Math.sin(e);
            var cyA = Math.cos(yaw), syA = Math.sin(yaw);
            var E1 = E * cyA - N * syA, N1 = E * syA + N * cyA, U1 = U;
            var cp = Math.cos(pitch), sp = Math.sin(pitch);
            var N2 = N1 * cp + U1 * sp, U2 = -N1 * sp + U1 * cp;
            var c = C();
            return { x: c.cx + E1 * c.R, y: c.cy - U2 * c.R, d: N2 };
        }
        function ring(el, st, wd) {
            ctx.beginPath();
            for (var a = 0; a <= 360; a += 5) { var p = P(a, el); if (a === 0) { ctx.moveTo(p.x, p.y); } else { ctx.lineTo(p.x, p.y); } }
            ctx.strokeStyle = st; ctx.lineWidth = wd; ctx.stroke();
        }
        function meridian(az) {
            ctx.beginPath();
            for (var e = 0; e <= 90; e += 5) { var p = P(az, e); if (e === 0) { ctx.moveTo(p.x, p.y); } else { ctx.lineTo(p.x, p.y); } }
            ctx.strokeStyle = 'rgba(120,150,190,.28)'; ctx.lineWidth = 1; ctx.stroke();
        }
        var floorImg = new Image();
        floorImg.src = '../img/world_minimap.jpg';
        var FLOOR_SPAN_HALF = 16;   // 바닥 디스크 반경 = 16°(지름 32° 패치), 본선 위치 중심
        function vesselFloorPos() {
            function ok(d) { return d && d.lat != null && d.lon != null && !isNaN(d.lat) && !isNaN(d.lon) && !(d.lat === 0 && d.lon === 0); }
            if (ok(acuLastVsat)) { return [acuLastVsat.lat, acuLastVsat.lon]; }
            if (ok(acuLastFbb))  { return [acuLastFbb.lat, acuLastFbb.lon]; }
            return null;
        }
        function horizonPath() {
            ctx.beginPath();
            for (var a = 0; a <= 360; a += 5) { var p = P(a, 0); if (a === 0) { ctx.moveTo(p.x, p.y); } else { ctx.lineTo(p.x, p.y); } }
            ctx.closePath();
        }
        // 반구 바닥(el=0 평면)에 world_minimap.jpg 를 본선 위치 중심으로 텍스처 매핑.
        //   바닥은 평면이라 이미지px(ix,iy)→화면(x,y) 이 "아핀"(setTransform 1회) → 수평선 타원에 클립.
        //   Eg=(ix-vx)/ppu, Ng=(vy-iy)/ppu; screen_x=cx+(Eg*cosY-Ng*sinY)*R; screen_y=cy+(Eg*sinY+Ng*cosY)*sinP*R
        function drawFloor() {
            var pos = vesselFloorPos();
            if (floorImg.complete && floorImg.naturalWidth > 0 && pos) {
                var iw = floorImg.naturalWidth, ih = floorImg.naturalHeight;
                var ppu = FLOOR_SPAN_HALF * ih / 180;
                var vx = (pos[1] + 180) / 360 * iw, vy = (90 - pos[0]) / 180 * ih;
                var c = C(), R = c.R, sp = Math.sin(pitch);
                var k = R / ppu, ks = R * sp / ppu;
                var cyA = Math.cos(yaw), syA = Math.sin(yaw);
                // 바닥 지도도 dome(와이어/위성/본선)과 동일한 yaw 회전을 적용 → 같이 회전.
                //   이미지px(ix,iy)→EN평면 오프셋→yaw 회전(E1,N1)→화면 x=cx+E1*R, y=cy+N1*sp*R.
                //   본선(vx,vy)은 디스크 중심(cx,cy)에 고정되어 그 둘레로 회전. yaw=0 이면 기존과 동일.
                horizonPath();
                ctx.save();
                ctx.clip();
                ctx.setTransform(
                    dpr * k * cyA,
                    dpr * ks * syA,
                    dpr * k * syA,
                    dpr * (-ks * cyA),
                    dpr * (c.cx - k * (cyA * vx + syA * vy)),
                    dpr * (c.cy + ks * (cyA * vy - syA * vx)));
                try { ctx.drawImage(floorImg, 0, 0, iw, ih); } catch (x) {}
                ctx.restore();
                horizonPath();
                ctx.fillStyle = 'rgba(8,16,30,.30)'; ctx.fill();   // 가독성 위해 바닥 살짝 어둡게
            } else {
                horizonPath();
                ctx.fillStyle = 'rgba(40,86,140,.13)'; ctx.fill();
            }
        }
        function boat(hdg) {
            var c = C();
            var hv = P((hdg === null ? 0 : hdg), 0);
            var ang = Math.atan2(hv.y - c.cy, hv.x - c.cx);
            ctx.save();
            ctx.translate(c.cx, c.cy);
            ctx.rotate(ang + Math.PI / 2);
            ctx.beginPath();
            ctx.moveTo(0, -12); ctx.lineTo(7.5, 8.5); ctx.lineTo(0, 4.5); ctx.lineTo(-7.5, 8.5); ctx.closePath();
            ctx.fillStyle = '#FFE08A'; ctx.fill();
            ctx.lineWidth = 1.6; ctx.strokeStyle = '#3A2D05'; ctx.stroke();
            ctx.beginPath(); ctx.arc(0, -1, 2, 0, 7); ctx.fillStyle = '#3A2D05'; ctx.fill();
            ctx.restore();
        }
        function satColor(d) {
            if (d.status === 'blocked' || (d.elevation !== null && d.elevation !== undefined && d.elevation < 0)) { return '#FA5252'; }
            if (d.status === 'searching') { return '#FAB005'; }
            return d._fbb ? '#4C6EF5' : '#19c37d';
        }
        function drawSat(d) {
            if (!d || d.azimuth === null || d.azimuth === undefined || isNaN(d.azimuth)) { return; }
            var c = C();
            var el = d.elevation; if (el === null || el === undefined || isNaN(el)) { el = 0; }
            var s = P(d.azimuth, Math.max(0, Math.min(90, el)));
            var col = satColor(d);
            ctx.beginPath(); ctx.moveTo(c.cx, c.cy); ctx.lineTo(s.x, s.y);
            ctx.strokeStyle = col; ctx.lineWidth = 2.2; ctx.stroke();
            ctx.beginPath(); ctx.arc(s.x, s.y, 6, 0, 7); ctx.fillStyle = col; ctx.fill();
            ctx.lineWidth = 1.4; ctx.strokeStyle = '#06140c'; ctx.stroke();
            var nm = (d._fbb ? 'FBB' : 'VSAT') + (d.satellite ? '  ' + d.satellite : '');
            ctx.font = '600 11px system-ui,sans-serif'; ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
            ctx.lineWidth = 3; ctx.strokeStyle = '#04130c'; ctx.strokeText(nm, s.x + 10, s.y - 8);
            ctx.fillStyle = '#d7e6f7'; ctx.fillText(nm, s.x + 10, s.y - 8);
        }
        function draw() {
            ctx.clearRect(0, 0, W, H);
            drawFloor();
            [0, 45, 90, 135, 180, 225, 270, 315].forEach(meridian);
            ring(30, 'rgba(120,150,190,.26)', 1);
            ring(60, 'rgba(120,150,190,.26)', 1);
            ring(0, 'rgba(150,182,222,.58)', 1.6);
            var c = C();
            ctx.font = '600 12px system-ui,sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            [['N', 0], ['E', 90], ['S', 180], ['W', 270]].forEach(function (L) {
                var p = P(L[1], 0), dx = p.x - c.cx, dy = p.y - c.cy, m = Math.hypot(dx, dy) || 1;
                ctx.fillStyle = (p.d > 0 ? 'rgba(159,182,212,.42)' : '#bcd0ea');
                ctx.fillText(L[0], p.x + dx / m * 13, p.y + dy / m * 13);
            });
            var z = P(0, 90); ctx.beginPath(); ctx.arc(z.x, z.y, 2.2, 0, 7); ctx.fillStyle = '#cfe0f5'; ctx.fill();
            var fb = acuLastFbb; if (fb) { fb._fbb = true; }
            drawSat(acuLastVsat); drawSat(fb);
            var vs = acuLastVsat;
            var hdg = (vs && vs.heading !== null && vs.heading !== undefined && !isNaN(vs.heading)) ? vs.heading
                    : ((fb && fb.heading !== null && fb.heading !== undefined && !isNaN(fb.heading)) ? fb.heading : 0);
            boat(hdg);
        }
        function loop() { if (!isOpen) { return; } if (!dragging) { orbitT += 0.016; yaw = Math.sin(orbitT * 0.3); } draw(); raf = requestAnimationFrame(loop); }
        function openModal() { yaw = 0; orbitT = 0; ov.classList.add('on'); isOpen = true; size(); if (raf) { cancelAnimationFrame(raf); } raf = requestAnimationFrame(loop); }
        function closeModal() { isOpen = false; ov.classList.remove('on'); if (raf) { cancelAnimationFrame(raf); raf = null; } }
        var trk = document.getElementById('acu_trk');
        if (trk) { trk.addEventListener('click', openModal); }
        var xb = document.getElementById('acu3d-x');
        if (xb) { xb.addEventListener('click', closeModal); }
        ov.addEventListener('click', function (e) { if (e.target === ov) { closeModal(); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen) { closeModal(); } });
        cv.addEventListener('pointerdown', function (e) { dragging = true; lastX = e.clientX; cv.style.cursor = 'grabbing'; try { cv.setPointerCapture(e.pointerId); } catch (x) {} });
        cv.addEventListener('pointermove', function (e) { if (dragging) { yaw += (e.clientX - lastX) * 0.01; lastX = e.clientX; } });
        cv.addEventListener('pointerup', function () { dragging = false; cv.style.cursor = 'grab'; });
        window.addEventListener('resize', function () { if (isOpen) { size(); } });
    })();

    // === 위성 커버리지 맵 (Position 미니맵 클릭 → 인터넷 경고(타일용) → Leaflet 지도 + DB polygon) ===
    //   커버리지 데이터: CP_COVERAGE_DB (PHP→JSON, 로컬 DB 10.8.128.1:3306/SynerSAT/coveragemap).
    //   DB 조회 실패(빈 객체)면 근사 위도대로 폴백.
    (function () {
        var warnOv  = document.getElementById('covwarn-ov');
        var covOv   = document.getElementById('cov-ov');
        var noteOv  = document.getElementById('covnote-ov');
        var trigger = document.getElementById('port_mm');
        if (!warnOv || !covOv || !trigger) { return; }

        // NexusWave gateway(terminal_type) 가 있을 때만 커버리지 오버레이를 렌더.
        // 없으면 월드맵은 그대로 열되 커버리지/토글을 숨기고 안내 팝업(covnote-ov)을 표시.
        var covEnabled = (typeof CP_HAS_NEXUSWAVE !== 'undefined') ? !!CP_HAS_NEXUSWAVE : false;

        // 카테고리 → 색상 (DB 키 소문자 기준)
        var COV_COLORS = {
            'gx1': '#2fd39a', 'gx2': '#4ecdc4', 'gx3': '#45b7d1',
            'gx4': '#96ceb4', 'gx5': '#7be0c8',
            'GX1': '#2fd39a', 'GX2': '#4ecdc4', 'GX3': '#45b7d1',
            'GX4': '#96ceb4', 'GX5': '#7be0c8',
            'oneweb': '#9b87ff', 'OneWeb': '#9b87ff',
            'fbb': '#ffc34d', 'FBB': '#ffc34d'
        };
        // 폴백 위도대 (DB 없을 때)
        var COV_FALLBACK = [
            { key: 'oneweb', lat: 88, color: '#9b87ff', name: 'OneWeb' },
            { key: 'GX',     lat: 76, color: '#2fd39a', name: 'Global Xpress' },
            { key: 'fbb',    lat: 70, color: '#ffc34d', name: 'FleetBroadband' }
        ];

        var consented = false, map = null, layers = {}, vesselMk = null, leafletTried = false;
        var dbKeys = (typeof CP_COVERAGE_DB === 'object' && CP_COVERAGE_DB !== null)
            ? Object.keys(CP_COVERAGE_DB) : [];
        var hasDb = dbKeys.length > 0;

        function colorFor(key) {
            if (COV_COLORS[key]) { return COV_COLORS[key]; }
            // 접두사 매칭 (GX1..GX9 → green 계열, 나머지 기본)
            if (/^[Gg][Xx]\d/.test(key)) { return '#2fd39a'; }
            if (/oneweb/i.test(key)) { return '#9b87ff'; }
            if (/fbb/i.test(key)) { return '#ffc34d'; }
            // 순차 fallback 팔레트
            var pal = ['#a78bfa','#34d399','#60a5fa','#f472b6','#fb923c','#facc15','#4ade80'];
            var idx = dbKeys.indexOf(key) % pal.length; if (idx < 0) { idx = 0; }
            return pal[idx];
        }

        function vesselPos() {
            function ok(d) { return d && d.lat != null && d.lon != null && !isNaN(d.lat) && !isNaN(d.lon) && !(d.lat === 0 && d.lon === 0); }
            if (ok(acuLastVsat)) { return [acuLastVsat.lat, acuLastVsat.lon]; }
            if (ok(acuLastFbb))  { return [acuLastFbb.lat, acuLastFbb.lon]; }
            return null;
        }
        function setMsg(t) { var m = document.getElementById('cov-map'); if (m) { m.innerHTML = '<div class="cov-msg">' + t + '</div>'; } }

        // 동적 토글 생성
        function buildToggles() {
            var container = document.getElementById('cov-toggles'); if (!container) { return; }
            container.innerHTML = '';
            // 비-NexusWave: 커버리지 토글 없음 + disc 에 안내문 (월드맵만 표시)
            if (!covEnabled) {
                container.style.display = 'none';
                var dnc = document.getElementById('cov-disc');
                if (dnc) {
                    dnc.textContent = 'ℹ Currently, only NEXUSWAVE antennas support the satellite coverage map. The world map is shown without coverage overlays.';
                    dnc.classList.add('approx');
                }
                return;
            }
            container.style.display = '';
            var keys = hasDb ? dbKeys : COV_FALLBACK.map(function(f){ return f.key; });
            keys.forEach(function (key) {
                var color = colorFor(key);
                // gx* 는 중첩 방지용 기본 비체크; oneweb/fbb 는 기본 체크
                var defaultOn = !(/^[Gg][Xx]/.test(key));
                var label = document.createElement('label');
                var cb = document.createElement('input'); cb.type = 'checkbox'; cb.id = 'cov-cb-' + key; cb.checked = defaultOn;
                var sw = document.createElement('span'); sw.className = 'sw'; sw.style.background = color;
                var txt = document.createTextNode(key + (hasDb ? '' : ' (approx)'));
                label.appendChild(cb); label.appendChild(sw); label.appendChild(txt);
                container.appendChild(label);
            });
            // gx 그룹 안내 문구
            if (hasDb && keys.some(function(k){ return /^[Gg][Xx]/.test(k); })) {
                var hint = document.createElement('span');
                hint.style.cssText = 'font-size:11px;color:#6f8aab;align-self:center;';
                hint.textContent = '(GX: select one at a time)';
                container.appendChild(hint);
            }
            // 免责 텍스트 업데이트
            var disc = document.getElementById('cov-disc');
            if (disc) {
                if (hasDb) {
                    disc.textContent = 'ℹ Coverage footprints provided by SynerSAT Korea — actual coverage may differ in real-world use.';
                    disc.classList.remove('approx');
                } else {
                    disc.textContent = '⚠ APPROXIMATE / INDICATIVE ONLY — generic latitude bands, not actual operator footprints.';
                    disc.classList.add('approx');
                }
            }
        }

        function loadLeaflet(cb) {
            if (window.L) { cb(); return; }
            if (leafletTried) { return; }
            leafletTried = true;
            setMsg('Loading map…');
            var css = document.createElement('link'); css.rel = 'stylesheet';
            css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
            document.head.appendChild(css);
            var js = document.createElement('script');
            js.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
            js.onload = function () { cb(); };
            js.onerror = function () { leafletTried = false; setMsg('Map could not be loaded.<br>Check the internet connection, then close and try again.'); };
            document.body.appendChild(js);
        }

        // 위도대 근사 폴백 레이어
        function covBandInto(grp, latLimit, color, name) {
            L.rectangle([[-latLimit, -180], [latLimit, 180]],
                { color: color, weight: 2, dashArray: '7 5', fillColor: color, fillOpacity: 0.07, interactive: false }).addTo(grp);
            L.marker([latLimit, -148], { interactive: false, keyboard: false, icon: L.divIcon({
                className: 'cov-lbl',
                html: '<span style="color:' + color + ';font-size:11px;font-weight:700;white-space:nowrap;text-shadow:0 0 3px #000,0 0 3px #000;">'
                    + name + ' ≈\xb1' + latLimit + '\xb0 (approx band)</span>',
                iconSize: [200, 14], iconAnchor: [0, 7] }) }).addTo(grp);
        }

        // DB polygon 레이어 생성
        function covDbLayer(key, polygons, color) {
            var grp = L.layerGroup();
            polygons.forEach(function (poly) {
                var pts = poly.points;
                if (!pts || !pts.length) { return; }
                L.polygon(pts, {
                    color: color, weight: 1.5, opacity: 0.85,
                    fillColor: color, fillOpacity: 0.13, interactive: true
                }).bindTooltip(poly.label || key, { sticky: true }).addTo(grp);
            });
            return grp;
        }

        function bindToggle(key) {
            var c = document.getElementById('cov-cb-' + key); if (!c) { return; }
            c.onchange = function () {
                if (!map || !layers[key]) { return; }
                if (c.checked) { layers[key].addTo(map); } else { map.removeLayer(layers[key]); }
            };
        }

        function initMap() {
            var el = document.getElementById('cov-map'); if (!el) { return; }
            el.innerHTML = '';
            map = L.map(el, { worldCopyJump: true, minZoom: 1, maxZoom: 6 }).setView([20, 0], 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                { maxZoom: 6, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

            // 커버리지 오버레이는 NexusWave 안테나가 있을 때만 렌더 (없으면 월드맵만)
            if (covEnabled) {
                if (hasDb) {
                    dbKeys.forEach(function (key) {
                        var color = colorFor(key);
                        var defaultOn = !(/^[Gg][Xx]/.test(key));
                        layers[key] = covDbLayer(key, CP_COVERAGE_DB[key], color);
                        if (defaultOn) { layers[key].addTo(map); }
                        bindToggle(key);
                    });
                } else {
                    COV_FALLBACK.forEach(function (f) {
                        var grp = L.layerGroup();
                        covBandInto(grp, f.lat, f.color, f.name);
                        layers[f.key] = grp.addTo(map);
                        bindToggle(f.key);
                    });
                }
            }

            var pos = vesselPos(), posEl = document.getElementById('cov-pos');
            if (pos) {
                vesselMk = L.circleMarker(pos, { radius: 6, color: '#fff', weight: 2, fillColor: '#FA5252', fillOpacity: 1 }).addTo(map);
                vesselMk.bindTooltip('Vessel', { permanent: false });
                map.setView(pos, 3);
                if (posEl) { posEl.textContent = 'Vessel position: ' + pos[0].toFixed(3) + '\xb0, ' + pos[1].toFixed(3) + '\xb0'; }
            } else if (posEl) { posEl.textContent = 'Vessel position: GPS not available'; }
            setTimeout(function () { if (map) { map.invalidateSize(); } }, 80);
        }

        function openCov() {
            covOv.classList.add('on');
            // 비-NexusWave: 월드맵 위에 "NEXUSWAVE 만 커버리지 지원" 안내 팝업
            if (!covEnabled && noteOv) { noteOv.classList.add('on'); }
            loadLeaflet(function () {
                if (!map) { initMap(); }
                else {
                    setTimeout(function () {
                        if (map) {
                            map.invalidateSize();
                            var p = vesselPos();
                            if (p && vesselMk) { vesselMk.setLatLng(p); map.setView(p, 3); }
                        }
                    }, 80);
                }
            });
        }

        buildToggles();  // 페이지 로드 시 토글 즉시 구성 (Leaflet 불필요)

        trigger.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('.pm-zoom')) { return; }
            if (consented) { openCov(); } else { warnOv.classList.add('on'); }
        });
        var wc  = document.getElementById('covwarn-cancel'); if (wc)  { wc.addEventListener('click',  function () { warnOv.classList.remove('on'); }); }
        var wok = document.getElementById('covwarn-ok');     if (wok) { wok.addEventListener('click', function () { consented = true; warnOv.classList.remove('on'); openCov(); }); }
        var cx  = document.getElementById('cov-x');          if (cx)  { cx.addEventListener('click',  function () { covOv.classList.remove('on'); }); }
        var nok = document.getElementById('covnote-ok');     if (nok && noteOv) { nok.addEventListener('click', function () { noteOv.classList.remove('on'); }); }
        covOv.addEventListener('click',   function (e) { if (e.target === covOv)   { covOv.classList.remove('on'); } });
        warnOv.addEventListener('click',  function (e) { if (e.target === warnOv)  { warnOv.classList.remove('on'); } });
        if (noteOv) { noteOv.addEventListener('click', function (e) { if (e.target === noteOv) { noteOv.classList.remove('on'); } }); }
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { warnOv.classList.remove('on'); covOv.classList.remove('on'); if (noteOv) { noteOv.classList.remove('on'); } } });
    })();
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


