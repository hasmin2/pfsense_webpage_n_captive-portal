<?php
require_once("common_ui.inc");
require_once("terminal_status.inc");
require_once('guiconfig.inc');
global $config, $g;
$vesselinfo = $config['system']['vesselinfo'];
if($_POST['routing_radiobutton']){
    set_routing($_POST['routing_radiobutton'], $_POST['routeduration']);
}
if (isset($_POST['allowance']) && is_array($_POST['allowance'])) {
    cp_apply_gateway_cutoff_settings($_POST['allowance'], isset($_POST['cutoff_enable']) ? $_POST['cutoff_enable'] : array());
}

$gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);
/*
 * 게이트웨이(안테나)별 상태 셀 데이터. 예전에는 tbody 전체를 HTML 문자열로 만들어
 * 10초마다 통째로 덮어썼는데, Allowance 입력/Cutoff 체크박스를 같은 행에 합치면서
 * 그 방식으로는 편집 중인 값이 폴링마다 리셋된다. 그래서 상태가 바뀌는 셀(Info/GW/
 * Net/Ext-Net)만 게이트웨이 이름으로 식별해 부분 갱신하도록 구조화했다 — Name/
 * Allowance/Cutoff 셀은 애초에 건드리지 않는다.
 */
$rowData = array();
foreach ($gateways as $gname => $gateway) {
    if (startswith($gateway['terminal_type'], 'vpn')) {
        continue;
    }
    $defaultgw = get_defaultgw($gateway);
    foreach ($config['interfaces'] as $ifname => $ifcfg) {
        if ($gateways[$gname]['interface'] !== $ifcfg['if']) {
            continue;
        }
        $infoHtml = '';
        if (!($gateway['allowance']=="" || $gateway['allowance']=="0" || $gateway['terminal_type']==='vsat_sec')) {
            $infoHtml = "<br>".get_datausage_from_db($ifcfg['if']).'/'.$gateway['allowance']."GB";
        }
        $netStatus = get_net_status($gateways_status[$gname]);
        $extnetStatus = get_extnet_status($gateways_status[$gname]);
        $rowData[$gname] = array(
            'row_on'       => ($defaultgw==1),
            'monitor'      => $gateway['monitor'],
            'info_html'    => $infoHtml,
            'gw_html'      => ($defaultgw ? get_routingduration() : '').'<br><span>'.get_speed_from_db($ifcfg['if']).'</span>',
            'net_class'    => $netStatus[0],
            'net_text'     => $netStatus[1],
            'extnet_class' => $extnetStatus[0],
            'extnet_text'  => $extnetStatus[1],
        );
        break;
    }
}

if ($_POST['data_update']) {
    echo json_encode(array('rows' => $rowData));
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
                                    <option value="">Monthly Allowance (GB)</option>
                                    <option value="">Cutoff</option>
                                </select>
                                <button class="btn-ic btn-sort"></button>
                            </div>
                        </div>
                        <form action="/terminal.php" method="post" id="cutoff_form">
                        <table>
                            <colgroup>
                                <col style="width: 16%;">
                                <col style="width: 16%;">
                                <col style="width: 16%;">
                                <col style="width: 13%;">
                                <col style="width: 13%;">
                                <col style="width: 14%;">
                                <col style="width: 12%;">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Name<button class="btn-ic btn-sort"></button></th>
                                <th>Info<button class="btn-ic btn-sort"></button></th>
                                <th>GW<button class="btn-ic btn-sort"></button></th>
                                <th>Net<button class="btn-ic btn-sort"></button></th>
                                <th>Ext-Net<button class="btn-ic btn-sort"></button></th>
                                <th>Monthly Allowance (GB)</th>
                                <th>Cutoff</th>
                            </tr>
                            </thead>
                            <tbody id="all_terminal_status">
                                <?php foreach ($rowData as $gname => $d):
                                    $gid = htmlspecialchars($gname);
                                    $gateway = $gateways[$gname];
                                    ?>
                                <tr data-gw="<?php echo($gid); ?>" class="<?php echo($d['row_on'] ? 'on' : ''); ?>">
                                    <td data-th="Name" data-th-width="100" data-width="100">
                                        <?php echo($gid); ?><br>
                                        <span><?php echo(htmlspecialchars($d['monitor'])); ?></span>
                                    </td>
                                    <td data-th="Info" data-th-width="100" data-width="100" class="cell-info">
                                        <?php echo($d['info_html']); ?>
                                    </td>
                                    <td data-th="GW" data-th-width="100" data-width="100" class="cell-gw">
                                        <?php echo($d['gw_html']); ?>
                                    </td>
                                    <td data-th="Net" data-th-width="100" data-width="100" class="cell-net">
                                        <p class="<?php echo($d['net_class']); ?>"><?php echo($d['net_text']); ?></p>
                                    </td>
                                    <td data-th="Ext-Net" data-th-width="100" data-width="100" class="cell-extnet">
                                        <p class="<?php echo($d['extnet_class']); ?>"><?php echo($d['extnet_text']); ?></p>
                                    </td>
                                    <td data-th="Monthly Allowance (GB)" data-th-width="100" data-width="100">
                                        <input type="text" name="allowance[<?php echo($gid); ?>]" value="<?php echo(htmlspecialchars($gateway['allowance'] ?? '')); ?>" placeholder="Blank = unlimited">
                                    </td>
                                    <td data-th="Cutoff" data-th-width="100" data-width="100">
                                        <div class="check v1">
                                            <input type="checkbox" name="cutoff_enable[<?php echo($gid); ?>]" id="cutoff_<?php echo($gid); ?>" value="1" <?php echo(!empty($gateway['cutoff_enable']) ? 'checked' : ''); ?>>
                                            <label for="cutoff_<?php echo($gid); ?>">
                                                <p>Cutoff when exceeded</p>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="btn-area mt20" style="text-align: right;">
                            <button type="submit" class="btn md fill-mint"><i class="ic-submit"></i>APPLY</button>
                        </div>
                        </form>
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
                $.each(result.rows, function (gwname, d) {
                    var $row = $("#all_terminal_status tr[data-gw='" + gwname + "']");
                    if ($row.length === 0) {
                        return;
                    }
                    $row.toggleClass('on', !!d.row_on);
                    $row.find('.cell-info').html(d.info_html);
                    $row.find('.cell-gw').html(d.gw_html);
                    $row.find('.cell-net p').attr('class', d.net_class).text(d.net_text);
                    $row.find('.cell-extnet p').attr('class', d.extnet_class).text(d.extnet_text);
                });
            }})
    }
        setInterval(refreshValue, 10000); // 밀리초 단위이므로 5초는 5000밀리초
</script>
</html>
