<?php
include_once ('auth.inc');
include_once ('config.lib.inc');
global $g;
global $adminlogin;

if(session_auth()){
    if(strpos(get_config_user(), "admin") !== false) {
        $adminlogin = "admin";
    }
    else if(strpos(get_config_user(), "customer") !== false) {
        $adminlogin = "customer";
    }
    else if(strpos(get_config_user(), "vesseladmin") !== false) {
        $adminlogin = "vesseladmin";
    }
    else {
        $adminlogin = "";
    }
}

if(session_auth()){
    require_once('guiconfig.inc');
    if (isset($_POST['closenotice'])) {
        close_notice($_POST['closenotice']);
        sleep(1);
        exit;
    }

    if (isset($_REQUEST['closenotice'])) {
        close_notice($_REQUEST['closenotice']);
        sleep(1);
    }

    if (($g['disablecrashreporter'] != true) && (system_has_crash_data() || system_has_php_errors())) {
        $savemsg = sprintf(gettext("%s has detected a crash report or programming bug."), $g['product_label']) . " ";
        if (isAllowedPage("/crash_reporter.php")) {
            $savemsg .= sprintf(gettext('Click %1$shere%2$s for more information.'), '<a href="crash_reporter.php">', '</a>');
        } else {
            $savemsg .= sprintf(gettext("Contact a firewall administrator for more information."));
        }
        $class = "warning";
    }
}
function print_css_n_head(){
    global $config;
    $vesselinfo = $config['system']['vesselinfo'];

    // 다크모드 GPS 모드용 일출/일몰(civil twilight) 캐시 → CP_SUN 으로 주입.
    //   cp_daynight_update.php 크론이 $config['daytimecheck'] 에 begin/end(UTC epoch)
    //   또는 polar('day'/'night') 를 채운다. 없으면 ok:false → GPS 모드는 시스템 폴백.
    $dtc = (isset($config['daytimecheck']) && is_array($config['daytimecheck']))
        ? $config['daytimecheck'] : array();
    $sun_ok     = false;
    $sun_polar  = isset($dtc['polar'])  ? (string)$dtc['polar'] : '';
    $sun_begin  = isset($dtc['begin'])  ? (int)$dtc['begin'] : 0;
    $sun_end    = isset($dtc['end'])    ? (int)$dtc['end']   : 0;
    $sun_nbegin = isset($dtc['nbegin']) ? (int)$dtc['nbegin'] : 0;
    if ($sun_polar === 'day' || $sun_polar === 'night' || ($sun_begin > 0 && $sun_end > 0)) {
        $sun_ok = true;
    }
    // 'now' = 박스의 현재 UTC epoch(권위 시각). day/night 판정은 클라이언트 브라우저
    //   시계(Date.now, 선박 PC 는 타임존 오설정 흔함)가 아니라 이 값을 기준으로 한다.
    //   (시스템은 항상 UTC, NTP 동기. 절대 epoch 비교라 offset 가산 불필요.)
    $cp_sun_js = json_encode(array(
        'ok'     => $sun_ok,
        'polar'  => $sun_polar,
        'begin'  => $sun_begin,
        'end'    => $sun_end,
        'nbegin' => $sun_nbegin,
        'now'    => time(),
    ));

    // 다크모드 조기 적용(FOUC 방지): CSS 링크보다 먼저 실행되어 <html>에 클래스 부여.
    //   모드(localStorage 'cp_theme'): auto(시스템)·gps(일출일몰)·light·dark. 기본 auto.
    //   GPS 데이터 없으면 시스템(prefers-color-scheme)으로 폴백.
    //   GPS 판정: 박스 UTC 시각(s.now) 이 dusk(end) 이전이면 dawn(begin) 이후가 낮,
    //   dusk 이후면 다음 dawn(nbegin) 전까지 밤. (조기 스크립트는 렌더 직후라 s.now 그대로 사용)
    $rtnstr  = '<script>window.CP_SUN=' . $cp_sun_js . ';</script>';
    $rtnstr .= '<script>(function(){try{'
        . 'var m=localStorage.getItem("cp_theme")||"auto";var d;var s=window.CP_SUN;'
        . 'if(m==="dark"){d=true;}'
        . 'else if(m==="light"){d=false;}'
        . 'else if(m==="gps"&&s&&s.ok){'
        .   'if(s.polar==="day"){d=false;}else if(s.polar==="night"){d=true;}'
        .   'else{var n=s.now||(Date.now()/1000);d=(n<s.end)?!(n>=s.begin):!(s.nbegin&&n>=s.nbegin);}'
        . '}else{d=!!(window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches);}'
        . 'if(d){document.documentElement.className+=" dark";}'
        . '}catch(e){}})();</script>';
    $rtnstr .= '<title>';
    $rtnstr .= $vesselinfo['vesselname'];
    $rtnstr .= '</title>';
    $rtnstr .= '<meta http-equiv="Content-Type" content="text/html" charset="utf-8">';
    $rtnstr .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
    $rtnstr .= '<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0, user-scalable=yes">';
    $rtnstr .= '<meta name="mobile-web-app-capable" content="yes" />';
    $rtnstr .= '<meta name="apple-mobile-web-app-status-bar-style" content="black" />';
    $rtnstr .= '<meta name="format-detection" content="telephone=no" />';
    $rtnstr .= '<!-- reset css -->';
    $rtnstr .= '<link href="css/reset.css" rel="stylesheet" type="text/css">';
    $rtnstr .= '<link href="css/fonts.css" rel="stylesheet" type="text/css">';
    $rtnstr .= '<link href="css/utility.css" rel="stylesheet" type="text/css">';
    $rtnstr .= '<!-- dist/css -->';
    $rtnstr .= '<link href="dist/css/jquery.mCustomScrollbar.css" rel="stylesheet" type="text/css">';
    $rtnstr .= '<!-- custom css -->';
    $rtnstr .= '<link href="css/common.css" rel="stylesheet" type="text/css">';
    $rtnstr .= '<link href="css/components.css" rel="stylesheet" type="text/css">';
    $rtnstr .= '<link href="css/style.css" rel="stylesheet" type="text/css">';
    $rtnstr .= '<!-- dark mode override (html.dark 스코프, 항상 로드) -->';
    $rtnstr .= '<link href="css/dark.css" rel="stylesheet" type="text/css">';
    $rtnstr .= '<!-- jquery -->';
    $rtnstr .= '<script src="js/jquery.min.js"></script>';
    $rtnstr .= '<!-- dist/js -->';
    $rtnstr .= '<script src="dist/js/jquery.mCustomScrollbar.js"></script>';
    $rtnstr .= '<!-- custom js -->';

    $rtnstr .= '<script src="js/common.js"></script>';
    $rtnstr .= '<script src="js/script.js"></script>';
    $rtnstr .='<link rel="icon" href="img/icon/favicon.png">';
    return $rtnstr;
}


// 공통: csrf_magic()를 문자열로 얻기 위한 헬퍼
function csrf_magic_str(): string
{
    if (!function_exists('csrf_magic')) {
        // guiconfig.inc가 제대로 include되면 보통 존재함
        return '';
    }
    ob_start();
    csrf_magic(); // echoes hidden input
    return ob_get_clean();
}

function print_sidebar($inputlink)
{
    global $config;

    $vesselinfo = $config['system']['vesselinfo'];
    $gmt = $config['time_offset_enabled']["time_offset"] ?? '0';
    $gmtmanualchecked = !empty($config['time_offset_enabled']['gmtcheck']) ? "checked" : "";
    $username = htmlspecialchars($_SESSION['Username'] ?? '', ENT_QUOTES);

    // CSRF hidden input (POST용)
    $csrf_html = csrf_magic_str();

    $rtnstr = '';
    $rtnstr .= '<div id="sidebar">';
    $rtnstr .= '  <div class="brand">';
    $rtnstr .= '    <button class="btn-ic btn-menu-open"></button>';

    if (($config['system']['vesselinfo']['logo'] ?? '') === "sktelink") {
        $rtnstr .= '    <h1 class="sklogo"><a href="/index.php"></a></h1>';
    } elseif (($config['system']['vesselinfo']['logo'] ?? '') === "inmarsat") {
        $rtnstr .= '    <h1 class="inmarsatlogo"><a href="/index.php"></a></h1>';
    } else {
        $rtnstr .= '    <h1 class="logo"><a href="/index.php"></a></h1>';
    }

    $rtnstr .= '    <p class="location" id="firewall-login">' . htmlspecialchars($vesselinfo["vesselname"] ?? '', ENT_QUOTES) . '</p>';

    // GMT 표시 (+ #48 변경 이력 버튼 — 클릭 버블은 JS stopPropagation 으로 차단해
    //  타임존 설정 팝업(pop-gmt)과 분리. 버튼 텍스트가 innerText 에 섞여도
    //  common.js currentOffset() 은 parseFloat 라 "9.5 history" → 9.5 로 안전)
    $rtnstr .= '    <p class="gmt" id="gmt-modify" style="cursor:pointer;">GMT ' . htmlspecialchars($gmt, ENT_QUOTES)
        . ' <button type="button" id="gmthist-btn" title="GMT change history"'
        . ' style="margin-left:6px; height:18px; padding:0 8px; border-radius:9px; font-size:10px; font-weight:700;'
        . ' background:transparent; border:1px solid #3d5878; color:#9fb6d4; cursor:pointer; vertical-align:middle;">history</button></p>';

    // GMT 체크박스(Manual Timezone Enable)
    $rtnstr .= '    <div class="gmt-option">';
    $rtnstr .= '      <label for="gmtcheck">';
    $rtnstr .= '        <input type="checkbox" name="gmtcheck" id="gmtcheck" ' . $gmtmanualchecked . '
                        style="display:inline-block !important; visibility:visible !important; opacity:1 !important;
                               -webkit-appearance:checkbox !important; appearance:checkbox !important;">';
    $rtnstr .= '        <span>Manual Timezone Enable</span>';
    $rtnstr .= '      </label>';
    $rtnstr .= '    </div>';

    $rtnstr .= '  </div>'; // brand

    $rtnstr .= '  <div id="lnb">';
    $rtnstr .= '    <button class="btn-ic btn-menu-close"></button>';
    $rtnstr .= '    <div class="depth01"><ul>';

    // 메뉴 helper
    $mk = function ($file, $label, $icon) use ($inputlink) {
        $on = ($inputlink === $file) ? ' class="on"' : '';
        return '<li' . $on . '><a href="/' . $file . '"><i class="' . $icon . '"></i><p>' . $label . '</p></a></li>';
    };

    $rtnstr .= $mk("index.php", "Main Panel", "ic-lnb01");
    $rtnstr .= $mk("network_control.php", "Network Control", "ic-lnb02");
    $rtnstr .= $mk("terminal.php", "Antenna", "ic-lnb03");
    $rtnstr .= $mk("lan_svrstatus.php", "LAN", "ic-lnb04");
    $rtnstr .= $mk("crew_account.php", "Crew Account", "ic-lnb05");
    // prepaid_enabled self-heal: 과거 코드가 captiveportal zone 배열에 플래그를 저장해
    // CP zone 목록에 '내부가 빈 가짜 zone' 이 생기던 버그를 정리한다.
    // 구 위치(captiveportal)가 보이면 system 으로 이전하고 구 키를 제거한 뒤 1회 영구화한다.
    // (이후 요청부터는 구 키가 없어 no-op)
    // phantom CP zone self-heal: 아래 3가지 비-zone 키가 $config['captiveportal'] 에
    // 남아있으면 pfSense CP zone 목록에 빈 zone 으로 표시된다.
    // 수정은 cp_routing_setup.php(배포 시)에서 먼저 처리하므로 여기는 fallback 역할.
    // write_config() 는 비용이 크므로 변경이 있을 때만 1회만 호출한다.
    $_cp_sidebar_dirty = false;
    if (isset($config['captiveportal']['prepaid_enabled'])) {
        $config['system']['prepaid_enabled'] = "";
        unset($config['captiveportal']['prepaid_enabled']);
        $_cp_sidebar_dirty = true;
    }
    if (isset($config['captiveportal']['filter'])) {
        unset($config['captiveportal']['filter']);
        $_cp_sidebar_dirty = true;
    }
    if (isset($config['captiveportal']['shutdown_gateways'])) {
        if (!isset($config['system']['cp_shutdown_gateways']) ||
            $config['captiveportal']['shutdown_gateways'] !== '') {
            $config['system']['cp_shutdown_gateways'] =
                $config['captiveportal']['shutdown_gateways'];
        }
        unset($config['captiveportal']['shutdown_gateways']);
        $_cp_sidebar_dirty = true;
    }
    if ($_cp_sidebar_dirty && function_exists('write_config')) {
        write_config("phantom CP zone 키 정리(captiveportal→system 이전)");
    }
    if (isset($config['system']['prepaid_enabled'])) {
        $rtnstr .= $mk("prepaid_account.php", "Prepaid Account", "ic-lnb05");
    }
    $rtnstr .= $mk("download_center.php", "Download Center", "ic-lnb06");
    $rtnstr .= $mk("release_note.php", "Release Note", "ic-lnb06");


    // 다크모드 토글 (전 페이지 공통 — 사이드바 메뉴 하단). 아이콘 자산 불필요(유니코드).
    $rtnstr .= '<li class="theme-toggle-li" style="margin-top:16px;">'
        . '<button type="button" id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode">'
        . '<span class="tt-ic" aria-hidden="true">&#9790;</span>'
        . '<span class="tt-label">Dark mode</span>'
        . '</button>'
        . '</li>';

    $rtnstr .= '<li style="margin-top:12px;">'
        . '<a href="/index.php?logout=1" title="Logout (' . $username . ')"">'
        . '  <i class="ic-lnb-logout"></i>'
        . '  <p>Logout</p>'
        . '</a>'
        . '</li>';
    $rtnstr .= '    </ul></div>';

    // 사이드바 하단 푸터 (전 페이지 공통 — print_sidebar 가 모든 커스텀 페이지의 사이드바를 그림).
    // 다크 사이드바 대비 인라인 스타일(외부 CSS 의존 없음). 링크는 새 탭.
    $rtnstr .= '    <div class="lnb-footer" style="margin-top:18px; padding:14px 16px 20px; '
        . 'font-size:11px; line-height:1.65; color:rgba(255,255,255,.45); '
        . 'border-top:1px solid rgba(255,255,255,.08);">'
        . 'This Web console is developed and maintained by '
        . '<a href="https://www.synersat.com" target="_blank" rel="noopener" '
        . 'style="color:rgba(255,255,255,.72); text-decoration:underline;">SynerSAT Korea</a>. '
        . '&copy; since 2016 Powered by '
        . '<a href="https://www.pfsense.org" target="_blank" rel="noopener" '
        . 'style="color:rgba(255,255,255,.72); text-decoration:underline;">PFSense</a>.'
        . '</div>';

    $rtnstr .= '  </div>'; // lnb
    $rtnstr .= '</div>';   // sidebar

    // ---- 팝업: 로그인 ----
    $rtnstr .= '<div class="popup layer pop-login">
        <div class="pop-head"><p class="title">Login</p></div>
        <div class="pop-cont">
            <form name="registerusers" id="firewall-loginform" method="post" action="/index.php">
                ' . $csrf_html . '
                <div class="form">
                    <div class="form-tit"><p class="tit">ID</p></div>
                    <div class="form-cont"><input type="text" name="usernamefld" id="usernamefld" autocomplete="username"></div>
                </div>
                <div class="form mt20">
                    <div class="form-tit"><p class="tit">Password</p></div>
                    <div class="form-cont"><input type="password" name="passwordfld" id="passwordfld" autocomplete="current-password"></div>
                </div>

                <div class="pop-foot">
                    <button type="submit" class="btn md fill-mint"><i class="ic-submit"></i>LOGIN</button>
                    <button type="button" class="btn md fill-dark" onclick="popClose(\'pop-login\')"><i class="ic-cancel"></i>CANCEL</button>
                </div>
            </form>

            <form id="gmtForm" action="/index.php" method="post" style="display:none;">
                ' . $csrf_html . '
                <input type="hidden" name="gmt" id="gmtVal">
            </form>

            <form id="gmtcheckForm" action="/index.php" method="post" style="display:none;">
                ' . $csrf_html . '
                <input type="hidden" name="gmtcheck" id="gmtcheckVal">
            </form>
        </div>
    </div>';

    // ---- 메시지 팝업 ----
    $rtnstr .= '<div class="popup layer pop-message">
        <div class="pop-head"><p class="title">Message</p></div>
        <div class="pop-cont"><div class="form"><div class="form-cont" id="message_text"></div></div></div>
        <div class="pop-foot">
            <button type="button" class="btn md fill-mint" onclick="popClose(\'pop-message\')"><i class="ic-submit"></i>Close</button>
        </div>
    </div>';

    // ---- 타임존(GMT 오프셋) 팝업 ----
    //  사이드바 "GMT n" 클릭 시 네이티브 prompt 대신 테마 팝업을 연다(다크 자동 대응).
    //  -11 ~ +12, 30분(0.5) 단위 select(JS 가 채움). 제출은 위 gmtForm(POST gmt).
    $rtnstr .= '<div class="popup layer pop-gmt" style="width:400px;">
        <div class="pop-head"><p class="title">Set Timezone</p></div>
        <div class="pop-cont">
            <div class="form">
                <div class="form-tit"><p class="tit">Time difference from GMT (-11 ~ +12, 30-min steps)</p></div>
                <div class="form-cont mt10"><select class="select v1" id="gmtSelect"></select></div>
            </div>
            <div class="pop-foot">
                <button type="button" class="btn md fill-mint" id="gmtApply"><i class="ic-submit"></i>Apply</button>
                <button type="button" class="btn md fill-dark" onclick="popClose(\'pop-gmt\')"><i class="ic-cancel"></i>Cancel</button>
            </div>
        </div>
    </div>';

    // ---- GMT 변경 이력 모달 (#48) ----
    //  사이드바 GMT 옆 history 버튼 → radius.gmt_history 조회(gmt_history_data.php AJAX).
    //  스타일은 Main Panel "Daily internet usage" 모달과 동일 계열(다크 카드 + pill 버튼)
    //  — 자체 색 고정이라 라이트/다크 테마 모두 무관. ID/클래스 gmthist-* 로 격리.
    $rtnstr .= '<style>
    #gmthist-ov{position:fixed;inset:0;background:rgba(8,12,20,.74);z-index:10001;display:none;align-items:center;justify-content:center;}
    #gmthist-ov.on{display:flex;}
    .gmthist-modal{width:min(560px,96vw);max-height:88vh;overflow:auto;background:#0d1726;border:1px solid #24405f;border-radius:14px;padding:18px 20px;box-shadow:0 18px 60px rgba(0,0,0,.55);}
    .gmthist-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
    .gmthist-head h3{margin:0;font-size:16px;font-weight:800;color:#f2f7ff;}
    .gmthist-x{background:none;border:0;color:#8ca6c6;font-size:22px;line-height:1;cursor:pointer;padding:0 2px;}
    .gmthist-x:hover{color:#fff;}
    .gmthist-range{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;}
    .gmthist-range .gh-btn{height:28px;padding:0 12px;border-radius:7px;font-size:12px;font-weight:700;background:#152238;border:1px solid #2c4363;color:#b9cbe4;cursor:pointer;}
    .gmthist-range .gh-btn.on{background:#1976d2;border-color:#1976d2;color:#fff;}
    .gmthist-custom{display:none;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap;}
    .gmthist-custom input[type=date]{height:28px;padding:0 8px;border-radius:7px;background:#0a192f;border:1px solid #2c4363;color:#e7eefc;font-size:12px;color-scheme:dark;}
    .gmthist-custom .gh-sep{color:#7e95b4;font-size:12px;}
    .gmthist-apply{height:28px;padding:0 14px;border-radius:7px;font-size:12px;font-weight:700;background:#1976d2;border:1px solid #1976d2;color:#fff;cursor:pointer;}
    .gmthist-tbl{width:100%;border-collapse:collapse;font-size:12px;}
    .gmthist-tbl th{color:#9fb6d4;text-align:left;font-weight:700;padding:6px 8px;border-bottom:1px solid #2c4363;}
    .gmthist-tbl td{color:#e7eefc;padding:6px 8px;border-bottom:1px solid #1a2c49;}
    .gmthist-tbl .gh-arrow{color:#3aa0ff;font-weight:700;}
    .gmthist-msg{color:#cdd9ea;text-align:center;padding:32px 12px;font-size:13px;line-height:1.6;margin:0;}
    .gmthist-note{margin:10px 0 0;font-size:11px;color:#7e95b4;}
    </style>';
    $rtnstr .= '<div id="gmthist-ov" role="dialog" aria-modal="true" aria-label="GMT change history">
        <div class="gmthist-modal">
            <div class="gmthist-head">
                <h3>GMT change history</h3>
                <button type="button" class="gmthist-x" id="gmthist-x" aria-label="Close">&times;</button>
            </div>
            <div class="gmthist-range" id="gmthist-range">
                <button type="button" class="gh-btn on" data-days="1">1d</button>
                <button type="button" class="gh-btn" data-days="7">7d</button>
                <button type="button" class="gh-btn" data-days="30">30d</button>
                <button type="button" class="gh-btn" data-custom="1">Custom</button>
            </div>
            <div class="gmthist-custom" id="gmthist-custom">
                <input type="date" id="gmthist-from"> <span class="gh-sep">~</span> <input type="date" id="gmthist-to">
                <button type="button" class="gmthist-apply" id="gmthist-apply">Apply</button>
            </div>
            <div id="gmthist-body"></div>
            <p class="gmthist-note">&#9432; Timezone offset change log (times in UTC) &mdash; manual, GPS-auto and remote changes.</p>
        </div>
    </div>';
    $rtnstr .= '<script>
    (function(){
        var btn = document.getElementById("gmthist-btn");
        var ov  = document.getElementById("gmthist-ov");
        if (!btn || !ov) { return; }
        var body   = document.getElementById("gmthist-body");
        var custom = document.getElementById("gmthist-custom");
        var fromI  = document.getElementById("gmthist-from");
        var toI    = document.getElementById("gmthist-to");
        var pills  = document.getElementById("gmthist-range").getElementsByTagName("button");

        function esc(s){
            return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
        }
        function fmtOff(v){
            v = String(v == null ? "" : v).trim();
            return (v === "") ? "(unset)" : ("GMT " + v);
        }
        function csrfToken(){
            var el = document.querySelector("#gmtForm input[name=__csrf_magic]");
            return el ? el.value : "";
        }
        function render(rows){
            if (!rows || !rows.length) {
                body.innerHTML = "<p class=\"gmthist-msg\">No timezone changes in this period.</p>";
                return;
            }
            var h = "<table class=\"gmthist-tbl\"><thead><tr><th>Time (UTC)</th><th>Change</th></tr></thead><tbody>";
            for (var i = 0; i < rows.length; i++) {
                var r = rows[i];
                h += "<tr><td>" + esc(r.timestamp) + "</td><td>" + esc(fmtOff(r.timefrom))
                   + " <span class=\"gh-arrow\">&rarr;</span> " + esc(fmtOff(r.timeto)) + "</td></tr>";
            }
            body.innerHTML = h + "</tbody></table>";
        }
        function load(params){
            body.innerHTML = "<p class=\"gmthist-msg\">Loading&hellip;</p>";
            var tok = csrfToken();
            if (tok) { params.__csrf_magic = tok; }
            var q = [];
            for (var k in params) { q.push(encodeURIComponent(k) + "=" + encodeURIComponent(params[k])); }
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "/gmt_history_data.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function(){
                if (xhr.readyState !== 4) { return; }
                var d = null;
                try { d = JSON.parse(xhr.responseText); } catch(e) {}
                if (!d || !d.ok) {
                    body.innerHTML = "<p class=\"gmthist-msg\">History unavailable (database unreachable).</p>";
                    return;
                }
                render(d.rows);
            };
            xhr.send(q.join("&"));
        }
        function setOn(b){
            for (var i = 0; i < pills.length; i++) { pills[i].classList.remove("on"); }
            b.classList.add("on");
        }
        for (var i = 0; i < pills.length; i++) {
            (function(b){
                b.addEventListener("click", function(){
                    setOn(b);
                    if (b.getAttribute("data-custom")) { custom.style.display = "flex"; return; }
                    custom.style.display = "none";
                    load({ mode: "days", days: b.getAttribute("data-days") });
                });
            })(pills[i]);
        }
        var applyB = document.getElementById("gmthist-apply");
        if (applyB) {
            applyB.addEventListener("click", function(){
                if (!fromI.value || !toI.value) { return; }
                load({ mode: "custom", from: fromI.value, to: toI.value });
            });
        }
        function openOv(){
            var today = new Date().toISOString().slice(0, 10);   // UTC 날짜(저장 timestamp 와 동일 기준)
            if (fromI && !fromI.value) { fromI.value = today; }
            if (toI && !toI.value)     { toI.value = today; }
            setOn(pills[0]);
            custom.style.display = "none";
            ov.classList.add("on");
            load({ mode: "days", days: "1" });
        }
        function closeOv(){ ov.classList.remove("on"); }
        btn.addEventListener("click", function(ev){
            ev.stopPropagation();      // 부모 #gmt-modify 클릭(타임존 설정 팝업 pop-gmt) 버블 차단
            ev.preventDefault();
            openOv();
        });
        document.getElementById("gmthist-x").addEventListener("click", closeOv);
        ov.addEventListener("click", function(ev){ if (ev.target === ov) { closeOv(); } });
        document.addEventListener("keydown", function(ev){
            if (ev.key === "Escape" && ov.classList.contains("on")) { closeOv(); }
        });
    })();
    </script>';

    // ---- JS: gmtcheck POST를 CSRF 포함해서 안정적으로 ----
    $rtnstr .= '<script>
    (function(){
        var loginEl = document.getElementById("firewall-login");
        if (loginEl) {
            loginEl.onclick = function(){
                location.replace("main_dashboard.php");
            };
        }

        // gmtcheck 토글 -> hidden form submit (CSRF 포함)
        var cb = document.getElementById("gmtcheck");
        var form = document.getElementById("gmtcheckForm");
        var valEl = document.getElementById("gmtcheckVal");
        if (cb && form && valEl) {
            cb.addEventListener("change", function(){
                valEl.value = this.checked ? "1" : "0";
                form.submit();
            });
        }

        // 다크모드 토글: 4-state 순환 Auto→GPS→Light→Dark→Auto + localStorage(cp_theme).
        //   Auto = OS prefers-color-scheme(실시간 추종). GPS = 현재 위치 일출/일몰(civil
        //   twilight; CP_SUN). GPS 데이터 없으면 시스템 폴백. Light/Dark = 수동 고정.
        //   조기 스크립트(print_css_n_head)가 첫 페인트를 이미 처리 → 여기선 토글/재평가.
        var root = document.documentElement;
        var tbtn = document.getElementById("theme-toggle");
        var THEME_MODES = ["auto", "gps", "light", "dark"];
        var THEME_META = {
            auto:  { ic: "◐", label: "System" },
            gps:   { ic: "◎", label: "GPS" },
            light: { ic: "☀", label: "Light" },
            dark:  { ic: "☾", label: "Dark" }
        };
        function cpThemeMode(){
            var m = null;
            try { m = localStorage.getItem("cp_theme"); } catch(e){}
            return (THEME_MODES.indexOf(m) >= 0) ? m : "auto";
        }
        function cpSystemDark(){
            return !!(window.matchMedia && matchMedia("(prefers-color-scheme: dark)").matches);
        }
        // 판정용 "현재 UTC 시각": 박스가 렌더 때 준 CP_SUN.now(권위) + 페이지 로드 후
        // 단조 경과시간(performance.now, 벽시계 무관). 선박 PC 시계 오설정 영향 제거.
        // CP_SUN.now 없으면(구버전 캐시) Date.now 폴백.
        var CP_PERF0 = (window.performance && performance.now) ? performance.now() : null;
        function cpNowUtc(){
            var s = window.CP_SUN;
            if (s && s.now && CP_PERF0 !== null) {
                return s.now + (performance.now() - CP_PERF0) / 1000;
            }
            if (s && s.now) { return s.now; }
            return Date.now() / 1000;
        }
        function cpGpsDark(){
            var s = window.CP_SUN;
            if (s && s.ok) {
                if (s.polar === "day")   { return false; }
                if (s.polar === "night") { return true; }
                var n = cpNowUtc();
                // dusk(end) 이전: dawn(begin) 이후가 낮. dusk 이후: 다음 dawn(nbegin) 전까지 밤.
                return (n < s.end) ? !(n >= s.begin) : !(s.nbegin && n >= s.nbegin);
            }
            return cpSystemDark();   // GPS 데이터 없음 → 시스템 폴백
        }
        function paintTheme(m){
            m = m || cpThemeMode();
            var meta = THEME_META[m] || THEME_META.auto;
            var ic = tbtn ? tbtn.querySelector(".tt-ic") : null;
            var lb = tbtn ? tbtn.querySelector(".tt-label") : null;
            if (ic) { ic.innerHTML = meta.ic; }
            if (lb) { lb.textContent = meta.label; }
        }
        function cpApplyTheme(){
            var m = cpThemeMode(), dark;
            if (m === "dark")       { dark = true; }
            else if (m === "light") { dark = false; }
            else if (m === "gps")   { dark = cpGpsDark(); }
            else                    { dark = cpSystemDark(); }
            root.classList.toggle("dark", dark);
            paintTheme(m);
        }
        if (tbtn) {
            tbtn.addEventListener("click", function(){
                var next = THEME_MODES[(THEME_MODES.indexOf(cpThemeMode()) + 1) % THEME_MODES.length];
                try { localStorage.setItem("cp_theme", next); } catch(e){}
                cpApplyTheme();
            });
        }
        // OS 테마 변경 실시간 반영(Auto 모드)
        if (window.matchMedia) {
            var mq = matchMedia("(prefers-color-scheme: dark)");
            var onMq = function(){ if (cpThemeMode() === "auto") { cpApplyTheme(); } };
            if (mq.addEventListener) { mq.addEventListener("change", onMq); }
            else if (mq.addListener) { mq.addListener(onMq); }
        }
        // GPS/Auto 모드: 분 단위 재평가(일출/일몰 경계에서 자동 전환)
        setInterval(function(){
            var m = cpThemeMode();
            if (m === "gps" || m === "auto") { cpApplyTheme(); }
        }, 60000);
        cpApplyTheme();
    })();
    </script>';

    return $rtnstr;
}


?>