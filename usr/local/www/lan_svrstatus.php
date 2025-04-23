<?php
include_once("auth.inc");
include_once("common_ui.inc");
require_once('guiconfig.inc');
include_once("terminal_status.inc");
include_once("lan_status.inc");
$vlan_contents=draw_vlantable_contents();
$lan_contents = draw_lantable_contents();
if($_POST['data_update']){
    //echo json_encode(array('return_str' => $rtnstr));
    exit(0);
}

?>

<!DOCTYPE HTML>
<html lang="ko">
<head>
    <?php
    echo print_css_n_head();
    ?>
</head>
<body>
<div id="wrapper">
    <?php echo print_sidebar( basename($_SERVER['PHP_SELF']));?>
    <div id="content">
        <div class="headline-wrap">
            <div class="title-area">
                <p class="headline">Lan, Server Status</p>
            </div>
        </div>

        <div class="contents">
            <div class="container">
                <div class="server-wrap">
                    <p class="tit v2">Vlan State</p>
                    <div class="list-wrap v1 mt20">
                        <table>
                            <colgroup>
                                <col style="width: 150px;">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                                <col style="width: calc((100% - 150px)/12);">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>IP<button class="btn-ic btn-sort"></button></th>
                                <th>#1</th>
                                <th>#2</th>
                                <th>#3</th>
                                <th>#4</th>
                                <th>#5</th>
                                <th>#6</th>
                                <th>#7</th>
                                <th>#8</th>
                                <th>#9</th>
                                <th>#10</th>
                                <th>#11</th>
                                <th>#12</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                            </tr>
                                <?php echo $vlan_contents;?>
                            </tbody>
                        </table>
                    </div>
                    <p class="tit v2 mt40">Lan State</p>
                    <div class="list-wrap v1 mt20">
                        <table>
                            <colgroup>
                                <col style="width: calc(100% / 6);">
                                <col style="width: calc(100% / 6);">
                                <col style="width: calc(100% / 6);">
                                <col style="width: calc(100% / 6);">
                                <col style="width: calc(100% / 6);">
                                <col style="width: calc(100% / 6);">
                            </colgroup>
                            <?php echo $lan_contents;?>
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
        <button class="btn lg line-mint justify-content-start"><i class="ic-open mint"></i>Open Core console</button>
        <button class="btn lg line-mint justify-content-start"><i class="ic-open mint"></i>Open VSAT console</button>
        <button class="btn lg line-mint justify-content-start"><i class="ic-open mint"></i>Open FBB console</button>
        <button class="btn lg line-red justify-content-start"><i class="ic-reset red"></i>Reset Firewall</button>
        <button class="btn lg line-red justify-content-start"><i class="ic-reset red"></i>Reset Core</button>
        <button class="btn lg line-red justify-content-start"><i class="ic-reboot red"></i>Reboot SVR</button>
    </div>
    <div class="pop-foot">
        <button class="btn md fill-dark" onclick="popClose('pop-set-server')"><i class="ic-cancel"></i>CANCEL</button>
    </div>
</div>

<!-- 20241223 수정 -->
<div class="popup layer pop-login">
    <div class="pop-head">
        <p class="title">Login</p>
    </div>
    <div class="pop-cont">
        <div class="form">
            <div class="form-tit">
                <p class="tit">ID</p>
            </div>
            <div class="form-cont">
                <input type="text" name="" id="">
            </div>
        </div>
        <div class="form mt20">
            <div class="form-tit">
                <p class="tit">Password</p>
            </div>
            <div class="form-cont">
                <input type="text" name="" id="">
            </div>
        </div>
    </div>
    <div class="pop-foot">
        <button class="btn md fill-mint" onclick="popClose('pop-login')"><i class="ic-submit"></i>LOGIN</button>
        <button class="btn md fill-dark" onclick="popClose('pop-login')"><i class="ic-cancel"></i>CANCEL</button>
    </div>
</div>
<!--// 20241223 수정 -->
</body>
</html>