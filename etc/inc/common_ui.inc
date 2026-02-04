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
else{
    if (strpos($_SERVER["REMOTE_ADDR"], "10.8.128.1") !== false){
        $adminlogin = "admin";
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
    $rtnstr = '<title>';
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

/*function print_sidebar($inputlink){
    global $config;
    $vesselinfo = $config['system']['vesselinfo'];
    $rtnstr='';
    $rtnstr.='<div id="sidebar">';
    $rtnstr.='<div class="brand">';
    $rtnstr.='<button class="btn-ic btn-menu-open"></button>';
    if($config['system']['vesselinfo']['logo']==="sktelink"){
        $rtnstr.='<h1 class="sklogo"><a href="/index.php"></a></h1>';
    }
    else if($config['system']['vesselinfo']['logo']==="inmarsat"){
        $rtnstr.='<h1 class="inmarsatlogo"><a href="/index.php"></a></h1>';
    }
    else{
        $rtnstr.='<h1 class="logo"><a href="/index.php"></a></h1>';
    }
    $rtnstr.='<p class="location" id="firewall-login">';
    $rtnstr.= $vesselinfo["vesselname"];
    $rtnstr.='</p>';
    $rtnstr .= '<p class="gmt" id="gmt-modify" style="cursor:pointer;">';
    $rtnstr .= 'GMT ' . $config['time_offset_enabled']["time_offset"];
    $rtnstr .= '</p>';
    $gmtmanualchecked = $config['time_offset_enabled']['gmtcheck']? "checked" : "";

    $rtnstr .= '<div class="gmt-option">';
    $rtnstr .= '  <label for="gmtcheck">';
    $rtnstr .= '    <input type="checkbox" name="gmtcheck" id="gmtcheck"'.$gmtmanualchecked.'
                 style="display:inline-block !important; visibility:visible !important; opacity:1 !important;
                        -webkit-appearance:checkbox !important; appearance:checkbox !important;">';
    $rtnstr .= '    <span>Manual Timezone Enable</span>';
    $rtnstr .= '  </label>';
    $rtnstr .= '</div>';


    $rtnstr.='</div><div id="lnb">';
    $rtnstr.='<button class="btn-ic btn-menu-close"></button>';
    $rtnstr.='<div class="depth01"><ul>';
    if($inputlink=="index.php"){
        $rtnstr.='<li class="on"><a href="/index.php"><i class="ic-lnb01"></i><p>Main Panel</p></a></li>';
    }
    else{
        $rtnstr.='<li><a href="/index.php"><i class="ic-lnb01"></i><p>Main Panel</p></a></li>';
    }
    if($inputlink=="network_control.php"){
        $rtnstr.='<li class="on"><a href="/network_control.php"><i class="ic-lnb02"></i><p>Network Control</p></a></li>';
    }
    else{
        $rtnstr.='<li><a href="/network_control.php"><i class="ic-lnb02"></i><p>Network Control</p></a></li>';
    }
    if($inputlink=="terminal.php"){
        $rtnstr.='<li class="on"><a href="/terminal.php"><i class="ic-lnb03"></i><p>Antenna</p></a></li>';
    }
    else{
        $rtnstr.='<li><a href="/terminal.php"><i class="ic-lnb03"></i><p>Antenna</p></a></li>';
    }
    if($inputlink=="lan_svrstatus.php"){
        $rtnstr.='<li class="on"><a href="/lan_svrstatus.php"><i class="ic-lnb04"></i><p>LAN</p></a></li>';
    }
    else{
        $rtnstr.='<li><a href="/lan_svrstatus.php"><i class="ic-lnb04"></i><p>LAN</p></a></li>';
    }
    if($inputlink=="crew_account.php"){
        $rtnstr.='<li class="on"><a href="/crew_account.php"><i class="ic-lnb05"></i><p>Crew Account</p></a></li>';
    }
    else{
        $rtnstr.='<li><a href="/crew_account.php"><i class="ic-lnb05"></i><p>Crew Account</p></a></li>';
    }
    if($inputlink=="download_center.php"){
        $rtnstr.='<li class="on"><a href="/download_center.php"><i class="ic-lnb06"></i><p>Download Center</p></a></li>';
    }
    else{
        $rtnstr.='<li><a href="/download_center.php"><i class="ic-lnb06"></i><p>Download Center</p></a></li>';
    }
    //$csrf = $_SESSION['csrfMagicToken'] ?? ($_SESSION['token'] ?? '');


    $username = htmlspecialchars($_SESSION['Username'] ?? '', ENT_QUOTES);
    $rtnstr .= '<form id="logoutForm" action="/index.php?logout" method="post" style="display:inline;">
  <input type="hidden" name="__csrf_magic" id="__csrf_magic" value="">
  <button type="submit" class="btn btn-link" title="Logout ('. $username.')"
          style="background:none;border:0;padding:0;cursor:pointer;">
    <i class="fa fa-sign-out"></i> Logout
  </button>
</form>';


    $rtnstr.='</ul></div>';

    $rtnstr.='</div></div>';

    $rtnstr .= '<div class="popup layer pop-login">
        <div class="pop-head">
            <p class="title">Login</p>
        </div>
        <div class="pop-cont">
        <form name="registerusers" id="#firewall-loginform" method="post" action="./index.php">
            <div class="form">
                <div class="form-tit">
                    <p class="tit">ID</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="usernamefld" id="usernamefld">
                </div>
            </div>
            <div class="form mt20">
                <div class="form-tit">
                    <p class="tit">Password</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="passwordfld" id="passwordfld">
                </div>
            </div>
        </div>
        <div class="pop-foot">
            <button class="btn md fill-mint" onclick="firewall_login()"><i class="ic-submit"></i>LOGIN</button>
            <button type=button class="btn md fill-dark" onclick="popClose(\'pop-login\')"><i class="ic-cancel"></i>CANCEL</button>
        </div>
        </form>
        <form id="gmtForm" action="/index.php" method="post" style="display:none;">
            <input type="hidden" name="gmt" id="gmtVal">
        </form>
        <form id="gmtEnabledForm" action="/index.php" method="post" style="display:none;">
            <input type="hidden" name="manaulgmt" id="manualgmt">
        </form>
    </div>';
    $rtnstr .= '<div class="popup layer pop-message"><div class="pop-head"><p class="title">Message</p></div><div class="pop-cont"><div class="form">';
    $rtnstr .= '<div class="form-cont" id="message_text"></div></div></div><div class="pop-foot">';
    $rtnstr .= '<button type="button" class="btn md fill-mint" onclick="popClose(\'pop-message\')"><i class="ic-submit"></i>Close</button></div></div>';
    $rtnstr .= '<script>
    document.getElementById("firewall-login").onclick=function(){
        location.replace("main_dashboard.php");
        //popOpenAndDim(\'pop-login\', true);
    }
    document.getElementById("gmt-modify").onclick=function(){
        location.replace("/");
        
    }
    function firewall_login(){
        //alert($(\'#usernamefld\').val());
        //$(\'#firewall-loginform\').submit();
        popClose(\'pop-login\');
    }
     (function(){
      var el = document.getElementById("gmtcheck");
      if (!el) return;
    
      el.addEventListener("change", function () {
        var csrfEl = document.querySelector(\'input[name="__csrf_magic"]\');
        var csrf = csrfEl ? csrfEl.value : "";
        var val  = this.checked ? "1" : "0";
    
        fetch("/index.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "__csrf_magic=" + encodeURIComponent(csrf) + "&gmtcheck=" + encodeURIComponent(val)
        })
        .then(function(){ console.log("gmtcheck POST sent:", val); })
        .catch(function(e){ console.error("gmtcheck error:", e); });
      });
    })();
    </script>';


    return $rtnstr;
}*/


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

    // GMT 표시
    $rtnstr .= '    <p class="gmt" id="gmt-modify" style="cursor:pointer;">GMT ' . htmlspecialchars($gmt, ENT_QUOTES) . '</p>';

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
    $rtnstr .= $mk("download_center.php", "Download Center", "ic-lnb06");


    $rtnstr .= '<li style="margin-top:12px;">'
        . '<a href="/index.php?logout=1" title="Logout (' . $username . ')"">'
        . '  <i class="ic-lnb-logout"></i>'
        . '  <p>Logout</p>'
        . '</a>'
        . '</li>';
    $rtnstr .= '    </ul></div>';
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
    })();
    </script>';

    return $rtnstr;
}


?>