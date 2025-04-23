<?php

include_once("auth.inc");
require_once('guiconfig.inc');
include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("lan_status.inc");
function print_crewwifi_timeduration(){
    global $config;
    $rtnstr = "";
    if(isset($config['captiveportal']['crew']['terminate_duration'])){
        $date = new DateTime();
        $terminate_timeleft = $config['captiveportal']['crew']['terminate_duration']-(round($date->getTimestamp()/60,0) -$config['captiveportal']['crew']['terminate_timestamp']);
        if($terminate_timeleft>1440){ $rtnstr .= "<p class='text'>". Fixed."</p>";}
        else{ $rtnstr .= "<p class='text'>". $terminate_timeleft." minutes left</p>";}
    }
    else{
        $rtnstr .= "<select name='terminate_duration' id='terminate_duration' class='select v1'>
                                                <option value='5'>5 minutes</option>
                                                <option value='30'>30 minutes</option>
                                                <option value='60'>60 minutes</option>
                                                <option value='300'>5 hours</option>
                                                <option value='1440'>24 hours</option>
                                                <option value='999999999'>Permanent</option></select>";
    }
    return $rtnstr;
}

global $config, $g;
global $adminlogin;
$settings='';
$crew_wifitoggle_disable='disabled';
if($adminlogin){
    $settings='<button class="btn-setting" onclick="popOpenAndDim(\'pop-set-terminal\', true)">Setting</button>';
    $crew_wifitoggle_disable='';
 }

init_config_arr(array('captiveportal'));
if(isset($_POST['crewcheckboxvalue'])){
    toggle_crew_wifi($_POST['crewcheckboxvalue']);
    echo '<script> location.replace("network_control_processing.php");</script>';

}
if(isset($_POST['terminate_crewinternetvalue'])){
    terminate_crew_internet($_POST['terminate_crewinternetvalue'], $_POST['terminate_duration']);
    echo '<script> location.replace("network_control_processing.php");</script>';
}
if($_POST['ban_all_ip']){
    $config['ban_all_ip'] = $_POST['ban_all_ip'];
    write_config("Ban all IP address");
    echo '<script> location.replace("network_control_processing.php");</script>';
}

if(isset($_POST['terminate_bizinternetvalue'])){
    terminate_biz_internet($_POST['terminate_bizinternetvalue'], $config['ban_all_ip']);
    echo '<script> location.replace("network_control_processing.php");</script>';
}

$gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);

////////////////////SIMPLE SELF API//////////////////////////
$terminate_biz_internet = isset($config['ban_all'])? "true" : "false";
if($_POST['data_update']){
    echo json_encode(array(
        'toggle_crew_wifi' => isset($config['captiveportal']['crew']['enable'])? "true" : "false",
        'terminate_crew_internet' => isset($config['captiveportal']['crew']['terminate_duration'])? "true" : "false",
        'print_crewwifi_duration' => print_crewwifi_timeduration(),
        'terminate_biz_internet' => $terminate_biz_internet,
        'ban_all_ip' => $config['ban_all_ip'],
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
        <div class="headline-wrap">
            <div class="title-area">
                <p class="headline">Private Internet Control</p>
            </div>
            <div class="etc-area">
                <?= $settings;?>
            </div>
        </div>
        <div class="contents">
            <div class="container">
                <div class="private-wrap">
                    <div class="tile-wrap" id="private_internet_control">
                        <dl class="tile-area">
                            <dt>
                                <img src="../img/img_private01.png" alt="">
                                <p>Crew WIFI Portal</p>
                            </dt>
                            <dd>
                                <div class="switch" id="toggle_crew_wifi_switch">
                                    <form action="" method="post" id="toggle_wifi">
                                        <input type="checkbox" name="crew" id="crew"<?= $crew_wifitoggle_disable ?> <?= isset($config['captiveportal']['crew']['enable']) ? "checked" : "" ?>>
                                        <label for="crew"></label>
                                        <input type="hidden" name="crewcheckboxvalue"  id="crewcheckboxvalue" value="0">
                                    </form>
                                </div>
                                <p class="text">Enable / Disable CREW WIFI Portal</p>
                                <p class="txt-caution">
                                    If Disable, private internet will be transmitted <br>
                                    without control
                                </p>
                            </dd>
                        </dl>
                        <dl class="tile-area">
                            <dt>
                                <img src="../img/img_private02.png" alt="">
                                <p>Terminate Private Internet</p>
                            </dt>
                            <dd>
                                <div class="switch" id="crew_wifi_switch">
                                    <form action="" method="post" id="terminate_private_internet">
                                        <input type="checkbox" name="terminate_crewinternet"  id="terminate_crewinternet" <?php echo isset($config['captiveportal']['crew']['terminate_duration']) ? "checked" : ""; ?>>
                                        <label for="terminate_crewinternet"></label>
                                        <input type="hidden" name="terminate_crewinternetvalue"  id="terminate_crewinternetvalue" value="<?php echo $terminate_biz_internet;?>">
                                </div>
                                <p class="text">
                                    Terminate private internet usage <br>
                                    except ‘00001’ during
                                </p>
                                <div id = 'terminate_remaintime'>
                                <?php echo print_crewwifi_timeduration(); ?>
                                </div>
                                </form>
                            </dd>
                        </dl>
                        <dl class="tile-area">
                            <dt>
                                <img src="../img/img_private03.png" alt="">
                                <p>Block Internet Access</p>
                            </dt>
                            <dd>
                                <form action="" method="post" id="terminate_business_internet" >
                                    <div class="switch" id="business_switch">
                                        <input type="checkbox" name="terminate_bizinternet"  id="terminate_bizinternet" <?php echo !isset($config['ban_all_ip'])||$config['ban_all_ip']==''? 'disabled': '';?> <?php echo isset($config['ban_all']) ? "checked" : ""; ?>>
                                        <label for="terminate_bizinternet"></label>
                                        <input type="hidden" name="terminate_bizinternetvalue"  id="terminate_bizinternetvalue" value="0">
                                    </div>
                                </form>
                                    <p class="text">
                                        Block all business internet access <br>
                                        except for
                                    </p>
                                    <p class="text"><?php echo $config['ban_all_ip'];?></p>
                                    <!--input type="text" name="ipaddr" id="ipaddr" value="<?php echo $config['ban_all_ip'];?>"-->


                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="popup layer pop-set-terminal">
        <div class="pop-head">
            <p class="title">Network Control</p>
        </div>
        <form action="/network_control.php" method="post" id="ban_all_form">
            <div class="pop-cont">
                <p class="tit v1 mt30">IP Address</p>
                <input type="text" id='ban_all_ip' name="ban_all_ip" class="v1 mt30" value="<?php echo $config['ban_all_ip'];?>">
            </div>
            <div class="pop-foot">
                <button type="button" class="btn md fill-mint" onclick="submit_banall_form(ban_all_ip.value)"><i class="ic-submit"></i>APPLY</button>
                <button type="button" class="btn md fill-dark" onclick="popClose('pop-set-terminal')"><i class="ic-cancel"></i>CANCEL</button>
            </div>
        </form>
    </div>
</div>


</body>
</html>
<script>
    function ipAddressCheck(ipAddress){
        var regEx = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        if(ipAddress.match(regEx)||ipAddress===""){
            return true;
        }
        else{
            alert ("You input wrong ip address format. please try again");
            return false;
        }
    }
    function submit_banall_form(ipAddress){
        if($('#terminate_bizinternet').is(':checked')){
            alert("Change value is not allowed during BIZ internet termination");
            popOpenAndDim('pop-message', true);
        }
        else{
            if(ipAddressCheck(ipAddress)){
                popClose('pop-set-terminal');
                $('#ban_all_form').submit();
            }
            else {return false;}
        }
    }
    $(document).ready(function() {
        // 엔터키를 눌렀을 때 이벤트 처리
        $('form').on('keydown', 'input, textarea', function(event) {
            if($('#terminate_bizinternet').is(':checked')){
                alert("Change value is not allowed during BIZ internet termination");
                event.preventDefault();
            }
            if (event.key === 'Enter' && !ipAddressCheck(document.getElementById('ban_all_ip').value)) {
                event.preventDefault();
            }
        });
    });
    function refreshValue() {
        $.ajax({
            url: "./network_control.php",
            data: {data_update: "true"},
            type: 'POST',
            dataType: 'json',
            success: function (result) {
                if(result.toggle_crew_wifi === "true") { $("#crew").prop('checked', true); }
                else { $("#crew").prop('checked', false); }
                if(result.terminate_crew_internet === "true") { $("#terminate_crewinternet").prop('checked', true); }
                else { $("#terminate_crewinternet").prop('checked', false); }
                if(result.terminate_biz_internet === "true") { $("#terminate_bizinternet").prop('checked', true); }
                else { $("#terminate_bizinternet").prop('checked', false); }
                $("#ipaddr").val(result.ban_all_ip);
                $("#wan_status").html(result.wan_status);
                $("#terminate_remaintime").html(result.print_crewwifi_duration);
            },
            error: function (request, status, error) {
            }
        })
    }
    //setInterval(refreshValue, 10000); // 밀리초 단위이므로 5초는 5000밀리초
    //setTimeout('location.reload()', 60000);
    // adding event for crew wifi portal
    document.getElementById('crew').addEventListener('change', function() {
        const checkbox = document.getElementById('crew');
        const crewcheckboxvalue = document.getElementById('crewcheckboxvalue');
        if (checkbox.checked) { crewcheckboxvalue.value = "1";
        } else { crewcheckboxvalue.value = "0";}
        $('#toggle_wifi').submit();
    });
    document.getElementById('terminate_crewinternet').addEventListener('change', function() {
        const checkbox = document.getElementById('terminate_crewinternet');
        const crewcheckbox = document.getElementById('crew');
        const terminate_crewinternetvalue = document.getElementById('terminate_crewinternetvalue');
        if (checkbox.checked) { terminate_crewinternetvalue.value = "1";
        } else { terminate_crewinternetvalue.value = "0";}
        if(crewcheckbox.checked){
            $('#terminate_private_internet').submit();
        }
        else{
            document.getElementById('message_text').innerHTML="Please enable crew wifi portal first";
            popOpenAndDim('pop-message', true);
            checkbox.checked = false;
        }
    });
    document.getElementById('terminate_bizinternet').addEventListener('change', function() {
        //ipAddressCheck(document.getElementById('ipaddr').attributes[3].value);
        const checkbox = document.getElementById('terminate_bizinternet');
        const terminate_bizinternetvalue = document.getElementById('terminate_bizinternetvalue');
        if (checkbox.checked) { terminate_bizinternetvalue.value = "1";
        } else { terminate_bizinternetvalue.value = "0";}
        $('#terminate_business_internet').submit();
    });


</script>


