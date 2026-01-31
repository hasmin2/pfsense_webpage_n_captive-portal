<?php
require_once ("globals.inc");
require_once ("captiveportal.inc");
require_once ('functions.inc');


function build_wifi_rows() {
    global $config, $cpzone;
    $cpzone = "crew";

    $radiususers = $config['installedpackages']['freeradius']['config'] ?? [];
    if (!is_array($radiususers)) $radiususers = [];

    // username => user map
    $usersByName = [];
    foreach ($radiususers as $u) {
        $uname = $u['varusersusername'] ?? '';
        if ($uname !== '') {
            $usersByName[$uname] = $u;
        }
    }

    $names = array_keys($usersByName);
    sort($names);

    // online map username => session id
    $cpdb = captiveportal_read_db();
    $onlineByUser = [];
    if (is_array($cpdb)) {
        foreach ($cpdb as $cpent) {
            if (isset($cpent[4], $cpent[5])) {
                $onlineByUser[$cpent[4]] = $cpent[5];
            }
        }
    }

    $rows = [];
    foreach ($names as $name) {
        $eachuser = $usersByName[$name];

        // 기존 필터
        if (!preg_match("/[a-z]*[0-9]{5}/", $name)) continue;


        $used_quota = (float)check_quota($name, $eachuser['varusersmaxtotaloctetstimerange']);
        $maxtotal   = (float)($eachuser['varusersmaxtotaloctets'] ?? 0);

        // 기존 조건 유지
        $pointoftime = strtolower((string)($eachuser['varuserspointoftime'] ?? ''));
        if ($used_quota > $maxtotal && $pointoftime === 'forever') continue;
        $descr = (string)($eachuser['varusersdescription'] ?? '');


        $terminaltype = ($eachuser['varusersterminaltype'] ?? '') === '' ? 'Auto' : $eachuser['varusersterminaltype'];
        $halftime = (string)($eachuser['varusershalftimeperiod'] ?? '');
        $accountmodified = (string)($eachuser['varusersmodified'] ?? '');
        $accountreset = (string)($eachuser['varusersresetquota'] ?? '');
        $timerangeperiod = $eachuser['varusersmaxtotaloctetstimerange'] ?? '';
        $timerange = strtolower((string)$timerangeperiod) === "forever" ? "one-time" : $timerangeperiod;

        // Scheduler JSON (onclick용으로 안전하게)
        $schedule = get_scheduler($name);
        $scheduleJson = htmlspecialchars(json_encode($schedule, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

        $sessionId = $onlineByUser[$name] ?? null;

        $rows[] = [
            // 공통
            'ID'            => $name,
            'Description'   => $descr,
            'Timerange'          => $timerange,
            'Type'        => $terminaltype,
            'Half'        => $halftime,
            'Modified'        => $accountmodified,
            'Reset'         => $accountreset,
            // CSV용 숫자
            'Used(MB)'      => $used_quota,
            'Quota(MB)'     => $maxtotal,
            'Online'        => $sessionId ? 'Y' : 'N',

            // UI용
            'ScheduleJson'  => $scheduleJson,
            'SessionId'     => $sessionId,
        ];
    }

    return $rows;
}

function export_wifi_csv() {
    $rows = build_wifi_rows();
    global $config;
    $vesselname = $config['system']['vesselinfo']['vesselname'];
    $filename = $vesselname . "_" . date("Ymd_His") . ".csv";

    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // 엑셀 한글 깨짐 방지 BOM
    echo "\xEF\xBB\xBF";

    $out = fopen("php://output", "w");

    // 헤더
    $headers = ['ID', 'Description', 'Type', 'Update', 'Used(MB)', 'Quota(MB)', 'Online'];
    fputcsv($out, $headers);

    $totalUsed  = 0.0;
    $totalQuota = 0.0;
    $totalCount = 0;

    foreach ($rows as $r) {
        $totalCount++;

        if($r['Half']===''){
            $halftime = '';
        }
        else{
            $halftime = $r['Half'].'-';
        }
        $timerange = $halftime.$r['Timerange'];
        if(isset($r['Reset'])) {
            $used=0.0;
        }else {
            $used = isset($r['Used(MB)']) ? (float)preg_replace('/[^0-9.\-]/', '', (string)$r['Used(MB)']) : 0.0;
        }
        $quota = isset($r['Quota(MB)']) ? (float)preg_replace('/[^0-9.\-]/', '', (string)$r['Quota(MB)']) : 0.0;
        $totalUsed  += $used;
        $totalQuota += $quota;

        fputcsv($out, [
            $r['ID'] ?? '',
            $r['Description'] ?? '',
            $r['Type'] ?? '',
            $timerange,
            $r['Used(MB)'] ?? '',
            $r['Quota(MB)'] ?? '',
            $r['Online'] ?? '',
        ]);
    }

    // 마지막 TOTAL 라인: ID 칸 = 총 카운트
    fputcsv($out, [
        $totalCount, // ID = 총 개수
        'TOTAL',     // Description = TOTAL (원치 않으면 ''로)
        '',          // Type
        '',          // Update
        $totalUsed,
        $totalQuota,
        '',          // Online
    ]);

    fclose($out);
    exit;
}

function draw_wifi_contents() {
    $rows = build_wifi_rows();
    $rtnstr = '';

    // order 파라미터 안전 처리
    $order = isset($_GET['order']) ? rawurlencode($_GET['order']) : '';

    foreach ($rows as $r) {
        $name  = htmlspecialchars($r['ID'], ENT_QUOTES, 'UTF-8');
        $descr = htmlspecialchars($r['Description'], ENT_QUOTES, 'UTF-8');

        $rtnstr .= '<tr>';
        $rtnstr .= '<td data-th="" data-th-width="0" data-width="100"><div class="check v1">';
        $rtnstr .= '<input type="checkbox" name="userlist[]" id="'.$name.'" value="'.$name.'">';
        $rtnstr .= '<label for="'.$name.'"></label></div></td>';
        $rtnstr .= '<td data-th="ID" data-th-width="100" data-width="100">'.$name.'</td>';
        $rtnstr .= '<td><form action="crew_account.php" method="post" style="display:flex; align-items:center; gap:10px; margin:0;">
          <input type="text" name="description" value="'.$descr.'" style="border:1px solid #ccc; outline:none; padding:5px;">
          <input type="hidden" name="userid" value="'.$name.'">
          <button type="submit" style="background:none; border:none; padding:0;">
            <img src="img/icon/ic_apply.png" alt="제출" style="width:24px; height:24px;">
          </button>
        </form></td>';

        $json = $r['ScheduleJson']; // 이미 htmlspecialchars 처리됨
        $rtnstr .= '<td data-th="Date create" data-th-width="100" data-width="100">
          <a onclick="popOpenScheduler(\'pop-set-scheduler\', true,'.$json.')">
            <img src="img/icon/ic_scheduler.png" alt="Scheduler" style="width:24px; height:24px; display:block; margin:10px auto 0px;">
          </a><br class="hide-mo">
        </td>';

        // Type / Update
        $rtnstr .= '<td data-th="Type" data-th-width="100" data-width="100">'.htmlspecialchars($r['Type'], ENT_QUOTES, 'UTF-8').'</td>';
        if($r['Half']===''){
            $halftime='';
        }
        else{
            $halftime = (string)$r['Half'].'-';
        }
        $rtnstr .= '<td data-th="Update" data-th-width="100" data-width="100">'.htmlspecialchars($halftime.$r['Timerange'], ENT_QUOTES, 'UTF-8').'</td>';


        $usageSubText = number_format((float)$r['Used(MB)'], 2, '.', ',');
        if($r['Modified']){
            $usageSubText = 'Update';
        }
        if($r['Reset']){
            $usageSubText = 'Init';
        }

        $usageText = $usageSubText . ' / ' . number_format((float)$r['Quota(MB)'], 0, '.', ',') . 'MB';
        $rtnstr .= '<td data-th="Usage state" data-th-width="100" data-width="100">'.htmlspecialchars($usageText, ENT_QUOTES, 'UTF-8').'</td>';

        // Online / Logout
        if (!empty($r['SessionId'])) {
            $sid = rawurlencode($r['SessionId']);
            $rtnstr .= '<td data-th="Online" data-th-width="100" data-width="100" class="txt-green">
              <a href="?'.$order.'act=del&amp;zone=crew&amp;id='.$sid.'">Logout</a>
            </td>';
        } else {
            $rtnstr .= '<td data-th="Online" data-th-width="100" data-width="100" class="txt-green"></td>';
        }

        $rtnstr .= '</tr>';
    }

    return $rtnstr;
}



function set_descruption($userid, $description){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        if(($userid === $userentry['varusersusername'])){
            $config["installedpackages"]["freeradius"]["config"][$item]['varusersdescription'] = $description;
        }
    }
    write_config('set '.$userid.' description');
}


function get_scheduler($user){
    global $config;
    //$result = ["id" => $user];

    $rows[]=["id"=> $user];
    foreach ($config["schedules"]["schedule"] as $eachSchedule) {
        if($eachSchedule['name']===$user){
            foreach ($eachSchedule['timerange'] as $schedule) {
                $fromto = explode("-", $schedule['hour']);
                $rows[]=["enabled"=>$schedule['rangedescr']==='TRUE' ? true:false,  "from"=>$fromto[0], "to"=>$fromto[1], "days"=>explode(',', $schedule['position'])];
            }
        }
    }
    while (count($rows) < 4) {
        $rows[] = [
            "enabled" => false,
            "from"    => "00:00",
            "to"      => "12:00",
            "days"    => [1, 2, 3, 4, 5, 6, 7]
        ];
    }
    $result = json_encode($rows, JSON_UNESCAPED_UNICODE);
    return $result;
}

function set_scheduler($userid, $schedule) {
    global $config;
    $schdIndex = -1;
    if(!isset($config["schedules"]["schedule"])){
        $config["schedules"]["schedule"]=[];
    }
    foreach ($config["schedules"]["schedule"] as $index => $eachSchedule) {
        if ($eachSchedule['name']===$userid) {
            $schdIndex = $index;
        }
    }
    $scheduledItemList = ['name'=> $userid, 'descr'=>'', 'timerange'=>[]];

    foreach ($schedule as $item) {
        $enabled="FALSE";
        if($item['enabled']===true) {
            $enabled="TRUE";
        }
        $timerange = ["rangedescr"=> $enabled, "hour" => $item['from']."-".$item['to'], 'position'=>implode(",", $item['days'])];
        $scheduledItemList['timerange'][] = $timerange;
    }

    if ($schdIndex >= 0) {
        $config["schedules"]["schedule"][$schdIndex]=$scheduledItemList;
    }
    else{
        $config["schedules"]["schedule"][]=$scheduledItemList;
    }
    write_config('Update/write '.$userid.' schedule');
}

function check_wifi_account_password(){
    global $config;
    $passwordlist = array();
    foreach ($config['installedpackages']['freeradius']['config'] as $eachuser) {
        array_push($passwordlist, $eachuser['varuserspassword']);
    }
    return implode("|||", $passwordlist);
}
function check_wifi_account_id(){
    global $config;
    $passwordlist = array();
    foreach ($config['installedpackages']['freeradius']['config'] as $eachuser) {
        array_push($passwordlist, $eachuser['varusersusername']);
    }
    return implode("|||", $passwordlist);
}
function del_wifi_user($userlist){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user){
            if ($user === $userentry['varusersusername']) {
                unset($config["installedpackages"]["freeradius"]["config"][$item]);  // flag for remove DB for when anyone who is in site is open webpage.
                unlink_if_exists("/var/log/radacct/datacounter/{$userentry['varusersmaxtotaloctetstimerange']}/used-octets-$user*");
                captiveportal_syslog("Deleted user".$user);
            }
        }
    }
    freeradius_users_resync();
    write_config("Deleted Wifi user");
}
function modify_wifi_user($userlist, $modifydata){
    global $config;
    captiveportal_syslog("modifydata:::".$modifydata['datalimit']);
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
            if ($user === $userentry['varusersusername']) {
                $halftimeperiod=explode('-',$modifydata['timeperiod']);
                if(count($halftimeperiod)==2) {
                    $varusershalftimeperiod=$halftimeperiod[0];
                    $varuserstimeperiod=$halftimeperiod[1];
                }
                else{
                    $varusershalftimeperiod="";
                    $varuserstimeperiod=$halftimeperiod[0];
                }
                if($modifydata['datalimit']!='') {
                    $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctets']=$modifydata['datalimit'];
                }
                if($modifydata['timeperiod']!='') {
                    //$config["installedpackages"]["freeradius"]["config"][$item]['varusersamountoftime']=$modifydata['timelimit'];
                }
                $config["installedpackages"]["freeradius"]["config"][$item]['varusershalftimeperiod']=$varusershalftimeperiod;
                $config["installedpackages"]["freeradius"]["config"][$item]['varuserspointoftime']=$varuserstimeperiod;
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctetstimerange']=strtolower($varuserstimeperiod);
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxbandwidthdown']=$modifydata['downspeed'];
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxbandwidthup']=$modifydata['upspeed'];
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersterminaltype']=$modifydata['terminaltype'];
                $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
                captiveportal_syslog("User setting changed".$userentry['varusersusername']);
            }
        }
    }
    freeradius_users_resync();
    write_config("Reset datausage Wifi user");
}
function reset_wifi_user($userlist){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
            if ($user === $userentry['varusersusername']) {
                $config['installedpackages']['freeradius']['config'][$item]['varusersresetquota'] = "true";
                $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
                captiveportal_syslog("Reset Datausage for".$userentry['varusersusername']);
            }
        }
    }
    freeradius_users_resync();
    write_config("Reset datausage Wifi user");
}
function reset_wifi_user_pw($userlist){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
            if ($user === $userentry['varusersusername']) {
                $config["installedpackages"]["freeradius"]["config"][$item]['varuserspassword'] = "1111";
                captiveportal_syslog("Reset password for".$userentry['varusersusername']);
            }
        }
    }
    freeradius_users_resync();
    write_config("Reset password Wifi user");
}
function create_wifi_user($dataamount, $vouchernumber, $israndompw, $terminaltype, $timeperiod){
    global $config;
    $userprefix=strtolower($terminaltype).'user';
    $userpostfix = 0;
    foreach($config['installedpackages']['freeradius']['config'] as $item){
        if(strpos($item['varusersusername'], $userprefix) !== false){
            $curpostfix = intval(substr($item['varusersusername'], -strlen($item['varusersusername'])+strlen($userprefix)));
            if($curpostfix > $userpostfix){ $userpostfix = $curpostfix; }
        }
    }
    $userpostfix++;
    for ($usercount=$userpostfix;$usercount<$userpostfix+$vouchernumber;$usercount++){
        if($vouchernumber>100){ break; }
        $username = $userprefix.str_pad($usercount, 5, '0', STR_PAD_LEFT);
        $userexist = false;
        foreach($config['installedpackages']['freeradius']['config'] as $item){
            if($username === $item['varusersusername']){ $userexist = true; break; }
        }
        if($userexist) { continue; }
        $curdate = date('Y/m/d H:i:s');;
        $halftimeperiod=explode('-',$timeperiod);
        if(count($halftimeperiod)==2) {
            $varusershalftimeperiod=$halftimeperiod[0];
            $varuserstimeperiod=$halftimeperiod[1];
        }
        else{
            $varusershalftimeperiod="";
            $varuserstimeperiod=$halftimeperiod[0];
        }

        $userinfoentry = array(
            "sortable"=>"",
            "varuserspasswordencryption"=>"Cleartext-Password",
            "varusersmotpenable"=>"",
            "varusersauthmethod"=>"motp",
            "varusersmotpinitsecret"=>"",
            "varusersmotppin"=>"",
            "varusersmotpoffset"=>"",
            "qrcodetext"=>"",
            "varuserswisprredirectionurl"=>"",
            "varuserssimultaneousconnect"=>"",
            "description"=>"",
            "varusersframedipaddress"=>"",
            "varusersframedipnetmask"=>"",
            "varusersframedroute"=>"",
            "varusersframedip6address"=>"",
            "varusersframedip6route"=>"",
            "varusersvlanid"=>"",
            "varusersexpiration"=>"",
            "varuserssessiontimeout"=>"",
            "varusershalftimeperiod"=>$varusershalftimeperiod,
            "varuserslogintime"=>"",
            "varusersamountoftime"=>"",
            "varuserspointoftime"=>$varuserstimeperiod,
            "varusersmaxtotaloctetstimerange"=> strtolower($varuserstimeperiod),
            "varusersmaxbandwidthdown"=>"",
            "varusersmaxbandwidthup"=>"",
            "varusersacctinteriminterval"=>"600",
            "varuserstopadditionaloptions"=>"",
            "varuserscheckitemsadditionaloptions"=>"",
            "varusersreplyitemsadditionaloptions"=>"",
            "varusersterminaltype"=>$terminaltype,
            "varusersresetquota"=>"true",
            "varusersmodified"=>"create",
            "varuserscreatedate"=>$curdate,
        );
        $userinfoentry['varusersusername']=$username;
        if($israndompw==="randpwd"){
            $alphabet = '1234567890';
            $pass = array(); //remember to declare $pass as an array
            $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
            for ($passwordchar = 0; $passwordchar < 6; $passwordchar++) {
                $n = rand(0, $alphaLength);
                $pass[] = $alphabet[$n];
            }
            $userinfoentry['varuserspassword']= implode($pass);
        }
        else{ $userinfoentry['varuserspassword']="1111"; }

        if(is_numeric($dataamount)){ $userinfoentry['varusersmaxtotaloctets']=$dataamount; }
        else{ $userinfoentry['varusersmaxtotaloctets']=0; }

        if(!isset($config['installedpackages']['freeradius']['config'])){
            $config["installedpackages"]["freeradius"]=["config"=>[""]];
            array_push($config["installedpackages"]["freeradius"]["config"][0], $userinfoentry);
        }
        else{ array_push($config["installedpackages"]["freeradius"]["config"], $userinfoentry); }
    }
    freeradius_users_resync();
    write_config("Created Wifi user");
}

?>
