<?php
include_once ('auth.inc');
include_once ('config.lib.inc');

$adminlogin="";
if(session_auth()){
    $adminlogin = true;
}
else if (strpos($_SERVER["REMOTE_ADDR"], "10.8.128.1") !== false){
    $adminlogin = true;
}
else{
    $adminlogin = false;
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
    $rtnstr .= '<meta name="apple-mobile-web-app-capable" content="yes" />';
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
function print_sidebar($inputlink){
    global $config;
    $vesselinfo = $config['system']['vesselinfo'];
    $rtnstr='';
    $rtnstr.='<div id="sidebar">';
    $rtnstr.='<div class="brand">"';
    $rtnstr.='"<button class="btn-ic btn-menu-open"></button>"';
    $rtnstr.='"<h1 class="logo"><a href="/index.php">SynerSat</a></h1>"';
    $rtnstr.='"<p class="location" id="firewall-login">';
    $rtnstr.= $vesselinfo["vesselname"];
    $rtnstr.='</p>';
    $rtnstr.='<p class="gmt">';
    $rtnstr.= 'GMT '.$config["time_offset"];
    $rtnstr.='</p>';
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
    </div>';
    $rtnstr .= '<div class="popup layer pop-message"><div class="pop-head"><p class="title">Message</p></div><div class="pop-cont"><div class="form">';
    $rtnstr .= '<div class="form-cont" id="message_text"></div></div></div><div class="pop-foot">';
    $rtnstr .= '<button type="button" class="btn md fill-mint" onclick="popClose(\'pop-message\')"><i class="ic-submit"></i>Close</button></div></div>';
    $rtnstr .= '<script>
    document.getElementById("firewall-login").onclick=function(){
        location.replace("main_dashboard.php");
        //popOpenAndDim(\'pop-login\', true);
    }
    function firewall_login(){
        //alert($(\'#usernamefld\').val());
        //$(\'#firewall-loginform\').submit();
        popClose(\'pop-login\');
    }
    </script>';


    return $rtnstr;
}

?>

