<?php
require_once('guiconfig.inc');
include_once("auth.inc");
include_once("common_ui.inc");
include_once("terminal_status.inc");
include_once("manage_crew_wifi_account.inc");

global $adminlogin;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    export_wifi_csv("non-Prepaid");
}
// 비밀번호 평문이 포함되므로 일반 Export CSV 와 달리 admin/vesseladmin 만 허용
// (버튼도 이 역할에서만 노출 — customer 가 URL 직접 접근으로 우회하는 것을 차단).
if (isset($_GET['export']) && $_GET['export'] === 'creds'
    && ($adminlogin === 'admin' || $adminlogin === 'vesseladmin')) {
    export_wifi_credentials_csv("non-Prepaid");
}

$controldisplay="";
$addbutton="";
if($adminlogin==="admin"||$adminlogin==="vesseladmin") {
    $controldisplay = '<button class="btn md line-gray" onclick="confirm_exportCsv()"><i class="ic-reset gray"></i>Export CSV</button>
                       <button class="btn md line-gray" onclick="confirm_exportCredsCsv()"><i class="ic-reset gray"></i>Export Credentials CSV</button>
                       <button class="btn md line-gray" onclick="confirm_resetPw()"><i class="ic-reset gray"></i>Reset PW</button>
                       <button class="btn md line-gray" onclick="confirm_setRandomPw()"><i class="ic-reset gray"></i>SET RANDOM PW</button>
                       <button class="btn md line-gray" onclick="confirm_resetData()"><i class="ic-reset gray"></i>Reset Data</button>
                            <button class="btn md line-gray" onclick="confirm_checkPw()"><i class="ic-check gray"></i>Check PW</button>
                            <button class="btn md line-gray" onclick="confirm_delUser()"><i class="ic-delete gray"></i>Delete</button></>';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['userid'], $_POST['schedule_json'])) {
    $userid = trim((string)($_POST['userid'] ?? ''));

    if ($userid !== '') {
        $decoded = json_decode((string)$_POST['schedule_json'], true);

        if (is_array($decoded)) {
            $schedulePost = [];

            $rowIndex = 0;

            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if (!array_key_exists('from_hour', $row) && !array_key_exists('from', $row)) {
                    continue;
                }

                if ($rowIndex >= 3) {
                    break;
                }

                $active = !empty($row['active']);

                if ($active) {
                    $schedulePost['act_' . $rowIndex] = 'on';
                }

                /*
                 * 현재 schedule_json이 from_hour/from_min 형태인 경우
                 */
                if (isset($row['from_hour'], $row['from_min'], $row['to_hour'], $row['to_min'])) {
                    $schedulePost['from_hour_' . $rowIndex] = $row['from_hour'];
                    $schedulePost['from_min_' . $rowIndex]  = $row['from_min'];
                    $schedulePost['to_hour_' . $rowIndex]   = $row['to_hour'];
                    $schedulePost['to_min_' . $rowIndex]    = $row['to_min'];
                }
                /*
                 * 혹시 from:"00:30", to:"13:00" 형태인 경우도 처리
                 */
                else {
                    $from = explode(':', (string)($row['from'] ?? '00:00'));
                    $to   = explode(':', (string)($row['to'] ?? '00:00'));

                    $schedulePost['from_hour_' . $rowIndex] = $from[0] ?? '00';
                    $schedulePost['from_min_' . $rowIndex]  = $from[1] ?? '00';
                    $schedulePost['to_hour_' . $rowIndex]   = $to[0] ?? '00';
                    $schedulePost['to_min_' . $rowIndex]    = $to[1] ?? '00';
                }

                /*
                 * 핵심:
                 * 여기서 day_0을 배열로 강제 구성
                 */
                $days = $row['days'] ?? [];

                if (!is_array($days)) {
                    $days = [$days];
                }

                $schedulePost['day_' . $rowIndex] = array_values($days);

                $rowIndex++;
            }

            /*
             * 3줄 미만이면 기본값 채움
             */
            for ($i = $rowIndex; $i < 3; $i++) {
                $schedulePost['from_hour_' . $i] = '00';
                $schedulePost['from_min_' . $i]  = '00';
                $schedulePost['to_hour_' . $i]   = '12';
                $schedulePost['to_min_' . $i]    = '00';
                $schedulePost['day_' . $i]       = [];
            }

            set_scheduler($userid, $schedulePost);

            echo '<script> location.replace("processing.php?to=crew_account.php");</script>';
            exit;
        }
    }
}


if (isset($_POST['description']) && !empty($_POST['userid'])) {
    $description=$_POST['description'];
    $userid=$_POST['userid'];
    set_description($userid, $description);
    echo '<script> location.replace("processing.php?to=crew_account.php");</script>';
}
//print_r($_POST);

$table_contents = draw_wifi_contents("non-Prepaid");
$gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);
$terminaltypeoption='<option value="">Auto</option>';
foreach ($gateways as $gname => $gateway){
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
if(isset($_POST['setrandompw'])){ reset_random_wifi_user_pw($_POST['userlist']); exit(0);}
if(isset($_POST['resetdata'])){reset_wifi_user($_POST['userlist']);exit(0);}
if(isset($_POST['deluser'])){del_wifi_user($_POST['userlist']);exit(0);}
if ($_POST['dataamount']){
    create_wifi_user($_POST['dataamount'], $_POST['vouchernumber'], $_POST['randpwd'], $_POST['terminaltype'], $_POST['timeperiod'], $_POST['issimplefied']);
    echo '<script> location.replace("processing.php?to=crew_account.php");</script>';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <?php echo print_css_n_head();?>
    <style>
        .sched-popup {
            width: 780px;
            max-width: 95vw;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.2);
            overflow: hidden;
            position: fixed;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }

        .sched-popup .pop-head {
            background: #2b3035;
            color: #fff;
            padding: 16px 24px;
            font-size: 17px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sched-popup .pop-head .title {
            margin: 0;
            color: #fff;
            font-size: 17px;
            font-weight: 600;
        }

        .sched-close {
            background: none;
            border: none;
            color: #adb5bd;
            font-size: 22px;
            cursor: pointer;
            line-height: 1;
        }

        .sched-close:hover {
            color: #fff;
        }

        .sched-modal-body {
            padding: 20px 24px;
            overflow-x: auto;
        }

        .sched-setup-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .sched-setup-table th {
            background: #f1f3f5;
            color: #495057;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: center;
            border-bottom: 2px solid #dee2e6;
        }

        .sched-setup-table td {
            padding: 14px 10px;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f5;
        }

        .sched-setup-table tr:last-child td {
            border-bottom: none;
        }

        .sched-setup-table tr:hover {
            background: #f8f9fa;
        }

        .sched-row-num {
            display: inline-block;
            width: 22px;
            height: 22px;
            line-height: 22px;
            background: #e9ecef;
            color: #6c757d;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
        }

        .sched-act-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #2ecc71;
            cursor: pointer;
        }

        .sched-time-group {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .sched-time-select {
            border: 1.5px solid #dee2e6;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 14px;
            font-weight: 500;
            color: #343a40;
            width: 68px;
            text-align: center;
            cursor: pointer;
            background: #fff;
        }

        .sched-time-select:focus {
            outline: none;
            border-color: #2ecc71;
            box-shadow: 0 0 0 2px rgba(46,204,113,0.15);
        }

        .sched-time-colon {
            font-weight: 700;
            color: #6c757d;
            font-size: 15px;
        }

        .sched-arrow-cell {
            color: #adb5bd;
            font-size: 18px;
        }

        .sched-day-chips {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 4px;
            justify-content: center;
            max-width: 210px;
        }

        .sched-day-chip input {
            display: none;
        }

        .sched-day-chip label {
            display: block;
            padding: 3px 9px;
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            background: #f1f3f5;
            border: 1.5px solid transparent;
            border-radius: 5px;
            cursor: pointer;
            user-select: none;
            line-height: 1.4;
        }

        .sched-day-chip label:hover {
            background: #e9ecef;
            color: #495057;
        }

        .sched-day-chip input:checked + label {
            background: #d5f5e3;
            color: #27ae60;
            border-color: #2ecc71;
        }

        .sched-modal-footer {
            padding: 14px 24px 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
            border-top: 1px solid #dee2e6;
        }

        .sched-modal-footer .btn {
            padding: 9px 24px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.3px;
        }

        .sched-modal-footer .fill-mint {
            background: #2ecc71;
            color: #fff;
        }

        .sched-modal-footer .fill-mint:hover {
            background: #27ae60;
        }

        .sched-modal-footer .fill-dark {
            background: #e9ecef;
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .sched-modal-footer .fill-dark:hover {
            background: #dee2e6;
        }
        .sched-act-check {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;

            width: 18px !important;
            height: 18px !important;

            appearance: checkbox !important;
            -webkit-appearance: checkbox !important;

            accent-color: #2ecc71;
            cursor: pointer;
            position: static !important;
            margin: 0 !important;
        }

        .sched-time-select {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;

            width: 68px !important;
            height: 31px !important;

            border: 1.5px solid #dee2e6 !important;
            border-radius: 6px !important;

            padding: 4px 8px !important;
            background: #fff !important;
            color: #343a40 !important;

            font-size: 14px !important;
            font-weight: 500 !important;
            line-height: normal !important;
            text-align: center !important;

            appearance: auto !important;
            -webkit-appearance: menulist !important;
        }

        .sched-time-select option {
            color: #343a40 !important;
            background: #fff !important;
        }

        .sched-time-group {
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
        }

        .sched-time-colon {
            color: #6c757d !important;
            font-weight: 700 !important;
            font-size: 15px !important;
        }

        .sched-day {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;

            min-width: 34px !important;
            height: 24px !important;

            padding: 3px 8px !important;
            border: 1.5px solid transparent !important;
            border-radius: 5px !important;

            background: #f1f3f5 !important;
            color: #6c757d !important;

            font-size: 11px !important;
            font-weight: 600 !important;
            cursor: pointer !important;

            appearance: none !important;
            -webkit-appearance: none !important;
        }

        .sched-day-check {
            display: none !important;
        }

        .sched-day-check:checked + .sched-day {
            background: #d5f5e3 !important;
            color: #27ae60 !important;
            border-color: #2ecc71 !important;
        }
    </style>
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
                    <div class="list-top" style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:nowrap; margin-bottom:14px;">
                        <div class="search-area" style="display:flex; align-items:flex-end; justify-content:flex-start; flex:1 1 auto; min-width:0;">
                            <?php echo draw_wifi_userid_search_box(); ?>
                        </div>

                        <div class="btn-area" style="display:flex; align-items:center; justify-content:flex-end; gap:8px; flex:0 0 auto; flex-wrap:nowrap;">
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
                                <col style="width: 18%;">
                                <col style="width: 8%;">
                                <col style="width: 8%;">
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
                                <th>History</th>
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
                <input type="checkbox" name="randpwd" id="randpwd" value="true">
                <label for="randpwd">
                    <p>Generate random password?</p>
                </label>
                <br>
                <input type="checkbox" name="issimplefied" id="issimplefied" value="issimplefied">
                <label for="issimplefied">
                    <p>Create simplefied ID?</p>
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
    <input type="hidden" name="schedule_json" id="scheduleJsonHidden">

    <div class="popup layer pop-set-scheduler sched-popup">
        <div class="pop-head">
            <p class="title">Suspension Setup</p>
            <button type="button" class="sched-close" onclick="popClose('pop-set-scheduler')">×</button>
        </div>

        <div class="pop-cont sched-modal-body">
            <table class="sched-setup-table">
                <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th style="width:50px">ACT</th>
                    <th style="width:180px">FROM</th>
                    <th style="width:30px"></th>
                    <th style="width:180px">TO</th>
                    <th style="width:200px">DAY</th>
                </tr>
                </thead>
                <tbody id="sched-body"></tbody>
            </table>
        </div>

        <div class="pop-foot sched-modal-footer">
            <button type="button" class="btn md fill-mint" onclick="submit_crewscheduler()">APPLY</button>
            <button type="button" class="btn md fill-dark" onclick="popClose('pop-set-scheduler')">CANCEL</button>
        </div>
    </div>
</form>

<?php
// #50: per-user 계정 변경 이력 모달 (행별 History 버튼 → openAcctHistory).
//   버전섞임 가드: 헬퍼 미배포면 버튼만 있고 모달 없음(무해).
if (function_exists('render_account_history_modal')) {
    echo render_account_history_modal();
}
?>

</body>
<script type="text/javascript">
    function initCrewScheduler() {
        const tbody = document.getElementById('sched-body');

        if (!tbody) {
            console.error('sched-body not found');
            return;
        }

        /*
         * 일요일 0 ~ 토요일 6
         */
        const days = [
            {label: 'Sun', value: '0'},
            {label: 'Mon', value: '1'},
            {label: 'Tue', value: '2'},
            {label: 'Wed', value: '3'},
            {label: 'Thu', value: '4'},
            {label: 'Fri', value: '5'},
            {label: 'Sat', value: '6'}
        ];

        function buildOptions(max, step, selectedValue) {
            let html = '';

            step = step || 1;
            selectedValue = selectedValue || '00';

            for (let i = 0; i <= max; i += step) {
                const v = String(i).padStart(2, '0');
                const selected = v === selectedValue ? ' selected' : '';
                html += `<option value="${v}"${selected}>${v}</option>`;
            }

            return html;
        }

        function timeSelect(name, id, max, step, selectedValue) {
            return `
            <select class="sched-time-select" name="${name}" id="${id}">
                ${buildOptions(max, step, selectedValue)}
            </select>
        `;
        }

        function dayButtons(rowIndex) {
            return `
            <div class="sched-days">
                ${days.map(day => {
                const inputId = `day_${rowIndex}_${day.value}`;

                return `
                        <input type="checkbox"
                               class="sched-day-check"
                               name="day_${rowIndex}[]"
                               id="${inputId}"
                               value="${day.value}">

                        <label class="sched-day" for="${inputId}">
                            ${day.label}
                        </label>
                    `;
            }).join('')}
            </div>
        `;
        }

        let rowsHtml = '';

        /*
         * 중요:
         * PHP set_scheduler()가 for ($i = 0; $i < 3; $i++) 구조이므로
         * HTML name도 0,1,2로 맞춰야 함
         */
        for (let i = 0; i < 3; i++) {
            const displayNo = i + 1;
            const defaultToHour = i === 0 ? '23' : '12';

            rowsHtml += `
            <tr class="sched-row" data-row="${i}">
                <td>
                    <span class="sched-no-badge">${displayNo}</span>
                </td>

                <td>
                    <input type="checkbox"
                           class="sched-act-check"
                           name="act_${i}"
                           id="act_${i}"
                           value="1">
                </td>

                <td>
                    <div class="sched-time-group">
                        ${timeSelect(`from_hour_${i}`, `from_hour_${i}`, 23, 1, '00')}
                        <span class="sched-time-colon">:</span>
                        ${timeSelect(`from_min_${i}`, `from_min_${i}`, 59, 10, '00')}
                    </div>
                </td>

                <td>
                    <span class="sched-arrow">→</span>
                </td>

                <td>
                    <div class="sched-time-group">
                        ${timeSelect(`to_hour_${i}`, `to_hour_${i}`, 23, 1, defaultToHour)}
                        <span class="sched-time-colon">:</span>
                        ${timeSelect(`to_min_${i}`, `to_min_${i}`, 59, 10, '00')}
                    </div>
                </td>

                <td>
                    ${dayButtons(i)}
                </td>
            </tr>
        `;
        }
        tbody.innerHTML = rowsHtml;
    }

    function submit_crewscheduler() {
        var form = document.getElementById('crewscheduler');
        var rows = [];

        /*
         * 이전 submit 시 만들어진 hidden day 제거
         */
        var oldHiddenDays = form.querySelectorAll('.sched-day-post');
        for (var x = 0; x < oldHiddenDays.length; x++) {
            oldHiddenDays[x].remove();
        }

        for (var i = 0; i < 3; i++) {
            var actEl = document.querySelector('input[name="act_' + i + '"]');

            var fromHourEl = document.getElementById('from_hour_' + i);
            var fromMinEl  = document.getElementById('from_min_' + i);
            var toHourEl   = document.getElementById('to_hour_' + i);
            var toMinEl    = document.getElementById('to_min_' + i);

            if (!fromHourEl || !fromMinEl || !toHourEl || !toMinEl) {
                console.error('scheduler input missing row:', i);
                continue;
            }

            var days = [];

            /*
             * 1순위: 정상 checkbox 방식
             * name="day_0[]"
             */
            var dayEls = document.querySelectorAll('input[name="day_' + i + '[]"]:checked');

            for (var d = 0; d < dayEls.length; d++) {
                days.push(dayEls[d].value);
            }

            /*
             * 2순위: 혹시 아직 예전 방식 name="day_0" checkbox가 남아 있는 경우
             */
            var oldDayEls = document.querySelectorAll('input[name="day_' + i + '"]:checked');

            for (var od = 0; od < oldDayEls.length; od++) {
                if (days.indexOf(oldDayEls[od].value) === -1) {
                    days.push(oldDayEls[od].value);
                }
            }

            /*
             * 3순위: 버튼 active 방식이 남아 있는 경우
             */
            var activeDayBtns = document.querySelectorAll('.sched-day[data-row="' + i + '"].active');

            for (var b = 0; b < activeDayBtns.length; b++) {
                var dayValue = activeDayBtns[b].getAttribute('data-day');

                if (dayValue && days.indexOf(dayValue) === -1) {
                    days.push(dayValue);
                }
            }

            /*
             * 기존 day_0, day_0[] input은 모두 disabled 처리
             * 이유:
             * name="day_0"이 하나라도 남아 있으면 PHP에서 마지막 값만 받을 수 있음
             */
            var oldInputs = form.querySelectorAll('input[name="day_' + i + '"], input[name="day_' + i + '[]"]');

            for (var r = 0; r < oldInputs.length; r++) {
                oldInputs[r].disabled = true;
            }

            /*
             * PHP에서 배열로 받도록 hidden input 재생성
             * 핵심: name="day_0[]"
             */
            for (var h = 0; h < days.length; h++) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.className = 'sched-day-post';
                hidden.name = 'day_' + i + '[]';
                hidden.value = days[h];

                form.appendChild(hidden);
            }

            rows.push({
                active: actEl && actEl.checked ? 1 : 0,
                from_hour: fromHourEl.value,
                from_min: fromMinEl.value,
                to_hour: toHourEl.value,
                to_min: toMinEl.value,
                days: days
            });
        }

        document.getElementById('scheduleJsonHidden').value = JSON.stringify(rows);

        console.log('schedule_json:', document.getElementById('scheduleJsonHidden').value);

        form.submit();
    }
    function confirm_exportCsv() {
        window.location.href = "crew_account.php?export=csv";
    }
    function confirm_exportCredsCsv() {
        window.location.href = "crew_account.php?export=creds";
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
        location.replace("processing.php?to=crew_account.php");
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
            location.replace("processing.php?to=crew_account.php");
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
    function confirm_setRandomPw(){
        if(window.confirm('Selected users password would be set to random 6 digits, OK to continue.')){

            location.replace("processing.php?to=crew_account.php");
            // 2. 동시에 AJAX 실행 (백그라운드)
            $.ajax({
                url: "./crew_account.php",
                data: {
                    setrandompw: "true",
                    userlist: $('input[name="userlist[]"]:checked')
                        .map(function(){ return $(this).val(); }).get()
                },
                type: 'POST'
                // success 콜백 불필요 — processing.php가 알아서 리디렉션
            });
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
        bindPositiveIntOnly('downspeed', true);   // 실험 기능이면 비워두기 허용 추천
        bindPositiveIntOnly('upspeed', true);
        bindPositiveIntOnly('dataamount', false);
        bindPositiveIntOnly('vouchernumber', false);
    })();

</script>
</html>