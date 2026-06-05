<?php
/*
 * captiveportal.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>.
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

/* include all configuration functions */
require_once("auth.inc");
require_once("PEAR.php"); // required for bcmath
require_once("Auth/RADIUS.php"); // required for radius accounting
require_once("config.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("gwlb.inc");
include_once("freeradius.inc");
require_once("voucher.inc");
require_once("xmlrpc_client.inc");

/* Captiveportal Radius Accounting */
PEAR::loadExtension('bcmath');
// The RADIUS Package doesn't have these vars so we create them ourself
define("CUSTOM_RADIUS_ACCT_INPUT_GIGAWORDS", "52");
define("CUSTOM_RADIUS_ACCT_OUTPUT_GIGAWORDS", "53");
define("GIGAWORDS_RIGHT_OPERAND", '4294967296'); // 2^32

function get_default_captive_portal_html() {
    global $config, $g, $cpzone;

    $translated_text1 = gettext("User");
    $translated_text2 = gettext("Password");
    $translated_text3 = gettext("First Authentication Method ");
    $translated_text4 = gettext("Second Authentication Method ");
    // default images to use.
    $logo_src = "captiveportal-default-logo.png";
    $bg_src = "linear-gradient(135deg, #1475CF, #2B40B5, #1C1275)";
    // Check if customlogo is set and if the element exists
    // Check if the image is in the directory
    if (isset($config['captiveportal'][$cpzone]['customlogo']) &&
        is_array($config['captiveportal'][$cpzone]['element']) &&
        !empty($config['captiveportal'][$cpzone]['element'])) {
        foreach ($config['captiveportal'][$cpzone]['element'] as $element) {
            if (strpos($element['name'], "captiveportal-logo.") !== false) {
                if (file_exists("{$g['captiveportal_path']}/{$element['name']}")) {
                    $logo_src = $element['name'];
                    break;
                }
            }
        }
    }
    // check if custombg is set and if the element exists
    if (isset($config['captiveportal'][$cpzone]['custombg']) &&
        is_array($config['captiveportal'][$cpzone]['element']) &&
        !empty($config['captiveportal'][$cpzone]['element'])) {
        foreach ($config['captiveportal'][$cpzone]['element'] as $element) {
            if (strpos($element['name'],"captiveportal-background.") !== false) {
                if( file_exists("{$g['captiveportal_path']}/{$element['name']}")) {
                    $bg_src = "url(" . $element['name'] . ")" . "center center no-repeat fixed";
                    break;
                }
            }
        }

    }
    // bring in terms and conditions
    $termsconditions = base64_decode($config['captiveportal'][$cpzone]['termsconditions']);
    // if there is no terms and conditions do not require the checkbox to be selected.
    $disabled = "";
    if ($termsconditions) {
        $disabled = "disabled";
    }
    $htmltext = <<<EOD
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <title>Captive Portal Login Page</title>
  <style>
	  #content,.login,.login-card a,.login-card h1,.login-help{text-align:center}body,html{margin:0;padding:0;width:100%;height:100%;display:table}#content{font-family:'Source Sans Pro',sans-serif;background-color:#1C1275;background:{$bg_src};-webkit-background-size:cover;-moz-background-size:cover;-o-background-size:cover;background-size:cover;display:table-cell;vertical-align:middle}.login-card{padding:40px;width:280px;background-color:#F7F7F7;margin:100px auto 10px;border-radius:2px;box-shadow:0 2px 2px rgba(0,0,0,.3);overflow:hidden}.login-card h1{font-weight:400;font-size:2.3em;color:#1383c6}.login-card h1 span{color:#f26721}.login-card img{width:70%;height:70%}.login-card input[type=submit]{width:100%;display:block;margin-bottom:10px;position:relative}.login-card input[type=text],input[type=password]{height:44px;font-size:16px;width:100%;margin-bottom:10px;-webkit-appearance:none;background:#fff;border:1px solid #d9d9d9;border-top:1px solid silver;padding:0 8px;box-sizing:border-box;-moz-box-sizing:border-box}.login-card input[type=text]:hover,input[type=password]:hover{border:1px solid #b9b9b9;border-top:1px solid #a0a0a0;-moz-box-shadow:inset 0 1px 2px rgba(0,0,0,.1);-webkit-box-shadow:inset 0 1px 2px rgba(0,0,0,.1);box-shadow:inset 0 1px 2px rgba(0,0,0,.1)}.login{font-size:14px;font-family:Arial,sans-serif;font-weight:700;height:36px;padding:0 8px}.login-submit{-webkit-appearance:none;-moz-appearance:none;appearance:none;border:0;color:#fff;text-shadow:0 1px rgba(0,0,0,.1);background-color:#4d90fe}.login-submit:disabled{opacity:.6}.login-submit:hover{border:0;text-shadow:0 1px rgba(0,0,0,.3);background-color:#357ae8}.login-card a{text-decoration:none;color:#222;font-weight:400;display:inline-block;opacity:.6;transition:opacity ease .5s}.login-card a:hover{opacity:1}.login-help{width:100%;font-size:12px}.list{list-style-type:none;padding:0}.list__item{margin:0 0 .7rem;padding:0}label{display:-webkit-box;display:-webkit-flex;display:-ms-flexbox;display:flex;-webkit-box-align:center;-webkit-align-items:center;-ms-flex-align:center;align-items:center;text-align:left;font-size:14px;}input[type=checkbox]{-webkit-box-flex:0;-webkit-flex:none;-ms-flex:none;flex:none;margin-right:10px;float:left}@media screen and (max-width:450px){.login-card{width:70%!important}.login-card img{width:30%;height:30%}}textarea{width:66%;margin:auto;height:120px;max-height:120px;background-color:#f7f7f7;padding:20px}#terms{display:none;padding-top:100px;padding-bottom:300px;}.auth_source{border: 1px solid lightgray; padding:20px 8px 0px 8px; margin-top: -2em; border-radius: 2px; }.auth_head{background-color:#f7f7f7;display:inline-block;}.auth_head_div{text-align:left;}#error-message{text-align:left;color:#ff3e3e;font-style:italic;}
  </style>
</head>
<body>
<div id="content">
	<div class="login-card">
		<img src="{$logo_src}"/><br>
 		<h1></h1>
		<div id="error-message">
			\$PORTAL_MESSAGE\$
		</div>
	  <form name="login_form" method="post" action="\$PORTAL_ACTION\$">
EOD;
    if ($config['captiveportal'][$cpzone]['auth_method'] != "none"){
        if ($config['captiveportal'][$cpzone]['auth_method'] === 'authserver' && !empty($config['captiveportal'][$cpzone]['auth_server2'])) {
            $htmltext .= <<<EOD
			<div class="auth_head_div">
				<h6 class="auth_head">{$translated_text3}</h6>
			</div>
			<div class="auth_source">
EOD;
        }
        $htmltext .=<<<EOD
		<input type="text" name="auth_user" placeholder="{$translated_text1}" id="auth_user">
		<input type="password" name="auth_pass" placeholder="{$translated_text2}" id="auth_pass">
EOD;

        if ($config['captiveportal'][$cpzone]['auth_method'] === 'authserver' && !empty($config['captiveportal'][$cpzone]['auth_server2'])) {
            $htmltext .= <<<EOD
			</div>
			<div class="auth_head_div">
				<h6 class="auth_head">{$translated_text4}</h6>
			</div>
			<div class="auth_source">
			<input type="text" name="auth_user2" placeholder="{$translated_text1}" id="auth_user2">
			<input type="password" name="auth_pass2" placeholder="{$translated_text2}" id="auth_pass2">
			</div>
EOD;
        }


        if (isset($config['voucher'][$cpzone]['enable'])) {
            $translated_text = gettext("Voucher Code");
            $htmltext .= <<<EOD
				<br  /><br  />
				<input name="auth_voucher" type="text" placeholder="{$translated_text}" value="#VOUCHER#">
EOD;
        }
    }

    if ($termsconditions) {
        $htmltext .= <<<EOD
		  <div class="login-help">
			<ul class="list">
				<li class="list__item">
				  <label class="label--checkbox">
					<input type="checkbox" class="checkbox" onchange="document.getElementById('login').disabled = !this.checked;">
					<span>I agree with the <a  rel="noopener" href="#terms" onclick="document.getElementById('terms').style.display = 'block';">terms & conditions</a></span>
				  </label>
				</li>
			</ul>
		  </div>
EOD;
    }
    $htmltext .= <<<EOD
		<input name="redirurl" type="hidden" value="\$PORTAL_REDIRURL\$">
		<input type="submit" name="accept" class="login login-submit" value="Login" id="login" {$disabled}>
	  </form>
	  <br  />
	  <span> <i>Made with &hearts; by</i> <strong>Netgate</strong></span>
	</div>
	<div id="terms">
		<textarea readonly>{$termsconditions}</textarea>
	</div>
</div>
</body>
</html>
EOD;

    return $htmltext;
}

function captiveportal_load_modules() {
    global $config;

    mute_kernel_msgs();
    if (!is_module_loaded("ipfw.ko")) {
        mwexec("/sbin/kldload ipfw");
        /* make sure ipfw is not on pfil hooks */
        set_sysctl(array(
            "net.inet.ip.pfil.inbound" => "pf",
            "net.inet6.ip6.pfil.inbound" => "pf",
            "net.inet.ip.pfil.outbound" => "pf",
            "net.inet6.ip6.pfil.outbound" => "pf"
        ));
    }
    /* Activate layer2 filtering */
    set_sysctl(array(
        "net.link.ether.ipfw" => "1",
        "net.inet.ip.fw.one_pass" => "1",
        "net.inet.ip.fw.tables_max" => "65534"
    ));

    /* Always load dummynet now that even allowed ip and mac passthrough use it. */
    if (!is_module_loaded("dummynet.ko")) {
        mwexec("/sbin/kldload dummynet");
        set_sysctl(array(
            "net.inet.ip.dummynet.io_fast" => "1",
            "net.inet.ip.dummynet.hash_size" => "256"
        ));
    }
    unmute_kernel_msgs();
}

function captiveportal_configure() {
    global $config, $cpzone, $cpzoneid;

    // 멱등 self-heal: 구버전 per-user 로그인 룰("[User Rule] <id> auto generated rule")을
    // config.xml 에서 일괄 제거한다. (pfctl 테이블 방식으로 전환되며 고아로 남은 룰 →
    //  DHCP IP 재할당 시 옛 gateway 로 오라우팅될 위험.) 제거할 게 없으면 no-op.
    cp_purge_legacy_user_login_rules();

    if (is_array($config['captiveportal'])) {
        foreach ($config['captiveportal'] as $cpkey => $cp) {
            $cpzone = $cpkey;
            $cpzoneid = $cp['zoneid'];
            captiveportal_configure_zone($cp);
        }
    }

    // filter 재로드 후 pfctl 라우팅 테이블 즉시 복원
    cp_sync_routing_tables();
}

function captiveportal_configure_zone($cpcfg) {
    global $config, $g, $cpzone, $cpzoneid;

    $captiveportallck = lock("captiveportal{$cpzone}", LOCK_EX);

    if (isset($cpcfg['enable'])) {
        if (platform_booting()) {
            echo "Starting captive portal({$cpcfg['zone']})... ";
        } else {
            captiveportal_syslog("Reconfiguring captive portal({$cpcfg['zone']}).");
        }

        /* init ipfw rules */
        captiveportal_init_rules();

        /* kill any running minicron */
        killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");

        /* initialize minicron interval value */
        $croninterval = $cpcfg['croninterval'] ? $cpcfg['croninterval'] : 60;

        /* double check if the $croninterval is numeric and at least 10 seconds. If not we set it to 60 to avoid problems */
        if ((!is_numeric($croninterval)) || ($croninterval < 10)) {
            $croninterval = 60;
        }

        /* write portal page */
        /*if (is_array($cpcfg['page']) && $cpcfg['page']['htmltext']) {
            $htmltext = base64_decode($cpcfg['page']['htmltext']);
        } else {
            $htmltext = get_default_captive_portal_html();
        }

        /fd = @fopen("{$g['varetc_path']}/captiveportal_{$cpzone}.html", "w");
        if ($fd) {
            // Special case handling.  Convert so that we can pass this page
            // through the PHP interpreter later without clobbering the vars.
            $htmltext = str_replace("\$PORTAL_ZONE\$", "#PORTAL_ZONE#", $htmltext);
            $htmltext = str_replace("\$PORTAL_REDIRURL\$", "#PORTAL_REDIRURL#", $htmltext);
            $htmltext = str_replace("\$PORTAL_MESSAGE\$", "#PORTAL_MESSAGE#", $htmltext);
            $htmltext = str_replace("\$CLIENT_MAC\$", "#CLIENT_MAC#", $htmltext);
            $htmltext = str_replace("\$CLIENT_IP\$", "#CLIENT_IP#", $htmltext);
            $htmltext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $htmltext);
            if ($cpcfg['preauthurl']) {
                $htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $htmltext);
                $htmltext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $htmltext);
            }
            fwrite($fd, $htmltext);
            fclose($fd);
        }
        unset($htmltext);


        /*if (is_array($cpcfg['page']) && $cpcfg['page']['errtext']) {
            $errtext = base64_decode($cpcfg['page']['errtext']);
        } else {

            $errtext = get_default_captive_portal_html();
        }

        $fd = @fopen("{$g['varetc_path']}/captiveportal_{$cpzone}.html", "w");
        if ($fd) {
            // Special case handling.  Convert so that we can pass this page
            // through the PHP interpreter later without clobbering the vars.
            $errtext = str_replace("\$PORTAL_ZONE\$", "#PORTAL_ZONE#", $errtext);
            $errtext = str_replace("\$PORTAL_REDIRURL\$", "#PORTAL_REDIRURL#", $errtext);
            $errtext = str_replace("\$PORTAL_MESSAGE\$", "#PORTAL_MESSAGE#", $errtext);
            $errtext = str_replace("\$CLIENT_MAC\$", "#CLIENT_MAC#", $errtext);
            $errtext = str_replace("\$CLIENT_IP\$", "#CLIENT_IP#", $errtext);
            $errtext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $errtext);
            if ($cpcfg['preauthurl']) {
                $errtext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $errtext);
                $errtext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $errtext);
            }
            fwrite($fd, $errtext);
            fclose($fd);
        }
        unset($errtext);


        if (is_array($cpcfg['page']) && $cpcfg['page']['logouttext']) {
            $logouttext = base64_decode($cpcfg['page']['logouttext']);
        } else {

            $translated_text1 = gettext("Redirecting...");
            $translated_text2 = gettext("Redirecting to");
            $translated_text3 = gettext("Logout");
            $translated_text4 = gettext("Click the button below to disconnect");
            $logouttext = <<<EOD
<html>
<head><title>{$translated_text1}</title></head>
<body>
<span style="font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
<b>{$translated_text2} <a href="<?=\$my_redirurl;?>"><?=\$my_redirurl;?></a>...</b>
</span>
<script type="text/javascript">
//<![CDATA[
LogoutWin = window.open('', 'Logout', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=256,height=64');
if (LogoutWin) {
	LogoutWin.document.write('<html>');
	LogoutWin.document.write('<head><title>{$translated_text3}</title></head>') ;
	LogoutWin.document.write('<body style="background-color:#435370">');
	LogoutWin.document.write('<div class="text-center" style="color: #ffffff; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">') ;
	LogoutWin.document.write('<b>{$translated_text4}</b><p />');
	LogoutWin.document.write('<form name="logouturl" value="<?=\$logouturl;?>">');
	LogoutWin.document.write('<form method="POST" action="<?=\$logouturl;?>">');
	LogoutWin.document.write('<input name="logout_id" type="hidden" value="<?=\$sessionid;?>" />');
	LogoutWin.document.write('<input name="zone" type="hidden" value="<?=\$cpzone;?>" />');
	LogoutWin.document.write('<input name="logout" type="submit" value="{$translated_text3}" />');
	LogoutWin.document.write('</form>');
	LogoutWin.document.write('</div></body>');
	LogoutWin.document.write('</html>');
	LogoutWin.document.close();
}
document.location.href="<?=\$my_redirurl;?>";
//]]>
</script>
</body>
</html>
EOD;
        }

        $fd = @fopen("{$g['varetc_path']}/captiveportal_{$cpzone}.html", "w");
        if ($fd) {
            fwrite($fd, $logouttext);
            fclose($fd);
        }
        unset($logouttext);*/

        /* write elements */
        captiveportal_write_elements();

        /* kill any running CP nginx instances */
        killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal.pid", 0.1);
        killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal-SSL.pid", 0.1);

        /* start up the webserving daemon */
        captiveportal_init_webgui_zone($cpcfg);

        /* Kill any existing prunecaptiveportal processes */
        if (file_exists("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid")) {
            killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");
        }

        /* start pruning process (interval defaults to 60 seconds) */
        mwexec("/usr/local/bin/minicron $croninterval {$g['varrun_path']}/cp_prunedb_{$cpzone}.pid " .
            "/etc/rc.prunecaptiveportal {$cpzone}");

        /* delete outdated radius server database if exist */
        unlink_if_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db");

        if (platform_booting()) {
            /* send Accounting-On to server */
            captiveportal_send_server_accounting('on');
            echo "done\n";

            if (isset($cpcfg['preservedb']) ||
                captiveportal_xmlrpc_sync_get_details($syncip, $syncport, $syncuser, $syncpass)) {

                $connected_users = captiveportal_read_db();
                if (!empty($connected_users)) {
                    echo "Reconnecting users to captive portal {$cpcfg['zone']}... ";
                    foreach ($connected_users as $user) {
                        captiveportal_reserve_ruleno($user['pipeno']);
                        $bw_up = intval($user['bw_up']);
                        $bw_down = intval($user['bw_down']);
                        $clientip = $user['ip'];
                        $clientmac = $user['mac'];
                        $bw_up_pipeno = $user['pipeno'];
                        $bw_down_pipeno = $user['pipeno'] + 1;

                        $rule_entry = "{$clientip}/" . (is_ipaddrv6($clientip) ? "128" : "32");
                        if (!isset($cpcfg['nomacfilter'])) {
                            $rule_entry .= ",{$clientmac}";
                        }

                        $_gb = @pfSense_ipfw_pipe("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
                        $_gb = @pfSense_ipfw_pipe("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");
                        $_gb = @pfSense_ipfw_table("{$cpzone}_auth_up", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_up_pipeno);
                        $_gb = @pfSense_ipfw_table("{$cpzone}_auth_down", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_down_pipeno);
                    }
                    echo "done\n";
                }
            }
        }

    } else {
        killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal.pid");
        killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal-SSL.pid");
        killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");
        #@unlink("{$g['varetc_path']}/captiveportal_{$cpzone}.html");
        #@unlink("{$g['varetc_path']}/captiveportal_{$cpzone}-error.html");
        #@unlink("{$g['varetc_path']}/captiveportal_{$cpzone}-logout.html");

        captiveportal_radius_stop_all(10); // NAS-Request

        captiveportal_filterdns_configure();

        /* remove old information */
        unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
        unlink_if_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db");
        unlink_if_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules");
        /* Release allocated pipes for this zone */
        $pipes_to_remove = captiveportal_free_dnrules();

        captiveportal_delete_rules($pipes_to_remove);

        if (empty($config['captiveportal'])) {
            set_single_sysctl("net.link.ether.ipfw", "0");
        } else {
            /* Deactivate ipfw(4) if not needed */
            $cpactive = false;
            if (is_array($config['captiveportal'])) {
                foreach ($config['captiveportal'] as $cpkey => $cp) {
                    if (isset($cp['enable'])) {
                        $cpactive = true;
                        break;
                    }
                }
            }
            if ($cpactive === false) {
                set_single_sysctl("net.link.ether.ipfw", "0");
            }
        }
    }

    unlock($captiveportallck);

    return 0;
}

function captiveportal_init_webgui() {
    global $config, $cpzone;

    if (is_array($config['captiveportal'])) {
        foreach ($config['captiveportal'] as $cpkey => $cp) {
            $cpzone = $cpkey;
            captiveportal_init_webgui_zone($cp);
        }
    }
}

function captiveportal_init_webgui_zonename($zone) {
    global $config, $cpzone;

    if (isset($config['captiveportal'][$zone])) {
        $cpzone = $zone;
        captiveportal_init_webgui_zone($config['captiveportal'][$zone]);
    }
}

function captiveportal_init_webgui_zone($cpcfg) {
    global $g, $config, $cpzone;

    if (!isset($cpcfg['enable'])) {
        return;
    }

    if (isset($cpcfg['httpslogin'])) {
        $cert = lookup_cert($cpcfg['certref']);
        $crt = base64_decode($cert['crt']);
        $key = base64_decode($cert['prv']);
        $ca = ca_chain($cert);

        /* generate nginx configuration */
        if (!empty($cpcfg['listenporthttps'])) {
            $listenporthttps = $cpcfg['listenporthttps'];
        } else {
            $listenporthttps = 8001 + $cpcfg['zoneid'];
        }
        system_generate_nginx_config("{$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal-SSL.conf",
            $crt, $key, $ca, "nginx-{$cpzone}-CaptivePortal-SSL.pid", $listenporthttps, "/usr/local/captiveportal",
            "cert-{$cpzone}-portal.pem", "ca-{$cpzone}-portal.pem", $cpzone, false);
    }

    /* generate nginx configuration */
    if (!empty($cpcfg['listenporthttp'])) {
        $listenporthttp = $cpcfg['listenporthttp'];
    } else {
        $listenporthttp = 8000 + $cpcfg['zoneid'];
    }
    system_generate_nginx_config("{$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal.conf",
        "", "", "", "nginx-{$cpzone}-CaptivePortal.pid", $listenporthttp, "/usr/local/captiveportal",
        "", "", $cpzone, false);

    @unlink("{$g['varrun']}/nginx-{$cpzone}-CaptivePortal.pid");
    /* attempt to start nginx */
    $res = mwexec("/usr/local/sbin/nginx -c {$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal.conf");

    /* fire up https instance */
    if (isset($cpcfg['httpslogin'])) {
        @unlink("{$g['varrun']}/nginx-{$cpzone}-CaptivePortal-SSL.pid");
        $res = mwexec("/usr/local/sbin/nginx -c {$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal-SSL.conf");
    }
    copy('/usr/local/captiveportal-crew.html', "{$g['varetc_path']}/captiveportal-crew.html");
}

function captiveportal_init_rules_byinterface($interface) {
    global $cpzone, $cpzoneid, $config;

    if (!is_array($config['captiveportal'])) {
        return;
    }

    foreach ($config['captiveportal'] as $cpkey => $cp) {
        $cpzone = $cpkey;
        $cpzoneid = $cp['zoneid'];
        $cpinterfaces = explode(",", $cp['interface']);
        if (in_array($interface, $cpinterfaces)) {
            captiveportal_init_rules();
            break;
        }
    }
}

/* Create basic rules used by all zones */
function captiveportal_init_general_rules($flush = false) {
    global $g;

    $flush_rule = '';
    if ($flush) {
        $flush_rule = 'flush';
    }

    /* Already loaded */
    if (!$flush && (mwexec("/sbin/ipfw list 1000", true) == 0)) {
        return;
    }

    $cprules = <<<EOD
{$flush_rule}
# Table with interfaces that have CP enabled
table cp_ifaces create type iface valtype skipto
# Redirect each CP interface to its specific rule
add 1000 skipto tablearg all from any to any via table(cp_ifaces)
# This interface has no cp zone configured
add 1100 allow all from any to any
# block everything else
add 65534 deny all from any to any
EOD;

    /* load rules */
    file_put_contents("{$g['tmp_path']}/ipfw.cp.rules", $cprules);
    mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw.cp.rules", true);
    @unlink("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules");
    unset($cprules);
}

/* Create a string with ipfw rule and increase rulenum */
function captiveportal_create_ipfw_rule($cmd, &$rulenum, $args) {
    $rule = "{$cmd} {$rulenum} {$args}\n";
    $rulenum++;

    return $rule;
}

/* Return first rule number for a cp zone */
function captiveportal_ipfw_ruleno($id) {
    global $g;

    return 2000 + $id * $g['captiveportal_rules_interval'];
}

/* reinit will disconnect all users, be careful! */
function captiveportal_init_rules($reinit = false) {
    global $config, $g, $cpzone;

    if (!isset($config['captiveportal'][$cpzone]['enable'])) {
        return;
    }

    captiveportal_load_modules();
    captiveportal_init_general_rules();

    /* Cleanup so nothing is leaked */
    captiveportal_free_dnrules(2000, 64500, false, $reinit);
    unlink_if_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules");

    $cpzoneid = $config['captiveportal'][$cpzone]['zoneid'];
    $skipto = captiveportal_ipfw_ruleno($cpzoneid);

    $cprules = '';

    $cpips = array();
    $ifaces = get_configured_interface_list();
    $cpinterfaces = explode(",", $config['captiveportal'][$cpzone]['interface']);
    $firsttime = 0;
    foreach ($cpinterfaces as $cpifgrp) {
        if (!isset($ifaces[$cpifgrp])) {
            continue;
        }
        $tmpif = get_real_interface($cpifgrp);
        if (empty($tmpif)) {
            continue;
        }

        $cpipm = get_interface_ip($cpifgrp);

        if (!is_ipaddr($cpipm)) {
            continue;
        }

        $cpips[] = $cpipm;
        if (is_array($config['virtualip']['vip'])) {
            foreach ($config['virtualip']['vip'] as $vip) {
                if (($vip['interface'] == $cpifgrp) &&
                    (($vip['mode'] == "carp") ||
                        ($vip['mode'] == "ipalias"))) {
                    $cpips[] = $vip['subnet'];
                }
            }
        }

        $cprules .= "table cp_ifaces add {$tmpif} {$skipto}\n";
    }
    if (count($cpips) > 0) {
        $cpactive = true;
    } else {
        return false;
    }

    $captiveportallck = try_lock("captiveportal{$cpzone}", 0);

    $tables = captiveportal_get_ipfw_table_names();

    $rulenum = $skipto;
    $cprules .= "table {$cpzone}_pipe_mac create type mac valtype pipe\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pipe tablearg MAC table({$cpzone}_pipe_mac)");
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "allow pfsync from any to any");
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "allow carp from any to any\n");
    $cprules .= "# layer 2: pass ARP\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pass layer2 mac-type arp,rarp");
    $cprules .= "# pfsense requires for WPA\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pass layer2 mac-type 0x888e,0x88c7");
    $cprules .= "# PPP Over Ethernet Session Stage/Discovery Stage\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pass layer2 mac-type 0x8863,0x8864\n");
    $cprules .= "# layer 2: block anything else non-IP(v4/v6)\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "deny layer2 not mac-type ip,ipv6");

    /* These tables contain host ips */
    $cprules .= "table {$cpzone}_host_ips create type addr\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pass ip from any to table({$cpzone}_host_ips) in");
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pass ip from table({$cpzone}_host_ips) to any out");
    foreach ($cpips as $cpip) {
        $cprules .= "table {$cpzone}_host_ips add {$cpip}\n";
    }
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pass ip from any to 255.255.255.255 in");
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pass ip from 255.255.255.255 to any out");

    /* Allowed ips */
    $cprules .= "table {$cpzone}_allowed_up create type addr valtype pipe\n";
    $cprules .= "table {$cpzone}_allowed_down create type addr valtype pipe\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pipe tablearg ip from table({$cpzone}_allowed_up) to any in");
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pipe tablearg ip from any to table({$cpzone}_allowed_down) in");
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pipe tablearg ip from table({$cpzone}_allowed_up) to any out");
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pipe tablearg ip from any to table({$cpzone}_allowed_down) out");

    /* Authenticated users rules. */
    if (!in_array("{$cpzone}_auth_up", $tables)) {
        $cprules .= "table {$cpzone}_auth_up create type addr valtype pipe\n";
    }
    if (!in_array("{$cpzone}_auth_down", $tables)) {
        $cprules .= "table {$cpzone}_auth_down create type addr valtype pipe\n";
    }
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pipe tablearg ip from table({$cpzone}_auth_up) to any layer2 in");
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pipe tablearg ip from any to table({$cpzone}_auth_down) layer2 out");

    if (!empty($config['captiveportal'][$cpzone]['listenporthttp'])) {
        $listenporthttp = $config['captiveportal'][$cpzone]['listenporthttp'];
    } else {
        $listenporthttp = 8000 + $cpzoneid;
    }

    if (isset($config['captiveportal'][$cpzone]['httpslogin'])) {
        if (!empty($config['captiveportal'][$cpzone]['listenporthttps'])) {
            $listenporthttps = $config['captiveportal'][$cpzone]['listenporthttps'];
        } else {
            $listenporthttps = 8001 + $cpzoneid;
        }
        if (!isset($config['captiveportal'][$cpzone]['nohttpsforwards'])) {
            $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
                "fwd 127.0.0.1,{$listenporthttps} tcp from any to any dst-port 443 in");
        }
    }

    $cprules .= "# redirect non-authenticated clients to captive portal\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "fwd 127.0.0.1,{$listenporthttp} tcp from any to any dst-port 80 in");
    $cprules .= "# let the responses from the captive portal web server back out\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "pass tcp from any to any out");
    $cprules .= "# This CP zone is over, skip to last rule\n";
    $cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
        "skipto 65534 all from any to any");

    /* generate passthru mac database */
    $cprules .= captiveportal_passthrumac_configure(true);
    $cprules .= "\n";

    /* allowed ipfw rules to make allowed ip work */
    $cprules .= captiveportal_allowedip_configure();

    /* allowed ipfw rules to make allowed hostnames work */
    $cprules .= captiveportal_allowedhostname_configure();

    /* load rules */
    captiveportal_delete_rules(array(), $reinit);
    file_put_contents("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules", $cprules);
    mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw_{$cpzone}.cp.rules", true);
    @unlink("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules");
    unset($cprules);

    captiveportal_filterdns_configure();

    if ($captiveportallck) {
        unlock($captiveportallck);
    }
}

/* Delete all rules related to specific cpzone */
function captiveportal_delete_rules($pipes_to_remove = array(), $clear_auth_rules = true) {
    global $g, $config, $cpzone;

    $cpzoneid = $config['captiveportal'][$cpzone]['zoneid'];

    $skipto1 = captiveportal_ipfw_ruleno($cpzoneid);
    $skipto2 = $skipto1 + $g['captiveportal_rules_interval'];

    $cp_ifaces = pfSense_ipfw_table_list("cp_ifaces");
    if (is_array($cp_ifaces)) {
        foreach ($cp_ifaces as $cp_iface) {
            if (empty($cp_iface['skipto']) ||
                $cp_iface['skipto'] != $skipto1) {
                continue;
            }

            pfSense_ipfw_table("cp_ifaces", IP_FW_TABLE_XDEL,
                $cp_iface['iface']);
        }
    }

    mwexec("/sbin/ipfw delete {$skipto1}-{$skipto2}", true);

    $tables = captiveportal_get_ipfw_table_names();

    $delrules = "";
    foreach ($tables as $table) {
        if (!$clear_auth_rules && substr($table, 0, strlen($cpzone . '_auth')) == $cpzone . '_auth') {
            continue;
        } else {
            $delrules .= "table {$table} destroy\n";
        }
    }

    foreach ($pipes_to_remove as $pipeno) {
        $delrules .= "pipe delete {$pipeno}\n";
    }

    if (empty($delrules)) {
        return;
    }

    file_put_contents("{$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules", $delrules);
    mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules", true);
    @unlink("{$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules");
}

/*
 * Remove clients that have been around for longer than the specified amount of time
 * db file structure:
 * timestamp,ipfw_rule_no,clientip,clientmac,username,sessionid,password,session_timeout,idle_timeout,session_terminate_time,interim_interval,traffic_quota,auth_method,context
 * (password is in Base64 and only saved when reauthentication is enabled)
 */
function captiveportal_prune_old() {
    global $g, $config, $cpzone, $cpzoneid;
    if (empty($cpzone)) {
        return;
    }

    $cpcfg = $config['captiveportal'][$cpzone];
    $vcpcfg = $config['voucher'][$cpzone];

    /* check for expired entries */
    $idletimeout = 0;
    $timeout = 0;
    if (!empty($cpcfg['timeout']) && is_numeric($cpcfg['timeout'])) {
        $timeout = $cpcfg['timeout'] * 60;
    }

    if (!empty($cpcfg['idletimeout']) && is_numeric($cpcfg['idletimeout'])) {
        $idletimeout = $cpcfg['idletimeout'] * 60;
    }

    /* check for entries exceeding their traffic quota */
    $trafficquota = 0;
    if (!empty($cpcfg['trafficquota']) && is_numeric($cpcfg['trafficquota'])) {
        $trafficquota = $cpcfg['trafficquota'] * 1048576;
    }
    /* Is there any job to do? If we are in High Availability sync, are we in backup mode ? */
    if ((!$timeout && !$idletimeout && !$trafficquota && !isset($cpcfg['reauthenticate']) &&
            !isset($cpcfg['radiussession_timeout']) && !isset($cpcfg['radiustraffic_quota']) &&
            !isset($vcpcfg['enable']) && !isset($cpcfg['radacct_enable'])) ||
        captiveportal_ha_is_node_in_backup_mode($cpzone)) {
        return;
    }


    /* Read database */
    /* NOTE: while this can be simplified in non radius case keep as is for now */
    $cpdb = captiveportal_read_db();
    $unsetindexes = array();
    $voucher_needs_sync = false;
    /*
     * Snapshot the time here to use for calculation to speed up the process.
     * If something is missed next run will catch it!
     */
    $pruning_time = time();
    foreach ($cpdb as $cpentry) {
        $stop_time = $pruning_time;

        $timedout = false;
        $term_cause = 1;
        /* hard timeout or session_timeout from radius if enabled */
        if (isset($cpcfg['radiussession_timeout'])) {
            $utimeout = (is_numeric($cpentry[7])) ? $cpentry[7] : $timeout;
        } else {
            $utimeout = $timeout;
        }
        if ($utimeout) {
            if (($pruning_time - $cpentry[0]) >= $utimeout) {
                $timedout = true;
                $term_cause = 5; // Session-Timeout
                $logout_cause = 'SESSION TIMEOUT';
            }
        }

        /* Session-Terminate-Time */
        if (!$timedout && !empty($cpentry[9])) {
            if ($pruning_time >= $cpentry[9]) {
                $timedout = true;
                $term_cause = 5; // Session-Timeout
                $logout_cause = 'SESSION TIMEOUT';
            }
        }

        /* check if an idle_timeout has been set and if its set change the idletimeout to this value */
        $uidletimeout = (is_numeric($cpentry[8])) ? $cpentry[8] : $idletimeout;
        /* if an idle timeout is specified, get last activity timestamp from ipfw */
        if (!$timedout && $uidletimeout > 0) {
            $lastact = captiveportal_get_last_activity($cpentry[2]);
            /*	If the user has logged on but not sent any traffic they will never be logged out.
             *	We "fix" this by setting lastact to the login timestamp.
             */
            $lastact = $lastact ? $lastact : $cpentry[0];
            if ($lastact && (($pruning_time - $lastact) >= $uidletimeout)) {
                $timedout = true;
                $term_cause = 4; // Idle-Timeout
                $logout_cause = 'IDLE TIMEOUT';
                if (!isset($config['captiveportal'][$cpzone]['includeidletime'])) {
                    $stop_time = $lastact;
                }
            }
        }

        /* if vouchers are configured, activate session timeouts */
        if (!$timedout && isset($vcpcfg['enable']) && !empty($cpentry[7])) {
            if ($pruning_time >= ($cpentry[0] + $cpentry[7])) {
                $timedout = true;
                $term_cause = 5; // Session-Timeout
                $logout_cause = 'SESSION TIMEOUT';
                $voucher_needs_sync = true;
            }
        }

        /* traffic quota, value retrieved from the radius attribute if the option is enabled */
        /*if (isset($cpcfg['radiustraffic_quota'])) {
            $utrafficquota = (is_numeric($cpentry[11])) ? $cpentry[11] : $trafficquota;
        } else {
            $utrafficquota = $trafficquota;
        }*/
        $used_mb   = (float)getcurquota_cached($cpentry[5], $cpentry[2]);
        $max_mb    = (float)getmaxquota_cached($cpentry[4]);
        $remain_mb = $max_mb - $used_mb;

        if (!$timedout) {
            if ($remain_mb <= 0) {
                $timedout     = true;
                $term_cause   = 10;
                $logout_cause = "QUOTA EXHAUSTED: used={$used_mb}MB max={$max_mb}MB";
            }
            // getVolume() 비교 로직 전체 제거
        }

        if ($timedout) {
            captiveportal_disconnect($cpentry, $term_cause, $stop_time);
            cp_wireless_auth($cpentry[4], $cpentry[3], $cpentry[2], $logout_cause);
            $unsetindexes[] = $cpentry[5];
        }

        /* do periodic reauthentication? For Radius servers, send accounting updates? */
        if (!$timedout) {
            //Radius servers : send accounting
            if (isset($cpcfg['radacct_enable']) && $cpentry['authmethod'] === 'radius') {
                if (substr($cpcfg['reauthenticateacct'], 0, 9) === "stopstart") {
                    /* stop and restart accounting */
                    if ($cpcfg['reauthenticateacct'] === "stopstartfreeradius") {
                        $rastart_time = 0;
                        $rastop_time = 60;
                    } else {
                        $rastart_time = $cpentry[0];
                        $rastop_time = time();
                    }
                    captiveportal_send_server_accounting('stop',
                        $cpentry[1], // ruleno
                        $cpentry[4], // username
                        $cpentry[2], // clientip
                        $cpentry[3], // clientmac
                        $cpentry[5], // sessionid
                        $rastart_time, // start time
                        $rastop_time, // Stop Time
                        10); // NAS Request
                    $clientsn = (is_ipaddrv6($cpentry[2])) ? 128 : 32;
                    pfSense_ipfw_table_zerocnt("{$cpzone}_auth_up", "{$cpentry[2]}/{$clientsn}");
                    pfSense_ipfw_table_zerocnt("{$cpzone}_auth_down", "{$cpentry[2]}/{$clientsn}");
                    if ($cpcfg['reauthenticateacct'] == "stopstartfreeradius") {
                        /* Need to pause here or the FreeRADIUS server gets confused about packet ordering. */
                        sleep(1);
                    }
                    captiveportal_send_server_accounting('start',
                        $cpentry[1], // ruleno
                        $cpentry[4], // username
                        $cpentry[2], // clientip
                        $cpentry[3], // clientmac
                        $cpentry[5]); // sessionid
                } else if ($cpcfg['reauthenticateacct'] == "interimupdate") {
                    // interim_interval(DB col 10) 이 60 초과로 설정된 경우에만 주기를 적용한다.
                    // 미설정/≤60 이면 interval=0 → 매 prune 사이클마다 송신(가장 견고).
                    $interval = (!empty($cpentry[10]) && $cpentry[10] > 60) ? (int)$cpentry[10] : 0;

                    // 경과시간(threshold) 기반 송신.
                    // 기존 modulo 창(session_time % interval <= 59) 은 minicron 드리프트로
                    // 60초 창을 통째로 건너뛰어 interim 이 누락(→ 마지막 구간 손실 확률↑)됐다.
                    // "마지막 송신 후 interval 초 이상 경과" 는 한 번 참이 되면 송신 전까지
                    // 계속 참이라 드리프트로 건너뛸 수 없다. 마지막 송신 시각은 /var/run 마커의
                    // mtime 으로 추적한다(리부트 시 자동 정리, 세션 종료 시 disconnect 가 제거).
                    $do_update = false;
                    $mfile = '';
                    if ($interval === 0) {
                        $do_update = true;
                    } else {
                        $sid_safe = preg_replace('/[^0-9a-zA-Z]/', '', (string)$cpentry[5]);
                        $mfile = "{$g['varrun_path']}/cp_lastinterim_{$cpzone}_{$sid_safe}";
                        $last_sent = @filemtime($mfile);
                        if ($last_sent === false) {
                            // 마커 없음(첫 송신 전): 세션이 interval 이상 지속됐을 때부터 송신.
                            if (($pruning_time - $cpentry[0]) >= $interval) {
                                $do_update = true;
                            }
                        } else if (($pruning_time - $last_sent) >= $interval) {
                            $do_update = true;
                        }
                    }

                    if ($do_update) {
                        captiveportal_send_server_accounting('update',
                            $cpentry[1], // ruleno
                            $cpentry[4], // username
                            $cpentry[2], // clientip
                            $cpentry[3], // clientmac
                            $cpentry[5], // sessionid
                            $cpentry[0]); // start time
                        if ($mfile !== '') {
                            @touch($mfile, $pruning_time);
                        }
                    }
                }
            }

            /* check this user again */
            if (isset($cpcfg['reauthenticate']) && $cpentry['context'] !== 'voucher') {
                $auth_result = captiveportal_authenticate_user(
                    $cpentry[4], // username
                    base64_decode($cpentry[6]), // password
                    $cpentry[3], // clientmac
                    $cpentry[2], // clientip
                    $cpentry[1], // ruleno
                    $cpentry['context']); // context
                if ($auth_result['result'] === false) {
                    captiveportal_disconnect($cpentry, 17);
                    captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "DISCONNECT - REAUTHENTICATION FAILED", $auth_list['reply_message']);
                    $unsetindexes[] = $cpentry[5];
                } else if ($auth_result['result'] === true) {
                    if ($cpentry['authmethod'] !== $auth_result['auth_method']) {
                        // if the user got authenticated against another server type:  we update the database
                        if (!empty($cpentry[5])) {
                            captiveportal_update_entry($cpentry['sessionid'], $auth_result['auth_method'], 'authmethod');
                            captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CHANGED AUTHENTICATION SERVER", $auth_list['reply_message']);
                        }
                        // User was logged on a RADIUS server, but is now logged in by another server type : we send an accounting Stop
                        if (isset($config['captiveportal'][$cpzone]['radacct_enable']) && $cpentry['authmethod'] == 'radius') {
                            if ($cpcfg['reauthenticateacct'] === "stopstartfreeradius") {
                                $rastart_time = 0;
                                $rastop_time = 60;
                            } else {
                                $rastart_time = $cpentry[0];
                                $rastop_time = time();
                            }
                            captiveportal_send_server_accounting('stop',
                                $cpentry[1], // ruleno
                                $cpentry[4], // username
                                $cpentry[2], // clientip
                                $cpentry[3], // clientmac
                                $cpentry[5], // sessionid
                                $rastart_time, // start time
                                $rastop_time, // Stop Time
                                3); // Lost Service
                            // User was logged on a non-RADIUS Server but is now logged in by a RADIUS server : we send an accounting Start
                        } else if(isset($config['captiveportal'][$cpzone]['radacct_enable']) && $auth_result['auth_method'] === 'radius') {
                            captiveportal_send_server_accounting('start',
                                $cpentry[1], // ruleno
                                $cpentry[4], // username
                                $cpentry[2], // clientip
                                $cpentry[3], // clientmac
                                $cpentry[5], // sessionid
                                $cpentry[0]); // start_time
                        }
                    }
                    captiveportal_reapply_attributes($cpentry, $auth_result['attributes']);
                }
            }
        }
    }
    unset($cpdb);

    captiveportal_prune_old_automac();

    if ($voucher_needs_sync == true) {
        /* perform in-use vouchers expiration using check_reload_status */
        send_event("service sync vouchers");
    }

    /* write database */
    if (!empty($unsetindexes)) {
        captiveportal_remove_entries($unsetindexes);
    }
}
function getcurquota_cached(string $sessionid, string $clientip): int {
    static $c = [];
    $k = $sessionid.'|'.$clientip;
    if (!isset($c[$k])) $c[$k] = (int)getcurquota($sessionid, $clientip);
    return $c[$k];
}
function getmaxquota_cached(string $user): int {
    static $c = [];
    if (!isset($c[$user])) $c[$user] = (int)getmaxquota($user);
    return $c[$user];
}

function captiveportal_prune_old_automac() {
    global $g, $config, $cpzone, $cpzoneid;

    if (is_array($config['captiveportal'][$cpzone]['passthrumac']) &&
        isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
        $tmpvoucherdb = array();
        $macrules = "";
        $writecfg = false;
        foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $eid => $emac) {
            if ($emac['logintype'] != "voucher") {
                continue;
            }
            if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
                if (isset($tmpvoucherdb[$emac['username']])) {
                    $temac = $config['captiveportal'][$cpzone]['passthrumac'][$tmpvoucherdb[$emac['username']]];
                    $pipeno = captiveportal_get_dn_passthru_ruleno($temac['mac']);
                    if ($pipeno) {
                        captiveportal_free_dn_ruleno($pipeno);
                        $macrules .= "table {$cpzone}_pipe_mac delete any,{$temac['mac']}\n";
                        $macrules .= "table {$cpzone}_pipe_mac delete {$temac['mac']},any\n";
                        $macrules .= "pipe delete {$pipeno}\n";
                        ++$pipeno;
                        $macrules .= "pipe delete {$pipeno}\n";
                    }
                    $writecfg = true;
                    captiveportal_logportalauth($temac['username'], $temac['mac'],
                        $temac['ip'], "DUPLICATE {$temac['username']} LOGIN - TERMINATING OLD SESSION");
                    unset($config['captiveportal'][$cpzone]['passthrumac'][$tmpvoucherdb[$emac['username']]]);
                }
                $tmpvoucherdb[$emac['username']] = $eid;
            }
        }
        unset($tmpvoucherdb);
        if (!empty($macrules)) {
            @file_put_contents("{$g['tmp_path']}/macentry.prunerules.tmp", $macrules);
            unset($macrules);
            mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry.prunerules.tmp");
        }
        if ($writecfg === true) {
            write_config("Prune session for auto-added macs");
        }
    }
}

/* remove a single client according to the DB entry */
function captiveportal_disconnect($dbent, $term_cause = 1, $stop_time = null, $carp_loop = false) {
    global $g, $config, $cpzone, $cpzoneid;

    $stop_time = (empty($stop_time)) ? time() : $stop_time;

    /* interimupdate 송신 추적 마커 정리: 세션 종료 시 /var/run 마커 제거(누수 방지). */
    if (!empty($dbent[5])) {
        @unlink("{$g['varrun_path']}/cp_lastinterim_{$cpzone}_" . preg_replace('/[^0-9a-zA-Z]/', '', (string)$dbent[5]));
    }

    /* this client needs to be deleted - remove ipfw rules */
    if (isset($config['captiveportal'][$cpzone]['radacct_enable']) && $dbent['authmethod'] == 'radius') {
        if ($config['captiveportal'][$cpzone]['reauthenticateacct'] == "stopstartfreeradius") {
            /*
             * Interim updates are on so the session time must be
             * reported as the elapsed time since the previous
             * interim update.
             */
            $session_time = ($stop_time - $dbent[0]) % 60;
            $start_time = $stop_time - $session_time;
        } else {
            $start_time = $dbent[0];
        }
        captiveportal_send_server_accounting('stop',
            $dbent[1], // ruleno
            $dbent[4], // username
            $dbent[2], // clientip
            $dbent[3], // clientmac
            $dbent[5], // sessionid
            $start_time, // start time
            $stop_time, // stop time
            $term_cause); // Acct-Terminate-Cause
    }

    if (is_ipaddr($dbent[2])) {
        /*
         * Delete client's ip entry from tables auth_up and auth_down.
         * It's not necessary to explicit specify mac address here
         */
        $cpsession = captiveportal_isip_logged($dbent[2]);
        if (!empty($cpsession)) {
            $clientsn = (is_ipaddrv6($dbent[2])) ? 128 : 32;
            pfSense_ipfw_table("{$cpzone}_auth_up",
                IP_FW_TABLE_XDEL, "{$dbent[2]}/{$clientsn}");
            pfSense_ipfw_table("{$cpzone}_auth_down",
                IP_FW_TABLE_XDEL, "{$dbent[2]}/{$clientsn}");
        }
        /* XXX: Redundant?! Ensure all pf(4) states are killed. */
        /* Deferred: 즉시 kill 하면 HTTP 응답 전에 포털 TCP 연결이 RST → ~19초 지연.
         * 웹 context (index.php): fastcgi_finish_request() 이후 shutdown 함수가 처리.
         * CLI context (cron/RC): 아래 static 등록 shutdown 함수가 스크립트 종료 시 처리. */
        $GLOBALS['_cp_deferred_state_kills'][] = $dbent[2];
        static $_cp_kill_shutdown_registered = false;
        if (!$_cp_kill_shutdown_registered) {
            $_cp_kill_shutdown_registered = true;
            // 즉시 kill 금지: 응답/연결 종료 전에 RST 되면 ~19초 지연.
            // detached 백그라운드(짧은 sleep)로 분리 — cp_flush_deferred_state_kills().
            register_shutdown_function(static function() {
                if (function_exists('cp_flush_deferred_state_kills')) {
                    cp_flush_deferred_state_kills();
                }
            });
        }
    }

    /*
     * These are the pipe numbers we use to control traffic shaping for
     * each logged in user via captive portal
     * We could get an error if the pipe doesn't exist but everything
     * should still be fine
     */
    if (!empty($dbent[1])) {
        /*
         * Call captiveportal_free_dnrules() in dry_run mode to verify
         * if there are pipes to be removed and prevent the attempt to
         * delete invalid pipes
         */
        $removed_pipes = captiveportal_free_dnrules($dbent[1],
            $dbent[1]+1, true);

        if (!empty($removed_pipes)) {
            $_gb = @pfSense_ipfw_pipe("pipe delete {$dbent[1]}");
            $_gb = @pfSense_ipfw_pipe("pipe delete " .
                ($dbent[1]+1));

            /*
             * Release the ruleno so it can be reallocated to new
             * clients
             */
            captiveportal_free_dn_ruleno($dbent[1]);
        }
    }

    // XMLRPC Call over to the backup node if necessary
    if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport,
        $syncuser, $syncpass, $carp_loop)) {
        $rpc_client = new pfsense_xmlrpc_client();
        $rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
        $rpc_client->set_noticefile("CaptivePortalUserSync");
        $arguments = array(
            'sessionid' => $dbent[5],
            'term_cause' => $term_cause,
            'stop_time' => $stop_time
        );

        $rpc_client->xmlrpc_method('captive_portal_sync',
            array(
                'op' => 'disconnect_user',
                'zone' => $cpzone,
                'session' => base64_encode(serialize($arguments))
            )
        );
    }
    return true;
}

/* remove a single client by sessionid */
function captiveportal_disconnect_client($sessionid, $term_cause = 1, $logoutReason = "LOGOUT") {
    global $g, $config;

    $sessionid = SQLite3::escapeString($sessionid);
    /* read database */
    $result = captiveportal_read_db("WHERE sessionid = '{$sessionid}'");

    /* find entry */
    if (!empty($result)) {

        foreach ($result as $cpentry) {
            captiveportal_disconnect($cpentry, $term_cause);
            cp_wireless_auth($cpentry[4], $cpentry[3], $cpentry[2], "DISCONNECT");

        }
        captiveportal_remove_entries(array($sessionid));
        unset($result);
    }
}

/* remove all clients */
function captiveportal_disconnect_all($term_cause = 6, $logoutReason = "DISCONNECT", $carp_loop = false) {
    global $g, $config, $cpzone, $cpzoneid;

    if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport, $syncuser, $syncpass, $carp_loop)) {
        $rpc_client = new pfsense_xmlrpc_client();
        $rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
        $rpc_client->set_noticefile("CaptivePortalUserSync");
        $arguments = array(
            'term_cause' => $term_cause,
            'logout_reason' => $logoutReason
        );

        $rpc_client->xmlrpc_method('captive_portal_sync',
            array(
                'op' => 'disconnect_all',
                'zone' => $cpzone,
                'arguments' => base64_encode(serialize($arguments))
            )
        );
    }
    /* check if we're pruning old entries and eventually wait */
    $rcprunelock = try_lock("rcprunecaptiveportal{$cpzone}", 15);

    /* if we still don't have the lock, unlock forcefully and take it */
    if (!$rcprunelock) {
        log_error("CP zone ${cpzone}: could not obtain the lock for more than 15 seconds, lock taken forcefully to disconnect all users");
        unlock_force("rcprunecaptiveportal{$cpzone}");
        $rcprunelock = lock("rcprunecaptiveportal{$cpzone}", LOCK_EX);
    }

    /* take a lock so new users won't be able to log in */
    $cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

    captiveportal_radius_stop_all($term_cause, $logoutReason);

    /* remove users from the database */
    $cpdb = captiveportal_read_db();
    $unsetindexes = array_column($cpdb,5);
    if (!empty($unsetindexes)) {
        // High Availability : do not sync removed entries
        captiveportal_remove_entries($unsetindexes, true);
    }

    /* reinit ipfw rules */
    captiveportal_init_rules(true);

    unlock($cpdblck);
    unlock($rcprunelock);
    return true;
}


function captiveportal_radius_stop_all($term_cause = 6, $logoutReason = "DISCONNECT") {
    global $g, $config, $cpzone, $cpzoneid;

    $cpdb = captiveportal_read_db();
    $radacct = isset($config['captiveportal'][$cpzone]['radacct_enable']);
    $stop_time = time(); // ✅ 추가

    foreach ($cpdb as $cpentry) {
        if ($cpentry['authmethod'] === 'radius' && $radacct) {
            if ($config['captiveportal'][$cpzone]['reauthenticateacct'] == "stopstartfreeradius") {
                $session_time = (time() - $cpentry[0]) % 60;
                $start_time = time() - $session_time;
            } else {
                $start_time = $cpentry[0];
            }
            captiveportal_send_server_accounting('stop',
                $cpentry[1],
                $cpentry[4],
                $cpentry[2],
                $cpentry[3],
                $cpentry[5],
                $start_time,
                $stop_time,   // ✅ 이제 정상
                $term_cause
            );
        }
        captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], $logoutReason);
    }
    unset($cpdb);
}



function captiveportal_passthrumac_configure_entry($macent, $pipeinrule = false) {
    global $config, $g, $cpzone;

    $bwUp = 0;
    if (!empty($macent['bw_up'])) {
        $bwUp = $macent['bw_up'];
    } elseif (isset($config['captiveportal'][$cpzone]['peruserbw']) &&
        !empty($config['captiveportal'][$cpzone]['bwdefaultup'])) {
        $bwUp = $config['captiveportal'][$cpzone]['bwdefaultup'];
    }
    $bwDown = 0;
    if (!empty($macent['bw_down'])) {
        $bwDown = $macent['bw_down'];
    } elseif (isset($config['captiveportal'][$cpzone]['peruserbw']) &&
        !empty($config['captiveportal'][$cpzone]['bwdefaultdn'])) {
        $bwDown = $config['captiveportal'][$cpzone]['bwdefaultdn'];
    }

    if ($macent['action'] == 'pass') {
        $rules = "";

        $pipeno = captiveportal_get_next_dn_ruleno('pipe_mac');

        $pipeup = $pipeno;
        if ($pipeinrule == true) {
            $_gb = @pfSense_ipfw_pipe("pipe {$pipeno} config bw {$bwUp}Kbit/s queue 100 buckets 16");
        } else {
            $rules .= "pipe {$pipeno} config bw {$bwUp}Kbit/s queue 100 buckets 16\n";
        }

        $pipedown = $pipeno + 1;
        if ($pipeinrule == true) {
            $_gb = @pfSense_ipfw_pipe("pipe {$pipedown} config bw {$bwDown}Kbit/s queue 100 buckets 16");
        } else {
            $rules .= "pipe {$pipedown} config bw {$bwDown}Kbit/s queue 100 buckets 16\n";
        }

        $rules .= "table {$cpzone}_pipe_mac add any,{$macent['mac']} {$pipeup}\n";
        $rules .= "table {$cpzone}_pipe_mac add {$macent['mac']},any {$pipedown}\n";
    }

    return $rules;
}

function captiveportal_passthrumac_delete_entry($macent) {
    global $cpzone;
    $rules = "";

    if ($macent['action'] == 'pass') {
        $pipeno = captiveportal_get_dn_passthru_ruleno($macent['mac']);

        if (!empty($pipeno)) {
            captiveportal_free_dn_ruleno($pipeno);
            $rules .= "table {$cpzone}_pipe_mac delete any,{$macent['mac']}\n";
            $rules .= "table {$cpzone}_pipe_mac delete {$macent['mac']},any\n";
            $rules .= "pipe delete " . $pipeno . "\n";
            $rules .= "pipe delete " . ++$pipeno . "\n";
        }
    }

    return $rules;
}

function captiveportal_passthrumac_configure($filename = false, $startindex = 0, $stopindex = 0) {
    global $config, $g, $cpzone;

    $rules = "";

    if (is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
        if ($stopindex > 0) {
            $fd = fopen($filename, "w");
            for ($idx = $startindex; $idx <= $stopindex; $idx++) {
                if (isset($config['captiveportal'][$cpzone]['passthrumac'][$idx])) {
                    $rules = captiveportal_passthrumac_configure_entry($config['captiveportal'][$cpzone]['passthrumac'][$idx]);
                    fwrite($fd, $rules);
                }
            }
            fclose($fd);

            return;
        } else {
            $nentries = count($config['captiveportal'][$cpzone]['passthrumac']);
            if ($nentries > 2000) {
                $nloops = $nentries / 1000;
                $remainder= $nentries % 1000;
                for ($i = 0; $i < $nloops; $i++) {
                    mwexec_bg("/usr/local/sbin/fcgicli -f /etc/rc.captiveportal_configure_mac -d \"cpzone={$cpzone}&startidx=" . ($i * 1000) . "&stopidx=" . ((($i+1) * 1000) - 1) . "\"");
                }
                if ($remainder > 0) {
                    mwexec_bg("/usr/local/sbin/fcgicli -f /etc/rc.captiveportal_configure_mac -d \"cpzone={$cpzone}&startidx=" . ($i * 1000) . "&stopidx=" . (($i* 1000) + $remainder) ."\"");
                }
            } else {
                foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $macent) {
                    $rules .= captiveportal_passthrumac_configure_entry($macent, true);
                }
            }
        }
    }

    return $rules;
}

function captiveportal_passthrumac_findbyname($username) {
    global $config, $cpzone;

    if (is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
        foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $macent) {
            if ($macent['username'] == $username) {
                return $macent;
            }
        }
    }
    return NULL;
}

/*
 * table (3=IN)/(4=OUT) hold allowed ip's without bw limits
 */
function captiveportal_allowedip_configure_entry($ipent, $ishostname = false) {
    global $g, $config, $cpzone;

    /*  Instead of copying this entire function for something
     *  easy such as hostname vs ip address add this check
     */
    if ($ishostname === true) {
        if (!platform_booting()) {
            $ipaddress = resolve_host_addresses($ipent['hostname'], array(DNS_A), false);
            if (empty($ipaddress)) {
                return;
            }
        } else {
            $ipaddress = array();
        }
    } else {
        $ipaddress = array($ipent['ip']);
    }

    $rules = "";
    $cp_filterdns_conf = "";
    $enBwup = 0;
    if (!empty($ipent['bw_up'])) {
        $enBwup = intval($ipent['bw_up']);
    } elseif (isset($config['captiveportal'][$cpzone]['peruserbw']) &&
        !empty($config['captiveportal'][$cpzone]['bwdefaultup'])) {
        $enBwup = $config['captiveportal'][$cpzone]['bwdefaultup'];
    }
    $enBwdown = 0;
    if (!empty($ipent['bw_down'])) {
        $enBwdown = intval($ipent['bw_down']);
    } elseif (isset($config['captiveportal'][$cpzone]['peruserbw']) &&
        !empty($config['captiveportal'][$cpzone]['bwdefaultdn'])) {
        $enBwdown = $config['captiveportal'][$cpzone]['bwdefaultdn'];
    }

    $pipeup = captiveportal_get_next_dn_ruleno('allowed');
    $_gb = @pfSense_ipfw_pipe("pipe {$pipeup} config bw {$enBwup}Kbit/s queue 100 buckets 16");
    $pipedown = $pipeup + 1;
    $_gb = @pfSense_ipfw_pipe("pipe {$pipedown} config bw {$enBwdown}Kbit/s queue 100 buckets 16");

    if ($ishostname === true) {
        $cp_filterdns_conf .= "ipfw {$ipent['hostname']} {$cpzone}_allowed_up pipe {$pipeup}\n";
        $cp_filterdns_conf .= "ipfw {$ipent['hostname']} {$cpzone}_allowed_down pipe {$pipedown}\n";
        if (!is_ipaddr($ipaddress[0])) {
            return array("", $cp_filterdns_conf);
        }
    }

    $subnet = "";
    if (!empty($ipent['sn'])) {
        $subnet = "/{$ipent['sn']}";
    }
    foreach ($ipaddress as $ip) {
        $rules .= "table {$cpzone}_allowed_up add {$ip}{$subnet} {$pipeup}\n";
        $rules .= "table {$cpzone}_allowed_down add {$ip}{$subnet} {$pipedown}\n";
    }

    if ($ishostname === true) {
        return array($rules, $cp_filterdns_conf);
    } else {
        return $rules;
    }
}

function captiveportal_allowedhostname_configure() {
    global $config, $g, $cpzone, $cpzoneid;

    $rules = "";
    if (!is_array($config['captiveportal'][$cpzone]['allowedhostname'])) {
        return $rules;
    }

    $rules = "\n# captiveportal_allowedhostname_configure()\n";
    $cp_filterdns_conf = "";
    foreach ($config['captiveportal'][$cpzone]['allowedhostname'] as $hostnameent) {
        $tmprules = captiveportal_allowedip_configure_entry($hostnameent, true);
        $rules .= $tmprules[0];
        $cp_filterdns_conf .= $tmprules[1];
    }
    $cp_filterdns_filename = "{$g['varetc_path']}/filterdns-{$cpzone}-captiveportal.conf";
    @file_put_contents($cp_filterdns_filename, $cp_filterdns_conf);
    unset($cp_filterdns_conf);

    return $rules;
}

function captiveportal_filterdns_configure() {
    global $config, $g, $cpzone, $cpzoneid;

    $cp_filterdns_filename = $g['varetc_path'] .
        "/filterdns-{$cpzone}-captiveportal.conf";

    if (isset($config['captiveportal'][$cpzone]['enable']) &&
        is_array($config['captiveportal'][$cpzone]['allowedhostname']) &&
        file_exists($cp_filterdns_filename)) {
        if (isvalidpid($g['varrun_path'] .
            "/filterdns-{$cpzone}-cpah.pid")) {
            sigkillbypid($g['varrun_path'] .
                "/filterdns-{$cpzone}-cpah.pid", "HUP");
        } else {
            mwexec("/usr/local/sbin/filterdns -p " .
                "{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid" .
                " -i 300 -c {$cp_filterdns_filename} -d 1");
        }
    } else {
        killbypid("{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid");
        @unlink("{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid");
    }

    return $rules;
}

function captiveportal_allowedip_configure() {
    global $config, $g, $cpzone;

    $rules = "";
    if (is_array($config['captiveportal'][$cpzone]['allowedip'])) {
        foreach ($config['captiveportal'][$cpzone]['allowedip'] as $ipent) {
            $rules .= captiveportal_allowedip_configure_entry($ipent);
        }
    }

    return $rules;
}

/* get last activity timestamp given client IP address */
function captiveportal_get_last_activity($ip) {
    global $cpzone;

    /* Reading only from one of the tables is enough of approximation. */
    $tables = array("{$cpzone}_allowed_up", "{$cpzone}_auth_up");

    foreach ($tables as $table) {
        $ipfw = pfSense_ipfw_table_lookup($table, $ip);
        if (is_array($ipfw)) {
            /* Workaround for #46652 */
            if ($ipfw['packets'] > 0) {
                return $ipfw['timestamp'];
            } else {
                return 0;
            }
        }
    }

    return 0;
}


if (!function_exists('cp_wireless_log')) {
    function cp_wireless_log(string $message, string $tag = 'logportal', string $process = 'captiveportal'): bool
    {
        global $cpzone;

        $logfile = '/var/log/wireless.log';

        // 로그 인젝션/줄바꿈/탭 방지
        $message = trim(str_replace(["\r", "\n", "\t"], ' ', $message));
        $message = preg_replace('/\s+/', ' ', $message);

        $tag = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $tag);
        if ($tag === '') {
            $tag = 'logportal';
        }

        $process = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $process);
        if ($process === '') {
            $process = 'logportal';
        }

        $zone = '';
        if (!empty($cpzone)) {
            $zone = " zone={$cpzone}";
        }

        $pid = getmypid();

        /*
         * pfSense log viewer 정상 인식 포맷:
         * May 6 03:50:04  capt: CAPt[45228] CaptivePortal zone=crew - DISCONNECT: ...
         */
        $line = sprintf(
                "%s %s: %s[%d] CaptivePortal%s - %s\n",
                date('M j H:i:s'),
                $tag,
                $process,
                $pid,
                $zone,
                $message
        );

        $ret = @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);

        if ($ret === false) {
            error_log("[cp_wireless_log] failed to write {$logfile}: {$message}");
            return false;
        }

        return true;
    }
}


/* log successful captive portal authentication to syslog */
/* part of this code from php.net */
function captiveportal_logportalauth($user, $mac, $ip, $status, $message = null) {
    // Log it
    if (!$message) {
        $message = "{$status}: {$user}, {$mac}, {$ip}";
    } else {
        $message = trim($message);
        $message = "{$status}: {$user}, {$mac}, {$ip}, {$message}";
    }
    captiveportal_syslog($message);
}
function cp_wireless_auth($user, $mac, $ip, $status, $message = null): bool {
    $user   = trim((string)$user);
    $mac    = trim((string)$mac);
    $ip     = trim((string)$ip);
    $status = trim((string)$status);

    if ($message === null || $message === '') {
        $logmsg = "{$status}: {$user}, {$mac}, {$ip}";
    } else {
        $message = trim((string)$message);
        $logmsg = "{$status}: {$user}, {$mac}, {$ip}, {$message}";
    }

    if (!function_exists('cp_wireless_log')) {
        error_log("[CP] cp_wireless_auth failed: cp_wireless_log() not found");
        return false;
    }

    $ret = cp_wireless_log($logmsg);

    if ($ret === false) {
        error_log("[CP] cp_wireless_auth failed to write log: {$logmsg}");
    }

    return $ret;
}

/* log simple messages to syslog */
function captiveportal_syslog($message) {
    global $cpzone;

    $message = trim($message);
    $message = "Zone: {$cpzone} - {$message}";
    openlog("logportalauth", LOG_PID, LOG_LOCAL4);
    // Log it
    syslog(LOG_INFO, $message);
    closelog();
}

/* Authenticate users using Authentication Backend */
function captiveportal_authenticate_user(&$login = '', &$password = '', $clientmac = '', $clientip = '', $pipeno = 'null', $context = 'first') {
    if($login===''){
        return array('result'=> false, 'attributes'=>array(), 'auth_method' =>'', 'login_status'=> 'FAILURE', 'login_message' => "Username blank");
    }
    global $g, $config, $cpzone;
    $cpcfg = $config['captiveportal'][$cpzone];

    $login_status = 'FAILURE';
    $login_msg = gettext('Invalid credentials specified');
    $reply_attributes = array();
    $auth_method = '';
    $auth_result = null;

    foreach($config['installedpackages']['freeradius']['config'] as $item => $value){
        if($login==$value['varusersusername']){
            $userindex=$item;
        }
    }
    if (!isset($userindex)) {
        return array(
            'result'        => false,
            'attributes'    => $reply_attributes,
            'auth_method'   => $auth_method,
            'login_status'  => $login_status,
            'login_message' => "$login - User doesn't exist!!"
        );
    } else {
        // 사용자 확인됨. 넘어감
        $uconf =& $config['installedpackages']['freeradius']['config'][$userindex];

        // 사용자 업데이트 상황이 있는지 확인
        if (!empty($uconf['varusersmodified']) && isset($login) && $login !== '') {

            // resetquota가 있으면 사용량 파일 삭제 (메인 + 세션파일)
            if (!empty($uconf['varusersresetquota'])) {
                unset($uconf['varusersresetquota']);

                $timerange = strtolower($uconf['varuserspointoftime'] ?? '');

                // timerange 화이트리스트 (예: daily/weekly/monthly)
                $allowed_ranges = array('daily', 'weekly', 'monthly');
                if (in_array($timerange, $allowed_ranges, true)) {

                    if (preg_match('/^[A-Za-z0-9_.-]+$/', $login)) {
                        $dir  = "/var/log/radacct/datacounter/{$timerange}";
                        $base = "{$dir}/used-octets-{$login}";

                        // 1) 메인 파일 삭제
                        unlink_if_exists($base);

                        // 2) 세션 파일 삭제 (used-octets-username-*)
                        $session_files = glob($base . '-*');
                        if (is_array($session_files)) {
                            foreach ($session_files as $f) {
                                if (is_file($f)) {
                                    @unlink($f);
                                }
                            }
                        }
                    } else {
                        // 필요 시 로깅
                        // log_error("Invalid login format for resetquota: " . $login);
                    }
                } else {
                    // 필요 시 로깅
                    // log_error("Invalid timerange for resetquota: " . $timerange);
                }
            }

            // 마지막에 modified 플래그 해제 (처리 완료 후)
            unset($uconf['varusersmodified']);
            write_config("freeradius user update");

            // NOTE: freeradius_update_user() 는 여기서 호출하지 않음.
            // 이유:
            //   1) 쿼터 초과 여부는 datacounter_auth.sh 가 used-octets 파일을 읽어
            //      결정하며, 위 단계에서 해당 파일을 이미 삭제했으므로 radiusd 재로드
            //      없이도 RADIUS 인증이 즉시 통과된다.
            //   2) freeradius_update_user() 는 내부에서 kill -HUP radiusd 를 실행해
            //      radiusd 가 설정을 다시 읽는 동안 RADIUS 인증 요청이 5초 타임아웃으로
            //      실패하여 로그인에 10초 이상 지연이 발생한다.
            //   3) write_config() 가 이미 위에서 호출됐으므로 중복 호출도 없어진다.
            // 사용자 속성(비밀번호 등)이 변경된 경우에는 crew_account.php 저장 시점에
            // freeradius_update_user() 가 이미 호출되므로 여기서 다시 호출할 필요가 없다.
        }
    }
    //////////////////////////////////////////////////////////////////////////////////////////////////    //////////////////////////////////////////////////////////////////////////////////////////////////    //////////////////////////////////////////////////////////////////////////////////////////////////

    /*
    Management of the reply Message (reason why the authentication failed) :
    multiple authentication servers can be used, so multiple reply messages could theoretically be returned.
    But only one message is returned (the most important one).
    The return value of authenticate_user() define how important messages are :
        - Reply message of a successful auth is more important than reply message of
        a user failed auth(invalid credentials/authorization)
        - Reply message of a user failed auth is more important than reply message of
        a server failed auth (unable to contact server)
        - When multiple user failed auth are encountered, messages returned by remote servers
        (eg. reply in RADIUS Access-Reject) are more important than pfSense error messages.
    The $authlevel variable is a flag indicating the status of authentication
    0 = failed server auth
    1 = failed user auth
    2 = failed user auth with custom server reply recieved
    3 = successful auth
    */
    $authlevel = 0;

    /* Getting authentication servers from captiveportal configuration */
    $auth_servers = array();

    if ($cpcfg['auth_method'] === 'none') {
        $auth_servers[] = array('type' => 'none');
    } else {
        if ($context === 'second') {
            $fullauthservers = explode(",", $cpcfg['auth_server2']);
        } else {
            $fullauthservers = explode(",", $cpcfg['auth_server']);
        }

        foreach ($fullauthservers as $authserver) {
            if (strpos($authserver, ' - ') !== false) {
                $authserver = explode(' - ', $authserver);
                array_shift($authserver);
                $authserver = implode(' - ', $authserver);

                if (auth_get_authserver($authserver) !== null) {
                    $auth_servers[] = auth_get_authserver($authserver);
                } else {
                    log_error("Zone: {$cpzone} - Captive portal was unable to find the settings of the server '{$authserver}' used for authentication !");
                }
            }
        }
    }
    /* Unable to find the any authentication server config - shouldn't happen! - bail out */
    if (count($auth_servers) === 0) {
        log_error("Zone: {$cpzone} - No valid server could be used for authentication.");
        $login_msg = gettext("Firewall server offline, trying to restart system.");
    } else {
        foreach ($auth_servers as $authcfg) {
            if ($authlevel < 3) {
                $radmac_error = false;
                $attributes = array("nas_identifier" => empty($cpcfg["radiusnasid"]) ? "CaptivePortal-{$cpzone}" : $cpcfg["radiusnasid"],
                    "nas_port_type" => RADIUS_ETHERNET,
                    "nas_port" => $pipeno,
                    "framed_ip" => $clientip);
                if (mac_format($clientmac) !== null) {
                    $attributes["calling_station_id"] = mac_format($clientmac);
                }

                $result = null;
                $status = null;
                $msg = null;

                /* Radius MAC authentication */
                if ($context === 'radmac' && $clientmac) {
                    if ($authcfg['type'] === 'radius') {
                        $login = mac_format($clientmac);
                        $status = "MACHINE LOGIN";
                    } else {
                        /* Trying to perform a Radius MAC authentication on a non-radius server - shouldn't happen! - bail out */
                        $msg = gettext("Internal Error");
                        log_error("Zone: {$cpzone} - Trying to perform RADIUS MAC authentication on a non-RADIUS server !");
                        $radmac_error = true;
                        $result = null;
                    }
                }

                if (!$radmac_error) {
                    if ($authcfg['type'] === 'none') {
                        $result = true;
                    } else {
                        $result=false;
                        $antenna_allowed = antenna_allowed($login);
                        $isPortalShutdown = false;
                        $schedule_suspended = false;
                        if (isset($config['captiveportal'][$cpzone]['terminate_duration'])){
                            if(strpos( $login, '00001') === false) {
                                $isPortalShutdown=true;
                            }
                        }
                        if(get_suspend_timeschedule($login)) {
                            $schedule_suspended = true;
                        }
                        if($antenna_allowed && !$isPortalShutdown && !$schedule_suspended) {
                            $result = authenticate_user($login, $password, $authcfg, $attributes);
                        }
                    }

                    if (!empty($attributes['error_message'])) {
                        $msg = $attributes['error_message'];
                    }

                    if ($authcfg['type'] == 'Local Auth' && $result && isset($cpcfg['localauth_priv'])) {
                        if (!userHasPrivilege(getUserEntry($login), "user-services-captiveportal-login")) {
                            $result = false;
                            $msg = gettext("Access Denied");
                        }
                    }
                    if ($context === 'radmac' && $result === null && empty($attributes['reply_message'])) {
                        $msg = gettext("RADIUS MAC Authentication Failed.");
                    }

                    if (empty($status)) {
                        if ($result === true) {
                            $status = "ACCEPT";
                        } elseif ($result === null) {
                            $status = "ERROR";
                        } else {
                            $status = "FAILURE";
                        }
                    }

                    if ($context === 'radmac' && $login == mac_format($clientmac) || $authcfg['type'] === 'none' && empty($login)) {
                        $login = "unauthenticated";
                    }
                }
                // We determine a flag

                if ($result === true) {
                    $val = 3;
                }
                elseif ($result === false && !empty($attributes['reply_message'])) {
                    $val = 2;
                    $msg = $attributes['reply_message'];
                } elseif ($result === false) {
                    $val = 1;
                    if($login==="" || $password===""){
                        $msg = "ID or password is empty. Please check and try again";
                    }
                    else if($attributes['ms_chap_error']){  //if login failed
                        $msg="Password is incorrect.Please try again.";
                    }
                    else if ($attributes['ms_chap2_success']||$attributes['ms_chap_success']){
                        $msg="You have reached maximum data usage.";
                    }
                    else if (!$antenna_allowed){
                        $msg = "The antenna has been disabled due to the main policy";
                    }
                    else if ($isPortalShutdown){
                        $date = new DateTime();
                        $timeduration = $config['captiveportal'][$cpzone]['terminate_timestamp'] + $config['captiveportal'][$cpzone]['terminate_duration'] - round($date->getTimestamp() / 60, 0);
                        if($timeduration>10000){
                            $msg = "Portal has shutdown by ADMIN until ADMIN back system on.";
                        }
                        else {
                            $msg = "Portal has shutdown by ADMIN, wait for " . $timeduration . " minutes.";
                        }
                    }
                    else if ($schedule_suspended){
                        $msg = "Your ID has been suspended by admin schedule policy.";
                        cp_wireless_log("$login has been suspended due to schedule");
                    }
                    else {
                        // RADIUS 가 속성 없는 plain Access-Reject 반환.
                        // 가장 흔한 원인: datacounter_auth.sh 가 quota 초과로 exit 1.
                        // PHP 쪽에서 직접 quota 파일을 확인해 정확한 메시지를 출력한다.
                        $quota_exceeded = captiveportal_check_quota_exceeded(
                            $login,
                            $uconf['varuserspointoftime'] ?? ''
                        );
                        if ($quota_exceeded) {
                            $msg = "You have reached your data quota for this period.";
                            cp_wireless_log("[AUTH-QUOTA] user={$login} quota exceeded (fallback check)");
                        } else {
                            $msg = "Authentication failed. Contact administrator.";
                            cp_wireless_log(sprintf(
                                "[AUTH-UNKNOWN] user=%s result=%s status=%s attrs=%s",
                                $login,
                                var_export($result, true),
                                (string)$status,
                                implode(',', array_keys($attributes))
                            ));
                        }
                    }
                } elseif ($result === null) {
                    $val = 0;
                }

                if ($val >= $authlevel) {
                    $authlevel = $val;
                    $auth_method = $authcfg['type'];
                    $login_status = $status;
                    $login_msg = $msg;
                    $reply_attributes = $attributes;
                    $auth_result = $result;
                }
            }
        }
    }

    return array('result'=>$auth_result, 'attributes'=>$reply_attributes, 'auth_method' =>$auth_method, 'login_status'=> $login_status, 'login_message' => $login_msg);
}

/**
 * RADIUS 가 plain Access-Reject(속성 없음)를 반환했을 때 PHP 쪽에서
 * quota 파일을 직접 읽어 초과 여부를 확인하는 fallback.
 *
 * datacounter_auth.sh 가 exit 1 만 하고 Reply-Message 를 출력하지 않기 때문에
 * PHP 는 result=false + 빈 attributes 만 받아 원인을 알 수 없다.
 * 이 함수로 "quota 초과" vs "기타 인증 오류"를 구분한다.
 *
 * @param string $username   로그인 사용자명
 * @param string $timerange  varuserspointoftime (daily/weekly/monthly/forever)
 * @return bool  true = quota 초과, false = quota 미초과 또는 quota 미설정
 */
function captiveportal_check_quota_exceeded(string $username, string $timerange): bool
{
    $timerange = strtolower(trim($timerange));
    $allowed   = ['daily', 'weekly', 'monthly', 'forever'];

    if (!in_array($timerange, $allowed, true)) {
        return false;
    }

    // 사용자명 sanitize (datacounter_auth.sh 와 동일 규칙)
    $safe = preg_replace('/[^0-9a-zA-Z.:_-]/', 'X', $username);
    if ($safe === '') {
        return false;
    }

    $base_dir = "/var/log/radacct/datacounter/{$timerange}";
    $max_file = "{$base_dir}/max-octets-{$safe}";

    if (!file_exists($max_file)) {
        return false;  // quota 미설정 → block 없음
    }

    $max_octets = (int)trim((string)file_get_contents($max_file));
    if ($max_octets <= 0) {
        return false;
    }

    // used-octets-USER 와 used-octets-USER-* 파일을 모두 합산
    $used_base  = "{$base_dir}/used-octets-{$safe}";
    $used_files = glob("{$used_base}*") ?: [];
    $total_used = 0;

    foreach ($used_files as $f) {
        if (!is_file($f)) {
            continue;
        }
        $v = (int)trim((string)file_get_contents($f));
        if ($v > 0) {
            $total_used += $v;
        }
    }

    return ($total_used >= $max_octets);
}

function antenna_allowed($username)
{
    global $config;

    // BUG FIX: 기존 코드는 첫 번째 반복에서 무조건 return 해버려 username 일치와
    // 무관하게 항상 첫 번째 사용자 기준으로 판단하는 버그가 있었음.
    $terminaltype = null;
    foreach ($config['installedpackages']['freeradius']['config'] as $item => $value) {
        if ($username === $value['varusersusername']) {
            $terminaltype = $value['varusersterminaltype'] ?? '';
            break;
        }
    }

    if ($terminaltype === null) {
        // 사용자를 찾지 못한 경우: 허용 (인증 자체에서 거절될 것임)
        return true;
    }

    $shutdown_gateways = $config['system']['cp_shutdown_gateways'] ?? '';
    if ($terminaltype !== '' && strpos($shutdown_gateways, $terminaltype . '||') !== false) {
        return false;
    }
    return true;
}
function get_suspend_timeschedule($loginId) {
    global $config;

    // 빈 loginId 는 어떤 스케줄도 매칭하지 않는다
    if ($loginId === null || $loginId === '') {
        return false;
    }

    if (empty($config["schedules"]["schedule"]) || !is_array($config["schedules"]["schedule"])) {
        return false;
    }

    // pfSense config parser가 단일 schedule을 associative array로 반환하는 경우 보정
    // (set_scheduler()와 동일 처리)
    if (isset($config['schedules']['schedule']['name'])) {
        $scheduleList = [$config['schedules']['schedule']];
    } else {
        $scheduleList = $config['schedules']['schedule'];
    }

    foreach ($scheduleList as $scheduleItem) {

        if (!is_array($scheduleItem)) {
            continue;
        }

        if (($scheduleItem['name'] ?? '') !== $loginId) {
            continue;
        }

        if (empty($scheduleItem['timerange']) || !is_array($scheduleItem['timerange'])) {
            continue;
        }

        // timerange가 단일 항목인 경우 보정
        if (isset($scheduleItem['timerange']['rangedescr'])) {
            $timeranges = [$scheduleItem['timerange']];
        } else {
            $timeranges = $scheduleItem['timerange'];
        }

        foreach ($timeranges as $idx => $eachScheduleItem) {

            if (!is_array($eachScheduleItem)) {
                continue;
            }

            $allowedWeekdays = [];
            $effectiveWeekday = null;

            // rangedescr == 'TRUE' 인 것만 적용
            if (($eachScheduleItem['rangedescr'] ?? '') !== 'TRUE') {
                continue;
            }

            // 1) 로컬 시간 계산 (time_offset: -11 ~ +12, '시간'만)
            $time_offset = 0;
            if (isset($config['time_offset_enabled']['time_offset']) && $config['time_offset_enabled']['time_offset'] !== '') {
                $time_offset = (int)$config['time_offset_enabled']['time_offset'];
            }

            // 범위 강제 (-11 ~ +12)
            if ($time_offset < -11) $time_offset = -11;
            if ($time_offset >  12) $time_offset =  12;

            $utc = new DateTime('now', new DateTimeZone('UTC'));

            // ✅ 시간만 적용
            if ($time_offset !== 0) {
                $utc->modify(($time_offset > 0 ? '+' : '') . $time_offset . ' hours');
            }

            // ✅ 오프셋 적용 후 로컬 시간/요일
            $currentLocalTime = $utc->format('H:i');
            $currentWeekday   = (int)$utc->format('N'); // 1=Mon ... 7=Sun

            // 2) 스케줄 시간 파싱
            if (empty($eachScheduleItem['hour']) || strpos($eachScheduleItem['hour'], '-') === false) {
                continue;
            }

            list($start, $end) = explode('-', $eachScheduleItem['hour'], 2);

            $currentMinutes = toMinutes($currentLocalTime);
            $startMinutes   = toMinutes($start);
            $endMinutes     = toMinutes($end);

            // 3) 요일 추출
            $allowedWeekdays = array_map('intval', (array)extract_schedule_weekdays_v2($eachScheduleItem));
            $weekdayRestricted = !empty($allowedWeekdays);

            // 요일이 지정되지 않은 활성 스케줄은 의도치 않은 설정일 가능성이 높다.
            // 요일 없음(=모든 요일 적용)은 허용하되 로그를 남긴다.
            if (!$weekdayRestricted) {
                cp_wireless_log("[SUSPEND-WARN] user={$loginId} has active schedule with no weekday restriction. hour={$eachScheduleItem['hour']}");
            }

            $inRange = false;

            if ($startMinutes <= $endMinutes) {
                $inRange = ($startMinutes <= $currentMinutes && $currentMinutes <= $endMinutes);
                $effectiveWeekday = $currentWeekday;
            } else {
                $inRange = ($currentMinutes >= $startMinutes) || ($currentMinutes <= $endMinutes);

                if ($currentMinutes <= $endMinutes) {
                    $prev = clone $utc;
                    $prev->modify('-1 day');
                    $effectiveWeekday = (int)$prev->format('N');
                } else {
                    $effectiveWeekday = $currentWeekday;
                }
            }
            $weekdayOk = true;
            if ($weekdayRestricted) {
                $weekdayOk = in_array((int)$effectiveWeekday, $allowedWeekdays, true);
            }

            if ($inRange && $weekdayOk) {
                cp_wireless_log("[SUSPEND-MATCH] user={$loginId} suspended by schedule: hour={$eachScheduleItem['hour']} position='" . ($eachScheduleItem['position'] ?? '') . "' localtime={$currentLocalTime} weekday={$currentWeekday}");
                return true;
            }
        }
        break;
    }
    return false;
}

function extract_schedule_weekdays_v2(array $tr) : array
{
    $pos = $tr['position'] ?? null;
    if ($pos !== null && $pos !== '' && $pos !== false) {
        // CSV "1,2,3,4,5,6,7"
        if (is_string($pos) && strpos($pos, ',') !== false) {
            $parts = preg_split('/\s*,\s*/', trim($pos), -1, PREG_SPLIT_NO_EMPTY);
            return array_values(array_map('strval', $parts));
        }

        // 배열 [1,2,3]
        if (is_array($pos)) {
            $out = array_values(array_map('strval', $pos));
            return $out;
        }

        // 숫자 처리
        if (is_numeric($pos)) {
            $n = (int)$pos;

            if ($n >= 1 && $n <= 7) {
                return [(string)$n];
            }

            $mask = $n;
            $out = [];
            for ($i = 0; $i <= 6; $i++) {
                if ($mask & (1 << $i)) {
                    $out[] = (string)($i + 1); // bit0=월(1) ... bit6=일(7)
                }
            }
            return $out;
        }
    }

    foreach (['weekdays', 'weekday', 'days', 'wdays'] as $k) {
        if (!empty($tr[$k])) {
            $vals = is_array($tr[$k])
                ? $tr[$k]
                : preg_split('/[,\s]+/', (string)$tr[$k], -1, PREG_SPLIT_NO_EMPTY);
            $out = array_values(array_map('strval', $vals));
            return $out;
        }
    }
    return [];
}





function toMinutes($time) {
    list($h, $m) = explode(':', $time);
    return $h * 60 + $m;
}

function captiveportal_opendb() {
    global $g, $config, $cpzone, $cpzoneid;

    $db_path = "{$g['vardb_path']}/captiveportal{$cpzone}.db";
    $createquery = "CREATE TABLE IF NOT EXISTS captiveportal (" .
        "allow_time INTEGER, pipeno INTEGER, ip TEXT, mac TEXT, username TEXT, " .
        "sessionid TEXT, bpassword TEXT, session_timeout INTEGER, idle_timeout INTEGER, " .
        "session_terminate_time INTEGER, interim_interval INTEGER, traffic_quota INTEGER, " .
        "bw_up INTEGER, bw_down INTEGER, authmethod TEXT, context TEXT); " .
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_active ON captiveportal (sessionid, username); " .
        "CREATE INDEX IF NOT EXISTS user ON captiveportal (username); " .
        "CREATE INDEX IF NOT EXISTS ip ON captiveportal (ip); " .
        "CREATE INDEX IF NOT EXISTS starttime ON captiveportal (allow_time)";

    try {
        $DB = new SQLite3($db_path);
        $DB->busyTimeout(60000);
    } catch (Exception $e) {
        captiveportal_syslog("Could not open {$db_path} as an sqlite database for {$cpzone}. Error message: " . $e->getMessage() . " -- Trying again.");
        unlink_if_exists($db_path);
        try {
            $DB = new SQLite3($db_path);
            $DB->busyTimeout(60000);
        } catch (Exception $e) {
            captiveportal_syslog("Still could not open {$db_path} as an sqlite database for {$cpzone}. Error message: " . $e->getMessage() . " -- Remove the database file manually and ensure there is enough free space.");
            return;
        }
    }

    if (!$DB) {
        captiveportal_syslog("Could not open {$db_path} as an sqlite database for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Trying again.");
        unlink_if_exists($db_path);
        $DB = new SQLite3($db_path);
        $DB->busyTimeout(60000);
        if (!$DB) {
            captiveportal_syslog("Still could not open {$db_path} as an sqlite database for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Remove the database file manually and ensure there is enough free space.");
            return;
        }
    }

    if (! $DB->exec($createquery)) {
        captiveportal_syslog("Error during table {$cpzone} creation. Error message: {$DB->lastErrorMsg()}. Resetting and trying again.");

        /* If unable to initialize the database, reset and try again. */
        $DB->close();
        unset($DB);
        unlink_if_exists($db_path);
        $DB = new SQLite3($db_path);
        $DB->busyTimeout(60000);
        if ($DB->exec($createquery)) {
            captiveportal_syslog("Successfully reinitialized tables for {$cpzone} -- database has been reset.");
            if (!is_numericint($cpzoneid)) {
                if (is_array($config['captiveportal'])) {
                    foreach ($config['captiveportal'] as $cpkey => $cp) {
                        if ($cpzone == $cpkey) {
                            $cpzoneid = $cp['zoneid'];
                        }
                    }
                }
            }
            if (is_numericint($cpzoneid)) {
                $table_names = captiveportal_get_ipfw_table_names();
                foreach ($table_names as $table_name) {
                    mwexec("/sbin/ipfw table {$table_name} flush");
                }
                captiveportal_syslog("Flushed tables for {$cpzone} after database reset.");
            }
        } else {
            captiveportal_syslog("Still unable to create tables for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Remove the database file manually and try again.");
        }
    }

    return $DB;
}

/* Get all tables for specific cpzone */
function captiveportal_get_ipfw_table_names() {
    global $cpzone;

    $result = array();
    $tables = pfSense_ipfw_tables_list();

    if (!is_array($tables)) {
        return $result;
    }

    $len = strlen($cpzone) + 1;
    foreach ($tables as $table) {
        if (substr($table['name'], 0, $len) != $cpzone . '_') {
            continue;
        }

        $result[] = $table['name'];
    }

    return $result;
}

/* read captive portal DB into array */
function captiveportal_read_db($query = "") {
    $cpdb = array();

    $DB = captiveportal_opendb();
    if ($DB) {
        $response = $DB->query("SELECT * FROM captiveportal {$query}");
        if ($response != FALSE) {
            while ($row = $response->fetchArray()) {
                $cpdb[] = $row;
            }
        }
        $DB->close();
    }

    return $cpdb;
}

function captiveportal_remove_entries($remove, $carp_loop = false) {
    global $cpzone;

    if (!is_array($remove) || empty($remove)) {
        return;
    }

    $query = "DELETE FROM captiveportal WHERE sessionid in (";
    foreach ($remove as $idx => $unindex) {
        $query .= "'{$unindex}'";
        if ($idx < (count($remove) - 1)) {
            $query .= ",";
        }
    }
    $query .= ")";
    captiveportal_write_db($query);

    if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport, $syncuser, $syncpass, $carp_loop)) {
        $rpc_client = new pfsense_xmlrpc_client();
        $rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
        $rpc_client->set_noticefile("CaptivePortalUserSync");
        $rpc_client->xmlrpc_method('captive_portal_sync',
            array(
                'op' => 'remove_entries',
                'zone' => $cpzone,
                'entries' => base64_encode(serialize($remove))
            )
        );
    }
    return true;
}

/* write captive portal DB */
function captiveportal_write_db($queries) {
    global $g;

    if (is_array($queries)) {
        $query = implode(";", $queries);
    } else {
        $query = $queries;
    }

    $DB = captiveportal_opendb();
    if ($DB) {
        $DB->exec("BEGIN TRANSACTION");
        $result = $DB->exec($query);
        if (!$result) {
            captiveportal_syslog("Trying to modify DB returned error: {$DB->lastErrorMsg()}");
        } else {
            $DB->exec("END TRANSACTION");
        }
        $DB->close();
        return $result;
    } else {
        return true;
    }
}

function captiveportal_write_elements() {
    global $g, $config, $cpzone;

    $cpcfg = $config['captiveportal'][$cpzone];

    if (!is_dir($g['captiveportal_element_path'])) {
        @mkdir($g['captiveportal_element_path']);
    }

    if (is_array($cpcfg['element'])) {
        foreach ($cpcfg['element'] as $data) {
            /* Do not attempt to decode or write out empty files. */
            if (isset($data['nocontent'])) {
                continue;
            }
            if (empty($data['content']) || empty(base64_decode($data['content']))) {
                unlink_if_exists("{$g['captiveportal_element_path']}/{$data['name']}");
                touch("{$g['captiveportal_element_path']}/{$data['name']}");
            } elseif (!@file_put_contents("{$g['captiveportal_element_path']}/{$data['name']}", base64_decode($data['content']))) {
                printf(gettext('Error: cannot open \'%1$s\' in captiveportal_write_elements()%2$s'), $data['name'], "\n");
                return 1;
            }
            if (!file_exists("{$g['captiveportal_path']}/{$data['name']}")) {
                @symlink("{$g['captiveportal_element_path']}/{$data['name']}", "{$g['captiveportal_path']}/{$data['name']}");
            }
        }
    }

    return 0;
}

function captiveportal_free_dnrules($rulenos_start = 2000,
                                    $rulenos_range_max = 64500, $dry_run = false, $clear_auth_pipes = true) {
    global $g, $cpzone;

    $removed_pipes = array();

    if (!file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
        return $removed_pipes;
    }

    if (!$dry_run) {
        $cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
    }

    $rules = unserialize(file_get_contents(
        "{$g['vardb_path']}/captiveportaldn.rules"));
    $ridx = $rulenos_start;
    while ($ridx < $rulenos_range_max) {
        if (substr($rules[$ridx], 0, strlen($cpzone . '_')) == $cpzone . '_') {
            if (!$clear_auth_pipes && substr($rules[$ridx], 0, strlen($cpzone . '_auth')) == $cpzone . '_auth') {
                $ridx += 2;
            } else {
                if (!$dry_run) {
                    $rules[$ridx] = false;
                }
                $removed_pipes[] = $ridx;
                $ridx++;
                if (!$dry_run) {
                    $rules[$ridx] = false;
                }
                $removed_pipes[] = $ridx;
                $ridx++;
            }
        } else {
            $ridx += 2;
        }
    }

    if (!$dry_run) {
        file_put_contents("{$g['vardb_path']}/captiveportaldn.rules",
            serialize($rules));
        unlock($cpruleslck);
    }

    unset($rules);

    return $removed_pipes;
}

function captiveportal_reserve_ruleno($ruleno) {
    global $g, $cpzone;

    $cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
    if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
        $rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
    } else {
        $rules = array_pad(array(), 64500, false);
    }
    $rules[$ruleno] = $cpzone . '_auth';
    $ruleno++;
    $rules[$ruleno] = $cpzone . '_auth';

    file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
    unlock($cpruleslck);
    unset($rules);

    return $ruleno;
}

function captiveportal_get_next_dn_ruleno($rule_type = 'default', $rulenos_start = 2000, $rulenos_range_max = 64500) {
    global $config, $g, $cpzone;

    $cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
    $ruleno = 0;
    if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
        $rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
        $ridx = $rulenos_start;
        while ($ridx < $rulenos_range_max) {
            if (empty($rules[$ridx])) {
                $ruleno = $ridx;
                $rules[$ridx] = $cpzone . '_' . $rule_type;
                $ridx++;
                $rules[$ridx] = $cpzone . '_' . $rule_type;
                break;
            } else {
                $ridx += 2;
            }
        }
    } else {
        $rules = array_pad(array(), $rulenos_range_max, false);
        $ruleno = $rulenos_start;
        $rules[$rulenos_start] = $cpzone . '_' . $rule_type;
        $rulenos_start++;
        $rules[$rulenos_start] = $cpzone . '_' . $rule_type;
    }
    file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
    unlock($cpruleslck);
    unset($rules);

    return $ruleno;
}

function captiveportal_free_dn_ruleno($ruleno) {
    global $config, $g;

    $cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
    if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
        $rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
        $rules[$ruleno] = false;
        $ruleno++;
        $rules[$ruleno] = false;
        file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
        unset($rules);
    }
    unlock($cpruleslck);
}

function captiveportal_get_dn_passthru_ruleno($value) {
    global $config, $g, $cpzone, $cpzoneid;

    $cpcfg = $config['captiveportal'][$cpzone];
    if (!isset($cpcfg['enable'])) {
        return NULL;
    }

    $cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
    $ruleno = NULL;
    if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
        unset($output);
        $item = pfSense_ipfw_table_lookup("{$cpzone}_pipe_mac",
            "any,{$value}");
        if (!is_array($item) || empty($item['pipe'])) {
            unlock($cpruleslck);
            return NULL;
        }

        $ruleno = intval($item['pipe']);
        $rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
        if (!$rules[$ruleno]) {
            $ruleno = NULL;
        }
        unset($rules);
    }
    unlock($cpruleslck);

    return $ruleno;
}

/**
 * This function will calculate the traffic produced by a client
 * based on its firewall rule
 *
 * Point of view: NAS
 *
 * Input means: from the client
 * Output means: to the client
 *
 */

function getVolume($ip) {
    global $config, $cpzone;

    $reverse = isset($config['captiveportal'][$cpzone]['reverseacct'])
        ? true : false;
    $volume = array();
    // Initialize vars properly, since we don't want NULL vars
    $volume['input_pkts'] = $volume['input_bytes'] = 0;
    $volume['output_pkts'] = $volume['output_bytes'] = 0;

    $tables = array("allowed", "auth");

    foreach($tables as $table) {
        $ipfw = pfSense_ipfw_table_lookup("{$cpzone}_{$table}_up", $ip);
        if (!is_array($ipfw)) {
            continue;
        }
        if ($reverse) {
            $volume['output_pkts'] = $ipfw['packets'];
            $volume['output_bytes'] = $ipfw['bytes'];

        } else {
            $volume['input_pkts'] = $ipfw['packets'];
            $volume['input_bytes'] = $ipfw['bytes'];
        }
    }

    foreach($tables as $table) {
        $ipfw = pfSense_ipfw_table_lookup("{$cpzone}_{$table}_down",
            $ip);
        if (!is_array($ipfw)) {
            continue;
        }
        if ($reverse) {
            $volume['input_pkts'] = $ipfw['packets'];
            $volume['input_bytes'] = $ipfw['bytes'];
        } else {
            $volume['output_pkts'] = $ipfw['packets'];
            $volume['output_bytes'] = $ipfw['bytes'];
        }
    }
    return $volume;
}

function portal_ip_from_client_ip($cliip) {
    global $config, $cpzone;

    $isipv6 = is_ipaddrv6($cliip);
    $interfaces = explode(",", $config['captiveportal'][$cpzone]['interface']);
    foreach ($interfaces as $cpif) {
        if ($isipv6) {
            $ip = get_interface_ipv6($cpif);
            $sn = get_interface_subnetv6($cpif);
        } else {
            $ip = get_interface_ip($cpif);
            $sn = get_interface_subnet($cpif);
        }
        if (ip_in_subnet($cliip, "{$ip}/{$sn}")) {
            return $ip;
        }
    }

    $route = route_get($cliip, 'inet', true);
    if (empty($route)) {
        return false;
    }

    $iface = $route[0]['interface-name'];
    if (!empty($iface)) {
        $ip = ($isipv6) ? find_interface_ipv6($iface)
            : find_interface_ip($iface);
        if (is_ipaddr($ip)) {
            return $ip;
        }
    }

    // doesn't match up to any particular interface
    // so let's set the portal IP to what PHP says
    // the server IP issuing the request is.
    // allows same behavior as 1.2.x where IP isn't
    // in the subnet of any CP interface (static routes, etc.)
    // rather than forcing to DNS hostname resolution
    $ip = $_SERVER['SERVER_ADDR'];
    if (is_ipaddr($ip)) {
        return $ip;
    }

    return false;
}

function portal_hostname_from_client_ip($cliip) {
    global $config, $cpzone;

    $cpcfg = $config['captiveportal'][$cpzone];

    if (isset($cpcfg['httpslogin'])) {
        $listenporthttps = $cpcfg['listenporthttps'] ? $cpcfg['listenporthttps'] : ($cpcfg['zoneid'] + 8001);
        $ourhostname = $cpcfg['httpsname'];
        if ($listenporthttps != 443) {
            $ourhostname .= ":" . $listenporthttps;
        }
    } else {
        $listenporthttp = $cpcfg['listenporthttp'] ? $cpcfg['listenporthttp'] : ($cpcfg['zoneid'] + 8000);
        $ifip = portal_ip_from_client_ip($cliip);
        if (!$ifip) {
            $ourhostname = $config['interfaces']['opt3']['ipaddr'];
        } else {
            $ourhostname = (is_ipaddrv6($ifip)) ? "[{$ifip}]" : "{$ifip}";
        }
        if ($listenporthttp != 80) {
            $ourhostname .= ":" . $listenporthttp;
        }
    }
    return $ourhostname;
}

/* functions move from index.php */

function portal_reply_page($redirurl, $type = null, $title = null, $clientmac = null, $clientip = null, $username = null, $password = null, $voucher = null) {
    global $g, $config, $cpzone;
    $cpcfg = $config['captiveportal'][$cpzone];
    $ourhostname = portal_hostname_from_client_ip($clientip);
    $protocol = (isset($cpcfg['httpslogin'])) ? 'https://' : 'http://';
    $portal_url=$redirurl;
    $connectinfo = already_connected($clientip, $clientmac);
    $sessionid = (is_array($connectinfo) && isset($connectinfo[5])) ? $connectinfo[5] : '';

    if ($username == '' && is_array($connectinfo) && isset($connectinfo[4])) {
        $username = $connectinfo[4];
    }

    // already_connected() 는 IP+MAC 정확일치만 본다. 이 재조회가 실패(false)해도
    // flash 로 전달받은 username 이 있으면 sessionid 를 username 기준으로 복구한다.
    // (복구하지 않으면 quota 가 0 으로 잘못 표시되거나, 아래 connected 분기에서
    //  username/sessionid 공백으로 엉뚱하게 'removed' 처리될 수 있다.)
    if (($sessionid === '' || $sessionid === null) && !empty($username)) {
        $recovered = getsession($username);
        if ($recovered !== false) {
            $sessionid = $recovered;
        }
    }
    if($config['system']['vesselinfo']['logo']==="sktelink") {
        $logo = "captiveportal-skt-logo.svg";
    }
    else if($config['system']['vesselinfo']['logo']==="inmarsat") {
        $logo = "captiveportal-inmarsat-logo.svg";
    }
    else{
        $logo = "captiveportal-logo.svg";
    }
    $htmltext = get_include_contents("{$g['varetc_path']}/captiveportal-{$cpzone}.html");
    $currentquota = getcurquota($sessionid);
    $maxquota = getmaxquota($username);

    if ($type == "redir") {
        $redirurl = is_URL($redirurl) ? $redirurl : $portal_url;
        header("Location: {$redirurl}");
    }
    else if ($type == "error") {
        $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
            'logo'     => $logo,
            'portalError'     => "$title",
            'portalInfo'      => "",
            'loginUserValue'  => $username,
            'uiConfig'        => [],
            'showQuotaResult' => false,
            'subject'  => "Login Failed!",
        ]), $htmltext);
        echo $htmltext;
    }
    else if ($type == "change_pw") {
        $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderChangepwPortalHtml([
                'portalError'     => "",
                'portalInfo'      => "",
                'loginUserValue'  => $username,
        ]), $htmltext);
        echo $htmltext;
    }
    else if ($type=="commit_change_pw"){
        // 동시 PW 변경 lost-update 방지: 전용 락 + 락 안에서 config 최신본 재로딩 후 저장.
        // (write_config 내부의 lock('config') 와 데드락되지 않도록 다른 이름의 락 사용.)
        $cnf_lock = lock('freeradius_user_config', LOCK_EX);
        try {
            $config = parse_config(true);
            foreach($config['installedpackages']['freeradius']['config'] as $item => $value){
                if($username===$value['varusersusername']){
                    $config['installedpackages']['freeradius']['config'][$item]['varuserspassword']=$password;
                }
            }
            write_config("{$username} change password applied");
        } finally {
            unlock($cnf_lock);
        }
        captiveportal_disconnect_client($sessionid);
        sleep(2);

        // persist=false: 위 락 구간에서 이미 write_config 했으므로 중복 write(=clobber) 방지.
        if(freeradius_update_user($username, false)){
            $title="Password Changed Successfully!!";
            $result='Your Password has been successfully changed.';
        }
        else{
            $title="Problem occurred while changing password.";
            $result='Password has not been changed, You may login old password.';
        }
        $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                'logo'     => $logo,
                'portalError'     => '',
                'portalInfo'      => $result,
                'loginUserValue'  => $username,
                'uiConfig'        => [],
                'showQuotaResult' => false,
                'subject'  => $title,
        ]), $htmltext);

        echo $htmltext;
        //freeradius_users_resync(false,"user change applied");//-> Freeradius 에다가 리싱크 명령함.
    }
    else if ($type === "connected"){
        // ── passthrough(freelogins) 게스트 처리 ──────────────────────────────
        // 'unauthenticated' 세션은 freeradius config 사용자가 아니므로 아래의
        // config 조회·quota·suspend·removed 판정 대상이 아니다. 과거에는 config 에서
        // 못 찾아 $userindex=-1 → 'User is removed' 분기로 빠져 게스트가 강제
        // disconnect 되었다(재오픈 시마다 끊김).
        //
        // 게스트는 끊지 않고 목적지로 바로 redirect 한다.
        // 단, 포털 호스트로 redirect 하면 무한 루프가 되므로, 존 설정의 redirurl
        // (로그인 후 리디렉션 URL)이 유효한 외부 URL 일 때만 redirect 하고,
        // 대상이 없으면 게스트 상태 페이지로 폴백한다(루프 방지).
        if ($username === 'unauthenticated') {
            $guest_redir = '';
            if (is_URL($config['captiveportal'][$cpzone]['redirurl'] ?? '')) {
                $guest_redir = $config['captiveportal'][$cpzone]['redirurl'];
            }
            if ($guest_redir !== '') {
                header("Location: {$guest_redir}");
                return;
            }
            // 폴백: redirect 대상이 없으면 게스트 상태 페이지 표시 (disconnect 안 함)
            $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLogoutPortalHtml([
                'logo'           => $logo,
                'portalError'    => '',
                'displayname'    => 'Guest',
                'portalInfo'     => 'You are connected as a guest.',
                'loginUserValue' => 'unauthenticated',
                'quotaUsed'      => 0,
                'quotaAllocated' => 0,
                'loginUsers'     => count(captiveportal_read_db()),
            ]), $htmltext);
            echo $htmltext;
            return;
        }

        $userindex= -1;
        $usernickname = "";
        $usercount=count(captiveportal_read_db());
        foreach (($config['installedpackages']['freeradius']['config'] ?? []) as $item => $value) {
            if (($value['varusersusername'] ?? '') === $username) {
                $userindex = $item;
                $usernickname = $value['varusersdescription'] ?? '';
                break;
            }
        }
        //captiveportal_logportalauth($clientip, $clientmac, $username, $sessionid, "User is showed".$userindex);
        if ($userindex> -1){//사용자가 있을 때
            $terminalType = $config['installedpackages']['freeradius']['config'][$userindex]['varusersterminaltype'] ?? '';
            if ($terminalType === '') {
                $isexist = true;
            }
            else {
                // NOTE: return_gateways_status(true) 는 사용하지 않음.
                // 이유:
                //   return_gateways_status() → get_dpinger_status() → dpinger 소켓 폴링.
                //   latency_stddev='0' && loss='0' 인 게이트웨이(VPN, 안정적 WAN 등)마다
                //   최대 300회 × 10 ms = 3 초씩 블로킹이 발생한다.
                //   게이트웨이가 6-7 개인 환경에서는 6×3 = 18초 이상 지연이 누적된다.
                // 이 지점에서 필요한 것은 "terminalType 이름의 게이트웨이가 config 에 존재하는가"
                // 뿐이므로, config 메모리 탐색으로 대체한다. (즉시 반환)
                $isexist = false;
                if (!empty($config['gateways']['gateway_item']) && is_array($config['gateways']['gateway_item'])) {
                    foreach ($config['gateways']['gateway_item'] as $gw) {
                        if (($gw['name'] ?? '') === $terminalType) {
                            $isexist = true;
                            break;
                        }
                    }
                }
            }
            if($isexist) {
                if ($config['installedpackages']['freeradius']['config'][$userindex]['varusersmodified'] == "update") {
                    if ($config['installedpackages']['freeradius']['config'][$userindex]['varusersmodified'] == "update") {
                        $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                                        'logo'            => $logo,
                                        'portalError'     => "",
                                        'portalInfo'      => "This account will be logged out to apply updates.",
                                        'loginUserValue'  => $username,
                                        'uiConfig'        => [],
                                        'showQuotaResult' => false,
                                        'subject'         => "Account Update",
                                ]), $htmltext);
                        session_write_close();
                        echo $htmltext;
                        captiveportal_disconnect_client($sessionid);
                        sleep(2);
                        exit;

                    }
                } else {//사용량 확인함.
                    if (isset($config['captiveportal'][$cpzone]['terminate_duration']) && (strpos($username, '00001') === false)) {
                        $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                            'logo'     => $logo,
                            'portalError'     => "",
                            'portalInfo'      => "System Administrator has been shutdown WIFI portal site",
                            'loginUserValue'  => $username,
                            'uiConfig'        => [],
                            'showQuotaResult' => false,
                            'subject'  => "WIFI Portal Shutdown!",
                        ]), $htmltext);
                        captiveportal_disconnect_client($sessionid);
                        echo $htmltext;
                    }
                    else {
                        if ($currentquota < $maxquota) {
                            $schedule_suspended = get_suspend_timeschedule($username);
                            if($schedule_suspended == true){
                                captiveportal_disconnect_client($sessionid);
                                sleep(2);
                                freeradius_update_user($username);
                                $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                                    'logo'     => $logo,
                                    'portalError'     => "",
                                    'portalInfo'      => "You are currently entered suspend schedule",
                                    'loginUserValue'  => $username,
                                    'uiConfig'        => [],
                                    'showQuotaResult' => false,
                                    'subject'  => "User Suspended",
                                ]), $htmltext);
                                echo $htmltext;
                            }
                            else{

                                if (strpos($username, 'crewpay-') === 0) {
                                    $title = 'You are currently logged in with a Prepaid ID.';
                                    $displayname = substr($username, strlen('crewpay-'));
                                }
                                else{
                                    $displayname = $username;
                                }
                                if ($usernickname !== ''){
                                    $displayname = $usernickname;
                                }


                                $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLogoutPortalHtml([
                                    'logo'     => $logo,
                                    'portalError'     => '',
                                    'displayname'     => $displayname,
                                    'portalInfo'      => $title,
                                    'loginUserValue'  => $username,
                                    'quotaUsed'       => $currentquota,
                                    'quotaAllocated'     => $maxquota,
                                    'loginUsers'     => $usercount,
                                ]), $htmltext);
                            }
                            echo $htmltext;
                        }
                        else {
                            captiveportal_disconnect_client($sessionid, "Usage-Exceed");
                            $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                                'logo'     => $logo,
                                'portalError'     => "",
                                'portalInfo'      => "You have used up all of your data quota.",
                                'loginUserValue'  => $username,
                                'uiConfig'        => [],

                                'showQuotaResult' => false,
                                'subject'  => "Quota Reached",
                            ]), $htmltext);
                            echo $htmltext;
                            exit;
                        }
                    }
                }
            }
            else { //No gateway found
                $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                    'logo'     => $logo,
                    'portalError'     => "Current login Antenna is offline, please try later.",
                    'portalInfo'      => "",
                    'loginUserValue'  => $username,
                    'uiConfig'        => [],

                    'showQuotaResult' => false,
                    'subject'  => "Antenna Offline",
                ]), $htmltext);
                captiveportal_disconnect_client($sessionid, "No-Gateway");
                echo $htmltext;
            }
        }
        else {
            // userindex == -1 인 경우는 두 가지로 나뉜다.
            //   (A) $username 이 빈 값  → username 회수 실패(already_connected 재조회 실패 등).
            //       실제 사용자 삭제가 아니므로 강제 disconnect 하면 안 되고, WELCOME 로그인
            //       페이지를 띄워야 한다. (기존엔 무조건 removing 으로 빠져 세션이 끊겼다.)
            //   (B) $username 은 있으나 freeradius config 에 없음 → 진짜 삭제된 사용자.
            if ($username === '' || $username === null) {
                $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                        'logo'     => $logo,
                        'portalError'     => "",
                        'portalInfo'      => "",
                        'loginUserValue'  => "",
                        'uiConfig'        => [],
                        'showQuotaResult' => false,
                        'subject'  => "Welcome",
                ]), $htmltext);
                echo $htmltext;   // disconnect 호출하지 않음
            }
            else {
                captiveportal_logportalauth($clientip, $clientmac, $username, $sessionid, "User is Removing ".$userindex);
                $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                        'logo'     => $logo,
                        'portalError'     => "",
                        'portalInfo'      => "User has  been removed",
                        'uiConfig'        => [],
                        'showQuotaResult' => false,
                        'subject'  => "User is removed!",
                ]), $htmltext);
                captiveportal_disconnect_client($sessionid);
                echo $htmltext;
            }
        }
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    }
    else if ($type == "login") {
        if($title==='resetPW'){
            $subject = "Reset Password Completed.";
            $info = "Your Password has been reset. Please login with new password again.";
        }
        $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
            'logo'     => $logo,
            'portalError'     => "",
            'portalInfo'      => $info,
            'loginUserValue'  => $username,
            'uiConfig'        => [],

            'showQuotaResult' => false,
            'subject'  => $subject,
        ]), $htmltext);

        echo $htmltext;

        exit;
    }
    else if ($type == "check_quota") {

        if(!empty($username)){
            foreach($config['installedpackages']['freeradius']['config'] as $item => $value){
                if($username==$value['varusersusername']){
                    $userindex = $item;
                    break;
                }
            }
            $subject="Check Quota";
            if(isset($userindex)){
                $allocated = $config['installedpackages']['freeradius']['config'][$userindex]['varusersmaxtotaloctets'];
                $curoctets=check_quota($username, $config['installedpackages']['freeradius']['config'][$userindex]['varusersmaxtotaloctetstimerange']);
                $remain = number_format(($allocated-$curoctets), 2);
                $ratio = ($curoctets/$allocated)*100;
                $info = "${username} quota check was successful";
                $currentquota = number_format($ratio, 2)."%";
                $showqutaresult=true;
            } else {
                $info = "Username not found";
                $showqutaresult=false;
            }
            $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                    'logo'     => $logo,
                    'portalError'     => "",
                    'portalInfo'      => '',
                    'loginUserValue'  => '',
                    'uiConfig'        => [],
                    'quotaNotice'     => $info,
                    'quotaUserValue'  => $remain.'MB',
                    'showQuotaResult' => $showqutaresult,
                    'subject'  => $subject,
                    'quotaUsed'       => number_format($curoctets, 2)."MB",
                    'quotaAllocated'     => $allocated."MB",
            ]), $htmltext);
            echo $htmltext;

        }
    }
    else {
        $info = "Your id was not found";
        $title = "Login failed!";
        $htmltext = str_replace("\$PORTAL_MAIN_CONTENTS\$", renderLoginPortalHtml([
                'logo'     => $logo,
                'portalError'     => "",
                'portalInfo'      => $info,
                'loginUserValue'  => $username,
                'uiConfig'        => [],
                'showQuotaResult' => false,
        ]), $htmltext);
        echo $htmltext;

    }

}

function captiveportal_reapply_attributes($cpentry, $attributes) {
    global $config, $cpzone, $g;

    if (isset($config['captiveportal'][$cpzone]['peruserbw'])) {
        $dwfaultbw_up = !empty($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
        $dwfaultbw_down = !empty($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
    } else {
        $dwfaultbw_up = $dwfaultbw_down = 0;
    }
    /* pipe throughputs must always be an integer, enforce that restriction again here. */
    if (isset($config['captiveportal'][$cpzone]['radiusperuserbw'])) {
        $bw_up = round(!empty($attributes['bw_up']) ? intval($attributes['bw_up'])/1000 : $dwfaultbw_up, 0);
        $bw_down = round(!empty($attributes['bw_down']) ? intval($attributes['bw_down'])/1000 : $dwfaultbw_down, 0);
    } else {
        $bw_up = round($dwfaultbw_up,0);
        $bw_down = round($dwfaultbw_down,0);
    }

    $bw_up_pipeno = $cpentry[1];
    $bw_down_pipeno = $cpentry[1]+1;

    if ($cpentry['bw_up'] !== $bw_up) {
        $_gb = @pfSense_ipfw_pipe("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
        captiveportal_update_entry($cpentry['sessionid'], $bw_up, 'bw_up');
    }
    if ($cpentry['bw_down'] !== $bw_down) {
        $_gb = @pfSense_ipfw_pipe("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");
        captiveportal_update_entry($cpentry['sessionid'], $bw_down, 'bw_down');
    }
    unset($bw_up_pipeno, $bw_down_pipeno, $bw_up, $bw_down);
}

function captiveportal_update_entry($sessionid, $new_value, $field_to_update) {
    global $cpzone;

    if (!intval($new_value)) {
        $new_value = "'{$new_value}'";
    }
    captiveportal_write_db("UPDATE captiveportal SET {$field_to_update} = {$new_value} WHERE sessionid = '{$sessionid}'");
}

function portal_allow($clientip, $clientmac, $username, $password = null, $redirurl = null,
                      $attributes = null, $pipeno = null, $authmethod = null, $context = 'first', $existing_sessionid = null) {
    global $g, $config, $cpzone;
    // Ensure we create an array if we are missing attributes
    if (!is_array($attributes)) {
        $attributes = array();
    }

    unset($sessionid);

    /* Do not allow concurrent login execution. */
    $cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

    if ($attributes['voucher']) {
        $remaining_time = $attributes['session_timeout'];
        $authmethod = "voucher"; // Set RADIUS-Attribute to Voucher to prevent ReAuth-Request for Vouchers Bug: #2155
        $context = "voucher";
    }

    $writecfg = false;
    /* If both "Add MAC addresses of connected users as pass-through MAC" and "Disable concurrent logins" are checked,
    then we need to check if the user was already authenticated using another MAC Address, and if so remove the previous Pass-Through MAC. */
    if ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && ($config['captiveportal'][$cpzone]['noconcurrentlogins'] == 'last') && ($username != 'unauthenticated') && isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
        $mac = captiveportal_passthrumac_findbyname($username);
        if (!empty($mac)) {
            foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $idx => $macent) {
                if ($macent['mac'] != $mac['mac']) {
                    continue;
                }

                $pipeno = captiveportal_get_dn_passthru_ruleno($mac['mac']);
                if ($pipeno) {
                    captiveportal_free_dn_ruleno($pipeno);
                    @pfSense_ipfw_table("{$cpzone}_pipe_mac", IP_FW_TABLE_XDEL, "any,{$mac['mac']}");
                    @pfSense_ipfw_table("{$cpzone}_pipe_mac", IP_FW_TABLE_XDEL, "{$mac['mac']},any");
                    @pfSense_ipfw_pipe("pipe delete " . ($pipeno+1));
                    @pfSense_ipfw_pipe("pipe delete " . $pipeno);
                }
                unset($config['captiveportal'][$cpzone]['passthrumac'][$idx]);
            }
        }
    }

    /* read in client database */
    $query = "WHERE ip = '{$clientip}'";
    $tmpusername = SQLite3::escapeString(strtolower($username));
    if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
        $query .= " OR (username != 'unauthenticated' AND lower(username) = '{$tmpusername}')";
    }
    $cpdb = captiveportal_read_db($query);

    /* Snapshot the timestamp */
    $allow_time = time();

    if ($existing_sessionid !== null) {
        // If we recieved this connection through XMLRPC sync :
        // we fetch allow_time from the info given by the other node
        $allow_time = $attributes['allow_time'];
    }
    $unsetindexes = array();

    foreach ($cpdb as $cpentry) {
        /* on the same ip */
        if ($cpentry[2] == $clientip) {
            if (isset($config['captiveportal'][$cpzone]['nomacfilter']) || $cpentry[3] == $clientmac) {
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - REUSING OLD SESSION");
            } else {
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - REUSING IP {$cpentry[2]} WITH DIFFERENT MAC ADDRESS {$cpentry[3]}");
            }
//////////////////////// no need add firewall rule///////////////////////
            $sessionid = $cpentry[5];
            break;
        } elseif (($attributes['voucher']) && ($username != 'unauthenticated') && ($cpentry[4] == $username)) {
            // user logged in with an active voucher. Check for how long and calculate
            // how much time we can give him (voucher credit - used time)
            $remaining_time = $cpentry[0] + $cpentry[7] - $allow_time;
            if ($remaining_time < 0) { // just in case.
                $remaining_time = 0;
            }

            /* This user was already logged in so we disconnect the old one, or
            keep the old one, refusing the new login, or
            allow the login */

            if (!isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 2 does not exists = NOT set");
            } else {
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 2 exists = set");
            }

            if ($config['captiveportal'][$cpzone]['noconcurrentlogins'] == "last") {
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "Found last");
            } else {
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "Found NOT last");
            }

            if (!isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
                /* 'noconcurrentlogins' not set : accept login 'username' creating multiple sessions. */
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 3 does not exists = NOT set");
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - NOT TERMINATINGACCEPT EXISTING SESSION(S)");
//////////////////////// add (multiple) firewall rule here on next time///////////////////////
            } elseif ($config['captiveportal'][$cpzone]['noconcurrentlogins'] == "last") {
                /* Classic situation : accept the new login, disconnect the old - present - connection */
                if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
                    captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 4 exists = set");
                } else {
                    captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 4 does not exists = NOT set");
                }
                captiveportal_disconnect($cpentry, 13);
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - TERMINATING OLD SESSION");
//////////////////////// renew firewall rule here on next time///////////////////////
                $unsetindexes[] = $cpentry[5];
                break;
            } else {
                /* Implicit 'first' : refuse the new login - 'username' is already logged in */
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT VOUCHER LOGIN - NOT ALLOWED KEEPING OLD SESSION ");
                unlock($cpdblck);
                return 2;
            }
        } elseif ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && ($username != 'unauthenticated')) {
            if ($config['captiveportal'][$cpzone]['noconcurrentlogins'] == "last") {
                /* on the same username */
                if (strcasecmp($cpentry[4], $username) == 0) {
                    /* This user was already logged in so we disconnect the old one */
                    captiveportal_disconnect($cpentry, 13);
                    captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT USER LOGIN - TERMINATING OLD SESSION");
                    $unsetindexes[] = $cpentry[5];
//////////////////////// delete/add (renew) firewall rule here on next time///////////////////////
                    //add_crew_linked_rule($clientip, $username);
//////////////////////////////////////////////////////////////////////////////////////////////////
                    break;
                }
            } else {
                /* Implicit 'first' : refuse the new login - 'username' is already logged in */
                captiveportal_trafficquotaauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT USER LOGIN - NOT ALLOWED KEEPING OLD SESSION ");
                unlock($cpdblck);
                return 2;
            }
        }
    }
    unset($cpdb);

    if (!empty($unsetindexes)) {
        captiveportal_remove_entries($unsetindexes);
    }

    if ($attributes['voucher'] && $remaining_time <= 0) {
        return 0;       // voucher already used and no time left
    }

    if (!isset($sessionid)) {
        if ($existing_sessionid != null) { // existing_sessionid should only be set during XMLRPC sync
            $sessionid = $existing_sessionid;
        } else {
            /* generate unique session ID */
            $tod = gettimeofday();
            $sessionid = substr(md5(mt_rand() . $tod['sec'] . $tod['usec'] . $clientip . $clientmac), 0, 16);
        }

        if (isset($config['captiveportal'][$cpzone]['peruserbw'])) {
            $dwfaultbw_up = !empty($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
            $dwfaultbw_down = !empty($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
        } else {
            $dwfaultbw_up = $dwfaultbw_down = 0;
        }
        /* pipe throughputs must always be an integer, enforce that restriction again here. */
        if (isset($config['captiveportal'][$cpzone]['radiusperuserbw'])) {
            $bw_up = round(!empty($attributes['bw_up']) ? intval($attributes['bw_up'])/1000 : $dwfaultbw_up, 0);
            $bw_down = round(!empty($attributes['bw_down']) ? intval($attributes['bw_down'])/1000 : $dwfaultbw_down, 0);
        } else {
            $bw_up = round($dwfaultbw_up,0);
            $bw_down = round($dwfaultbw_down,0);
        }

        if (isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {

            $mac = array();
            $mac['action'] = 'pass';
            $mac['mac'] = $clientmac;
            $mac['ip'] = $clientip; /* Used only for logging */
            $mac['username'] = $username;
            if ($attributes['voucher']) {
                $mac['logintype'] = "voucher";
            }
            if ($username == "unauthenticated") {
                $mac['descr'] = "Auto-added";
            } else if ($authmethod == "voucher") {
                $mac['descr'] = "Auto-added for voucher {$username}";
            } else {
                $mac['descr'] = "Auto-added for user {$username}";
            }
            if (!empty($bw_up)) {
                $mac['bw_up'] = $bw_up;
            }
            if (!empty($bw_down)) {
                $mac['bw_down'] = $bw_down;
            }
            if (!is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
                $config['captiveportal'][$cpzone]['passthrumac'] = array();
            }
            //check for mac duplicates before adding it to config.
            $mac_duplicate = false;
            foreach($config['captiveportal'][$cpzone]['passthrumac'] as $mac_check){
                if($mac_check['mac'] == $mac['mac']){
                    $mac_duplicate = true;
                }
            }
            if(!$mac_duplicate){
                $config['captiveportal'][$cpzone]['passthrumac'][] = $mac;
            }
            unlock($cpdblck);
            $macrules = captiveportal_passthrumac_configure_entry($mac);
            file_put_contents("{$g['tmp_path']}/macentry_{$cpzone}.rules.tmp", $macrules);
            mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry_{$cpzone}.rules.tmp");
            $writecfg = true;
        } else {
            /* See if a pipeno is passed, if not start sessions because this means there isn't one atm */
            if (is_null($pipeno)) {
                $pipeno = captiveportal_get_next_dn_ruleno('auth');
            }

            /* if the pool is empty, return appropriate message and exit */
            if (is_null($pipeno)) {
                captiveportal_syslog("Zone: {$cpzone} - WARNING!  Captive portal has reached maximum login capacity");
                unlock($cpdblck);
                return false;
            }

            $bw_up_pipeno = $pipeno;
            $bw_down_pipeno = $pipeno + 1;
            //$bw_up /= 1000; // Scale to Kbit/s
            $_gb = @pfSense_ipfw_pipe("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
            $_gb = @pfSense_ipfw_pipe("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");

            $rule_entry = "{$clientip}/" . (is_ipaddrv6($clientip) ? "128" : "32");
            if (!isset($config['captiveportal'][$cpzone]['nomacfilter'])) {
                $rule_entry .= ",{$clientmac}";
            }
            $_gb = @pfSense_ipfw_table("{$cpzone}_auth_up", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_up_pipeno);
            $_gb = @pfSense_ipfw_table("{$cpzone}_auth_down", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_down_pipeno);

            if ($attributes['voucher']) {
                $attributes['session_timeout'] = $remaining_time;
            }

            /* handle empty attributes */
            $session_timeout = (!empty($attributes['session_timeout'])) ? $attributes['session_timeout'] : 'NULL';
            $idle_timeout = (!empty($attributes['idle_timeout'])) ? $attributes['idle_timeout'] : 'NULL';
            $session_terminate_time = (!empty($attributes['session_terminate_time'])) ? $attributes['session_terminate_time'] : 'NULL';
            $interim_interval = (!empty($attributes['interim_interval'])) ? $attributes['interim_interval'] : 'NULL';
            $traffic_quota = (!empty($attributes['maxbytes'])) ? $attributes['maxbytes'] : 'NULL';

            /* escape username */
            $safe_username = SQLite3::escapeString($username);

            /* encode password in Base64 just in case it contains commas */
            $bpassword = (isset($config['captiveportal'][$cpzone]['reauthenticate'])) ? base64_encode($password) : '';
            $insertquery = "INSERT INTO captiveportal (allow_time, pipeno, ip, mac, username, sessionid, bpassword, session_timeout, idle_timeout, session_terminate_time, interim_interval, traffic_quota, bw_up, bw_down, authmethod, context) ";
            $insertquery .= "VALUES ({$allow_time}, {$pipeno}, '{$clientip}', '{$clientmac}', '{$safe_username}', '{$sessionid}', '{$bpassword}', ";
            $insertquery .= "{$session_timeout}, {$idle_timeout}, {$session_terminate_time}, {$interim_interval}, {$traffic_quota}, {$bw_up}, {$bw_down}, '{$authmethod}', '{$context}')";

            /* store information to database */
            captiveportal_write_db($insertquery);

            unlock($cpdblck);
            unset($insertquery, $bpassword);
            $radacct = isset($config['captiveportal'][$cpzone]['radacct_enable']) ? true : false;
            if ($authmethod === 'radius' && $radacct) {
                captiveportal_send_server_accounting('start',
                    $pipeno, // ruleno
                    $username, // username
                    $clientip, // clientip
                    $clientmac, // clientmac
                    $sessionid, // sessionid
                    time());  // start time
            }
            if(isset($syncip)&&isset($syncport)){
                if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport, $syncuser, $syncpass, isset($existing_sessionid))) {
                    // $existing_sessionid prevent carp loop : only forward
                    // the connection to the other node if we generated the sessionid by ourselves
                    $rpc_client = new pfsense_xmlrpc_client();
                    $rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
                    $rpc_client->set_noticefile("CaptivePortalUserSync");
                    $arguments = array(
                        'clientip' => $clientip,
                        'clientmac' => $clientmac,
                        'username' => $username,
                        'password' => $password,
                        'attributes' => $attributes,
                        'allow_time' => $allow_time,
                        'authmethod' => $authmethod,
                        'context' => $context,
                        'sessionid' => $sessionid
                    );
                    $rpc_client->xmlrpc_method('captive_portal_sync',
                        array(
                            'op' => 'connect_user',
                            'zone' => $cpzone,
                            'user' => base64_encode(serialize($arguments))
                        )
                    );
                }
            }
        }
    } else {
        /* NOTE: #3062-11 If the pipeno has been allocated free it to not DoS the CP */
        if (!is_null($pipeno)) {
            captiveportal_free_dn_ruleno($pipeno);
        }

        unlock($cpdblck);
    }

    if ($writecfg == true) {
        write_config(gettext("Captive Portal allowed users configuration changed"));
    }

    if ($existing_sessionid !== null) {
        if (!empty($sessionid)) {
            return $sessionid;
        } else {
            return false;
        }
    }
    /* redirect user to desired destination */
    if (is_URL($attributes['url_redirection'])) {
        $my_redirurl = $attributes['url_redirection'];
    } else if (is_URL($config['captiveportal'][$cpzone]['redirurl'])) {
        $my_redirurl = $config['captiveportal'][$cpzone]['redirurl'];
    } else if (is_URL($redirurl)) {
        $my_redirurl = $redirurl;
    }
    if (isset($config['captiveportal'][$cpzone]['logoutwin_enable']) && !isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
        $ourhostname = portal_hostname_from_client_ip($clientip);
        $protocol = (isset($config['captiveportal'][$cpzone]['httpslogin'])) ? 'https://' : 'http://';
        $logouturl = "{$protocol}{$ourhostname}/";

        if (isset($attributes['reply_message'])) {
            $message = $attributes['reply_message'];
        } else {
            $message = 0;
        }
        include_once("{$g['varetc_path']}/captiveportal-{$cpzone}.html");

    }
    /*else {
        portal_reply_page($my_redirurl, "redir", "Just redirect the user.");
    }*/

    return $sessionid;
}

/*
 * Used for when pass-through credits are enabled.
 * Returns true when there was at least one free login to deduct for the MAC.
 * Expired entries are removed as they are seen.
 * Active entries are updated according to the configuration.
 */
function portal_consume_passthrough_credit($clientmac) {
    global $config, $cpzone;

    if (!empty($config['captiveportal'][$cpzone]['freelogins_count']) && is_numeric($config['captiveportal'][$cpzone]['freelogins_count'])) {
        $freeloginscount = $config['captiveportal'][$cpzone]['freelogins_count'];
    } else {
        return false;
    }

    if (!empty($config['captiveportal'][$cpzone]['freelogins_resettimeout']) && is_numeric($config['captiveportal'][$cpzone]['freelogins_resettimeout'])) {
        $resettimeout = $config['captiveportal'][$cpzone]['freelogins_resettimeout'];
    } else {
        return false;
    }

    if ($freeloginscount < 1 || $resettimeout <= 0 || !$clientmac) {
        return false;
    }

    $updatetimeouts = isset($config['captiveportal'][$cpzone]['freelogins_updatetimeouts']);

    /*
     * Read database of used MACs.  Lines are a comma-separated list
     * of the time, MAC, then the count of pass-through credits remaining.
     */
    $usedmacs = captiveportal_read_usedmacs_db();

    $currenttime = time();
    $found = false;
    foreach ($usedmacs as $key => $usedmac) {
        $usedmac = explode(",", $usedmac);

        if ($usedmac[1] == $clientmac) {
            if ($usedmac[0] + ($resettimeout * 3600) > $currenttime) {
                if ($usedmac[2] < 1) {
                    if ($updatetimeouts) {
                        $usedmac[0] = $currenttime;
                        unset($usedmacs[$key]);
                        $usedmacs[] = implode(",", $usedmac);
                        captiveportal_write_usedmacs_db($usedmacs);
                        xmlrpc_sync_usedmacs($usedmacs);
                    }

                    return false;
                } else {
                    $usedmac[2] -= 1;
                    $usedmacs[$key] = implode(",", $usedmac);
                }

                $found = true;
            } else {
                unset($usedmacs[$key]);
            }

            break;
        } else if ($usedmac[0] + ($resettimeout * 3600) <= $currenttime) {
            unset($usedmacs[$key]);
        }
    }

    if (!$found) {
        $usedmac = array($currenttime, $clientmac, $freeloginscount - 1);
        $usedmacs[] = implode(",", $usedmac);
    }

    captiveportal_write_usedmacs_db($usedmacs);
    xmlrpc_sync_usedmacs($usedmacs);
    return true;
}

function xmlrpc_sync_usedmacs($usedmacs) {
    global $config, $cpzone;

    // XMLRPC Call over to the other node
    if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport,
        $syncuser, $syncpass, $carp_loop)) {
        $rpc_client = new pfsense_xmlrpc_client();
        $rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
        $rpc_client->set_noticefile("CaptivePortalUsedmacsSync");
        $arguments = array(
            'usedmacs' => $usedmacs
        );

        $rpc_client->xmlrpc_method('captive_portal_sync',
            array(
                'op' => 'write_usedmacs',
                'zone' => $cpzone,
                'arguments' => base64_encode(serialize($arguments))
            )
        );
    }
}

function captiveportal_read_usedmacs_db() {
    global $g, $cpzone;

    $cpumaclck = lock("captiveusedmacs{$cpzone}");
    if (file_exists("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db")) {
        $usedmacs = file("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$usedmacs) {
            $usedmacs = array();
        }
    } else {
        $usedmacs = array();
    }

    unlock($cpumaclck);
    return $usedmacs;
}

function captiveportal_write_usedmacs_db($usedmacs) {
    global $g, $cpzone;

    $cpumaclck = lock("captiveusedmacs{$cpzone}", LOCK_EX);
    @file_put_contents("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db", implode("\n", $usedmacs));
    unlock($cpumaclck);
}

function captiveportal_blocked_mac($mac) {
    global $config, $g, $cpzone;

    if (empty($mac) || !is_macaddr($mac)) {
        return false;
    }

    if (!is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
        return false;
    }

    foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $passthrumac) {
        if (($passthrumac['action'] == 'block') &&
            ($passthrumac['mac'] == strtolower($mac))) {
            return true;
        }
    }

    return false;

}

/* Captiveportal Radius Accounting */

function gigawords($bytes) {

    /*
     * RFC2866 Specifies a 32bit unsigned integer, which is a max of 4294967295
     * Currently there is a fault in the PECL radius_put_int function which can handle only 32bit signed integer.
     */

    // We use BCMath functions since normal integers don't work with so large numbers
    $gigawords = bcdiv( bcsub( $bytes, remainder($bytes) ) , GIGAWORDS_RIGHT_OPERAND) ;

    // We need to manually set this to a zero instead of NULL for put_int() safety
    if (is_null($gigawords)) {
        $gigawords = 0;
    }

    return $gigawords;
}

function remainder($bytes) {
    // Calculate the bytes we are going to send to the radius
    $bytes = bcmod($bytes, GIGAWORDS_RIGHT_OPERAND);

    if (is_null($bytes)) {
        $bytes = 0;
    }

    return $bytes;
}

function captiveportal_send_server_accounting($type = 'on', $ruleno = null, $username = null, $clientip = null, $clientmac = null, $sessionid = null, $start_time = null, $stop_time = null, $term_cause = null) {
    global $cpzone, $config;

    $cpcfg = $config['captiveportal'][$cpzone];
    $acctcfg = auth_get_authserver($cpcfg['radacct_server']);
    if (!isset($cpcfg['radacct_enable']) || empty($acctcfg) ||
        captiveportal_ha_is_node_in_backup_mode($cpzone)) {
        return null;
    }

    // ---- retry 정책 ----
    // $max_retry: PHP 레벨 재시도 횟수.
    // addServer() 5번째 인자(maxtries)=1 로 PEAR 내부 재시도를 비활성화했으므로
    // PHP 레벨에서만 재시도가 일어난다.
    // 최대 대기 = max_retry × radius_timeout 초.
    // radius_timeout=3 기준: 1×3=3초 (start), 2×3=6초 (stop/update).
    $max_retry = ($type === 'start' || $type === 'on' || $type === 'off') ? 1 : 2;
    $retry_delay_us = 300000; // 0.3초
    $first_try_ts = time();

    $should_retry = function($err) {
        return (bool)preg_match('/No valid RADIUS responses received|timed? out|timeout/i', (string)$err);
    };

    if ($type === 'on') {
        $racct = new Auth_RADIUS_Acct_On;
    } elseif ($type === 'off') {
        $racct = new Auth_RADIUS_Acct_Off;
    } elseif ($type === 'start') {
        $racct = new Auth_RADIUS_Acct_Start;
        if (!is_int($start_time)) {
            $start_time = time();
        }
        add_crew_linked_rule($clientip, $username);
        // write_config 제거: pfctl table 방식으로 전환, config.xml 수정 불필요
    } elseif ($type === 'stop') {
        $racct = new Auth_RADIUS_Acct_Stop;
        if (!is_int($stop_time)) {
            $stop_time = time();
        }
        del_crew_linked_rule($clientip, $username, false);
        // kill_states=false: 실제 state 제거는 captiveportal_disconnect() 가 deferred 큐에 적재한다.
        // write_config 제거: pfctl table 방식으로 전환, config.xml 수정 불필요
    } elseif ($type === 'update') {
        $racct = new Auth_RADIUS_Acct_Update;
        if (!is_int($stop_time)) {
            $stop_time = time();
        }
    } else {
        return null;
    }

    // maxtries=1: PEAR 내부 재시도 비활성화.
    // 기본값(3)이면 타임아웃 1회당 최대 radius_timeout×3 초를 소비해
    // 로그인 응답이 18-27 초 지연될 수 있다.
    // PHP 레벨 retry 는 위 $max_retry 로 제어한다.
    $racct->addServer($acctcfg['host'], $acctcfg['radius_acct_port'],
        $acctcfg['radius_secret'], $acctcfg['radius_timeout'], 1);

    $racct->authentic = RADIUS_AUTH_RADIUS;
    if ($cpcfg['auth_method'] === 'radmac' && $username === "unauthenticated" && !empty($clientmac)) {
        $racct->username = mac_format($clientmac);
    } elseif (!empty($username)) {
        $racct->username = $username;
    }

    // ---- start() retry ----
    $started = false;
    for ($i = 1; $i <= $max_retry; $i++) {
        $res = $racct->start();
        if (!PEAR::isError($res)) {
            $started = true;
            break;
        }

        $err = $racct->getError();
        if (!$should_retry($err) || $i === $max_retry) {
            captiveportal_syslog('RADIUS ACCOUNTING FAILED : '.$err);
            $racct->close();
            return null;
        }

        usleep($retry_delay_us);
    }

    // ---- 이하 attribute 세팅은 그대로 ----
    $nasip = $acctcfg['radius_nasip_attribute'];
    if (!is_ipaddr($nasip)) {
        $nasip = get_interface_ip($nasip);
        if (!is_ipaddr($nasip)) {
            $nasip = get_interface_ip();
        }
    }
    $nasmac = get_interface_mac(find_ip_interface($nasip));
    $racct->putAttribute(RADIUS_NAS_IP_ADDRESS, $nasip, "addr");
    $racct->putAttribute(RADIUS_NAS_IDENTIFIER, empty($cpcfg["radiusnasid"]) ? "CaptivePortal-{$cpzone}" : $cpcfg["radiusnasid"]);

    if (is_int($ruleno)) {
        $racct->putAttribute(RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET);
        $racct->putAttribute(RADIUS_NAS_PORT, intval($ruleno), 'integer');
    }

    if (!empty($sessionid)) {
        $racct->putAttribute(RADIUS_ACCT_SESSION_ID, $sessionid);
    }

    if (!empty($clientip) && is_ipaddr($clientip)) {
        $racct->putAttribute(RADIUS_FRAMED_IP_ADDRESS, $clientip, "addr");
    }
    if (!empty($clientmac)) {
        $racct->putAttribute(RADIUS_CALLING_STATION_ID, mac_format($clientmac));
    }
    if (!empty($nasmac)) {
        $racct->putAttribute(RADIUS_CALLED_STATION_ID, mac_format($nasmac).':'.gethostname());
    }

    if (($type === 'stop' || $type === 'update') && is_int($start_time)) {
        $volume = getVolume($clientip);
        $session_time = $stop_time - $start_time;
        $volume['input_bytes_radius'] = remainder($volume['input_bytes']);
        $volume['input_gigawords'] = gigawords($volume['input_bytes']);
        $volume['output_bytes_radius'] = remainder($volume['output_bytes']);
        $volume['output_gigawords'] = gigawords($volume['output_bytes']);

        $racct->putAttribute(RADIUS_ACCT_INPUT_PACKETS, intval($volume['input_pkts']), "integer");
        $racct->putAttribute(RADIUS_ACCT_INPUT_OCTETS, intval($volume['input_bytes_radius']), "integer");
        $racct->putAttribute(RADIUS_ACCT_OUTPUT_PACKETS, intval($volume['output_pkts']), "integer");
        $racct->putAttribute(RADIUS_ACCT_OUTPUT_OCTETS, intval($volume['output_bytes_radius']), "integer");
        $racct->putAttribute(RADIUS_ACCT_SESSION_TIME, intval($session_time), "integer");

        $racct->putAttribute(CUSTOM_RADIUS_ACCT_OUTPUT_GIGAWORDS, intval($volume['output_gigawords']), "integer");
        $racct->putAttribute(CUSTOM_RADIUS_ACCT_INPUT_GIGAWORDS, intval($volume['input_gigawords']), "integer");
        $racct->session_time = $session_time;
    }

    if ($type === 'stop') {
        if (empty($term_cause)) {
            $term_cause = 1;
        }
        $racct->putAttribute(RADIUS_ACCT_TERMINATE_CAUSE, $term_cause);
    }

    // ---- send() retry (응답 없음/timeout일 때만) ----
    $result = null;
    for ($i = 1; $i <= $max_retry; $i++) {
        // (선택) 지연시간 힌트: 재시도일 때만 Delay-Time 넣고 싶으면 주석 해제
        // if ($i > 1) {
        //     $racct->putAttribute(RADIUS_ACCT_DELAY_TIME, max(0, time() - $first_try_ts), "integer");
        // }

        $result = $racct->send();

        if (!PEAR::isError($result)) {
            // 기존 로직 유지
            if ($result === true) {
                break;
            } else {
                $result = false;
                break;
            }
        }

        $err = $racct->getError();
        if (!$should_retry($err) || $i === $max_retry) {
            captiveportal_syslog('RADIUS ACCOUNTING FAILED : '.$err);
            $result = null;
            break;
        }

        usleep($retry_delay_us);
    }

    $racct->close();
    return $result;
}

function captiveportal_isip_logged($clientip) {
    global $g, $cpzone;
    /* read in client database */
    $query = "WHERE ip = '{$clientip}'";
    $cpdb = captiveportal_read_db($query);
    foreach ($cpdb as $cpentry) {
        return $cpentry;
    }
}
function already_connected($clientip, $clientmac) {
    global $g, $cpzone;
    if (($clientip != "") && ($clientmac != "")) {
        $query = "WHERE ip = '{$clientip}' AND mac = '{$clientmac}'";
        $cpentry = captiveportal_read_db($query);
        if($cpentry!=="") {
            return $cpentry[0];
        }
        else {
            return false;
        }
    } else
        return false;
}
function cpdb_lookup_best($clientip, $clientmac) {
    // Returns: ['match' => 'exact'|'mac'|'ip-mismatch'|'none', 'entry' => array|null]
    $cpdb = captiveportal_read_db();

    $exact = null;
    $macMatch = null;
    $ipOnly = null;

    foreach ($cpdb as $cpentry) {
        if (!is_array($cpentry) || !isset($cpentry[2], $cpentry[3])) {
            continue;
        }

        // last match wins (최근 엔트리가 뒤에 있을 가능성)
        if ($cpentry[3] === $clientmac) {
            $macMatch = $cpentry;
        }

        if ($cpentry[2] === $clientip) {
            if ($cpentry[3] === $clientmac) {
                $exact = $cpentry;
            } else {
                $ipOnly = $cpentry; // same IP but different MAC => concurrent candidate
            }
        }
    }

    if ($exact !== null) return ['match' => 'exact', 'entry' => $exact];
    if ($macMatch !== null) return ['match' => 'mac', 'entry' => $macMatch];
    if ($ipOnly !== null) return ['match' => 'ip-mismatch', 'entry' => $ipOnly];
    return ['match' => 'none', 'entry' => null];
}

/**
 * 기존 세션을 새 IP 로 마이그레이션한다. (같은 기기가 DHCP 등으로 IP 만 바뀐 경우)
 *
 * sessionid 는 그대로 유지하므로 RADIUS/datacounter 회계 연속성이 보장된다.
 * (datacounter 는 username+sessionid 기준이라 IP 변경과 무관)
 *
 *  - auth_up/auth_down ipfw 테이블: 구IP 제거 → 신IP 추가 (동일 pipeno 재사용)
 *  - 라우팅 pfctl 테이블(cp_gw_*): 구IP 제거 + state 정리 → 신IP 추가
 *  - captiveportal DB: ip/mac 갱신
 *
 * @param array  $cpentry  기존 세션 DB row (숫자 인덱스 배열)
 * @param string $newip    새 클라이언트 IP
 * @param string $newmac   새 클라이언트 MAC (보통 기존과 동일)
 * @return array|false     갱신된 cpentry (ip/mac 반영), 실패 시 false
 */
function captiveportal_migrate_session_ip($cpentry, $newip, $newmac) {
    global $config, $cpzone;

    if (!is_array($cpentry) || !is_ipaddr($newip)) {
        return false;
    }

    $oldip     = $cpentry[2] ?? '';
    $pipeno    = $cpentry[1] ?? null;
    $username  = $cpentry[4] ?? '';
    $sessionid = $cpentry[5] ?? '';

    if ($sessionid === '' || !is_ipaddr($oldip)) {
        return false;
    }

    // pipeno 가 유효하지 않으면 신IP 를 auth 테이블에 넣을 수 없어
    // "connected 인데 통신 불가" 상태가 된다. 이 경우 마이그레이션을 포기하고
    // false 를 반환해 호출측이 정상 로그인(파이프 재할당)을 받게 한다.
    if ($pipeno === null || $pipeno === '' || !is_numeric($pipeno)) {
        return false;
    }

    $cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

    $newsn = (is_ipaddrv6($newip)) ? 128 : 32;
    $oldsn = (is_ipaddrv6($oldip)) ? 128 : 32;

    // 1) auth_up/auth_down: 구IP 제거
    @pfSense_ipfw_table("{$cpzone}_auth_up",   IP_FW_TABLE_XDEL, "{$oldip}/{$oldsn}");
    @pfSense_ipfw_table("{$cpzone}_auth_down", IP_FW_TABLE_XDEL, "{$oldip}/{$oldsn}");

    // 2) auth_up/auth_down: 신IP 추가 (기존 pipeno 그대로 재사용 → 대역폭 파이프 유지)
    $rule_entry = "{$newip}/{$newsn}";
    if (!isset($config['captiveportal'][$cpzone]['nomacfilter'])) {
        $rule_entry .= ",{$newmac}";
    }
    @pfSense_ipfw_table("{$cpzone}_auth_up",   IP_FW_TABLE_XADD, "{$rule_entry}", (int)$pipeno);
    @pfSense_ipfw_table("{$cpzone}_auth_down", IP_FW_TABLE_XADD, "{$rule_entry}", (int)$pipeno + 1);

    // 3) DB: ip/mac 갱신 (sessionid 유지)
    $safe_newip  = SQLite3::escapeString($newip);
    $safe_newmac = SQLite3::escapeString((string)$newmac);
    $safe_sid    = SQLite3::escapeString($sessionid);
    captiveportal_write_db("UPDATE captiveportal SET ip='{$safe_newip}', mac='{$safe_newmac}' WHERE sessionid='{$safe_sid}'");

    unlock($cpdblck);

    // 4) 라우팅 테이블: 구IP 제거 + state 정리 → 신IP 추가
    //    구IP 는 더 이상 사용하지 않으므로 즉시 state kill 해도 현재 포털 연결(신IP)에 영향 없음.
    del_crew_linked_rule($oldip, $username, true);
    add_crew_linked_rule($newip, $username);

    cp_wireless_log("[MIGRATE] user={$username} sid={$sessionid} {$oldip} -> {$newip} mac={$newmac}");

    // index.php 가 [5]=sessionid 를 사용하므로 갱신된 row 반환
    $cpentry[2] = $newip;
    $cpentry[3] = $newmac;
    return $cpentry;
}

/**
 * 동일 MAC·다른 IP 세션을 찾아 신IP 로 자동 마이그레이션한다.
 * (정확 일치(IP+MAC) 실패 시 index.php 에서 호출)
 *
 * quota 초과 사용자는 마이그레이션하지 않고 기존 세션을 정리한 뒤 false 반환
 * → 호출측에서 로그인 프롬프트 표시.
 *
 * @return array|false  마이그레이션된 cpentry, 또는 false(해당없음/거부)
 */
function captiveportal_try_migrate_session_by_mac($newip, $newmac) {
    global $config, $cpzone;

    if (empty($newmac) || !is_ipaddr($newip)) {
        return false;
    }

    // MAC 으로 기존 세션 조회 (IP 는 다를 수 있음)
    $safe_mac = SQLite3::escapeString($newmac);
    $rows = captiveportal_read_db("WHERE mac = '{$safe_mac}'");
    if (empty($rows) || !is_array($rows)) {
        return false;
    }

    // IP 가 신IP 와 다른 엔트리만 마이그레이션 대상 (동일 IP 는 already_connected 가 이미 처리)
    $cpentry = null;
    foreach ($rows as $row) {
        if (is_array($row) && isset($row[2]) && $row[2] !== $newip) {
            $cpentry = $row;  // 마지막(최근) 매칭 우선
        }
    }
    if ($cpentry === null) {
        return false;
    }

    $username = $cpentry[4] ?? '';

    // quota 재확인: 사용자의 집계 주기(varuserspointoftime) 기준
    $timerange = '';
    foreach (($config['installedpackages']['freeradius']['config'] ?? []) as $u) {
        if (($u['varusersusername'] ?? '') === $username) {
            $timerange = $u['varuserspointoftime'] ?? '';
            break;
        }
    }
    if (captiveportal_check_quota_exceeded($username, $timerange)) {
        // quota 초과 → 자동 로그인 거부, 기존 세션 정리 후 재인증 유도
        cp_wireless_log("[MIGRATE-DENY] user={$username} quota exceeded; disconnecting stale session sid={$cpentry[5]}");
        captiveportal_disconnect_client($cpentry[5], 1);
        return false;
    }

    return captiveportal_migrate_session_ip($cpentry, $newip, $newmac);
}

function getusername($sessionid) {
    global $cpzone;
    $query = "WHERE sessionid = '{$sessionid}'";
    $cpdb = captiveportal_read_db();
    /* Lookup the username*/
    foreach ($cpdb as $cpentry) {
        if ($cpentry[5] == $sessionid){
            return $cpentry[4];
        }
    }
    return false;

}
function getsession($username) {
    global $cpzone;
    $cpdb = captiveportal_read_db();
    /* Lookup the username (대소문자 무시).
       세션 DB 의 username 과 config/요청 username 의 대소문자 불일치로 조회가
       실패하면 무효 리셋(파일만 삭제 → 살아있는 세션이 되씀)·로그아웃 실패로
       이어지므로 strcasecmp 로 매칭한다. */
    foreach ($cpdb as $cpentry) {
        if (isset($cpentry[4]) && strcasecmp((string)$cpentry[4], (string)$username) === 0){
            return $cpentry[5];
        }
    }
    return false;
}

/**
 * 주어진 username 의 활성 CP 세션 ID 를 전부 반환 (대소문자 무시).
 * getsession()은 첫 세션만 반환하므로, 다중 기기 동시 로그인 대응으로 별도 제공.
 * captiveportal_read_db() 는 전역 $cpzone 에 의존한다.
 */
function cp_get_sessionids_for_username_ci(string $username): array {
    global $cpzone;
    $out  = array();
    $cpdb = captiveportal_read_db();
    if (is_array($cpdb)) {
        foreach ($cpdb as $cpentry) {
            if (isset($cpentry[4]) && strcasecmp((string)$cpentry[4], $username) === 0) {
                if (isset($cpentry[5]) && (string)$cpentry[5] !== '') {
                    $out[] = $cpentry[5];
                }
            }
        }
    }
    return $out;
}

function getcurquota($sessionid) {
    global $config;

    $username = getusername($sessionid);
    if ($username === "" || $username === null) {
        return "0.00";
    }

    $safeUser = preg_replace('/[^0-9a-zA-Z._:-]/', 'X', (string)$username);

    $timerange = '';
    foreach ($config['installedpackages']['freeradius']['config'] as $value) {
        if (($value['varusersusername'] ?? '') === $username) {
            $timerange = (string)($value['varusersmaxtotaloctetstimerange'] ?? '');
            break;
        }
    }

    $safeRange = preg_replace('/[^a-z]/', '', strtolower($timerange));
    if ($safeRange === '') {
        return "0.00";
    }

    $dir = "/var/log/radacct/datacounter/{$safeRange}";
    $baseFile = "{$dir}/used-octets-{$safeUser}";
    $sessionPattern = "{$dir}/used-octets-{$safeUser}-*";

    $usagenum = 0;

    // BASE 파일
    if (is_file($baseFile) && is_readable($baseFile)) {
        $line = trim((string)@file_get_contents($baseFile));
        if (preg_match('/^\d+$/', $line)) {
            $usagenum += (int)$line;
        }
    }

    // SESSION 파일
    $files = glob($sessionPattern, GLOB_NOSORT);
    if (is_array($files)) {
        foreach ($files as $filepath) {
            if (!is_file($filepath) || !is_readable($filepath)) continue;
            $line = trim((string)@file_get_contents($filepath));
            if (preg_match('/^(\d+)/', $line, $m)) {
                $usagenum += (int)$m[1];
            }
        }
    }

    return number_format($usagenum / 1048576, 2, '.', '');
}

function check_quota($username, $timerange)
{
    // auth 셸과 비슷하게 sanitize
    $safeUser = preg_replace('/[^0-9a-zA-Z._:-]/', 'X', (string)$username);
    $safeRange = preg_replace('/[^a-z]/', '', strtolower((string)$timerange));

    $dir = "/var/log/radacct/datacounter/{$safeRange}";
    $baseFile = "{$dir}/used-octets-{$safeUser}";
    $sessionPattern = "{$dir}/used-octets-{$safeUser}-*"; // prefix 충돌 방지 (user1 vs user10)

    $usagenum = 0;

    // 1) BASE exact file
    if (is_file($baseFile) && is_readable($baseFile)) {
        $line = trim((string)@file_get_contents($baseFile));
        if (preg_match('/^\d+$/', $line)) {
            $usagenum += (int)$line;
        }
    }

    // 2) SESSION files exact-prefix only
    $files = glob($sessionPattern, GLOB_NOSORT);
    if (is_array($files)) {
        foreach ($files as $filepath) {
            if (!is_file($filepath) || !is_readable($filepath)) {
                continue;
            }

            $line = trim((string)@file_get_contents($filepath));

            // awk SUM += $1 와 비슷하게 첫 숫자만 사용
            if (preg_match('/^(\d+)/', $line, $m)) {
                $usagenum += (int)$m[1];
            }
        }
    }

    // MB, 소수점 2자리 (문자열 반환)
    return number_format($usagenum / 1048576, 2, '.', '');
}

function getmaxquota($username){
    global $config;
    if ($username != "") {
        foreach($config['installedpackages']['freeradius']['config'] as $item => $value){
            if($value['varusersusername']==$username){
                $result = $value['varusersmaxtotaloctets'];
            }
        }
    } else {
        $result = 0;
    }
    return $result;
}

/**
 * 사용자 사용량(쿼터)을 "즉시" 0 으로 리셋한다. (WIFI DATA RESET 명령 처리용)
 *
 * 기존 동작은 resetquota=true 태그만 달아두고 "차후 로그인 시" authenticate_user 에서
 * 파일을 지웠다. 본 함수는 그 리셋을 명령 시점에 바로 수행한다.
 *
 * 순서가 핵심:
 *   1) 활성 세션이 있으면 먼저 로그아웃(disconnect)시킨다. 로그아웃은 accounting Stop 을
 *      보내 세션을 종료하고 ipfw 카운터를 정리하므로, 이후 세션이 used-octets 파일을
 *      다시 쓰지 않는다. (살아있는 세션 중에 파일을 지우면 다음 Interim/Stop 이 다시 써서
 *      0 이 유지되지 않는다.)
 *   2) used-octets-{user}(메인) + used-octets-{user}-*(세션) 파일을 삭제 → 합산값 0.
 *      max-octets-{user}(한도)는 보존한다. timerange 디렉터리 전체를 훑어
 *      어느 range 든 확실히 0 으로 만든다(pointoftime↔maxtotaloctetstimerange 불일치 안전).
 *
 * 주의: resetquota/modified 태그는 호출측이 그대로 유지한다(차후 로그인 fallback).
 *       authenticate_user 의 차후-로그인 리셋은 그대로 두어 오프라인 유저/레이스를 커버.
 *
 * @param string $username
 * @return bool  유효 username 이면 true
 */
function captiveportal_reset_user_usage(string $username): bool {
    global $cpzone;
    if (empty($cpzone)) {
        $cpzone = 'crew';
    }

    $username = trim($username);
    // 파일 경로/세션 조회에 쓰이므로 안전한 사용자명만 허용
    if ($username === '' || !preg_match('/^[A-Za-z0-9_.:-]+$/', $username)) {
        cp_wireless_log("DATA RESET skipped (invalid username): '{$username}'");
        return false;
    }

    // 1) 활성 세션(대소문자 무시, 다중 기기 포함)을 먼저 전부 로그아웃
    //    → accounting Stop 으로 SESSFILE→USEDFILE 누적 후 세션 종료 + ipfw 카운터 정리.
    $sids = cp_get_sessionids_for_username_ci($username);
    foreach ($sids as $sid) {
        captiveportal_disconnect_client($sid, 6, "DATA RESET");
    }

    // 2) 재확인 가드: disconnect 후에도 활성 세션이 남아 있으면, 파일을 지워도
    //    살아있는 세션의 다음 Interim 이 사용량을 되써서 리셋이 무효가 된다.
    //    (getsession 케이스 미스로 disconnect 를 건너뛰던 기존 결함의 안전망.)
    //    → 삭제를 건너뛰고 경고를 남겨 "조용한 실패"를 가시화한다(다음 주기/명령이 재시도).
    $still = cp_get_sessionids_for_username_ci($username);
    if (!empty($still)) {
        cp_wireless_log("DATA RESET aborted: active session still present after disconnect for '{$username}' (sids=" . implode(',', $still) . ")");
        return false;
    }

    // 3) used-octets(메인 + 세션) 삭제 → 사용량 0. max-octets(한도)는 보존.
    $safeUser = preg_replace('/[^0-9a-zA-Z._:-]/', 'X', $username);
    $removed  = 0;
    foreach (array('daily', 'weekly', 'monthly', 'forever') as $range) {
        $base = "/var/log/radacct/datacounter/{$range}/used-octets-{$safeUser}";
        if (is_file($base)) {
            @unlink($base);
            $removed++;
        }
        $sessfiles = glob($base . '-*');
        if (is_array($sessfiles)) {
            foreach ($sessfiles as $f) {
                if (is_file($f)) {
                    @unlink($f);
                    $removed++;
                }
            }
        }
    }

    cp_wireless_log("DATA RESET applied (usage=0, files={$removed}): {$username}");
    return true;
}

function is_using_fx(){
    global $config;
    $result = "";
    foreach($config['gateways']['gateway_item'] as $key => $value){
        if($config['gateways']['defaultgw4'] == $value['name'] &&
            (strpos( $value['terminal_type'], 'vsat_pri') !== false||
                strpos( $value['terminal_type'], 'nexuswave_pri') !== false||
                strpos( $value['terminal_type'], 'nexuswave_sec') !== false)){
            $isusingvsat = true;
            break;
        }
    }
    if($isusingvsat){
        foreach($config['gateways']['gateway_item'] as $key => $value){
            if($value['terminal_type']=='vsat_sec'|| $value['terminal_type']=='nexuswave_thi'){
                $result=$value['name'];
                break;
            }
        }
    }
    return $result;
}

/**
 * ============================================================
 *  pfctl Table 기반 라우팅 관리 함수군  (per-gateway 동적 구조)
 *
 *  설계 원칙:
 *   - 게이트웨이 1개당 pfctl 테이블 1개 (cp_gw_{name})
 *   - 게이트웨이 추가/삭제/이름변경 → 코드 변경 없이 자동 대응
 *   - config.xml / filter_configure() 호출 없음 (로그인/로그아웃 시)
 *   - filter 재로드 후 복원은 captiveportal_configure() 훅이 담당
 *   - 1분 크론이 비정상 상황 자가복구 담당
 * ============================================================
 */

/**
 * gateway 이름을 pfctl/alias 에서 사용 가능한 안전한 문자열로 변환.
 * 반환: 'cp_gw_' + 소문자 영숫자+언더스코어, 최대 31자
 */
function cp_gw_table_name($gwname) {
    $safe = preg_replace('/[^a-z0-9]/', '_', strtolower($gwname));
    $safe = preg_replace('/_+/', '_', $safe);
    $safe = trim($safe, '_');
    return 'cp_gw_' . substr($safe, 0, 25);
}

/**
 * VPN 을 제외한 전체 WAN 게이트웨이 목록 반환.
 * terminal_type 이 설정된 게이트웨이만 포함 (미설정이면 관리 대상 외로 간주).
 * disabled 게이트웨이 제외.
 */
function cp_find_all_wan_gateways() {
    global $config;
    $result = [];

    foreach ($config['gateways']['gateway_item'] ?? [] as $gw) {
        $name  = trim($gw['name']  ?? '');
        $ttype = strtolower(trim($gw['terminal_type'] ?? ''));
        if ($name === '' || $ttype === '') continue;
        if (!empty($gw['disabled']))         continue;
        if (strpos($ttype, 'vpn') !== false) continue;
        $result[] = $gw;
    }

    return $result;
}

/**
 * 사용자명으로 varusersterminaltype(gateway name) 조회.
 * 빈 문자열이면 is_using_fx() 로 Auto 처리.
 */
function cp_get_terminaltype_for_user($userid) {
    global $config;

    $terminaltype = '';
    if (!is_array($config['installedpackages']['freeradius']['config'] ?? null)) {
        return '';
    }

    foreach ($config['installedpackages']['freeradius']['config'] as $value) {
        if (($value['varusersusername'] ?? '') === $userid) {
            $terminaltype = $value['varusersterminaltype'] ?? '';
            break;
        }
    }

    // Auto 사용자이고 FX(이중 WAN) 환경이면 보조 gateway 사용
    if ($terminaltype === '') {
        $fx = is_using_fx();
        if ($fx !== '') {
            $terminaltype = $fx;
        }
    }

    return $terminaltype;
}

/**
 * gateway name → pfctl table name 매핑.
 *
 * 탐색 순서:
 *   1) gateway_item 에서 name 정확히 일치하는 WAN 게이트웨이 → cp_gw_table_name() 반환
 *   2) 대소문자 무시 일치 (이름이 대소문자만 바뀐 경우)
 *   3) 찾지 못하면 null (Auto 또는 관리 대상 외)
 *
 * 반환값: 'cp_gw_{name}' 형태 문자열 | null
 */
function cp_get_pf_table_for_gateway($gwname) {
    global $config;

    if ($gwname === '') {
        return null;
    }

    $all_gws = cp_find_all_wan_gateways();

    // 1차: 정확히 일치
    foreach ($all_gws as $gw) {
        if ($gw['name'] === $gwname) {
            return cp_gw_table_name($gwname);
        }
    }

    // 2차: 대소문자 무시
    $lower = strtolower($gwname);
    foreach ($all_gws as $gw) {
        if (strtolower($gw['name']) === $lower) {
            return cp_gw_table_name($gw['name']);
        }
    }

    return null;
}

/**
 * config.xml 의 [CP Routing] 룰 전체를 현재 gateway 구성 기준으로 완전 재동기화.
 *
 * 처리 범위:
 *   - gateway 이름 변경 → 구 Rule 삭제 + 신 Rule 생성
 *   - gateway 추가    → 새 Pass Rule + Block Rule 자동 생성
 *   - gateway 삭제    → 불필요한 Rule 자동 제거
 *
 * 생성 규칙 (게이트웨이 N개 기준):
 *   Pass  : N개  (게이트웨이당 1개, route-to 해당 GW)
 *   Block : N*(N의 서로 다른 인터페이스 수 - 1) 개
 *           (자신의 WAN 이 아닌 모든 WAN 인터페이스에 block out)
 */
/**
 * 게이트웨이 테이블 이름에 해당하는 alias 가 없으면 자동 생성.
 * Host 타입, 빈 address (pfctl 이 직접 관리).
 * 반환: 생성됐으면 true, 이미 존재하면 false
 */
function cp_ensure_alias($name, $descr) {
    global $config;

    if (!is_array($config['aliases']['alias'])) {
        $config['aliases']['alias'] = [];
    }
    foreach ($config['aliases']['alias'] as $alias) {
        if (($alias['name'] ?? '') === $name) {
            return false; // 이미 존재
        }
    }
    $config['aliases']['alias'][] = [
        'name'    => $name,
        'type'    => 'host',
        'address' => '',
        'descr'   => $descr,
        'detail'  => '',
    ];
    return true;
}

function cp_refresh_pass_rules() {
    global $config;

    $all_gws = cp_find_all_wan_gateways();
    if (empty($all_gws)) {
        return;
    }

    $lan_iface  = $config['captiveportal']['crew']['interface'] ?? 'lan';
    $all_ifaces = array_unique(array_column($all_gws, 'interface'));

    // ── Alias 자동 생성 (없는 것만) ──────────────────────────────────────────
    $alias_changed = false;
    $alias_changed |= cp_ensure_alias('cp_gw_default', 'CP default gateway users (no terminaltype assigned)');
    foreach ($all_gws as $gw) {
        $table = cp_gw_table_name($gw['name']);
        $alias_changed |= cp_ensure_alias(
            $table,
            "CP routing table for gateway {$gw['name']} (terminal_type={$gw['terminal_type']})"
        );
    }

    // ── 기대하는 룰 세트를 구성 ──────────────────────────────────────────────
    $expected = [];

    // Default pass 룰: terminaltype 미설정 사용자 → 기본 게이트웨이로 통과 (route-to 없음)
    $expected[] = [
        'type'      => 'pass',
        'direction' => 'in',
        'interface' => $lan_iface,
        'src_alias' => 'cp_gw_default',
        'gateway'   => '',
        'descr'     => '[CP Routing] cp_gw_default pass (default gateway)',
    ];

    foreach ($all_gws as $gw) {
        $gwname   = $gw['name'];
        $table    = cp_gw_table_name($gwname);
        $gw_iface = $gw['interface'];

        // Pass Rule: 이 게이트웨이로 route-to (게이트웨이당 1개)
        $expected[] = [
            'type'      => 'pass',
            'direction' => 'in',
            'interface' => $lan_iface,
            'src_alias' => $table,
            'gateway'   => $gwname,
            'descr'     => "[CP Routing] {$table} route-to {$gwname}",
        ];

        // Block Rule: 자신의 WAN 이 아닌 다른 WAN 인터페이스마다 1개
        foreach ($all_ifaces as $iface) {
            if ($iface === $gw_iface) continue;
            $expected[] = [
                'type'      => 'block',
                'direction' => 'out',
                'interface' => $iface,
                'src_alias' => $table,
                'gateway'   => '',
                'descr'     => "[CP Routing] block {$table} on {$iface}",
            ];
        }
    }

    // ── 현재 config.xml 의 [CP Routing] 룰과 비교 ──────────────────────────
    $existing_descrs = [];
    foreach ($config['filter']['rule'] ?? [] as $rule) {
        if (isset($rule['floating']) && strpos($rule['descr'] ?? '', '[CP Routing]') !== false) {
            $existing_descrs[] = $rule['descr'];
        }
    }

    $expected_descrs = array_column($expected, 'descr');
    $need_add        = array_diff($expected_descrs, $existing_descrs);
    $need_remove     = array_diff($existing_descrs, $expected_descrs);

    // 변경 없으면 종료
    if (empty($need_add) && empty($need_remove)) {
        return;
    }

    // ── 삭제: 불필요해진 룰 제거 ─────────────────────────────────────────────
    foreach ($config['filter']['rule'] as $key => $rule) {
        if (!isset($rule['floating'])) continue;
        if (strpos($rule['descr'] ?? '', '[CP Routing]') === false) continue;
        if (in_array($rule['descr'], $need_remove, true)) {
            unset($config['filter']['rule'][$key]);
        }
    }
    $config['filter']['rule'] = array_values($config['filter']['rule']);

    // ── 추가: 누락된 룰 생성 ─────────────────────────────────────────────────
    foreach ($expected as $e) {
        if (!in_array($e['descr'], $need_add, true)) continue;

        $rule = [
            'id'         => '',
            'tracker'    => (string)(time() + rand(1, 9999)),
            'type'       => $e['type'],
            'floating'   => 'yes',
            'direction'  => $e['direction'],
            'quick'      => 'yes',
            'interface'  => $e['interface'],
            'ipprotocol' => 'inet',
            'tag'        => '',
            'tagged'     => '',
            'source'     => ['address' => $e['src_alias']],
            'destination'=> ['any' => true],
            'descr'      => $e['descr'],
            'updated'    => ['time' => (string)time(), 'username' => 'cp_routing_auto'],
            'created'    => ['time' => (string)time(), 'username' => 'cp_routing_auto'],
        ];
        if ($e['type'] === 'pass') {
            $rule['statetype'] = 'keep state';
            // gateway 비어있으면 route-to 없이 기본 라우팅 사용
            if ($e['gateway'] !== '') {
                $rule['gateway'] = $e['gateway'];
            }
        }

        // Floating 룰은 앞에 삽입 (우선순위 확보)
        array_unshift($config['filter']['rule'], $rule);
    }

    $added   = count($need_add);
    $removed = count($need_remove);

    if ($alias_changed || $added > 0 || $removed > 0) {
        write_config("cp_routing: aliases/rules resynced (aliases=+{$alias_changed}, rules added={$added}, removed={$removed})");

        // 변경사항을 pf 에 즉시 반영.
        // filter_configure() 내부에서 호출된 경우 재귀 방지를 위해
        // send_event 로 비동기 재로드 요청 (check_reload_status 가 처리)
        if (function_exists('send_event')) {
            send_event("filter reload");
        }
    }
}

/**
 * pfctl table 에 IP 원자적 교체.
 * $ips 가 비어있으면 테이블을 flush.
 */
function cp_sync_pf_table($table, array $ips) {
    $safeTable = escapeshellarg($table);
    if (empty($ips)) {
        mwexec("/sbin/pfctl -t {$safeTable} -T flush 2>/dev/null");
        return;
    }
    $tmp = "/tmp/cp_table_{$table}.txt";
    file_put_contents($tmp, implode("\n", array_unique($ips)) . "\n");
    mwexec("/sbin/pfctl -t {$safeTable} -T replace -f " . escapeshellarg($tmp));
}

/**
 * CP 세션 DB 기준으로 pfctl 라우팅 테이블 전체 재동기화.
 * captiveportal_configure() 훅 및 크론에서 호출.
 *
 * 수행 순서:
 *   1) config.xml Pass/Block Rule 이 현재 gateway 목록과 다르면 갱신
 *      (gateway 추가/삭제/이름변경 대응 — 다음 filter_configure() 사이클에 반영)
 *   2) CP 세션 DB → 각 게이트웨이별 pfctl 테이블 원자적 교체
 */
/**
 * crew zone CP 세션 DB 를 직접 읽어 세션 배열 반환.
 * captiveportal_read_db() 는 전역 $cpzone 에 의존하므로
 * cron/hook 에서 안전하게 호출하려면 직접 SQLite 파일을 열어야 함.
 * 반환: [[allow_time, pipeno, ip, mac, username, sessionid, ...], ...]
 */
function cp_read_crew_sessions() {
    global $g;

    $db_path = ($g['vardb_path'] ?? '/var/db') . '/captiveportalcrew.db';
    if (!file_exists($db_path)) {
        return [];
    }

    $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
    if (!$db) {
        return [];
    }

    $result   = [];
    $response = $db->query("SELECT allow_time, pipeno, ip, mac, username, sessionid FROM captiveportal");
    if ($response) {
        while ($row = $response->fetchArray(SQLITE3_NUM)) {
            $result[] = $row;
        }
    }
    $db->close();

    return $result;
}

function cp_sync_routing_tables() {
    // 1) gateway 변동 감지 및 Pass/Block Rule 동적 갱신
    cp_refresh_pass_rules();

    // 2) 모든 WAN 게이트웨이 테이블 + default 테이블을 빈 배열로 초기화
    $all_gws   = cp_find_all_wan_gateways();
    $table_ips = ['cp_gw_default' => []];
    foreach ($all_gws as $gw) {
        $table_ips[cp_gw_table_name($gw['name'])] = [];
    }

    // 3) 세션 DB 에서 IP → 게이트웨이 → 테이블 매핑
    // captiveportal_read_db() 는 전역 $cpzone 을 사용하므로 직접 DB 파일을 열어 읽음
    $sessions = cp_read_crew_sessions();
    if (is_array($sessions)) {
        foreach ($sessions as $s) {
            $ip   = $s[2] ?? '';
            $user = $s[4] ?? '';
            if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;

            $gwname = cp_get_terminaltype_for_user($user);
            $table  = cp_get_pf_table_for_gateway($gwname);

            if ($table === null) {
                // terminaltype 미설정 → 기본 게이트웨이 테이블
                $table = 'cp_gw_default';
            }

            if (array_key_exists($table, $table_ips)) {
                $table_ips[$table][] = $ip;
            }
        }
    }

    // 4) 각 테이블 원자적 교체 (빈 배열이면 flush)
    foreach ($table_ips as $table => $ips) {
        cp_sync_pf_table($table, $ips);
    }
}

/**
 * cp_gw_* pfctl 라우팅 테이블만 CP 세션 DB 기준으로 재적재(안전망).
 *
 * cp_sync_routing_tables() 와 달리 config.xml/룰을 건드리지 않는다
 * (cp_refresh_pass_rules 미호출 → write_config/filter_configure 없음 → #10 무관, 저비용).
 * 오직 pfctl 테이블 멤버십만 갱신한다.
 *
 * 목적(과금 무결성): route-to/block 룰은 테이블에 IP 가 있을 때만 동작한다.
 *   filter_configure()(게이트웨이 up/down 이 자동 트리거)가 빈 alias 기준으로
 *   테이블을 flush 하면, 그 순간 고정(pinned) 사용자 트래픽이 기본 경로(반대 uplink)로
 *   새어 오과금된다. 매분 이 함수로 재적재해 테이블이 절대 비지 않게 유지한다.
 *
 * 방어 가드:
 *   (1) empty-guard: 세션 읽기가 비거나 배열이 아니면 아무 것도 하지 않는다.
 *       (빈 세트로 전 테이블을 flush 하면 전원 라우팅이 붕괴되므로 절대 금지.
 *        실유저 0명이면 로그아웃 경로가 이미 테이블을 정리했으므로 skip 해도 안전.)
 *   (2) 미해석-고정 가드: terminal_type 이 "설정됐는데 매칭 실패"(오타/disabled/rename)면
 *       cp_gw_default 로 흡수(=누수)하지 않고 경고 로그만 남기고 어느 테이블에도 넣지 않는다.
 *       공란/'auto' 만 의도된 예외로 cp_gw_default(자동 라우팅) 처리.
 *       ※ 미해석 사용자의 진짜 블랙홀(전용 block 룰)은 별도 후속(재배포 동반).
 *
 * @return int 동기화한 IP 개수. 세션 없음/읽기 실패 시 -1(아무 것도 안 함).
 */
function cp_resync_pf_tables_only() {
    global $config;

    $sessions = cp_read_crew_sessions();

    // (1) empty-guard
    if (!is_array($sessions) || empty($sessions)) {
        return -1;
    }

    // username(소문자) => 원시 varusersterminaltype 맵 1회 구성
    // (cp_get_terminaltype_for_user 는 공란 시 is_using_fx 로 치환하므로,
    //  "의도된 auto/공란" vs "미해석 고정" 구분에는 원시값이 필요하다.)
    $rawMap = array();
    if (is_array($config['installedpackages']['freeradius']['config'] ?? null)) {
        foreach ($config['installedpackages']['freeradius']['config'] as $value) {
            $u = strtolower(trim((string)($value['varusersusername'] ?? '')));
            if ($u !== '') {
                $rawMap[$u] = strtolower(trim((string)($value['varusersterminaltype'] ?? '')));
            }
        }
    }

    // 대상 테이블 초기화(default + 모든 WAN GW)
    $all_gws   = cp_find_all_wan_gateways();
    $table_ips = array('cp_gw_default' => array());
    foreach ($all_gws as $gw) {
        $table_ips[cp_gw_table_name($gw['name'])] = array();
    }

    $count = 0;
    foreach ($sessions as $s) {
        $ip   = $s[2] ?? '';
        $user = $s[4] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            continue;
        }

        $gwname = cp_get_terminaltype_for_user($user);
        $table  = cp_get_pf_table_for_gateway($gwname);

        if ($table === null) {
            $rawNorm = $rawMap[strtolower(trim((string)$user))] ?? '';
            if ($rawNorm === '' || $rawNorm === 'auto') {
                // 의도된 예외: 공란/'auto' → 기본(자동) 라우팅
                $table = 'cp_gw_default';
            } else {
                // (2) 미해석-고정: 지정 GW 가 오타/disabled/rename 등으로 해석 실패.
                //     cp_gw_default 로 흡수하면 누수(오과금) → 경고만 남기고 넣지 않는다.
                cp_wireless_log("[CP Routing] PINNED user '{$user}' terminal_type '{$rawNorm}' unresolved (typo/disabled/renamed gateway) ip={$ip} — left unrouted (not default)");
                continue;
            }
        }

        if (array_key_exists($table, $table_ips)) {
            $table_ips[$table][] = $ip;
            $count++;
        }
    }

    // 테이블 원자적 교체(replace). empty-guard 통과(세션 존재) 상태이므로,
    // 특정 GW 테이블이 빈 것은 "그 GW 사용자 없음"이라 정상 flush.
    foreach ($table_ips as $table => $ips) {
        cp_sync_pf_table($table, $ips);
    }

    return $count;
}

/**
 * 구버전 per-user 로그인 룰 일괄 제거 (멱등 self-heal).
 *
 * 과거 구현은 로그인마다 $config['filter']['rule'] 에
 * "[User Rule] <id> auto generated rule" 을 추가하고 로그아웃 시 제거했다.
 * 현재는 pfctl 테이블(cp_gw_*) 방식으로 전환되어 이 룰들을 더 이상 만들지/지우지 않으므로,
 * 마이그레이션 이전 로그인 유저들의 룰이 config.xml 에 고아로 남는다.
 * → 한 번에 전부 제거(write_config 1회 + filter_configure 1회). 제거할 게 없으면 no-op.
 *
 * 주의: 다른 "[User Rule]" 룰(ban-all-rule / allow only 'this' PC / enable_crew_wifi 등)은
 *       건드리지 않는다. 접미사 " auto generated rule" 로만 정확히 매칭한다.
 *
 * @return int 제거한 룰 개수
 */
function cp_purge_legacy_user_login_rules(): int {
    global $config;

    if (empty($config['filter']['rule']) || !is_array($config['filter']['rule'])) {
        return 0;
    }

    $suffix = ' auto generated rule';
    $removed = 0;

    foreach ($config['filter']['rule'] as $k => $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $descr = (string)($rule['descr'] ?? '');
        if (strpos($descr, '[User Rule] ') === 0
            && substr($descr, -strlen($suffix)) === $suffix) {
            unset($config['filter']['rule'][$k]);
            $removed++;
        }
    }

    if ($removed > 0) {
        $config['filter']['rule'] = array_values($config['filter']['rule']);
        write_config("Purged {$removed} legacy per-user CP login rules");
        filter_configure();
        cp_wireless_log("Purged {$removed} legacy per-user CP login rules (auto generated)");
    }

    return $removed;
}

/**
 * 로그아웃/테이블 정리 처리.
 * - 모든 게이트웨이 pfctl table 에서 IP 제거 (어느 테이블에 있든 무조건 정리)
 * - $kill_states=true(기본) 이면 pf state 도 함께 삭제 (로그아웃 시 즉시 차단)
 * - $kill_states=false 이면 테이블 제거만 수행 (로그인 시 포털 TCP 연결 보존)
 * - config.xml 수정 없음, filter_configure() 없음
 *
 * ⚠️ 로그인 경로(add_crew_linked_rule)에서 kill_states=true 로 호출하면
 *    pfctl -k 가 클라이언트→포털 TCP 연결을 RST 시킨다.
 *    이 RST 는 PHP 가 302 응답을 보내기 전에 도달하므로 클라이언트가
 *    응답을 수신하지 못하고 iOS CNA 등이 ~19초 retry interval 후 재시도한다.
 *    로그인 경로에서는 반드시 kill_states=false 로 호출할 것.
 */
function del_crew_linked_rule($sourceip, $userid, bool $kill_states = true) {
    if (!filter_var($sourceip, FILTER_VALIDATE_IP)) {
        return false;
    }

    $safeIp  = escapeshellarg($sourceip);
    $all_gws = cp_find_all_wan_gateways();

    foreach ($all_gws as $gw) {
        $safeTable = escapeshellarg(cp_gw_table_name($gw['name']));
        mwexec("/sbin/pfctl -t {$safeTable} -T delete {$safeIp} 2>/dev/null");
    }

    // 기본 게이트웨이 테이블에서도 제거
    mwexec("/sbin/pfctl -t cp_gw_default -T delete {$safeIp} 2>/dev/null");

    // 로그아웃 시에만 pf state 정리 → 기존 라우팅 세션 즉시 종료
    if ($kill_states) {
        cp_kill_states_for_ip($sourceip);
    }

    return true;
}
function cp_is_valid_ip($ip) {
    return (bool)filter_var($ip, FILTER_VALIDATE_IP);
}

function cp_kill_states_for_ip($ip) {
    if (!cp_is_valid_ip($ip)) {
        return false;
    }

    $safeIp = escapeshellarg($ip);
    // IPv4 states
    mwexec("/sbin/pfctl -k {$safeIp} -k 0.0.0.0/0");
    // IPv6 states
    mwexec("/sbin/pfctl -k {$safeIp} -k ::/0");
    return true;
}

/**
 * deferred 큐에 쌓인 IP 의 pf state 를 정리한다 (web/CLI 공용 shutdown 처리).
 *
 * 주의: pfSense spawn-fcgi 에는 fastcgi_finish_request() 가 없어서 shutdown 함수가
 *       HTTP 응답/연결 종료 "전"에 실행된다. 여기서 즉시 pfctl -k 로 클라이언트 IP 의
 *       state 를 kill 하면 포털 자신의 TCP 연결이 RST 되어 응답 전달 전에 끊긴다
 *       (지연 큰 선박 WiFi 에서 일관되게 ~19초 지연 발생).
 *       → 짧게 대기 후 kill 하는 detached 백그라운드 프로세스로 분리한다.
 *       (로그아웃 즉시 ipfw auth 테이블/DB 에서는 이미 제거되어 신규 트래픽은 차단됨;
 *        state kill 은 기존 연결 종료용이라 2초 지연은 기능상 무해.)
 */
function cp_flush_deferred_state_kills(): void {
    if (empty($GLOBALS['_cp_deferred_state_kills'])) {
        return;
    }
    $ips = array_values(array_unique($GLOBALS['_cp_deferred_state_kills']));
    $GLOBALS['_cp_deferred_state_kills'] = [];

    $cmds = array();
    foreach ($ips as $ip) {
        if (!is_ipaddr($ip)) {
            continue;
        }
        $s = escapeshellarg($ip);
        $cmds[] = "/sbin/pfctl -k {$s} -k 0.0.0.0/0";
        $cmds[] = "/sbin/pfctl -k {$s} -k ::/0";
    }
    if (empty($cmds)) {
        return;
    }
    mwexec_bg("/bin/sleep 2; " . implode("; ", $cmds));
}


/**
 * 로그인 처리.
 * - pfctl table 에 IP 추가
 * - config.xml 수정 없음, filter_configure() 없음
 */
function add_crew_linked_rule($sourceip, $userid) {
    if (!filter_var($sourceip, FILTER_VALIDATE_IP)) {
        return false;
    }

    // 이전 세션 잔재 제거 — kill_states=false: 포털 TCP 연결은 유지
    // (kill_states=true 이면 pfctl -k 가 현재 포털 연결을 RST → HTTP 응답 전달 불가 → ~19초 지연)
    del_crew_linked_rule($sourceip, $userid, false);

    $gwname = cp_get_terminaltype_for_user($userid);
    $table  = cp_get_pf_table_for_gateway($gwname);

    if ($table === null) {
        // terminaltype 미설정 → 기본 게이트웨이 테이블에 등록 (route-to 없이 pass)
        $table = 'cp_gw_default';
    }

    $safeIp    = escapeshellarg($sourceip);
    $safeTable = escapeshellarg($table);
    mwexec("/sbin/pfctl -t {$safeTable} -T add {$safeIp}");

    return true;
}

function renderLoginPortalHtml(array $data = []): string
{
    $logo    = $data['logo'] ?? '';
    $portalError    = $data['portalError'] ?? '';
    $portalInfo     = $data['portalInfo'] ?? '';
    $loginUser = $data['loginUserValue'] ?? '';
    $uiConfig       = $data['uiConfig'] ?? [];
    $subject        = $data['subject'] ?? 'Welcome!';
    $quotaNotice    = $data['quotaNotice'] ?? '';
    $showQuotaResult= $data['showQuotaResult'] ?? false;
    $quotaResultMsg = $data['quotaResultMsg'] ?? '';
    $quotaRemain      = $data['quotaUserValue'] ?? '';
    $quotaUsed      = $data['quotaUsed'] ?? '';
    $quotaAllocated    = $data['quotaAllocated'] ?? '';

    if (!function_exists('h')) {
        function h($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
    }

    ob_start();
    ?>
    <div class="top-logo-area">
        <div class="top-logo-wrap">
            <img id="main_logo" src="<?=h($logo)?>" alt="Portal logo">
        </div>
    </div>
    <div class="portal">
        <section class="panel">
            <div class="card">
                <div class="card-head">
                    <div>
                        <h1 class="page-title"><?=h($subject)?></h1>
                        <p class="page-subtitle">
                            Secure network access with login, status information, and quota lookup in one place.
                        </p>
                    </div>
                </div>

                <?php if ($portalError !== ''): ?>
                    <div class="notice notice-error"><?= h($portalError) ?></div>
                <?php endif; ?>

                <?php if ($portalInfo !== ''): ?>
                    <div class="notice notice-info"><?= h($portalInfo) ?></div>
                <?php endif; ?>

                <div class="notice notice-warning">
                    If you are repeatedly asked to log in, please turn off
                    <b>&ldquo;Private / Random Wi-Fi Address&rdquo;</b> for this network on your device.
                </div>

                <form name="login_form" method="post" action="/index.php" autocomplete="on">
                    <div class="field">
                        <label for="login_user">Username</label>
                        <div class="input-wrap">
                            <input
                                    class="input"
                                    type="text"
                                    name="auth_user"
                                    id="login_user"
                                    placeholder="Enter your username"
                                    autocomplete="username"
                                    autocapitalize="none"
                                    spellcheck="false"
                                    required
                            />
                        </div>
                    </div>

                    <div class="field">
                        <label for="login_pass">Password</label>
                        <div class="input-wrap">
                            <input
                                    class="input with-action"
                                    type="password"
                                    name="auth_pass"
                                    id="login_pass"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                            />
                            <button type="button" class="input-action" id="togglePassword">Show</button>
                        </div>
                    </div>

                    <?php if (!empty($uiConfig['termsEnabled'])): ?>
                        <div class="terms-line" id="termsWrap">
                            <input
                                    type="checkbox"
                                    id="agree_terms"
                                    name="agree_terms"
                                    value="yes"
                                <?= !empty($uiConfig['termsRequired']) ? 'required' : '' ?>
                                    checked
                            />
                            <span>
                        I agree with the
                        <a target="_blank" rel="noopener" href="http://192.168.208.1:8002/example.pdf">terms &amp; licences</a>.
                    </span>
                        </div>
                    <?php endif; ?>
                    <div class="actions">
                        <input
                                type="submit"
                                name="accept"
                                class="btn btn-primary"
                                value="Login"
                                id="loginBtn"
                        >
                    </div>
                    <input name="login" type="hidden" value="true">
                    <div class="helper">
                        Login errors, session notices, and other messages are displayed in the alert area above.
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">Check quota</h2>
                        <p class="card-subtitle">Check Usage for your reference.</p>
                    </div>
                </div>

                <?php if ($quotaNotice !== ''): ?>
                    <div class="notice notice-warning"><?= h($quotaNotice) ?></div>
                <?php endif; ?>

                <form name="quota_form" method="post" action="/index.php" autocomplete="on">
                    <div class="field">
                        <label for="quota_user">Username</label>
                        <div class="input-wrap">
                            <input
                                    class="input"
                                    type="text"
                                    name="quota_user"
                                    id="quota_user"
                                    placeholder="Enter username for quota check"
                                    autocomplete="username"
                                    autocapitalize="none"
                                    spellcheck="false"
                            />
                        </div>
                    </div>

                    <input name="check_quota" type="hidden" value="true">

                    <div class="actions">
                        <input
                                type="submit"
                                name="quota"
                                class="btn btn-secondary"
                                value="Check data usage"
                                id="check_quota"
                            <?= $loginUser === '' ? 'disabled' : '' ?>
                        >
                    </div>

                    <div class="helper">
                        The quota lookup button is enabled after a username is entered.
                    </div>
                </form>

                <?php if ($showQuotaResult): ?>
                    <div id="quotaResultBox" class="quota-result">
                        <div class="notice notice-success" style="margin:0 0 12px 0;">
                            <?= h($quotaResultMsg !== '' ? $quotaResultMsg : 'Quota result loaded.') ?>
                        </div>

                        <div class="quota-grid">
                            <div class="quota-box">
                                <span class="label">Total</span>
                                <span class="value"><?= h($quotaAllocated !== '' ? $quotaAllocated : '-') ?></span>
                            </div>
                            <div class="quota-box">
                                <span class="label">Used</span>
                                <span class="value"><?= h($quotaUsed !== '' ? $quotaUsed : '-') ?></span>
                            </div>
                            <div class="quota-box">
                                <span class="label">Remain</span>
                                <span class="value"><?= h($quotaRemain !== '' ? $quotaRemain : '-') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php

    return ob_get_clean();
}

function renderChangepwPortalHtml(array $data = []): string
{
    $logo          = $data['logo'] ?? '';
    $portalError   = $data['portalError'] ?? '';
    $portalInfo    = $data['portalInfo'] ?? '';
    $loginUser     = $data['loginUserValue'] ?? '';   // portal_reply_page 전달 키와 통일
    $loginPassword = $data['loginPassword'] ?? '';

    if (!function_exists('h')) {
        function h($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
    }

    ob_start();
    ?>
    <div class="top-logo-area">
        <div class="top-logo-wrap">
            <img id="main_logo" src="<?= h($logo) ?>" alt="Portal logo">
        </div>
    </div>

    <div class="portal">
        <section class="panel">
            <div class="card">
                <div class="card-head">
                    <div>
                        <h1 class="page-title">Change Password!</h1>
                        <p class="page-subtitle">
                            You can change your own password once you enter here.
                        </p>
                    </div>
                </div>

                <?php if ($portalError !== ''): ?>
                    <div class="notice notice-error"><?= h($portalError) ?></div>
                <?php endif; ?>

                <?php if ($portalInfo !== ''): ?>
                    <div class="notice notice-info"><?= h($portalInfo) ?></div>
                <?php endif; ?>

                <form name="commit_change_pwform" method="post" action="/index.php" autocomplete="on" id="commit_change_pwform">
                    <div class="field">
                        <label for="new_password">New Password</label>
                        <div class="input-wrap">
                            <input
                                    class="input with-action"
                                    type="password"
                                    name="new_password"
                                    id="new_password"
                                    placeholder="Enter your new password"
                                    required
                            />
                            <button type="button" class="input-action" id="toggleNewPassword">Show</button>
                        </div>
                    </div>

                    <div class="field">
                        <label for="retype_password">Re-Type Password</label>
                        <div class="input-wrap">
                            <input
                                    class="input with-action"
                                    type="password"
                                    name="retype_password"
                                    id="retype_password"
                                    placeholder="Re-type your new password"
                                    required
                            />
                            <button type="button" class="input-action" id="toggleRetypePassword">Show</button>
                        </div>
                    </div>

                    <div class="actions">
                        <input
                                type="submit"
                                name="commit_change_pw_submit"
                                class="btn btn-primary"
                                id="commit_change_pw"
                                value="Change Password"
                        >
                    </div>

                    <input name="commit_change_pw" type="hidden" value="true">
                    <input name="auth_user" type="hidden" value="<?= h($loginUser) ?>">
                    <input name="login_password" type="hidden" value="<?= h($loginPassword) ?>">

                    <div class="helper">
                        Login errors, session notices, and other messages are displayed in the alert area above.
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        (function () {
            var form = document.getElementById('commit_change_pwform');
            var newPassword = document.getElementById('new_password');
            var retypePassword = document.getElementById('retype_password');
            var toggleNewPassword = document.getElementById('toggleNewPassword');
            var toggleRetypePassword = document.getElementById('toggleRetypePassword');

            function togglePassword(input, button) {
                if (!input || !button) {
                    return;
                }

                if (input.type === 'password') {
                    input.type = 'text';
                    button.textContent = 'Hide';
                } else {
                    input.type = 'password';
                    button.textContent = 'Show';
                }
            }

            function validatePasswordMatch() {
                if (!newPassword || !retypePassword) {
                    return true;
                }

                if (newPassword.value !== retypePassword.value) {
                    retypePassword.setCustomValidity('Passwords do not match.');
                    return false;
                }

                retypePassword.setCustomValidity('');
                return true;
            }

            if (toggleNewPassword) {
                toggleNewPassword.addEventListener('click', function () {
                    togglePassword(newPassword, toggleNewPassword);
                });
            }

            if (toggleRetypePassword) {
                toggleRetypePassword.addEventListener('click', function () {
                    togglePassword(retypePassword, toggleRetypePassword);
                });
            }

            if (newPassword) {
                newPassword.addEventListener('input', validatePasswordMatch);
            }

            if (retypePassword) {
                retypePassword.addEventListener('input', validatePasswordMatch);
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (!validatePasswordMatch()) {
                        e.preventDefault();
                        retypePassword.reportValidity();
                        retypePassword.focus();
                    }
                });
            }
        })();
    </script>
    <?php

    return ob_get_clean();
}

function renderLogoutPortalHtml(array $data = []): string
{
    $logo   = $data['logo'] ?? '';
    $curUsage   = $data['quotaUsed'] ?? '';
    $maxUsage   = $data['quotaAllocated'] ?? '';
    $portalError    = $data['portalError'] ?? '';
    $displayname    = $data['displayname'] ?? '';
    $portalInfo     = $data['portalInfo'] ?? '';
    $loginUserValue = $data['loginUserValue'] ?? '';
    $loginUsers = $data['loginUsers'] ?? '';
    $selectedSpeedProfile = $data['selectedSpeedProfile'] ?? 'hd';

    /*$speedOptions = [
        'pause' => [
            'label' => 'Pause',
            'help'  => 'Pause data usage. ** Note: Still DATA consumes very slowly.'
        ],
        'sd' => [
            'label' => 'SD',
            'help'  => 'Capable for SD quality video, minimize data usage - 512/1024Kbps'
        ],
        'fhd' => [
            'label' => 'HD-FHD',
            'help'  => 'Capable for HD quality video, moderate data usage - 1024/2048Kbps'
        ],
        'unlimited' => [
            'label' => 'Unlimited',
            'help'  => 'No limitation for data usage, until Satellite limit.'
        ],
    ];*/

    if (!function_exists('h')) {
        function h($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
    }

    ob_start();
    ?>
    <div class="top-logo-area">
        <div class="top-logo-wrap">
            <img id="main_logo" src="<?=h($logo)?>" alt="Portal logo">
        </div>
    </div>
    <div class="portal">
    <section class="panel">
    <div class="card">
        <div class="card-head">
            <div>
                <h1 class="page-title"><?php echo ($displayname);?> is online!</h1>
                <p class="page-subtitle"></p>
            </div>
        </div>

        <?php if ($portalError !== ''): ?>
            <div class="notice notice-error"><?= h($portalError) ?></div>
        <?php endif; ?>

        <?php if ($portalInfo !== ''): ?>
            <div class="notice notice-info"><?= h($portalInfo) ?></div>
        <?php endif; ?>

        <form name="login_form" method="post" action="/" autocomplete="on">

            <div class="field">
                <label for="connected_user">Online Details</label>
                <div class="input-wrap">

                    <!--DATA STATUS-->
                    <!--div class="quota-box" style="margin:0 0 12px 0;">
                        <span class="label">Used</span>
                        <span class="value"><?= h($curUsage !== '' ? $curUsage : '0') ?> MB</span>
                    </div>
                    <div class="quota-box" style="margin:0 0 12px 0;">
                        <span class="label">Limit</span>
                        <span class="value"><?= h($maxUsage !== '' ? $maxUsage : '0') ?> MB</span>
                    </div-->
                    <div style="display:flex; gap:12px; margin:0 0 12px 0;">
                        <div class="quota-box" style="flex:1; margin:0;">
                            <span class="label">Used</span>
                            <span class="value"><?= h($curUsage !== '' ? $curUsage : '0') ?> MB</span>
                        </div>

                        <div class="quota-box" style="flex:1; margin:0;">
                            <span class="label">Limit</span>
                            <span class="value"><?= h($maxUsage !== '' ? $maxUsage : '0') ?> MB</span>
                        </div>
                        <div class="quota-box" style="flex:1; margin:0;">
                            <span class="label">Ratio</span>
                            <span class="value">
                            <?= h(($maxUsage === null || (float)$maxUsage == 0.0)
                                ? 'N/A'
                                : number_format(((float)$curUsage / (float)$maxUsage) * 100, 0) . '%') ?>
                        </span>
                        </div>
                    </div>
                    <!-- CONNECTION INFO -->
                    <div class="quota-box" style="margin:0 0 12px 0;">
                        <span class="label">Connection Info</span>
                        <span class="value"><?= h($loginUsers) ?> users currently logged in.</span>
                    </div>
                    <!--Kbps 그래프 영역 시작 -->
                    <div class="quota-box" style="margin:0 0 12px 0; display:block;">

                        <!-- 접기/펼치기 헤더 -->
                        <div id="kbpsToggleHeader" style="display:flex; align-items:center; justify-content:space-between; cursor:pointer; user-select:none; padding:4px 0;">
                            <span class="label" style="display:block; margin:0;">Usage history</span>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span id="kbpsToggleIcon" style="font-size:13px; color:#6b7785; transition:transform 0.2s;">▼</span>
                            </div>
                        </div>

                        <!-- 펼쳐지는 본문 (기본 숨김) -->
                        <div id="kbpsGraphBody" style="display:none; margin-top:8px;">

                            <!-- 범위 버튼 -->
                            <div style="display:flex; gap:6px; align-items:center; margin-bottom:10px; justify-content:flex-end;">
                                <button type="button" id="range24h"   class="mini-btn range-btn active-range" data-hours="24">24h</button>
                                <button type="button" id="range7d"    class="mini-btn range-btn"              data-hours="168">7d</button>
                                <button type="button" id="rangeMonth" class="mini-btn range-btn"              data-mode="month">Month</button>
                                <button type="button" id="refreshBtn" class="mini-btn" title="Refresh">&#x21bb;</button>
                            </div>

                            <div id="kbpsGraphError" style="margin-bottom:8px; color:#c62828; font-size:12px;"></div>
                            <div id="kbpsLoading"   style="font-size:12px; color:#555; margin-bottom:6px; display:none;">Loading…</div>

                            <div style="width:100%; background:#fff; border:1px solid #d9e1ec; border-radius:12px; padding:12px; box-sizing:border-box;">
                                <canvas id="kbpsChart" height="260" style="width:100%; display:block;"></canvas>
                            </div>
                        </div>
                    </div>
                    <!--Kbps 그래프 영역 끝-->
                    <!-- SPEED PROFILE -->
                    <!--div class="quota-box" style="margin:0 0 12px 0;">
                        <span class="label">Speed Option</span>

                        <div class="speed-slider-wrap">
                            <?php foreach (($speedOptions ?? []) as $key => $opt): ?>
                                <label class="speed-option">
                                    <input
                                            type="radio"
                                            name="speed_profile"
                                            value="<?= h($key) ?>"
                                        <?= $selectedSpeedProfile === $key ? 'checked' : '' ?>
                                    >
                                    <span class="speed-pill">
                                <?= h($opt['label']) ?>
                                <span class="tooltip-wrap">
                                    <span class="tooltip-icon">?</span>
                                    <span class="tooltip-bubble"><?= h($opt['help']) ?></span>
                                </span>
                            </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div-->
                    <input type="hidden" name="update_speed_profile" id="update_speed_profile" value="0">
                    <input type="hidden" name="login_user" value="<?= h($loginUserValue) ?>">
                </div>
            </div>

            <div class="actions">
                <input
                        type="submit"
                        name="logout_form"
                        class="btn btn-primary"
                        value="Logout"
                        id="logoutBtn"
                >
            </div>

            <input name="logout_id" type="hidden" value="<?= h($loginUserValue) ?>">

            <div class="helper">
                If you want to change your own password, please click below.
            </div>
        </form>
        <form name="change_pw" method="post" action="/" autocomplete="on">

            <div class="actions">
                <input
                        type="submit"
                        name="change_pw"
                        class="btn btn-primary"
                        value="Change Password"
                        id="change_pw"
                >
            </div>
            <input name="change_pw" type="hidden" value="true">
            <input name="auth_user" type="hidden" value="<?=h($loginUserValue)?>">

    </div>
    </section>
    </div>
    <script>
        (function () {
            var INFLUX_URL  = 'http://192.168.209.210:8086/query';
            var INFLUX_DB   = 'wifiusage';
            var LOGIN_USER  = '<?= addslashes(h($loginUserValue)) ?>';

            var canvas    = document.getElementById('kbpsChart');
            var errorBox  = document.getElementById('kbpsGraphError');
            var loadingEl = document.getElementById('kbpsLoading');
            var header    = document.getElementById('kbpsToggleHeader');
            var body      = document.getElementById('kbpsGraphBody');
            var toggleIcon = document.getElementById('kbpsToggleIcon');

            /* ── 접기/펼치기 ── */
            var isOpen = false;
            if (header) {
                header.addEventListener('click', function () {
                    isOpen = !isOpen;
                    body.style.display = isOpen ? 'block' : 'none';
                    toggleIcon.style.transform = isOpen ? 'rotate(180deg)' : 'rotate(0deg)';
                    if (isOpen && !chartDrawn) {
                        fetchAndDraw();
                    }
                });
            }

            if (!canvas) { return; }

            var rangeMode  = 'hours';  /* 'hours' | 'month' */
            var rangeHours = 24;
            var chartDrawn = false;

            /* ── 시간 범위 버튼 ── */
            document.querySelectorAll('.range-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation(); /* 헤더 클릭 이벤트 버블링 방지 */
                    document.querySelectorAll('.range-btn').forEach(function (b) {
                        b.classList.remove('active-range');
                    });
                    this.classList.add('active-range');

                    var mode = this.getAttribute('data-mode');
                    if (mode === 'month') {
                        rangeMode = 'month';
                    } else {
                        rangeMode  = 'hours';
                        rangeHours = parseInt(this.getAttribute('data-hours'), 10);
                    }
                    fetchAndDraw();
                });
            });

            var refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    fetchAndDraw();
                });
            }

            /* ── InfluxDB 쿼리 생성 ── */
            function buildQuery() {
                var whereUser = LOGIN_USER
                    ? ' AND "user" = \'' + LOGIN_USER + '\''
                    : '';

                var timeFilter, interval;

                if (rangeMode === 'month') {
                    /* 이번달 1일 00:00:00 UTC 기준 */
                    var now = new Date();
                    var firstDay = new Date(Date.UTC(now.getFullYear(), now.getMonth(), 1));
                    var iso = firstDay.toISOString(); /* e.g. 2026-05-01T00:00:00.000Z */
                    timeFilter = ' time >= \'' + iso + '\'';
                    interval   = '1h';
                } else {
                    timeFilter = ' time >= now() - ' + rangeHours + 'h';
                    interval   = rangeHours <= 24 ? '5m' : '1h';
                }

                return 'SELECT non_negative_derivative(mean("total_bytes"), 1s) / 125 AS "kbps"'
                    + ' FROM "datacounter_interim_delta"'
                    + ' WHERE' + timeFilter
                    + whereUser
                    + ' GROUP BY time(' + interval + ') fill(0)';
            }

            /* ── fetch & 차트 그리기 ── */
            function fetchAndDraw() {
                if (!errorBox || !loadingEl) { return; }
                errorBox.textContent = '';
                loadingEl.style.display = 'block';

                var url = INFLUX_URL
                    + '?db=' + encodeURIComponent(INFLUX_DB)
                    + '&q='  + encodeURIComponent(buildQuery());

                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.timeout = 15000;

                xhr.onload = function () {
                    loadingEl.style.display = 'none';
                    if (xhr.status !== 200) {
                        errorBox.textContent = 'HTTP error: ' + xhr.status;
                        return;
                    }
                    try {
                        var json   = JSON.parse(xhr.responseText);
                        var points = parseInfluxResult(json);
                        drawChart(points);
                        chartDrawn = true;
                    } catch (e) {
                        errorBox.textContent = 'Parse error: ' + e.message;
                    }
                };

                xhr.onerror = xhr.ontimeout = function () {
                    loadingEl.style.display = 'none';
                    errorBox.textContent = 'Network error: InfluxDB에 연결할 수 없습니다.';
                };

                xhr.send();
            }

            /* ── InfluxDB 응답 파싱 ── */
            function parseInfluxResult(json) {
                var points = [];
                try {
                    var series  = json.results[0].series[0];
                    var timeIdx = series.columns.indexOf('time');
                    var kbpsIdx = series.columns.indexOf('kbps');

                    series.values.forEach(function (row) {
                        var ts   = new Date(row[timeIdx]);
                        var kbps = row[kbpsIdx] === null ? 0 : +row[kbpsIdx];

                        var label;
                        if (rangeMode === 'month') {
                            /* 월간: 날짜 + 시 */
                            label = (ts.getMonth() + 1) + '/' + ts.getDate()
                                + ' ' + ts.getHours().toString().padStart(2, '0') + 'h';
                        } else if (rangeHours <= 24) {
                            label = ts.getHours().toString().padStart(2, '0')
                                + ':' + ts.getMinutes().toString().padStart(2, '0');
                        } else {
                            label = (ts.getMonth() + 1) + '/' + ts.getDate()
                                + ' ' + ts.getHours().toString().padStart(2, '0') + 'h';
                        }
                        points.push({ label: label, value: kbps });
                    });
                } catch (e) {
                    /* series 없음 = 데이터 없음 → 빈 배열 반환 */
                }
                return points;
            }

            /* ── Canvas 차트 렌더링 ── */
            function drawChart(points) {
                var ctx      = canvas.getContext('2d');
                var ratio    = window.devicePixelRatio || 1;
                var rect     = canvas.getBoundingClientRect();
                var cssWidth = Math.max(300, rect.width || canvas.parentNode.clientWidth || 600);
                var cssHeight = 260;

                canvas.width  = cssWidth  * ratio;
                canvas.height = cssHeight * ratio;
                canvas.style.height = cssHeight + 'px';

                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.clearRect(0, 0, cssWidth, cssHeight);

                var pad = { top: 20, right: 20, bottom: 40, left: 60 };
                var cw  = cssWidth  - pad.left - pad.right;
                var ch  = cssHeight - pad.top  - pad.bottom;

                if (cw <= 0 || ch <= 0) { return; }

                var maxVal = 0;
                points.forEach(function (p) { if (p.value > maxVal) maxVal = p.value; });
                if (maxVal <= 0) maxVal = 10;
                var roundedMax = Math.ceil(maxVal / 50) * 50;
                if (roundedMax < 100) roundedMax = 100;

                /* 배경 */
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, cssWidth, cssHeight);

                /* 격자 + Y 라벨 */
                var gridCount = 5;
                ctx.font = '12px Arial';
                for (var g = 0; g <= gridCount; g++) {
                    var gy = pad.top + (ch / gridCount) * g;
                    ctx.strokeStyle = '#e7edf5'; ctx.lineWidth = 1;
                    ctx.beginPath(); ctx.moveTo(pad.left, gy); ctx.lineTo(pad.left + cw, gy); ctx.stroke();
                    ctx.fillStyle = '#6b7785'; ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
                    ctx.fillText(
                        Math.round(roundedMax - (roundedMax / gridCount) * g) + ' K',
                        pad.left - 6, gy
                    );
                }

                /* 축 */
                ctx.strokeStyle = '#aebdce'; ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.moveTo(pad.left, pad.top);
                ctx.lineTo(pad.left, pad.top + ch);
                ctx.lineTo(pad.left + cw, pad.top + ch);
                ctx.stroke();

                if (points.length === 0) {
                    ctx.fillStyle = '#8a94a6'; ctx.textAlign = 'center';
                    ctx.fillText('No data', pad.left + cw / 2, pad.top + ch / 2);
                    return;
                }

                var stepX = (points.length === 1) ? 0 : cw / (points.length - 1);
                function getX(i) { return pad.left + stepX * i; }
                function getY(v) { return pad.top  + ch  - (v / roundedMax) * ch; }

                /* 영역 채우기 */
                ctx.beginPath();
                points.forEach(function (p, i) {
                    if (i === 0) ctx.moveTo(getX(i), getY(p.value));
                    else         ctx.lineTo(getX(i), getY(p.value));
                });
                ctx.lineTo(getX(points.length - 1), pad.top + ch);
                ctx.lineTo(getX(0), pad.top + ch);
                ctx.closePath();
                ctx.fillStyle = 'rgba(52,152,219,0.18)'; ctx.fill();

                /* 라인 */
                ctx.beginPath();
                points.forEach(function (p, i) {
                    if (i === 0) ctx.moveTo(getX(i), getY(p.value));
                    else         ctx.lineTo(getX(i), getY(p.value));
                });
                ctx.strokeStyle = '#3498db'; ctx.lineWidth = 2; ctx.stroke();

                /* 포인트 */
                points.forEach(function (p, i) {
                    ctx.beginPath();
                    ctx.arc(getX(i), getY(p.value), 3.5, 0, Math.PI * 2);
                    ctx.fillStyle   = '#3498db'; ctx.fill();
                    ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 1.5; ctx.stroke();
                });

                /* X 라벨 */
                ctx.fillStyle = '#6b7785'; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
                var skip = Math.max(1, Math.ceil(points.length / 12));
                points.forEach(function (p, i) {
                    if (i % skip !== 0 && i !== points.length - 1) { return; }
                    ctx.fillText(p.label, getX(i), pad.top + ch + 8);
                });
            }

            /* 리사이즈 시 열려 있을 때만 재렌더 */
            window.addEventListener('resize', function () {
                if (isOpen) { fetchAndDraw(); }
            });

        })();

        (function () {
            var form = document.forms['login_form'];
            if (!form) {
            return;
        }

            var radios = form.querySelectorAll('input[name="speed_profile"]');
            var hiddenFlag = document.getElementById('update_speed_profile');

            if (!radios.length || !hiddenFlag) {
            return;
        }

            var previousValue = '';
            var checkedRadio = form.querySelector('input[name="speed_profile"]:checked');
            if (checkedRadio) {
            previousValue = checkedRadio.value;
        }

            function setRadioValue(value) {
            for (var i = 0; i < radios.length; i++) {
            radios[i].checked = (radios[i].value === value);
        }
        }

            for (var i = 0; i < radios.length; i++) {
            radios[i].addEventListener('change', function () {
            var newValue = this.value;

            if (newValue === previousValue) {
            return;
        }

            var ok = window.confirm('Are you sure you want to change the speed limit?');

            if (!ok) {
            setRadioValue(previousValue);
            return;
        }

            hiddenFlag.value = '1';
            form.submit();
        });
        }
        })();
    </script>
    <?php
    return ob_get_clean();
}




?>