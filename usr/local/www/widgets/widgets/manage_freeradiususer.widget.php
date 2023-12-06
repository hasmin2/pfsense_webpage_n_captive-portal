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


if (!function_exists('compose_manage_freeradiususer_contents')) {
	function compose_manage_freeradiususer_contents($widgetkey) {
		$rtnstr = '';
		global $config;
		if(isset($config['installedpackages']['freeradius']['config'])){
			$radiususers = &$config['installedpackages']['freeradius']['config'];
			foreach ($radiususers as $eachuser) {
				$rtnstr .= "<tr>";
				$rtnstr .= "<td><center><input type=checkbox id={$eachuser['varusersusername']} name=userlist[] value={$eachuser['varusersusername']} /></center></td>";
				$rtnstr .= "<td><center>{$eachuser['varusersusername']}</center></td>";
				$terminaltype = $eachuser['varusersterminaltype']=='' ?  'Auto' : $eachuser['varusersterminaltype'];
				$rtnstr .= "<td><center>".$terminaltype ."</center></td>";
				$rtnstr .="<td><center>{$eachuser['varusersmaxtotaloctetstimerange']}</center></td>";
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
			foreach ($config['interfaces'] as $gwname => $gwitem){
				if(is_array($gwitem) && $gwname == $_POST['createuserterminaltype']) {
					$terminaltype=$gwitem['descr'];
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
			captiveportal_syslog(" !!!!!!!!!!!!!!!".$username);
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
			$userinfoentry['varuserspassword']='1111';
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
			<th center><center><input type="checkbox" id="alluser_select" name="userlist" value="alluser_select" onclick="selectAll(this)" /></center></th>
			<th center><center><?=gettext("ID");?></center></th>

			<th><center><?=gettext("Type");?></center></th>
			<th><center><?=gettext("Update");?></center></th>
			<th><center><?=gettext("# MB Allowed");?></center></th>
			<th><center><?=gettext("# MB Used");?></center></th>
			<th><center><?=gettext("Online");?></center></th>
			<!--th><center><?=gettext("Password");?></center></th>
			<th><center><?=gettext("Data");?></center></th>
			<th><center><?=gettext("Remove");?></center></th-->

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
	$echostr .= 'Reset Password';
	$echostr .= '</button>';
	$echostr .= '<button type="submit" onclick="confirm_resetData();" class="btn btn-primary" name="resetuser" value="resetuser">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Reset Data';
	$echostr .= '</button>';
	$echostr .= '<button type="submit" onclick="confirm_delUser();" class="btn btn-primary" style="float: right;" name="deluser" value="deluser">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Delete';
	$echostr .= '</button>';
	$echostr .= '</form></div>';
	$echostr .= '<div id='.$widget_panel_footer_id.' class="panel-footer collapse">';
	$echostr .= '<form name=registeruser action="/widgets/widgets/manage_freeradiususer.widget.php" method="post" class="form-horizontal">';
	$echostr .= '<div><div class="form-group">';
	//$echostr .= '<label class="col-sm-4 control-label">"Input User Information"</label>';
	//$echostr .= '<div class="col-sm-6"><div class="radio"><label>User Name <input name="createusername" type="text"  value></label>';
	//$echostr .= '<label>Password <input name="createuserpassword" type="text"  value></label>';
	$echostr .= '<label>Allow data(MB)<input name="createuserquota" type="number"  value></label>';
	$echostr .= '<label># of Vouchers <input name="createusernumber" type="number"  value></label>';
	$echostr .= '</div></div>';
	$echostr .= '<label class="col-sm-4 control-label">"Reset/Terminal Type"</label>';
	$echostr .= '<div class="col-sm-6">';
	$echostr .= '<div class="radio"><class>';
	$echostr .= '<select name="createsuerquotaperiod" size="1">';
	$echostr .= '<option value="monthly">Monthly </option>';
	$echostr .= '<option value="forever">Forever </option>';
	$echostr .= '<option value="daily">Daily </option></select>';
	$echostr .= '<class="radio"></class>';
	$echostr .= '<select name="createuserterminaltype" size="1">';
	$echostr .= '<option value="">Auto </option>';
	foreach ($config['interfaces'] as $gwname => $gwitem) {
		if (is_array($gwitem) && isset($gwitem['alias-subnet'])) {
			$echostr .= '<option value='.$gwname.'> '.$gwitem["descr"]. '</option>';
		}
	}
	$echostr .= '</select></div></div></br>';
	$echostr .= '<input type="hidden" name="widgetkey" value='.$widgetkey.'><div>';
	$echostr .= '<button type="submit" class="btn btn-primary">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Apply';
	$echostr .= '</button>';
	$echostr .= '</div></div></form>';
	$echostr .= '</div></div>';
}
else{
	$echostr .= '<div class="form-group">';
	$echostr .= '<input type="hidden" name="widgetkey" value='.$widgetkey.'>';
	$echostr .= '<button type="submit" onclick="confirm_resetPw();" class="btn btn-primary"  value="resetpw">';
	$echostr .= '<i class="fa fa-save icon-embed-btn"></i>';
	$echostr .= 'Reset Password';
	$echostr .= '</button>';
	$echostr .= '</form></div>';


}
echo $echostr;

?>
<script type="text/javascript">
	/*events.push(function(){
        // --------------------- Centralized widget refresh system ------------------------------

        // Callback function called by refresh system when data is retrieved
        function manage_freeradiususer_callback(s) {
            $(<?= json_encode('#' . $widgetkey .'-manage_freeradiususer')?>).html(s);
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		widgetkey : <?=json_encode($widgetkey)?>
	 };
	// Create an object defining the widget refresh AJAX call
	var manage_freeradiususerObject= new Object();
	manage_freeradiususerObject.name = "manage_freeradiususer";
	manage_freeradiususerObject.url = "/widgets/widgets/manage_freeradiususer.widget.php";
	manage_freeradiususerObject.callback = manage_freeradiususer_callback;
	manage_freeradiususerObject.parms = postdata;
	manage_freeradiususerObject.freq = 60;

	// Register the AJAX object
	register_ajax(manage_freeradiususerObject);

	// ---------------------------------------------------------------------------------------------------
});*/
</script>