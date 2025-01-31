<?php
    require_once("common_ui.inc");
    require_once("terminal_status.inc");
    global $config, $g;
    $vesselinfo = $config['system']['vesselinfo'];
    if($_POST['routing_radiobutton']){
        set_routing($_POST['routing_radiobutton'], $_POST['routeduration']);
    }

    $gateways = return_gateways_array();
    $gateways_status = return_gateways_status(true);
    $rtnstr = '';

    foreach ($gateways as $gname => $gateway){
        $defaultgw = get_defaultgw($gateway);
        if (!startswith($gateway['terminal_type'], 'vpn')){
            if($defaultgw==1){$rtnstr .= '<tr class="on">';}
            else{ $rtnstr .= '<tr>';}
            foreach ($config['interfaces'] as $ifname => $ifcfg) {
                if ($gateways[$gname]['interface']===$ifcfg['if']) {
                    $rtnstr .='<td data-th="Name" data-th-width="100" data-width="100">';
                    $rtnstr .=$gname.'<br>';
                    $rtnstr .='<span>'.$gateway['monitor'].'</span></td>';
                    $rtnstr .='<td data-th="Info" data-th-width="100" data-width="100">';
                    if($gateway['allowance']=="" || $gateway['allowance']=="0"||$gateway['terminal_type']==='vsat_sec') $rtnstr .= "";
                    else $rtnstr .= "<br>".get_datausage_from_db($ifcfg['if']).'/'.$gateway['allowance']."GB";
                    $rtnstr .='<td data-th="GW" data-th-width="100" data-width="100">';
                    $rtnstr .=($defaultgw ? get_routingduration() : '').'<br>';
                    //$rtnstr .='<span>'.get_speed($gateway).'</span></td>';
                    $rtnstr .='<span>'.get_speed_from_db($ifcfg['if']).'</span></td>';
                    $rtnstr .='<td data-th="Net" data-th-width="100" data-width="100">';
                    $rtnstr .='<p class='.get_net_status($gateways_status[$gname])[0].'>';
                    $rtnstr .=get_net_status($gateways_status[$gname])[1].'</p></td>';
                    $rtnstr .='<td data-th="Ext-Net" data-th-width="100" data-width="100">';
                    $rtnstr .='<p class='.get_extnet_status($gateways_status[$gname])[0].'>';
                    $rtnstr .=get_extnet_status($gateways_status[$gname])[1].'</p></td>';
                    $rtnstr .='</tr>';
                    break;
                }
            }
        }
    }
    if($_POST['data_update']){
        echo json_encode(array('return_str' => $rtnstr));
        exit(0);
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
                <p class="headline">Terminal Status</p>
            </div>

            <div class="etc-area">
                <button class="btn-setting" onclick="popOpenAndDim('pop-set-terminal', true)">Setting</button>
            </div>
        </div>

        <div class="contents">
            <div class="container">
                <div class="terminal-wrap">
                    <div class="list-wrap v1">
                        <div class="sort-area">
                            <div class="inner">
                                <select name="" id="" class="select v1">
                                    <option value="">Name</option>
                                    <option value="">Info</option>
                                    <option value="">GW</option>
                                    <option value="">Net</option>
                                    <option value="">Ext-Net</option>
                                </select>
                                <button class="btn-ic btn-sort"></button>
                            </div>
                        </div>
                        <table>
                            <colgroup>
                                <col style="width: 20%;">
                                <col style="width: 20%;">
                                <col style="width: 20%;">
                                <col style="width: 20%;">
                                <col style="width: 20%;">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Name<button class="btn-ic btn-sort"></button></th>
                                <th>Info<button class="btn-ic btn-sort"></button></th>
                                <th>GW<button class="btn-ic btn-sort"></button></th>
                                <th>Net<button class="btn-ic btn-sort"></button></th>
                                <th>Ext-Net<button class="btn-ic btn-sort"></button></th>
                            </tr>
                            </thead>
                            <tbody id="all_terminal_status">
                                <?= $rtnstr;?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="popup layer pop-set-terminal">
    <div class="pop-head">
        <p class="title">Terminal Setting</p>
    </div>
    <div class="pop-cont">
        <form action="/terminal.php" method="post" id="override_form">
        <p class="tit v1">Manual Override</p>
        <div class="override-list scroll-y">
            <ul>
                <li>
                    <div class="radio v1">

                        <input type="radio" name="routing_radiobutton" id="automatic" value="automatic" >
                        <label for="automatic">
                            <p class="txt-mint">Automatic</p>
                        </label>
                    </div>
                </li>
                <?php
                $gateways = return_gateways_array();
                foreach ($gateways as $gname => $gateway):
                    if (!startswith($gateway['terminal_type'], 'vpn') ):
                        ?>
                        <li>
                            <div class="radio v1">
                                <input type="radio" name="routing_radiobutton"  value="<?php echo($gname);?>" id="<?php echo($gname);?>">
                                <label for="<?php echo($gname);?>">
                                    <p><?php echo($gname);?></p>
                                </label>
                            </div>
                        </li>
                    <?php
                    endif;
                endforeach;
                ?>
            </ul>
        </div>
        <hr class="line v1 mt30">

        <p class="tit v1 mt30">Time duration</p>

        <select name="routeduration" id="routeduration" class="select v1 mt10">
            <option value="5">5 minutes</option>
            <option value="60">60 minutes</option>
            <option value="300">5 hours</option>
            <option value="86400">1 day</option>
            <option value="864000000">Permanent</option>
        </select>
    </div>
    <div class="pop-foot">
        <button type="submit" class="btn md fill-mint" onclick="popClose('pop-set-terminal')"><i class="ic-submit"></i>APPLY</button>
        <button type="button" class="btn md fill-dark" onclick="popClose('pop-set-terminal')"><i class="ic-cancel"></i>CANCEL</button>
    </div>
    </form>
</div>

<!-- 20241223 수정 -->
<div class="popup layer pop-login">
    <div class="pop-head">
        <p class="title">Login</p>
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
        </div>
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
<script>
    function refreshValue() {
        $.ajax({
            url: "./terminal.php",
            data: {data_update: "true"},
            type: 'POST',
            dataType: 'json',
            success: function (result) {
                $("#all_terminal_status").html(result.return_str)
            }})
    }
        setInterval(refreshValue, 10000); // 밀리초 단위이므로 5초는 5000밀리초
</script>
</html>