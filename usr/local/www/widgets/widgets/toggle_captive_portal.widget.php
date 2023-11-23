<?php
/*
 * captive_portal_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2007 Sam Wenham
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

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("captiveportal.inc");
require_once("/usr/local/www/widgets/include/toggle_captive_portal.inc");

init_config_arr(array('captiveportal'));
$a_cp = &$config['captiveportal'];
$clientip = $_SERVER['REMOTE_ADDR'];
$serverip = $_SERVER['SERVER_ADDR'];
$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}
$cpzone = strtolower($cpzone);
if ($_POST['widgetkey']) {//변경할때이므로
//여기에 컨트롤 코드 넣음.
	//이건 각 포탈별로 Enable/Disable 할 때
    if(isset($_POST['crew'])){
        $config['captiveportal']['crew']['enable']='';
    }
    else{
        if(isset($_POST['crewhidden'])){
            $config['captiveportal']['crew']['enable']='';
        }
        else{
            unset($config['captiveportal']['crew']['enable']);
        }
    }
    if(isset($_POST['ban_all'])){
        $config['interface']['ban_all']='';
        add_linked_rule($serverip, $clientip);
    }
    else{
        unset($config['interface']['ban_all']);
        del_linked_rule($serverip, $clientip);
    }

    if($_POST['auto_portal_enable']){
        $config['captiveportal']['crew']['autoportal']='';
    }
    else {
        unset($config['captiveportal']['crew']['autoportal']);
    }
    if($_POST['terminate_portal']){
		$cpzone = strtolower($config['captiveportal']['crew']['zone']);
    	$cpzoneid = $config['captiveportal']['crew']['zoneid'];
        //captiveportal_disconnect_all();
        $config['captiveportal']['crew']['terminate_duration']=$_POST['terminate_duration'];
        $date = new DateTime();
        $config['captiveportal']['crew']['terminate_timestamp']=round($date->getTimestamp()/60, 0);
		captiveportal_disconnect_all($term_cause = 6, $logoutReason = "DISCONNECT", $carp_loop = false);
    }
    else{
        unset($config['captiveportal']['crew']['terminate_duration']);
        unset($config['captiveportal']['crew']['terminate_timestamp']);

    }

	write_config("Toggle Portal");
	captiveportal_configure();
	filter_configure();

	header("Location: /");
	exit(0);
}
function get_interfacename($ipaddr){
    global $config;
    foreach($config['interfaces'] as $ifname => $ifitem){
        if($ifitem['ipaddr'] == $ipaddr){
            $interface = $ifname;
            break;
        }
    }
    return $interface;
}

function del_linked_rule($serverip, $clientip){
    global $config;
    $interface = get_interfacename($serverip);
    if(isset($interface)) {
        foreach ($config['filter']['rule'] as $key => $rule) {
            if ($rule['type']=='pass'
                && $rule['interface']==$interface
                && $rule['source']['address']==$clientip){
                unset($config['filter']['rule'][$key]);
            }
            if ($rule['type']=='block'
                && $rule['interface']==$interface
                && $rule['source']['network']==$interface
                && $rule['destination']['network']=='(self)'
                && $rule['destination']['not']==''
                && startsWith($rule['descr'], "[User Rule] {$clientip}")){
                unset($config['filter']['rule'][$key]);
            }
        }
    }
}
function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}
function add_linked_rule($serverip, $clientip){
    global $config;
    $interface = get_interfacename($serverip);
    if(isset($interface)){
        $newrule = array();
        $newrule['id'] = '';
        $newrule['tracker']=time();
        $newrule['type']='block';
        $newrule['interface']=$interface;
        $newrule['ipprotocol']='inet';
        $newrule['tag'] = '';
        $newrule['tagged'] = '';
        $newrule['max'] = '';
        $newrule['max-src-nodes'] = '';
        $newrule['max-src-conn'] = '';
        $newrule['max-src-states'] = '';
        $newrule['statetimeout'] = '';
        $newrule['statetype'] = 'keep state';
        $newrule['os'] = '';
        $newrule['source']['network']=$interface;
        $newrule['destination']['network']='(self)';
        $newrule['destination']['not']='';
        $newrule['descr']="[User Rule] {$clientip} ban-all-rule";
        $newrule['gateway']="";
        $newrule['updated']['time']=time();
        $newrule['updated']['username']='admin@{$clientip}';
        $newrule['created']['time']=time();
        $newrule['created']['username']='admin@{$clientip}';
        array_unshift($config['filter']['rule'], $newrule);
        $newrule['type']='pass';
        unset($newrule['source']['network']);
        unset($newrule['destination']['network']);
        unset($newrule['destination']['not']);
        $newrule['source']['address']=$clientip;
        $newrule['destination']['any']='';
        $newrule['descr']="[User Rule] {$clientip} allow only 'this' PC";
        array_unshift($config['filter']['rule'], $newrule);
    }
    return $interface;
}

?>

<style>
.material-switch > input[type="checkbox"] {
    display: none;
}

.material-switch > label {
    cursor: pointer;
    height: 0px;
    position: relative;
    width: 40px;
}

.material-switch > label::before {
    background: rgb(0, 0, 0);
    box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
    border-radius: 8px;
    content: '';
    height: 16px;
    margin-top: -8px;
    position:absolute;
    opacity: 0.3;
    transition: all 0.4s ease-in-out;
    width: 40px;
}
.material-switch > label::after {
    background: rgb(255, 255, 255);
    border-radius: 16px;
    box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
    content: '';
    height: 24px;
    left: -4px;
    margin-top: -8px;
    position: absolute;
    top: -4px;
    transition: all 0.3s ease-in-out;
    width: 24px;
}
.material-switch > input[type="checkbox"]:checked + label::before {
    background: inherit;
    opacity: 0.5;
}
.material-switch > input[type="checkbox"]:checked + label::after {
    background: inherit;
    left: 20px;
}
</style>
<form action="/widgets/widgets/toggle_captive_portal.widget.php" method="post" class="form-horizontal">
<div class="container">
    <div class="row">
        <div class="col-xs-2 col-sm-10 col-md-5 col-sm-offset-0 col-md-offset-0">
            <div class="panel panel-default">
                <!-- Default panel contents -->
                <!--div class="panel-heading" align="center">Private internet control panel</div-->

                <!-- List group -->
                <ul class="list-group">
<?php
		if(isset($a_cp['crew']['enable'])){
			$checkbox = "checked";
			$disabled = "";
		}
		else {
			$checkbox = "";
			$disabled="disabled";
		}
		if(isset($a_cp['crew']['terminate_duration'])){
			$terminated= "checked";
		}
		else{ $terminated="";}
		if(isset($a_cp['crew']['autoportal'])){
			$autoportal= "checked";
		}
		else{
			$autoportal="";
		}
		if(isset($config['interface']['ban_all'])){
            $banned= "checked";
        }
        else{
            $banned="";
        }
		if(strpos(get_config_user(), "admin") !== false){
        	$toggledisable="";
        }
        else{
           $toggledisable="disabled";
        }
?>
                    <li class="list-group-item">
			Enable / Disable private internet<br> **If Disable, private internet will be transmitted without control**
                        <div class="material-switch pull-right">
                            <input id="crew" name=crew type="checkbox" <?echo($checkbox);?> <?echo($toggledisable);?>/>
                            <label for="crew" class="label-primary"></label>
		<?if($toggledisable==="disabled"){
                            echo ("<input id='crewhidden' name='crewhidden' type='hidden'". $checkbox .' '.$toggleadmin.'/>');
		}
		?>
                        </div>
                    </li>

                    <li class="list-group-item">
                         Auto disable private internet on 4G/Landline
                        <div class="material-switch pull-right">
                            <input id="auto_portal_enable" name="auto_portal_enable" type="checkbox"<?echo($autoportal);?>/>
                            <label for="auto_portal_enable" class="label-primary"></label>
                        </div>
                    </li>
                    <li class="list-group-item">
                        Terminate private internet usage for  &nbsp;
				<select name="terminate_duration" size="1" <?echo($disabled);?>>
					<option value="5">5 minutes </option>
					<option value="30">30 minutes </option>
					<option value="60">60 minutes </option>
					<option value="300">5 hours </option>
					<option value="1440">1 day </option>
					<option value="100000000">permanent </option>
				</select>
                        <div class="material-switch pull-right">
                            <input id="terminate_portal" name="terminate_portal" type="checkbox" <?echo($terminated);?> <?echo($disabled);?>/>
                            <label for="terminate_portal" class="label-success"></label>
                        </div>

                    </li>
                    <li class="list-group-item">
                         Block all business internet access except for "this" PC
                        <div class="material-switch pull-right">
                            <input id="ban_all" name="ban_all" type="checkbox" <?echo($banned);?>/>
                            <label for="ban_all" class="label-success"></label>
                        </div>
                    </li>
                </ul>
            </div>            
        </div>
    </div>
</div>
	


        <div align="center">
		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
		<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Apply')?></button>
	</div>
</form>
