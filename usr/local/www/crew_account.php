<?php
require_once('guiconfig.inc');
include_once("auth.inc");
include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("manage_crew_wifi_account.inc");

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    export_wifi_csv();
}

global $adminlogin;
$controldisplay="";
$addbutton="";
if($adminlogin==="admin"||$adminlogin==="vesseladmin") {
    $controldisplay = '<td><button class="btn md line-gray" onclick="confirm_exportCsv()"><i class="ic-reset gray"></i>Export CSV</button>
                       <button class="btn md line-gray" onclick="confirm_resetPw()"><i class="ic-reset gray"></i>Reset PW</button>
                       <button class="btn md line-gray" onclick="confirm_resetData()"><i class="ic-reset gray"></i>Reset Data</button>
                            <button class="btn md line-gray" onclick="confirm_checkPw()"><i class="ic-check gray"></i>Check PW</button>
                            <button class="btn md line-gray" onclick="confirm_delUser()"><i class="ic-delete gray"></i>Delete</button></td>';
        $setupbutton = '<button class="btn-setting" onclick="popOpenAndDim(\'pop-modify-manage\', true)">Modify Voucher</button>';
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
if ($_POST['schedule_json'] && $_POST['userid']) {
    $userid="";
    $schedule=[];
    $schedule_json = json_decode($_POST['schedule_json'], true);
    foreach ($schedule_json as $eachItem) {
        if(isset($eachItem['userid'])){
            $userid=$eachItem['userid'];
        }
        else{
            $schedule[]=$eachItem;
        }
    }
   set_scheduler($userid, $schedule);
    echo '<script> location.replace("crew_account_processing.php");</script>';
}
if ($_POST['description'] && $_POST['userid']) {
    $description=$_POST['description'];
    $userid=$_POST['userid'];
    set_descruption($userid, $description);
    echo '<script> location.replace("crew_account_processing.php");</script>';
}
//print_r($_POST);

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

if (isset($_POST['modifyusers'])) {

    // 1) 기본 POST 값
    $userlist = $_POST['userlist'] ?? [];

    // 2) modifydata 파싱
    $modifydata = [];
    if (!empty($_POST['modifydata'])) {
        parse_str($_POST['modifydata'], $modifydata);
    }

    // 이후 처리
    modify_wifi_user($userlist, $modifydata);
    exit;
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
<!DOCTYPE html>
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
                <div style="display:flex; align-items:center; gap:20px;">
                    <?= $setupbutton ?>
                    <div style="flex:1;"></div>
                    <?= $addbutton ?>
                </div>            </div>
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
                                    <option value="">Description</option>
                                    <option value="">Duty</option>
                                    <option value="">Type</option>
                                    <option value="">Update</option>
                                    <option value="">Usage state</option>
                                    <option value="">Online</option>
                                    <option value="">Topup</option>
                                </select>
                                <button class="btn-ic btn-sort"></button>
                            </div>
                        </div>
                        <table>
                            <colgroup>
                                <col style="width: 5%;">
                                <col style="width: 10%;">
                                <col style="width: 15%;">
                                <col style="width: 5%;">
                                <col style="width: 10%;">
                                <col style="width: 10%;">
                                <col style="width: 20%;">
                                <col style="width: 10%;">
                                <!--col style="width: 15%;"-->
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
                                <th>Description<button class="btn-ic btn-sort"></button></th>
                                <th>Duty<button class="btn-ic btn-sort"></button></th>
                                <th>Type<button class="btn-ic btn-sort"></button></th>
                                <th>Update<button class="btn-ic btn-sort"></button></th>
                                <th>Usage state<button class="btn-ic btn-sort"></button></th>
                                <th>Online<button class="btn-ic btn-sort"></button></th>
                                <!--<th><button class="btn-ic btn-sort"></button></th>-->
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
<form name="modifyusers" id='modifyusers' method="post" action="/crew_account.php">
    <div class="popup layer pop-modify-manage">
        <div class="pop-head">
            <p class="title">Modify Voucher</p>
        </div>
        <div class="pop-cont">
            <div class="form">
                <div class="form-tit">
                    <p class="tit">Data limit (Mbytes)</p>
                </div>
                <div class="form-cont">
                    <!--input type="text" name="datalimit" id="datalimit"-->
                    <input
                            type="text"
                            name="datalimit"
                            id="datalimit"
                            inputmode="numeric"
                            autocomplete="off"
                            pattern="[0-9]*"
                            aria-label="Data limit (Mbytes)"
                    >
                </div>
            </div>
            <div class="form">
                <div class="form-tit">
                    <br>
                    <p class="tit">Time limit (Time minutes)</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="timelimit" id="timelimit"
                           placeholder="Time based limit, NOT IMPLEMENTED YET"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">
                </div>
            </div>
            <div class="form mt20">
                <div class="form-tit">
                    <p class="tit">Data speed (Kbps)</p>
                </div>

                <div class="form-cont" style="display:flex; gap:10px;">
                    <input type="text" name="downspeed" id="downspeed" style="width:100%;"
                           placeholder="Download Kbps, Experimental"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">

                    <input type="text" name="upspeed" id="upspeed" style="width:100%;"
                           placeholder="Upload Kbps, Experimental"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">

                </div>
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
                        <option value="half-Monthly">Half-Monthly</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Daily">Daily</option>
                        <option value="Forever">one-time</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="pop-foot">
            <button type='button' class="btn md fill-mint" onclick="submit_modifyusers()"><i class="ic-submit"></i>APPLY</button>
            <button type='button' class="btn md fill-dark" onclick="popClose('pop-modify-manage')"><i class="ic-cancel"></i>CANCEL</button>
        </div>
    </div>
</form>
<form name="registerusers" id='registerusers' method="post" action="/crew_account.php">
    <div class="popup layer pop-set-manage">
        <div class="pop-head">
            <p class="title">Create Voucher</p>
        </div>
        <div class="pop-cont">
            <div class="form">
                <div class="form-tit">
                    <p class="tit">Allow data (MB)</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="dataamount" id="dataamount"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">                </div>
            </div>
            <div class="form mt20">
                <div class="form-tit">
                    <p class="tit"># of Vouchers</p>
                </div>
                <div class="form-cont">
                    <input type="text" name="vouchernumber" id="vouchernumber"
                           inputmode="numeric" autocomplete="off" pattern="[0-9]*">                </div>
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
                        <option value="half-Monthly">Half-Monthly</option>
                        <option value="Weekly">Weekly</option>
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
<form name="crewscheduler" id="crewscheduler" method="post" action="/crew_account.php">
    <input type="hidden" name="userid" id="userIdHidden">

    <div class="popup layer pop-set-scheduler"
         style="width:720px; max-width:90%; left:50%; transform:translateX(-50%);">
        <div class="pop-head">
            <p class="title">Suspension Setup</p>
        </div>

        <div id="content">
            <div class="contents" style="padding:15px 20px;">
                <div class="container">
                    <div class="manage-wrap">
                        <div class="list-wrap v1">

                            <div class="sort-area">
                                <div class="inner">
                                    <select class="select v1">
                                        <option value="">Act</option>
                                        <option value="">From Hour</option>
                                        <option value="">Minute</option>
                                        <option value="">To Hour</option>
                                        <option value="">Minute</option>
                                        <option value="">Day</option>
                                    </select>
                                    <button class="btn-ic btn-sort"></button>
                                </div>
                            </div>

                            <table id="scheduleTable"
                                   style="width:100%; table-layout:fixed; border-collapse:collapse;">
                                <colgroup>
                                    <col style="width: 50px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 200px;">
                                </colgroup>

                                <thead>
                                <tr>
                                    <th style="text-align:center; padding:4px 6px;">Act</th>
                                    <th style="text-align:center; padding:4px 6px;">From Hour</th>
                                    <th style="text-align:center; padding:4px 6px;">From Min</th>
                                    <th style="text-align:center; padding:4px 6px;">To Hour</th>
                                    <th style="text-align:center; padding:4px 6px;">To Min</th>
                                    <th style="text-align:center; padding:4px 6px;">Day</th>
                                </tr>
                                </thead>

                                <tbody id="sched-body"></tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pop-foot" style="text-align:center; padding:10px 0;">
            <button type="button" class="btn md fill-mint" onclick="submit_crewscheduler()"
                    style="min-width:120px; margin:0 4px;">
                <i class="ic-submit"></i>APPLY
            </button>
            <button type="button" class="btn md fill-dark" onclick="popClose('pop-set-scheduler')"
                    style="min-width:120px; margin:0 4px;">
                <i class="ic-cancel"></i>CANCEL
            </button>
        </div>
    </div>
</form>

</body>
<script type="text/javascript">
    function confirm_exportCsv() {
       window.location.href = "crew_account.php?export=csv";
    }
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
    function submit_modifyusers() {
        if (!confirm("Selected users are being set this configure, OK to continue.")) return;

        // 1) modifyusers 폼 데이터 → __csrf_magic 포함됨
        let data = $("#modifyusers").serialize();   // 여기 안에 __csrf_magic 있어야 함

        // 2) PHP에서 트리거로 쓰는 플래그 이름은 modifyusers (s 붙음)
        data += "&modifyusers=true";

        // 3) 체크된 userlist 추가
        let userlist = $('input[name="userlist[]"]:checked')
            .map(function () { return $(this).val(); })
            .get();

        for (let i = 0; i < userlist.length; i++) {
            data += "&userlist[]=" + encodeURIComponent(userlist[i]);
        }

        // (선택) modifydata 로 폼 내용 통째로 넘기고 싶으면:
        data += "&modifydata=" + encodeURIComponent($("#modifyusers").serialize());

        $.ajax({
            url: "crew_account.php",   // 스킴/호스트 동일하게, ./ 도 가능
            type: "POST",
            data: data,                // ★ 그냥 문자열
            // processData / contentType 기본값 유지 (건들지 말기)
            success: function (result) {
                location.replace("crew_account.php");
            }
        });

        popClose('pop-modify-manage');
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
        var pwlist = "<?php echo check_wifi_account_password(); ?>".split("|||");
        var idlist = "<?php echo check_wifi_account_id(); ?>".split("|||");
        var result="";
        var resultlist = document.getElementsByName('userlist[]');
        for(let idcount=0; idcount<resultlist.length; idcount++){
            if(resultlist[idcount].checked){
                for(let idlistcount=0; idlistcount<idlist.length; idlistcount++){
                    if(resultlist[idcount].value===idlist[idlistcount]){
                        result += "\n" + resultlist[idcount].value + " : " + pwlist[idlistcount]+"<br>";
                    }
                }
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

        (function () {
        function bindPositiveIntOnly(id, allowEmpty) {
            const el = document.getElementById(id);
            if (!el) return;

            el.addEventListener('input', function () {
                let v = el.value;
                v = v.replace(/\D+/g, '');  // 숫자만 남김
                v = v.replace(/^0+/, '');   // 선행 0 제거 -> 0 방지
                el.value = v;
            });

            el.form?.addEventListener('submit', function (e) {
                const v = el.value.trim();
                if (allowEmpty) {
                    if (v && !/^[1-9]\d*$/.test(v)) {
                        e.preventDefault();
                        alert(id + '는 1 이상의 정수만 입력 가능합니다.');
                        el.focus();
                    }
                } else {
                    if (!/^[1-9]\d*$/.test(v)) {
                        e.preventDefault();
                        alert(id + '는 1 이상의 정수만 입력 가능합니다.');
                        el.focus();
                    }
                }
            });
        }

        // 필요에 맞게 allowEmpty 조절 가능
        bindPositiveIntOnly('datalimit', false);
        bindPositiveIntOnly('timelimit', true);   // 미구현이면 비워두기 허용 추천
        bindPositiveIntOnly('downspeed', true);   // 실험 기능이면 비워두기 허용 추천
        bindPositiveIntOnly('upspeed', true);
        bindPositiveIntOnly('dataamount', false);
        bindPositiveIntOnly('vouchernumber', false);
    })();

</script>
</html>