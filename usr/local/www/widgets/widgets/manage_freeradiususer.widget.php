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
				$rtnstr .= "<td><center>{$eachuser['varusersusername']}</center></td>";
				$rtnstr .="<td><center>{$eachuser['varusersmaxtotaloctetstimerange']}</center></td>";
				$rtnstr .="<td><center>{$eachuser['varusersmaxtotaloctets']}&nbsp;MBytes</center></td>";
				$used_quota=check_quota($eachuser['varusersusername']);
				if($eachuser['varusersmodified']=="update"){$rtnstr .= "<td><center>Wait for logon</center></td>";}
				else{$rtnstr .="<td><center>$used_quota MBytes</center></td>";}
				$widgetkey_html = htmlspecialchars($widgetkey);
				$rtnstr .= "<td><a><center><form id=resetpw action='/widgets/widgets/manage_freeradiususer.widget.php' method='post' class='form-horizontal'  onSubmit='return confirm_resetPw(\"{$eachuser['varusersusername']}\")'> <input type='hidden' value={$widgetkey_html} name=widgetkey>";
				$rtnstr .="<input type='hidden' name=resetpw value = {$eachuser['varusersusername']}><input type=submit class='btn-square-little-rich' value=Reset title='Reset Password'></form></center></a></td>";
				if(strpos(get_config_user(), "admin") !== false){
	        		$rtnstr .="<td><a><center><form action='/widgets/widgets/manage_freeradiususer.widget.php' method='post' class='form-horizontal' onSubmit='return confirm_resetData(\"{$eachuser['varusersusername']}\")'> <input type='hidden' value={$widgetkey_html} name=widgetkey>";
             		$rtnstr .="<input type='hidden' name=resetuser value = {$eachuser['varusersusername']}><input type=submit class='btn-square-little-rich' value=Reset title='Reset User data usage'></form></center></a></td>";
		            $rtnstr .="<td><a><center><form action='/widgets/widgets/manage_freeradiususer.widget.php' method='post' class='form-horizontal' onSubmit='return confirm_delUser(\"{$eachuser['varusersusername']}\")'> <input type='hidden' value={$widgetkey_html} name=widgetkey>";
        		    $rtnstr .="<input type='hidden' name=delusername value = {$eachuser['varusersusername']}><input type=submit class='btn-square-little-rich' value=Delete title='delete'></form></center></a></td>";
				} else {
					$rtnstr .="<td><a></td>";
					$rtnstr .="<td><a></td>";
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

if ($_POST['widgetkey']) {//º¯°æÇÒ¶§ÀÌ¹Ç·Î
	global $config;
	if($_POST['delusername']){
		 foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
			if ($_POST['delusername'] === $userentry['varusersusername']) {
				unset($config["installedpackages"]["freeradius"]["config"][$item]);  // flag for remove DB for when anyone who is in site is open webpage.
			}
		}
	}
	else if($_POST['resetpw']){
		 foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
			if ($_POST['resetpw'] === $userentry['varusersusername']) {
				$config["installedpackages"]["freeradius"]["config"][$item]['varuserspassword']="1111";
			}
		}
	}
	else if($_POST['resetuser']){
		 foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
			if ($_POST['resetuser'] === $userentry['varusersusername']) {
				$config['installedpackages']['freeradius']['config'][$item]['varusersresetquota']="true";
				$config['installedpackages']['freeradius']['config'][$item]['varusersmodified']="update";

			}
		}
	}
	else if($_POST['createusername'] && $_POST['createuserpassword'] && $_POST['createuserquota']){
	    foreach($config['installedpackages']['freeradius']['config'] as $item){
	        if($_POST['createusername'] === $item['varusersusername']){
	            header("Location: /");
                exit(0);
	        }
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
            "varusersresetquota"=>"true",
        );
		$userinfoentry['varusersusername']=$_POST['createusername'];
		$userinfoentry['varuserspassword']=$_POST['createuserpassword'];
		if(is_numeric($_POST['createuserquota'])){
		    $userinfoentry['varusersmaxtotaloctets']=$_POST['createuserquota'];
		}
		else{
		    $userinfoentry['varusersmaxtotaloctets']=0;
		}
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
  background: #03A9F4;/*ßä*/
  border: solid 1px #0f9ada;/*àÊßä*/
  border-radius: 4px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
  text-shadow: 0 1px 0 rgba(0,0,0,0.2);
}

.btn-square-little-rich:active {
  /*äãª·ª¿ªÈª­*/
  border: solid 1px #03A9F4;
  box-shadow: none;
  text-shadow: none;
}
</style>
<script>
function checkForm(){
    if(registeruser.createusername.value==""|| registeruser.createuserpassword.value==""){
        alert("ID or password is blank");
        return false;
    }
    if(isNaN(registeruser.createuserquota.value)){
        alert("Wrong datacount, input Number only");
        return false;
    }
}

function confirm_resetPw(username){
   return window.confirm(`${username} password will be reset  '1111' for this user. Ok to continue`);
}
function confirm_resetData(username){
   return window.confirm(`${username} data usage will be reset, OK to continue.`);
}
function confirm_delUser(username){
   return window.confirm(`ID ${username} will be deleted, OK to continue.`);
}
</script>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
		<tr>
			<th center><center><?=gettext("ID");?></center></th>
			<th><center><?=gettext("Update Period");?></center></th>
			<th><center><?=gettext("# MB Allowed");?></center></th>
			<th><center><?=gettext("# MB Used");?></center></th>
			<th><center><?=gettext("Password");?></center></th>
			<th><center><?=gettext("Data");?></center></th>
			<th><center><?=gettext("Remove");?></center></th>

		</tr>
		</thead>
		<tbody id="<?=htmlspecialchars($widgetkey)?>-manage_freeradiususer">
<?php
		print(compose_manage_freeradiususer_contents($widgetkey));
?>
		</tbody>
	</table>
</div>
	<!-- close the body we're wrapped in and add a configuration-panel -->
	</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse"><form name=registeruser action="/widgets/widgets/manage_freeradiususer.widget.php" method="post" class="form-horizontal" onSubmit="return checkForm()"><div class="form-group">
	<label class="col-sm-4 control-label"><?=gettext("Input User Information")?></label><div class="col-sm-6"><div class="radio"><label>User Name <input name="createusername" type="text"  value></label><label>Password <input name="createuserpassword" type="text"  value></label>
	<label>Allow data <input name="createuserquota" type="text"  value></label></div></div>
	<label class="col-sm-4 control-label"><?=gettext("Reset Period")?></label><div class="col-sm-6"><div class="radio"><select name="createsuerquotaperiod" size="1"><option value="monthly">Monthly </option><option value="daily">Daily </option>
	</select></div></div><br/><input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>"><div>
	<button <? if(strpos(get_config_user(), "admin") !== false) {} else {?>disabled="disabled" <?}?>type="submit" class="btn btn-primary">
	<i class="fa fa-save icon-embed-btn"></i>
	<?=gettext("Apply")?>
	</button></div></div></form>

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
	manage_freeradiususerObject.freq = 30;

	// Register the AJAX object
	register_ajax(manage_freeradiususerObject);

	// ---------------------------------------------------------------------------------------------------
});*/
</script>