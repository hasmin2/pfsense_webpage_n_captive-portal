<?php
include_once("auth.inc");
include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("manage_crew_wifi_account.inc");


global $adminlogin;
$controldisplay="";
$addbutton="";
if($adminlogin==="admin") {
    $controldisplay = '<button class="btn md line-gray" onclick="confirm_resetPw()"><i class="ic-reset gray"></i>Reset PW</button>
                       <button class="btn md line-gray" onclick="confirm_resetData()"><i class="ic-reset gray"></i>Reset Data</button>
                            <button class="btn md line-gray" onclick="confirm_checkPw()"><i class="ic-check gray"></i>Check PW</button>
                            <button class="btn md line-gray" onclick="confirm_delUser()"><i class="ic-delete gray"></i>Delete</button>';
    $addbutton = '<button class="btn-setting" onclick="popOpenAndDim(\'pop-set-manage\', true)">Add Voucher</button>';
}
else if($adminlogin==="customer"){
    $controldisplay = '<button class="btn md line-gray" onclick="confirm_resetPw()"><i class="ic-reset gray"></i>Reset PW</button>';
}
else{
    $controldisplay="";
}
$cpzone='crew';

if (isset($cpzone) && !empty($cpzone) && isset($a_cp[$cpzone]['zoneid'])) {
	$cpzoneid = $a_cp[$cpzone]['zoneid'];
}

if (($_GET['act'] == "del") && !empty($cpzone)) {
	captiveportal_disconnect_client($_GET['id'], 6);
}

$table_contents = draw_wifi_contents();
$gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);
$terminaltypeoption='<option value="">Auto</option>';
foreach ($gateways as $gname => $gateway){
    $defaultgw = get_defaultgw($gateway);
    if (!startswith($gateway['terminal_type'], 'vpn')){
        $terminaltypeoption .= '<option value="'.$gname.'">'.$gname.'</option>';
    }
}

if(isset($_POST['resetpw'])){ reset_wifi_user_pw($_POST['userlist']); exit(0);}
if(isset($_POST['resetdata'])){reset_wifi_user($_POST['userlist']);exit(0);}
if(isset($_POST['deluser'])){del_wifi_user($_POST['userlist']);exit(0);}
if ($_POST['dataamount']){
    create_wifi_user($_POST['dataamount'], $_POST['vouchernumber'], $_POST['randpwd'], $_POST['terminaltype'], $_POST['timeperiod']);
    echo '<script> location.replace("crew_account_processing.php");</script>';
}
////////////////////SIMPLE SELF API//////////////////////////

if(isset($_POST['resetfw'])){reset_fw(); exit(0);}
if(isset($_POST['resetcore'])){reset_core(); exit(0);}
if(isset($_POST['rebootsvr'])){reboot_svr(); exit(0);}

$terminate_biz_internet = isset($config['ban_all'])? "true" : "false";
if($_POST['data_update']){
    echo json_encode(array(
        'crew_wifi_table' => $table_contents,
    ));
    exit(0);
}
////////////////////SIMPLE SELF API//////////////////////////
?>
<!DOCTYPE HTML>
<html lang="ko">
<head>
<?php echo print_css_n_head();?>
</head>
<body>
<div id="wrapper">
    <?php echo print_sidebar(basename($_SERVER['PHP_SELF']));?>
    <div id="content">
        <div class="headline-wrap">
            <div class="title-area">
                <p class="headline">Manage Crew Account</p>
            </div>

            <div class="etc-area">
                <?= $addbutton ?>
            </div>
        </div>

        <div class="contents">
            <div class="container">
                <div class="manage-wrap">
                    <div class="list-top justify-content-end">
                        <div class="btn-area">
                            <?= $controldisplay ?>
                        </div>
                    </div>
                    <div class="list-wrap v1">
                        <div class="sort-area">
                            <div class="inner">
                                <select name="" id="" class="select v1">
                                    <option value="">ID</option>
                                    <option value="">Date create</option>
                                    <option value="">Type</option>
                                    <option value="">Update</option>
                                    <option value="">Usage state</option>
                                    <option value="">Online</option>
                                    <!--option value="">Topup/Action</option-->
                                </select>
                                <button class="btn-ic btn-sort"></button>
                            </div>
                        </div>
                        <table>
                            <colgroup>
                                <col style="width: 50px;">
                                <col style="width: 170px;">
                                <col style="width: 130px;">
                                <col style="width: 170px;">
                                <col style="width: 120px;">
                                <col style="width: 150px;">
                                <col style="width: 80px;">
                                <!--col style="width: 170px;"-->
                            </colgroup>
                            <thead>
                            <tr>
                                <th>
                                    <div class="check v1">
                                        <input type="checkbox" name="userselectall" id="userselectall" onclick="selectAll(this)">
                                        <label for="userselectall"></label>
                                    </div>
                                </th>
                                <th>ID<button class="btn-ic btn-sort"></button></th>
                                <th>Date create<button class="btn-ic btn-sort"></button></th>
                                <th>Type<button class="btn-ic btn-sort"></button></th>
                                <th>Update<button class="btn-ic btn-sort"></button></th>
                                <th>Usage state<button class="btn-ic btn-sort"></button></th>
                                <th>Online<button class="btn-ic btn-sort"></button></th>
                                <!--th>Topup/Action<button class="btn-ic btn-sort"></button></th-->
                            </tr>
                            </thead>
                            <tbody id="crew_account_table">
                            <?= $table_contents;?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<form name="registerusers" id='registerusers' method="post" action="/crew_account.php">
    <div class="popup layer pop-set-manage">
        <div class="pop-head">
            <p class="title">Account Setting</p>
        </div>
        <div class="pop-cont">
            <div class="form">
                <div class="form-tit">
                    <p class="tit">Allow data (MB)</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="dataamount" id="dataamount">
                </div>
            </div>
            <div class="form mt20">
                <div class="form-tit">
                    <p class="tit"># of Vouchers</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="vouchernumber" id="vouchernumber">
                </div>
            </div>

            <div class="check v1 mt30">
                <input type="checkbox" name="randpwd" id="randpwd" value="randpwd">
                <label for="randpwd">
                    <p>Generate random password?</p>
                </label>
            </div>
            <hr class="line v1 mt30">
            <div class="form mt30">
                <div class="form-tit">
                    <p class="tit">Terminal Type</p>
                </div>
                <div class="form-cont">
                    <select name="terminaltype" id="terminaltype" class="select v1">
                        <?php echo $terminaltypeoption;?>


                    </select>
                </div>
                <div class="form-tit">
                    <p class="tit">Reset every...</p>
                </div>
                <div class="form-cont">
                    <select name="timeperiod" id="timeperiod" class="select v1">
                        <option value="Monthly">Monthly</option>
                        <option value="Daily">Daily</option>
                        <option value="Forever">one-time</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="pop-foot">
            <button type='button' class="btn md fill-mint" onclick="submit_registerusers()"><i class="ic-submit"></i>APPLY</button>
            <button type='button' class="btn md fill-dark" onclick="popClose('pop-set-manage')"><i class="ic-cancel"></i>CANCEL</button>
        </div>
    </div>
</form>
</body>
<script type="text/javascript">
    function refreshValue() {
        $.ajax({
            url: "./crew_account.php",
            data: {data_update: "true"},
            type: 'POST',
            dataType: 'json',
            success: function (result) {


                $("#crew_account_table").html(result.crew_wifi_table);
            },
            error: function (request, status, error) {
                alert(error);
            }
        })
    }
    //setInterval(refreshValue, 60000); // 밀리초 단위이므로 5초는 5000밀리초
    function submit_registerusers(){
        popClose('pop-set-manage');
        $('#registerusers').submit();
    }
    const checkboxes = document.querySelectorAll('.userlist', 'input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('click', handleCheck);
    });
    let lastChecked;
    function handleCheck(e) {
        let inBetween = false;
        if (e.shiftKey && this.checked) {
            checkboxes.forEach(checkbox => {
                if (checkbox === this || checkbox === lastChecked) {
                    inBetween = !inBetween;
                }

                if (inBetween) {
                    checkbox.checked = true;
                }
            });
        }

        lastChecked = this;
    }
    function confirm_resetPw(){
        if(window.confirm('Selected user passwords will be reset to 1111, OK to continue.')){
            $.ajax({
                url: "./crew_account.php",
                data: {resetpw: "true", userlist: $('input[name="userlist[]"]:checked').map(function(){return $(this).val();}).get()},
                type: 'POST',
                success: function (result) {
                    location.replace("crew_account.php");
                },
                error: function (result) {
                }
            })
        }
        else { return false; }
    }
    function confirm_resetData(){
        if(window.confirm(`Selected user data usage will be reset, OK to continue.`)){
            $.ajax({
                url: "./crew_account.php",
                data: {resetdata: "true", userlist: $('input[name="userlist[]"]:checked').map(function(){return $(this).val();}).get()},
                type: 'POST',
                success: function (result) {
                    location.replace("crew_account.php");
                },
                error: function (result) {
                }
            })
    }
    else { return false; }
    }
    function confirm_delUser(){
        if(window.confirm(`Selected user IDs are being deleted, OK to continue.`)){
            $.ajax({
                url: "./crew_account.php",
                data: {deluser: "true", userlist: $('input[name="userlist[]"]:checked').map(function () {return $(this).val();}).get()},
                type: 'POST',
                success: function (result) {
                    location.replace("crew_account.php");
                },
                error: function (result) {}
            })
        }
    }
    function confirm_checkPw(){
        var pwlist = "<?php
            global $config;
            $passwordlist = array();
            foreach ($config['installedpackages']['freeradius']['config'] as $eachuser) {
                $used_quota=check_quota($eachuser['varusersusername'], $eachuser['varusersmaxtotaloctetstimerange']);
                if(preg_match("/[a-z]*[0-9]{5}/", $eachuser['varusersusername'])) {
                    if ($used_quota <= $eachuser['varusersmaxtotaloctets'] || strtolower($eachuser['varuserspointoftime']) !== 'forever') {
                        array_push($passwordlist, $eachuser['varuserspassword'].'<br>');
                    }
                }
            }
            echo implode("|||", $passwordlist);
            ?>";

        var result="";
        var resultlist = document.getElementsByName('userlist[]');
        for(let idcount=0; idcount<resultlist.length; idcount++){
            if(resultlist[idcount].checked){
                result += "\n" + resultlist[idcount].value + " : " + pwlist.split("|||")[idcount];
            }
        }
        if(result===''){
            document.getElementById('message_text').innerHTML = "Please select a user";
            return popOpenAndDim("pop-message",true);
        }
        else{
            document.getElementById('message_text').innerHTML = result;
            return popOpenAndDim("pop-message",true);
        }
    }
    function selectAll(selectAll)  {
        const checkboxes = document.getElementsByName('userlist[]');
        checkboxes.forEach((checkbox) => {checkbox.checked = selectAll.checked;})
    }
</script>
</html>
