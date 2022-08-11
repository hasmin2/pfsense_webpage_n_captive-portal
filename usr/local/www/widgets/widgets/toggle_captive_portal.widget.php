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
        unset($config['captiveportal']['crew']['enable']);
    }
    if($_POST['auto_portal_enable']){
        $config['captiveportal']['crew']['autoportal']="";
    }
    else {
        unset($config['captiveportal']['crew']['autoportal']);
    }
    if($_POST['terminate_portal']){
        captiveportal_disconnect_all();
        $config['captiveportal']['crew']['terminate_duration']=$_POST['terminate_duration'];
        $date = new DateTime();
        $config['captiveportal']['crew']['terminate_timestamp']=round($date->getTimestamp()/60, 0);

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
        <div class="col-xs-0 col-sm-10 col-md-5 col-sm-offset-0 col-md-offset-0">
            <div class="panel panel-default">
                <!-- Default panel contents -->
                <div class="panel-heading" align="center">Private internet control panel</div>
            
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
			$toggledisable="disabled";
		}
		else{
			$autoportal="";
			$toggledisable="";
		}
?>
                    <li class="list-group-item">
			Enable / Disable private internet<br> **If Disable, private internet will be transmitted without control**
                        <div class="material-switch pull-right">
                            <input id="crew" name=crew type="checkbox" <?echo($checkbox);?> />
                            <label for="crew" class="label-primary"></label>
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
				</select>
                        <div class="material-switch pull-right">
                            <input id="terminate_portal" name="terminate_portal" type="checkbox" <?echo($terminated);?> <?echo($disabled);?>/>
                            <label for="terminate_portal" class="label-success"></label>
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
