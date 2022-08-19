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


if (!function_exists('compose_manage_freeradiususer_contents')) {
	function compose_manage_freeradiususer_contents($widgetkey) {
		$rtnstr = '';
		global $config;
		if(isset($config['installedpackages']['freeradius']['config'])){
		    $radiususers = &$config['installedpackages']['freeradius']['config'];
		    foreach ($radiususers as $eachuser) {
                $rtnstr .= "<tr>";
                $rtnstr .= "<td>${eachuser['varusersusername']}</td>";
                $rtnstr .= "<td>${eachuser['varuserspassword']}</td>";
                $rtnstr .="<td>${eachuser['varusersmaxtotaloctetstimerange']}</td>";
                $rtnstr .="<td>${eachuser['varusersmaxtotaloctets']}&nbsp;MBytes</td>";
                $used_quota=check_quota($eachuser['varusersusername']);
                $rtnstr .="<td>$used_quota MBytes</td>";
                $rtnstr .="<td><a> <form action='/widgets/widgets/manage_freeradiususer.widget.php' method='post' class='form-horizontal'> <input type='hidden' value=$widgetkey name=widgetkey>";
                $rtnstr .="<input type='hidden' name=resetuser value = ${eachuser['varusersusername']}><input type=submit class='btn-square-little-rich' value=reset title='Reset User data usage'></form></a></td>";
                $rtnstr .="<td><a> <form action='/widgets/widgets/manage_freeradiususer.widget.php' method='post' class='form-horizontal'> <input type='hidden' value=$widgetkey name=widgetkey>";
                $rtnstr .="<input type='hidden' name=delusername value = ${eachuser['varusersusername']}><input type=submit class='btn-square-little-rich' value=Delete title='delete'></form></a></td>";
            }
		}
		return($rtnstr);
	}
}

// Compose the table contents and pass it back to the ajax caller
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
	write_config("Freeradius user update");
	}

	if($_POST['resetuser']){
		 foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
			if ($_POST['resetuser'] === $userentry['varusersusername']) {
				$config['installedpackages']['freeradius']['config'][$item]['varusersresetquota']="true";
				$config['installedpackages']['freeradius']['config'][$item]['varusersmodified']=update;

			}
		}
		write_config("Reset freeradius user");
	}
	if($_POST['createusername'] && $_POST['createuserpassword'] && $_POST['createuserquota']){
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
		$userinfoentry['varusersmaxtotaloctets']=$_POST['createuserquota'];
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
	write_config("Added freeradius user");
	}
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
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
		<tr>
			<th><?=gettext("ID");?></th>
			<th><?=gettext("Password");?></th>
			<th><?=gettext("Update Period");?></th>
			<th><?=gettext("# MB Allowed");?></th>
			<th><?=gettext("# MB Used");?></th>
			<th><?=gettext("Data");?></th>
			<th><?=gettext("UserDel");?></th>

		</tr>
		</thead>
		<tbody id="<?=htmlspecialchars($widgetkey)?>-gwtblbody">
<?php
		print(compose_manage_freeradiususer_contents($widgetkey));
?>
		</tbody>
	</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">
<form action="/widgets/widgets/manage_freeradiususer.widget.php" method="post" class="form-horizontal">
	<?//=gen_customwidgettitle_div($widgetconfig['title']);?>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Input User Information')?></label>
		<div class="col-sm-6">



			<div class="radio">
				<label>User Name <input name="createusername" type="text"  value'></label>
				<label>Password <input name="createuserpassword" type="text"  value'></label>
				<label>Allow data <input name="createuserquota" type="text"  value'></label>
			</div>

		</div>
		<label class="col-sm-4 control-label"><?=gettext('Reset Period')?></label>
		<div class="col-sm-6">
			<div class="radio">
				<select name="createsuerquotaperiod" size="1">
					<option value="daily">Daily </option>
					<option value="monthly">Monthly </option>
				</select>
			</div>
		</div>

<br/>

		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
		<div>
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Apply')?></button>
		</div>
	</div>
</form>
<script>
//<![CDATA[

events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function manage_freeradiususer_callback(s) {
		$(<?= json_encode('#' . $widgetkey . '-man-freeradiususer')?>).html(s);
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
	manage_freeradiususerObject.freq = 1;

	// Register the AJAX object
	register_ajax(manage_freeradiususerObject);

	// ---------------------------------------------------------------------------------------------------
});

//]]>
</script>
