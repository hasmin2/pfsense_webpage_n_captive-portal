<?php
require_once ("globals.inc");
require_once ("captiveportal.inc");
require_once ('functions.inc');

/*function draw_wifi_contents(){
    global $config, $cpzone;
    global $adminlogin;
    $cpzone="crew";
    $radiususers = &$config['installedpackages']['freeradius']['config'];
    $rtnstr='';
    $radiususeridsorted= array();
    foreach($radiususers as $eachuser){
        $radiususeridsorted[] = $eachuser['varusersusername'];
    }
    sort($radiususeridsorted);

    $cpdb = captiveportal_read_db();
    foreach($radiususeridsorted as $eachusersorted) {
        foreach ($radiususers as $eachuser) {
            $used_quota = check_quota($eachuser['varusersusername'], $eachuser['varusersmaxtotaloctetstimerange']);
            if (preg_match("/[a-z]*[0-9]{5}/", $eachuser['varusersusername']) && $eachuser['varusersusername'] === $eachusersorted) {
                if ($used_quota <= $eachuser['varusersmaxtotaloctets'] || strtolower($eachuser['varuserspointoftime']) !== 'forever') {
                    $rtnstr .= '<tr><td data-th="" data-th-width="0" data-width="100"><div class="check v1">';
                    $rtnstr .= '<input type="checkbox" name="userlist[]" id=' . $eachuser['varusersusername'] . ' value="' . $eachuser['varusersusername'] . '">';
                    $rtnstr .= '<label for="' . $eachuser['varusersusername'] . '"></label></div></td><td data-th="ID" data-th-width="100" data-width="100">';
                    $rtnstr .= $eachuser['varusersusername'];
                    //$rtnstr .= '<td><form id="" action="submit.php" method="post"><input type="text" id="nameInput" name="name" placeholder="이름을 입력하세요" /><button type="submit" id="realSubmit">submit</button></form></td>';
                    $descr = $eachuser['varusersdescription'];
                    $rtnstr  .='<td> <form action="crew_account.php" method="post" style="display:flex; align-items:center; gap:10px; margin:0;">      
      <input type="text" name="description" value="'.$descr.'"style="border:1px solid #ccc; outline:none; outline:none; padding:5px;">
      <input type="hidden" name="userid" value= "'.$eachuser['varusersusername'].'">
   <button type="submit" style="background:none; border:none; padding:0;">
      <img src="img/icon/ic_apply.png" alt="제출" style="width:24px; height:24px;"></form></td>';
                    //$rtnstr .= '</td><td data-th="Date create" data-th-width="100" data-width="100">' . $createdate . '<br class="hide-mo"></td>';
                    $schedule = get_scheduler($eachuser['varusersusername']);
                    $json = htmlspecialchars(json_encode($schedule, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    $rtnstr .= '</td><td data-th="Date create" data-th-width="100" data-width="100"><a onclick="popOpenScheduler(\'pop-set-scheduler\', true,'.$json.')"><img src="img/icon/ic_scheduler.png" alt="Scheduler" style="width:24px; height:24px; display:block; margin:10px auto 0px;"></a><br class="hide-mo"></td>';
                    $terminaltype = $eachuser['varusersterminaltype'] === '' ? 'Auto' : $eachuser['varusersterminaltype'];
                    $rtnstr .= '<td data-th="Type" data-th-width="100" data-width="100">' . $terminaltype . '</td>';
                    if(strtolower($eachuser['varusersmaxtotaloctetstimerange']) === "forever"){
                        $updateperiod='one-time';
                    }
                    else{
                        if($eachuser['varusershalftimeperiod']==""||!isset($eachuser['varusershalftimeperiod'])) {
                            $updateperiod = $eachuser['varusersmaxtotaloctetstimerange'];
                        }
                        else{
                            $updateperiod = $eachuser['varusershalftimeperiod'].'-'.$eachuser['varusersmaxtotaloctetstimerange'];
                        }
                    }

                    $rtnstr .= '<td data-th="Update" data-th-width="100" data-width="100">' . $updateperiod . '</td>';
                    $state = '';
                    if ($eachuser['varusersmodified'] == "update") {
                        $state = "Init / {$eachuser['varusersmaxtotaloctets']}MB";
                    } else {
                        $state .= number_format($used_quota, 2, '.', ',') . " / {$eachuser['varusersmaxtotaloctets']}MB";
                    }
                    $rtnstr .= '<td data-th="Usage state" data-th-width="100" data-width="100">' . $state . '</td>';
                    foreach ($cpdb as $cpent) {
                        if ($cpent[4] == $eachuser['varusersusername']) {
                            $isonline = '<a href="?' . $_GET['order'] . 'act=del&amp;zone=crew&amp;id=' . $cpent[5] . '">Logout</a>';
                            break;
                        } else {
                            $isonline = '';
                        }
                    }
                    $rtnstr .= '<td data-th="Online" data-th-width="100" data-width="100" class="txt-green">' . $isonline . '</td>';
                 //   $rtnstr .= '<td></td>';
                    $rtnstr .= '</tr>';
                }
            }
        }
    }
    return $rtnstr;
}*/

function draw_wifi_contents(){
    global $config, $cpzone, $adminlogin;
    $cpzone="crew";

    $radiususers = &$config['installedpackages']['freeradius']['config'];
    $rtnstr='';

    // 1) username => user 맵 만들기
    $usersByName = [];
    foreach ($radiususers as $u) {
        if (!empty($u['varusersusername'])) {
            $usersByName[$u['varusersusername']] = $u;
        }
    }

    $names = array_keys($usersByName);
    sort($names);

    $cpdb = captiveportal_read_db();
    $onlineByUser = [];
    foreach ($cpdb as $cpent) {
        if (isset($cpent[4], $cpent[5])) {
            $onlineByUser[$cpent[4]] = $cpent[5];
        }
    }

    foreach ($names as $name) {
        $eachuser = $usersByName[$name];

        // 기존 필터 동일
        if (!preg_match("/[a-z]*[0-9]{5}/", $name)) {
            continue;
        }

        $used_quota = check_quota($name, $eachuser['varusersmaxtotaloctetstimerange']);

        if ($used_quota > $eachuser['varusersmaxtotaloctets'] && strtolower($eachuser['varuserspointoftime']) === 'forever') {
            continue;
        }

        $rtnstr .= '<tr><td data-th="" data-th-width="0" data-width="100"><div class="check v1">';
        $rtnstr .= '<input type="checkbox" name="userlist[]" id=' . $name . ' value="' . $name . '">';
        $rtnstr .= '<label for="' . $name . '"></label></div></td><td data-th="ID" data-th-width="100" data-width="100">';
        $rtnstr .= $name;

        $descr = $eachuser['varusersdescription'];
        $rtnstr  .= '<td><form action="crew_account.php" method="post" style="display:flex; align-items:center; gap:10px; margin:0;">
          <input type="text" name="description" value="'.$descr.'" style="border:1px solid #ccc; outline:none; padding:5px;">
          <input type="hidden" name="userid" value="'.$name.'">
          <button type="submit" style="background:none; border:none; padding:0;">
            <img src="img/icon/ic_apply.png" alt="제출" style="width:24px; height:24px;">
          </button>
        </form></td>';

        $schedule = get_scheduler($name);
        $json = htmlspecialchars(json_encode($schedule, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $rtnstr .= '<td data-th="Date create" data-th-width="100" data-width="100"><a onclick="popOpenScheduler(\'pop-set-scheduler\', true,'.$json.')"><img src="img/icon/ic_scheduler.png" alt="Scheduler" style="width:24px; height:24px; display:block; margin:10px auto 0px;"></a><br class="hide-mo"></td>';

        $updateperiod = $eachuser['varusersterminaltype'] === '' ? 'Auto' : $eachuser['varusersterminaltype'];
        $rtnstr .= '<td data-th="Type" data-th-width="100" data-width="100">' . $updateperiod . '</td>';
        if($eachuser['varusershalftimeperiod']=='') {
            $updateperiodname= $eachuser['varusersmaxtotaloctetstimerange'];
        }
        else{
            $updateperiodname = $eachuser['varusershalftimeperiod'].'-'.$eachuser['varusersmaxtotaloctetstimerange'];
        }
        $terminaltype = strtolower($eachuser['varusersmaxtotaloctetstimerange']) === "forever" ? "one-time" : $updateperiodname;
        $rtnstr .= '<td data-th="Update" data-th-width="100" data-width="100">' . $terminaltype . '</td>';

        if ($eachuser['varusersmodified'] == "update" && $eachuser['varusersresetquota']==="true") {
            $state = "Init / {$eachuser['varusersmaxtotaloctets']}MB";
        } else if ($eachuser['varusersmodified'] == "update") {
            $state = "Update / {$eachuser['varusersmaxtotaloctets']}MB";
        } else {
            $state = number_format($used_quota, 2, '.', ',') . " / {$eachuser['varusersmaxtotaloctets']}MB";
        }
        $rtnstr .= '<td data-th="Usage state" data-th-width="100" data-width="100">' . $state . '</td>';

        $isonline = isset($onlineByUser[$name])
            ? '<a href="?' . $_GET['order'] . 'act=del&amp;zone=crew&amp;id=' . $onlineByUser[$name] . '">Logout</a>'
            : '';
        $rtnstr .= '<td data-th="Online" data-th-width="100" data-width="100" class="txt-green">' . $isonline . '</td>';

        $rtnstr .= '<td></td></tr>';
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
