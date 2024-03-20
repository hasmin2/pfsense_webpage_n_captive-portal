<?php
/*
 * gateways.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Seth Mos
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("freeradius.inc");
require_once ("captiveportal.inc");
require_once ("auth.inc");
require_once("/usr/local/www/widgets/include/manage_freeradiususer.inc");
$passwordlist;

if (!function_exists('compose_manage_freeradiususer_contents')) {
	function compose_manage_freeradiususer_contents($widgetkey) {
		global $config;
		if(isset($config['installedpackages']['freeradius']['config'])){
			$radiususers = &$config['installedpackages']['freeradius']['config'];
			foreach ($radiususers as $eachuser) {
                if(preg_match("/[a-z]*[0-9]{5}/", $eachuser['varusersusername'])){
                    $rtnstr .= "<tr>";
                    $rtnstr .= "<td><center><input type=checkbox class=userlist id={$eachuser['varusersusername']} name=userlist[] value={$eachuser['varusersusername']} /></center></td>";
                    $rtnstr .= "<td><center>{$eachuser['varusersusername']}</center></td>";
                    $terminaltype = $eachuser['varusersterminaltype']=='' ?  'Auto' : $eachuser['varusersterminaltype'];
                    $rtnstr .= "<td><center>".$terminaltype ."</center></td>";
                    $usertimeperiod = $eachuser['varuserspointoftime'] == "forever" ? "one-time":$eachuser['varuserspointoftime'];
                    $rtnstr .="<td><center>{$usertimeperiod}</center></td>";
                    $rtnstr .="<td><center>{$eachuser['varusersmaxtotaloctets']}&nbsp;MBytes</center></td>";
                    $used_quota=check_quota($eachuser['varusersusername'], $eachuser['varusersmaxtotaloctetstimerange']);
                    if($eachuser['varusersmodified']=="update"){$rtnstr .= "<td><center>Wait for logon</center></td>";}
                    else{$rtnstr .="<td><center>".number_format($used_quota,2,'.',',')." MBytes</center></td>";}
                    $cpdb = captiveportal_read_db();
                    if(count ($cpdb) == 0){
                        $rtnstr .= "<td><a></a></td>";
                    }
                    else {
                        $rtnstr .= "<td>";
                        foreach ($cpdb as $cpent) {
                            $eachuser['varusersusername'] === $cpent[4] ? $rtnstr .= "<center><font color='#adff2f'>Login</center>" : $rtnstr .= "<a></a>";
                        }
                        $rtnstr .= "</td>";
                    }
                    $widgetkey_html = htmlspecialchars($widgetkey);
                }
			}
		}
		return($rtnstr);
	}
}
if ($_REQUEST && $_REQUEST['ajax']) {
	print(compose_manage_freeradiususer_contents($_REQUEST['widgetkey']));
	exit;
}

if ($_POST['widgetkey']) {//???????????
	global $config;
	$userlist = $_POST['userlist'];
	if($_POST['deluser']){
		foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
			foreach ($userlist as $user){
				if ($user === $userentry['varusersusername']) {
					unset($config["installedpackages"]["freeradius"]["config"][$item]);  // flag for remove DB for when anyone who is in site is open webpage.
					unlink_if_exists("/var/log/radacct/datacounter/{$userentry['varusersmaxtotaloctetstimerange']}/used-octets-{$_POST['delusername']}*");
					captiveportal_syslog("Deleted user".$user);
				}
			}
		}
	}
	else if($_POST['resetuser']){
		foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
			foreach ($userlist as $user) {
				if ($user === $userentry['varusersusername']) {
					$config['installedpackages']['freeradius']['config'][$item]['varusersresetquota'] = "true";
					$config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
					captiveportal_syslog("Reset Datausage for".$userentry['varusersusername']);
				}
			}
		}
	}
	else if($_POST['resetpw']){
		foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
			foreach ($userlist as $user) {
				if ($user === $userentry['varusersusername']) {
					$config["installedpackages"]["freeradius"]["config"][$item]['varuserspassword'] = "1111";
					captiveportal_syslog("Reset password for".$userentry['varusersusername']);
				}
			}
		}
		freeradius_users_resync();
	}
	else if($_POST['createusernumber'] && $_POST['createuserquota']){
		$vouchernumber = $_POST['createusernumber'];
		$terminaltype= "";
		if($_POST['createuserterminaltype'] != ""){
			/*foreach ($config['interfaces'] as $gwname => $gwitem){
				if(is_array($gwitem) && $gwname == $_POST['createuserterminaltype']) {
					$terminaltype=$gwitem['descr'];
					break;
				}
			}*/
            foreach ($config['gateways']['gateway_item'] as $key => $gwitem){
                if(is_array($gwitem) && $gwitem['name'] == $_POST['createuserterminaltype']) {
                    $terminaltype=$gwitem['name'];
                    break;
                }
            }
		}
		$userprefix=strtolower($terminaltype).'user';
		$userpostfix = 0;
		$usercount = count();
		foreach($config['installedpackages']['freeradius']['config'] as $item){
			if(strpos($item['varusersusername'], $userprefix) !== false){
				$curpostfix = intval(substr($item['varusersusername'], -strlen($item['varusersusername'])+strlen($userprefix)));
				if($curpostfix > $userpostfix){
					$userpostfix = $curpostfix;
				}
			}
		}
		$userpostfix++;
		for ($i=$userpostfix;$i<$userpostfix+$vouchernumber;$i++){
			$username = $userprefix.str_pad($i, 5, '0', STR_PAD_LEFT);
			$userexist = false;
			foreach($config['installedpackages']['freeradius']['config'] as $item){
				if($username === $item['varusersusername']){
					$userexist = true;
					break;
				}
			}
			if($userexist){
				header("Location: /");
				continue;
			}
			$userinfoentry = array(
				"sortable"=>"",
				"varusersusername"=>"",
				"varuserspassword"=>"",
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
				"varuserslogintime"=>"",
				"varusersamountoftime"=>"",
				"varuserspointoftime"=>"Monthly",
				"varusersmaxtotaloctets"=>"1",
				"varusersmaxtotaloctetstimerange"=>"monthly",
				"varusersmaxbandwidthdown"=>"",
				"varusersmaxbandwidthup"=>"",
				"varusersacctinteriminterval"=>"600",
				"varuserstopadditionaloptions"=>"",
				"varuserscheckitemsadditionaloptions"=>"",
				"varusersreplyitemsadditionaloptions"=>"",
				"varuserslastreceivedata"=>0,
				"varuserslastsentdata"=>0,
				"varuserslastbasedata"=>0,
				"varusersterminaltype"=>"",
				"varusersresetquota"=>"true",
			);
			$userinfoentry['varusersusername']=$username;
            if($_POST['createsuerquotaperiod']==="forever"){
                $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
                $pass = array(); //remember to declare $pass as an array
                $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
                for ($i = 0; $i < 8; $i++) {
                    $n = rand(0, $alphaLength);
                    $pass[] = $alphabet[$n];
                }
                $userinfoentry['varuserspassword']= implode($pass);
            }
            else{
                $userinfoentry['varuserspassword']="1111";
            }
			if(is_numeric($_POST['createuserquota'])){
				$userinfoentry['varusersmaxtotaloctets']=$_POST['createuserquota'];
			}
			else{
				$userinfoentry['varusersmaxtotaloctets']=0;
			}
			$userinfoentry['varusersterminaltype']=$terminaltype;
			$userinfoentry['varusersmaxtotaloctetstimerange']=$_POST['createsuerquotaperiod'];
			$userinfoentry['varuserspointoftime']=$_POST['createsuerquotaperiod'];
			$userinfoentry['varusersmodified']='create';
			if(!isset($config['installedpackages']['freeradius']['config'])){
				$config["installedpackages"]["freeradius"]=["config"=>[""]];
				array_push($config["installedpackages"]["freeradius"]["config"][0]=$userinfoentry);
			}
			else{
				array_push($config["installedpackages"]["freeradius"]["config"], $userinfoentry);
			}
		}
	}
	write_config("Modifed freeradius user");
	header("Location: /");
	exit(0);
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);

?>
<style>

	.btn-square-little-rich {
		position: relative;
		display: inline-block;
		padding: 0.25em 0.5em;
		text-decoration: none;
		color: #FFF;
		background: #03A9F4;/*??*/
		border: solid 1px #0f9ada;/*????*/
		border-radius: 4px;
		box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
		text-shadow: 0 1px 0 rgba(0,0,0,0.2);
	}

	.btn-square-little-rich:active {
		/*???????*/
		border: solid 1px #03A9F4;
		box-shadow: none;
		text-shadow: none;
	}
</style>
<script>
	function approval(){
		return window.confirm('This will reset all private data usage and not to be recoverd!\n Are you sure to continue?');
	}
	function checkForm(){
		/*if(registeruser.createusername.value==""|| registeruser.createuserpassword.value==""){
			alert("ID or password is blank");
			return false;
		}*/
	}

	function confirm_resetPw(){
		return window.confirm(`Selected password will be reset  '1111' for this user. Ok to continue`);
	}
	function confirm_resetData(){
		return window.confirm(`Selected user data usage will be reset, OK to continue.`);
	}
	function confirm_delUser(){
		return window.confirm(`Selected user IDs are being deleted, OK to continue.`);
	}
    function confirm_checkPw(){
        var pwlist = "<?php
            $passwordlist = array();
            foreach ($config['installedpackages']['freeradius']['config'] as $item=>$userentry) {
                if($userentry['varusersusername']!=='synersat'){
                    array_push($passwordlist, $userentry['varuserspassword']);
                }
            }
            echo implode(", ", $passwordlist);
        ?>";

        var result="";
        var resultlist = document.getElementsByName('userlist[]');
        for(let i=0; i<resultlist.length; i++){
            if(resultlist[i].checked){
                result += "\n" + resultlist[i].value + " : " + pwlist.split(",")[i];
            }
        }
        if(result===''){
            return window.alert("No user selected");
        }
        else{
            return window.alert(result);
        }
    }
	function selectAll(selectAll)  {
		const checkboxes
			= document.getElementsByName('userlist[]');
		checkboxes.forEach((checkbox) => {
			checkbox.checked = selectAll.checked;
		})
	}
</script>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
		<tr>
			<th center><center><input class="alluser_selected" type="checkbox" id="alluser_select" name="userlist" value="alluser_select" onclick="selectAll(this)" /></center></th>
			<th center><center><?=gettext("ID");?></center></th>
			<th><center><?=gettext("Type");?></center></th>
			<th><center><?=gettext("Update");?></center></th>
			<th><center><?=gettext("# MB Allowed");?></center></th>
			<th><center><?=gettext("# MB Used");?></center></th>
			<th><center><?=gettext("Online");?></center></th>
		</tr>
		</thead>
		<tbody id="<?=htmlspecialchars($widgetkey)?>-manage_freeradiususer">
		<?php
		echo '<form name=selectuser action="/widgets/widgets/manage_freeradiususer.widget.php" method="post" class="form-horizontal">';
		print(compose_manage_freeradiususer_contents($widgetkey));
		?>
		</tbody>
	</table>
</div>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->

<?php
global $config;

if(strpos(get_config_user(), "admin") !== false){
	$echostr .= '<div class="form-group">';
	$echostr .= '<input type="hidden" name="widgetkey" value='.$widgetkey.'>';
	$echostr .= '<button type="submit" onclick="confirm_resetPw();" class="btn btn-primary" name="resetpw" value="resetpw">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Reset PW';
	$echostr .= '</button>';
	$echostr .= '<button type="submit" onclick="confirm_resetData();" class="btn btn-primary" name="resetuser" value="resetuser">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Reset Data';
	$echostr .= '</button>';
	$echostr .= '<button type="submit" onclick="confirm_delUser();" class="btn btn-primary" style="float: right;" name="deluser" value="deluser">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Delete';
	$echostr .= '</button>';
	$echostr .= '</form>';
    $echostr .= '<button type="submit" onclick="confirm_checkPw();" class="btn btn-primary" name="checkpw" value="checkpw">';
    $echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
    $echostr .= 'Check PW';
    $echostr .= '</button>';
    $echostr .= '</div>';
    $echostr .= '<div id='.$widget_panel_footer_id.' class="panel-footer collapse">';
	$echostr .= '<form name=registeruser action="/widgets/widgets/manage_freeradiususer.widget.php" method="post" class="form-horizontal">';
	$echostr .= '<div><div class="form-group">';
	$echostr .= '<label>..          Allow data(MB)<input name="createuserquota" type="number"  value></label><p>';
	$echostr .= '<label>..         # of Vouchers <input name="createusernumber" type="number"  value></label>';
	$echostr .= '</div></div>';
	$echostr .= '<label class="col-sm-4 control-label">"Reset/Terminal Type"</label>';
	$echostr .= '<div class="col-sm-6">';
	$echostr .= '<div class="radio"><class>';
	$echostr .= '<select name="createsuerquotaperiod" size="1">';
	$echostr .= '<option value="monthly">Monthly </option>';
	$echostr .= '<option value="forever">Onetime </option>';
	$echostr .= '<option value="daily">Daily </option></select>';
	$echostr .= '<class="radio"></class>';
	$echostr .= '<select name="createuserterminaltype" size="1">';
	$echostr .= '<option value="">Auto </option>';
	foreach ($config['gateways']['gateway_item'] as $key => $gwitem) {
		if (is_array($gwitem) && strpos($gwitem['name'], "VPN") !== true) {
			$echostr .= '<option value='.$gwitem['name'].'> '.$gwitem["name"]. '</option>';
		}
	}
    /*foreach ($config['interfaces'] as $gwname => $gwitem) {
        if (is_array($gwitem) && isset($gwitem['alias-subnet'])) {
            $echostr .= '<option value='.$gwname.'> '.$gwitem["descr"]. '</option>';
        }
    }*/
	$echostr .= '</select></div></div></br>';
	$echostr .= '<input type="hidden" name="widgetkey" value='.$widgetkey.'><div>';
	$echostr .= '<button type="submit" class="btn btn-primary">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Apply';
	$echostr .= '</button>';
	$echostr .= '</div></form>';
}
else{
	$echostr .= '<div class="form-group">';
	$echostr .= '<input type="hidden" name="widgetkey" value='.$widgetkey.'>';
	$echostr .= '<button type="submit" onclick="confirm_resetPw();" class="btn btn-primary" name="resetpw" value="resetpw">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Reset Password';
	$echostr .= '</button>';
	$echostr .= '</form>';


}
echo $echostr;

?>
<script type="text/javascript">
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
</script>