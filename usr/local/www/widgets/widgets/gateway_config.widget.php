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



if ($_POST['widgetkey']) {//변경할때이므로
//여기에 컨트롤 코드 넣음.
    //이건 각 포탈별로 Enable/Disable 할 때
    if($_POST['gw_list']){

    }

    if($_POST['auto_portal_enable']){
        $config['captiveportal']['crew']['autoportal']='';
    }
    else {
        unset($config['captiveportal']['crew']['autoportal']);
    }

    /*write_config("Toggle Portal");
    captiveportal_configure();
    filter_configure();*/

    header("Location: /");
    exit(0);
}

?>

<script>
    function ipAddressCheck(ipAddress)
    {
        var regEx = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        if(ipAddress.value.match(regEx)||ipAddress.value=="")
        {
            return true;
        }
        else
        {
            alert("Please enter a valid ip Address.");
            return false;
        }
    }
</script>

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
<form name=gw_selection action="/widgets/widgets/gateway_config.widget.php" method="post" class="form-horizontal">
    <div class="container">
        <div class="row">
            <div class="col-xs-2 col-sm-10 col-md-5 col-sm-offset-0 col-md-offset-0">
                <div class="panel panel-default">
                    <ul class="list-group">
                        <li class="list-group-item">
                            <select name="gw_list" size="1" onchange="this.form.submit()">
                        <?php
                        $echostr= '';
                        global $config;
                        foreach ($config['interfaces'] as $gwname => $gwitem) {
                            if (is_array($gwitem) && isset($gwitem['alias-subnet'])) {
                                if($gwname == $_POST['gw_list'])
                                    $echostr .= '<option value='.$gwname.' selected> '.$gwitem["descr"]. '</option>';
                                else
                                    $echostr .= '<option value='.$gwname.'> '.$gwitem["descr"]. '</option>';
                                $echostr .= '<option value='.$gwname.'> '.$_POST['gw_list']. '</option>';
                            }
                        }
                        echo($echostr);
                        /*if(file_exists("/etc/inc/".$gateway['rootinterface']."_cumulative") && ($cumulative_file = fopen($filepath.$gateway['rootinterface']."_cumulative", "r"))!==false ){
                            $cur_usage = fgets($cumulative_file);
                            fclose($cumulative_file);
                        }
                        else {
                            $cur_usage = 0;
                        }*/

                        ?>
                        </select>
                        <input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>


    <div align="center">
        <input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
        <button type="submit" onclick="ipAddressCheck(document.internet_control.ipaddr)" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Apply')?>  </button>
    </div>
</form>
