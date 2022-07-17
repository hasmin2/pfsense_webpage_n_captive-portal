<?php
/*
 * index.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Originally part of m0n0wall (http://m0n0.ch/wall)
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

require_once("auth.inc");
require_once("util.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

header("Expires: 0");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Connection: close");

global $cpzone, $cpzoneid;

$cpzone = strtolower($_REQUEST['zone']);
if(empty($cpzone)){	$cpzone = "crew"; }
$cpcfg = $config['captiveportal'][$cpzone];

/* NOTE: IE 8/9 is buggy and that is why this is needed */
$orig_request = trim($_REQUEST['redirurl'], " /");
$protocol = (isset($config['captiveportal'][$cpzone]['httpslogin'])) ? 'https://' : 'http://';


$cpzoneid = $cpcfg['zoneid'];
$clientip = $_SERVER['REMOTE_ADDR'];


if (empty($cpcfg)) {
	portal_reply_page($loginurl, "redir");
	ob_flush();
	return;
}

if (!$clientip) {
	/* not good - bail out */
	log_error("Zone: {$cpzone} - Captive portal could not determine client's IP address.");
	$errormsg = gettext("An error occurred.  Please check the system logs for more information.");
	portal_reply_page($redirurl, "error", $errormsg);
	ob_flush();
	return;
}

$cpsession = captiveportal_isip_logged($clientip);
$ourhostname = portal_hostname_from_client_ip($clientip);
$macfilter = !isset($cpcfg['nomacfilter']);
$redirurl="{$protocol}{$ourhostname}";
$loginurl="{$protocol}{$ourhostname}/index.php?zone=crew";

/* find MAC address for client */
if ($macfilter || isset($cpcfg['passthrumacadd'])) {
	$tmpres = pfSense_ip_to_mac($clientip);
	if (!is_array($tmpres)) {
		/* unable to find MAC address - shouldn't happen! - bail out */
		captiveportal_logportalauth("unauthenticated", "noclientmac", $clientip, "ERROR");
		echo "An error occurred.  Please check the system logs for more information.";
		log_error("Zone: {$cpzone} - Captive portal could not determine client's MAC address.  Disable MAC address filtering in captive portal if you do not need this functionality.");
		ob_flush();
		return;
	}
	$clientmac = $tmpres['macaddr'];
	unset($tmpres);
}
if ($_POST['logout_id']) {//When User click logout button from './logout.php'
	$safe_logout_id = SQLite3::escapeString($_POST['logout_id']);
	captiveportal_disconnect_client($safe_logout_id);
	portal_reply_page($redirurl, "login","Logged out!!!", $clientmac, $clientip);
} elseif ($_POST['new_password']){
	//$safe_logout_id = SQLite3::escapeString($_POST['logout_id']);
	portal_reply_page($redirurl, "commit_change_pw","Changed PW", $clientmac, $clientip, null, $_POST['new_password']);

} elseif ($_POST['check_quota']){
	portal_reply_page($loginurl, "check_quota", "Quota details", null, null, $_POST['auth_user']);
} elseif ($_POST['change_pw']){
    portal_reply_page($loginurl, "change_pw", "Reset PW", $clientmac, $clientip);
} elseif (($_POST['accept'] || $cpcfg['auth_method'] === 'radmac' || !empty($cpcfg['blockedmacsurl'])) && $macfilter && $clientmac &&  captiveportal_blocked_mac($clientmac)) {
	captiveportal_logportalauth($clientmac, $clientmac, $clientip, "Blocked MAC address");
	if (!empty($cpcfg['blockedmacsurl'])) {
		portal_reply_page($cpcfg['blockedmacsurl'], "redir");
	} else {
		if ($cpcfg['auth_method'] === 'radmac') {
			echo gettext("This MAC address has been blocked");
		} else {
			portal_reply_page($redirurl, "error", "This MAC address has been blocked");
		}
	}
} elseif (portal_consume_passthrough_credit($clientmac)) {
	// allow the client through if it had a pass-through credit for its MAC 
	captiveportal_logportalauth("unauthenticated", $clientmac, $clientip, "ACCEPT");
	portal_allow($clientip, $clientmac, "unauthenticated", null, $redirurl);
} elseif (isset($config['voucher'][$cpzone]['enable']) && ($_POST['accept'] && $_POST['auth_voucher']) || $_GET['voucher']) {
	if (isset($_POST['auth_voucher'])) {
		$voucher = trim($_POST['auth_voucher']);
	} else {
		//submit voucher via URL, see https://redmine.pfsense.org/issues/1984 
		$voucher = trim($_GET['voucher']);
		portal_reply_page($redirurl, "login", null, $clientmac, $clientip, null, null, $voucher);
		return;
	}
	$errormsg = gettext("Invalid credentials specified.");
	$timecredit = voucher_auth($voucher);
	// $timecredit contains either a credit in minutes or an error message
	if ($timecredit > 0) {  // voucher is valid. Remaining minutes returned
		// if multiple vouchers given, use the first as username
		$a_vouchers = preg_split("/[\t\n\r ]+/s", $voucher);
		$voucher = $a_vouchers[0];
		$attr = array(
			'voucher' => 1,
			'session_timeout' => $timecredit*60,
			'session_terminate_time' => 0);
		if (portal_allow($clientip, $clientmac, $voucher, null, $redirurl, $attr, null, 'voucher', 'voucher') === 2) {
			portal_reply_page($redirurl, "error", "Reuse of identification not allowed.");
		} elseif (portal_allow($clientip, $clientmac, $voucher, null, $redirurl, $attr, null, 'voucher', 'voucher')) {
			// YES: user is good for $timecredit minutes.
			captiveportal_logportalauth($voucher, $clientmac, $clientip, "Voucher login good for $timecredit min.");
		} else {
			portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['descrmsgexpired'] ? $config['voucher'][$cpzone]['descrmsgexpired']: $errormsg);
		}
	} elseif (-1 == $timecredit) {  // valid but expired
		captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE", "voucher expired");
		portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['descrmsgexpired'] ? $config['voucher'][$cpzone]['descrmsgexpired']: $errormsg);
	} else {
		captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE");
		portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['descrmsgnoaccess'] ? $config['voucher'][$cpzone]['descrmsgnoaccess'] : $errormsg);
	}
} elseif ($_POST['accept'] || $cpcfg['auth_method'] === 'radmac') {//Login Button Click
	if ($cpcfg['auth_method'] === 'radmac' && !isset($_POST['accept'])) {
		$user = $clientmac; 
		$passwd = $cpcfg['radmac_secret'];
		$context = 'radmac'; // Radius MAC authentication
	} elseif (!empty(trim($_POST['auth_user2']))) { 
		$user = trim($_POST['auth_user2']);
		$passwd = $_POST['auth_pass2'];
		$context = 'second'; // Assume users to use the first context if auth_user2 is empty/does not exist
	} else {
		$user = trim($_POST['auth_user']);
		$passwd = $_POST['auth_pass'];
		$context = 'first';
	}
	
	$pipeno = captiveportal_get_next_dn_ruleno('auth');
	// if the pool is empty, return appropriate message and exit 
	if (is_null($pipeno)) {
		$replymsg = gettext("System reached maximum login capacity");
		if ($cpcfg['auth_method'] === 'radmac') {
			ob_flush();
			return;
		} else {
			portal_reply_page($redirurl, "error", $replymsg);
		}
		log_error("Zone: {$cpzone} - WARNING!  Captive portal has reached maximum login capacity");
		
	}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	$auth_result = captiveportal_authenticate_user($user, $passwd, $clientmac, $clientip, $pipeno, $context);
	if ($auth_result['result']) {
		captiveportal_logportalauth($user, $clientmac, $clientip, $auth_result['login_status']);
		portal_allow($clientip, $clientmac, $user, $passwd, $redirurl, $auth_result['attributes'], $pipeno, $auth_result['auth_method'], $context);
        portal_reply_page($loginurl, "connected", "You are Online!", $clientmac, $clientip);
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	} else {
		captiveportal_free_dn_ruleno($pipeno);
		$type = "error";
        $replymsg = $auth_result['login_message'];
		captiveportal_logportalauth($user, $clientmac, $clientip, $auth_result['login_status'], $replymsg);
        portal_reply_page($rediurl, $type, $replymsg, $clientmac, $clientip);
	}
} else { //anything else 
	/* display captive portal page */
	$isDisconnected = already_connected($clientip, $clientmac);
	if($isDisconnected===false){//isDisconnected
		portal_reply_page($redirurl, "login", "Welcome!", $clientmac, $clientip);
	}
	else {//is still connected
		portal_reply_page($redirurl, "connected", "You are Online!", $clientmac, $clientip);
	}
}

ob_flush();
?>
