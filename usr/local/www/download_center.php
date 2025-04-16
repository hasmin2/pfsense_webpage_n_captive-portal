<?php

include_once("auth.inc");
include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("lan_status.inc");
function print_download_link($title, $image, $filename){
    $rtnstr='<dl class="tile-area">';
    $rtnstr.='<dt>';
    $rtnstr.='<img src="../img/'.$image.'" alt="">';
    $rtnstr.='<p>'.$title.'</p>';
    $rtnstr.='</dt>';
    $rtnstr.='<dd>';

    $rtnstr.='<p class="text"><a href="/manuals/'.$filename.'">'.$filename.'</a></p>';

    $rtnstr.='</dd></dl>';
    return $rtnstr;
}

	$help_menu[] = array(gettext("TeamViewer Quick Support"), "TMV.DE.png", "TeamViewerQS.exe");
    array_push($help_menu, array(gettext("AnyDesk Remote Support"), "anydesk.png", "AnyDesk.exe"));
    array_push($help_menu, array(gettext("WaveSync Guide - English"), "manual.png", "SmartBox User Guide-Kor.pdf"));
    array_push($help_menu, array(gettext("WaveSync Guide - Korean"), "manual.png","SmartBox User Guide-Eng.pdf"));
	array_push($help_menu, array(gettext("FX Guide Book - English"), "manual.png", "Fleet Xpress User guide latest-Eng.pdf"));
	array_push($help_menu, array(gettext("FX Guide Book - Korean"), "manual.png","Fleet Xpress User guide latest-Kor.pdf"));
    array_push($help_menu, array(gettext("Bluewave Mail Guide Book - English"), "manual.png","Bluewave Mail User guide latest-Eng.pdf"));
    array_push($help_menu, array(gettext("Bluewave Mail Guide Book - Korean"), "manual.png","Bluewave Mail User guide latest-Kor.pdf"));
    array_push($help_menu, array(gettext("FX Quick Guide - English"), "manual.png","Fleet Xpress Quick guide latest-Eng.pdf"));
    array_push($help_menu, array(gettext("Fleet Hotspot Guide - English"), "manual.png","Fleet Hotspot User guide latest-Eng.pdf"));
    array_push($help_menu, array(gettext("Crew Internet Guide - English"),"manual.png", "Crew Internet User guide latest-Eng.pdf"));
    array_push($help_menu, array(gettext("Crew Internet Guide - Korean"), "manual.png","Crew Internet User guide latest-Kor.pdf"));

$downloadlinks ="";
$step = 0;
foreach($help_menu as $menu){
    if($step % 3 == 0){
        $downloadlinks .= '<div class="contents"><div class="container"><div class="private-wrap"><div class="tile-wrap" id="private_internet_control">';
    }
    $downloadlinks .= print_download_link($menu[0], $menu[1], $menu[2]);

    if($step % 3 == 2){
        $downloadlinks .= '</div></div></div></div>';
    }
    $step++;
}
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
        <div class="headline-wrap">
            <div class="title-area">
                <p class="headline">Downloads</p>
            </div>
        </div>
        <?= $downloadlinks;?>
        <!--div class="contents">
            <div class="container">
                <div class="private-wrap">
                    <div class="tile-wrap" id="private_internet_control">
                        <?= $downloadlinks;?>
                    </div>
                </div>
            </div>
        </div>
    </div-->
</div>


</body>
</html>
