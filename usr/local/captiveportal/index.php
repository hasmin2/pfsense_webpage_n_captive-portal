<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/cp_portal_error.log');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

if (!ob_get_level()) {
	ob_start();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
require_once("auth.inc");
require_once("util.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

header("Expires: 0");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Connection: close");

/**
 * PRG(Post->Redirect->Get) flash helpers
 */
function cp_flash_set(array $data): void
{
	$_SESSION['cp_flash'] = $data;
}

function cp_flash_get(): ?array
{
	if (empty($_SESSION['cp_flash'])) {
		return null;
	}
	$d = $_SESSION['cp_flash'];
	unset($_SESSION['cp_flash']);
	return $d;
}

function cp_redirect_self(array $query = []): void {
    $path = '/index.php'; // ✅ hard-fix to avoid redirect loop on weird URIs
    $qs = $query ? ('?' . http_build_query($query)) : '';
    header('Location: ' . $path . $qs, true, 303);
    exit;
}

function cp_render_from_flash(array $flash): void {
	$ru  = $flash['redirurl'] ?? '';
	$ty  = $flash['type'] ?? 'login';
	$msg = $flash['msg'] ?? null;
	$mac = $flash['mac'] ?? null;
	$ip  = $flash['ip'] ?? null;

	// ✅ IMPORTANT: real HTTP redirect
	if ($ty === 'redir' && !empty($ru)) {
		header('Location: ' . $ru, true, 302);
		exit;
	}

	if ($ty === 'commit_change_pw') {
		portal_reply_page($ru, $ty, $msg, $mac, $ip, null, ($flash['new_password'] ?? null));
	} elseif ($ty === 'check_quota') {
		portal_reply_page($ru, $ty, $msg, null, null, ($flash['user'] ?? null));
	} else {
		portal_reply_page($ru, $ty, $msg, $mac, $ip);
	}
	ob_flush();
	exit;
}


/**
 * ---- Init zone / cfg ----
 */
global $cpzone, $cpzoneid, $config;

$cpzone = strtolower($_REQUEST['zone'] ?? '');
if (empty($cpzone)) {
	$cpzone = "crew";
}

$cpcfg = $config['captiveportal'][$cpzone] ?? null;

$protocol = (!empty($config['captiveportal'][$cpzone]['httpslogin'])) ? 'https://' : 'http://';

$clientip = $_SERVER['REMOTE_ADDR'] ?? null;

// Must have config
if (empty($cpcfg)) {
	// fallback: just try to render something safe
	$loginurl = "/";
	portal_reply_page($loginurl, "redir");
	ob_flush();
	return;
}

$cpzoneid = $cpcfg['zoneid'] ?? null;

if (empty($clientip)) {
	log_error("Zone: {$cpzone} - Captive portal could not determine client's IP address.");
	$errormsg = gettext("An error occurred.  Please check the system logs for more information.");
	$ourhostname = portal_hostname_from_client_ip("127.0.0.1");
	$redirurl = "{$protocol}{$ourhostname}";
	portal_reply_page($redirurl, "error", $errormsg);
	ob_flush();
	return;
}

$ourhostname = portal_hostname_from_client_ip($clientip);
$redirurl = "{$protocol}{$ourhostname}";
$loginurl = "{$protocol}{$ourhostname}/index.php?zone=" . urlencode($cpzone);

$macfilter = !isset($cpcfg['nomacfilter']);
$clientmac = null;

// ---- If we have a flash message (from previous POST), render it now (GET step) ----
$flash = cp_flash_get();
if ($flash) {
	cp_render_from_flash($flash);
}

/**
 * ---- Determine client MAC if needed ----
 */
if ($macfilter || isset($cpcfg['passthrumacadd'])) {
	$tmpres = pfSense_ip_to_mac($clientip);
	if (!is_array($tmpres) || empty($tmpres['macaddr'])) {
		captiveportal_logportalauth("unauthenticated", "noclientmac", $clientip, "ERROR");
		echo "An error occurred.  Please check the system logs for more information.";
		log_error("Zone: {$cpzone} - Captive portal could not determine client's MAC address.  Disable MAC address filtering in captive portal if you do not need this functionality.");
		ob_flush();
		return;
	}
	$clientmac = $tmpres['macaddr'];
	unset($tmpres);
}

/**
 * ---- POST actions (PRG: process -> flash -> redirect) ----
 */
if (!empty($_POST['logout_id'])) {
	// Logout button click from './logout.php'
	$safe_logout_id = SQLite3::escapeString($_POST['logout_id']);
	captiveportal_disconnect_client($safe_logout_id);

	cp_flash_set([
		'redirurl' => $redirurl,
		'type' => 'login',
		'msg' => 'Logged out!!!',
		'mac' => $clientmac,
		'ip' => $clientip,
	]);
	cp_redirect_self(['zone' => $cpzone]);
}

if (!empty($_POST['new_password'])) {
	cp_flash_set([
		'redirurl' => $redirurl,
		'type' => 'commit_change_pw',
		'msg' => 'Changed PW',
		'mac' => $clientmac,
		'ip' => $clientip,
		'new_password' => $_POST['new_password'],
	]);
	cp_redirect_self(['zone' => $cpzone]);
}

if (!empty($_POST['check_quota'])) {
	cp_flash_set([
		'redirurl' => $loginurl,
		'type' => 'check_quota',
		'msg' => 'Quota details',
		'user' => ($_POST['auth_user'] ?? null),
	]);
	cp_redirect_self(['zone' => $cpzone]);
}

if (!empty($_POST['change_pw'])) {
	cp_flash_set([
		'redirurl' => $loginurl,
		'type' => 'change_pw',
		'msg' => 'Reset PW',
		'mac' => $clientmac,
		'ip' => $clientip,
	]);
	cp_redirect_self(['zone' => $cpzone]);
}

/**
 * ---- Blocked MAC check ----
 */
if ((
		!empty($_POST['accept']) ||
		($cpcfg['auth_method'] ?? '') === 'radmac' ||
		!empty($cpcfg['blockedmacsurl'])
	) && $macfilter && $clientmac && captiveportal_blocked_mac($clientmac)
) {
	captiveportal_logportalauth($clientmac, $clientmac, $clientip, "Blocked MAC address");
	if (!empty($cpcfg['blockedmacsurl'])) {
		cp_flash_set([
			'redirurl' => $cpcfg['blockedmacsurl'],
			'type' => 'redir',
			'msg' => null,
		]);
	} else {
		if (($cpcfg['auth_method'] ?? '') === 'radmac') {
			// radmac path historically echoes directly
			echo gettext("This MAC address has been blocked");
			ob_flush();
			exit;
		}
		cp_flash_set([
			'redirurl' => $redirurl,
			'type' => 'error',
			'msg' => 'This MAC address has been blocked',
			'mac' => $clientmac,
			'ip' => $clientip,
		]);
	}
	cp_redirect_self(['zone' => $cpzone]);
}

/**
 * ---- Passthrough credit ----
 */
if ($clientmac && portal_consume_passthrough_credit($clientmac)) {
	captiveportal_logportalauth("unauthenticated", $clientmac, $clientip, "ACCEPT");
	portal_allow($clientip, $clientmac, "unauthenticated", null, $redirurl);

	cp_flash_set([
		'redirurl' => $redirurl,
		'type' => 'connected',
		'msg' => 'unauthenticated online',
		'mac' => $clientmac,
		'ip' => $clientip,
	]);
	cp_redirect_self(['zone' => $cpzone]);
}

/**
 * ---- Voucher handling (kept close to original but PRG for outputs) ----
 */
if (!empty($config['voucher'][$cpzone]['enable']) && ((!empty($_POST['accept']) && !empty($_POST['auth_voucher'])) || !empty($_GET['voucher']))) {
	if (!empty($_POST['auth_voucher'])) {
		$voucher = trim($_POST['auth_voucher']);
	} else {
		// submit voucher via URL
		$voucher = trim($_GET['voucher']);
		portal_reply_page($redirurl, "login", null, $clientmac, $clientip, null, null, $voucher);
		ob_flush();
		return;
	}

	$errormsg = gettext("Invalid credentials specified.");
	$timecredit = voucher_auth($voucher);

	if ($timecredit > 0) {
		$a_vouchers = preg_split("/[\t\n\r ]+/s", $voucher);
		$voucher = $a_vouchers[0];
		$attr = [
			'voucher' => 1,
			'session_timeout' => $timecredit * 60,
			'session_terminate_time' => 0,
		];

		$allowret = portal_allow($clientip, $clientmac, $voucher, null, $redirurl, $attr, null, 'voucher', 'voucher');

		if ($allowret === 2) {
			cp_flash_set([
				'redirurl' => $redirurl,
				'type' => 'error',
				'msg' => "Reuse of identification not allowed.",
				'mac' => $clientmac,
				'ip' => $clientip,
			]);
		} elseif ($allowret) {
			captiveportal_logportalauth($voucher, $clientmac, $clientip, "Voucher login good for $timecredit min.");
			cp_flash_set([
				'redirurl' => $redirurl,
				'type' => 'connected',
				'msg' => "{$voucher} online",
				'mac' => $clientmac,
				'ip' => $clientip,
			]);
		} else {
			$m = !empty($config['voucher'][$cpzone]['descrmsgexpired']) ? $config['voucher'][$cpzone]['descrmsgexpired'] : $errormsg;
			cp_flash_set([
				'redirurl' => $redirurl,
				'type' => 'error',
				'msg' => $m,
				'mac' => $clientmac,
				'ip' => $clientip,
			]);
		}
		cp_redirect_self(['zone' => $cpzone]);
	} elseif ($timecredit == -1) {
		captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE", "voucher expired");
		$m = !empty($config['voucher'][$cpzone]['descrmsgexpired']) ? $config['voucher'][$cpzone]['descrmsgexpired'] : $errormsg;
		cp_flash_set([
			'redirurl' => $redirurl,
			'type' => 'error',
			'msg' => $m,
			'mac' => $clientmac,
			'ip' => $clientip,
		]);
		cp_redirect_self(['zone' => $cpzone]);
	} else {
		captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE");
		$m = !empty($config['voucher'][$cpzone]['descrmsgnoaccess']) ? $config['voucher'][$cpzone]['descrmsgnoaccess'] : $errormsg;
		cp_flash_set([
			'redirurl' => $redirurl,
			'type' => 'error',
			'msg' => $m,
			'mac' => $clientmac,
			'ip' => $clientip,
		]);
		cp_redirect_self(['zone' => $cpzone]);
	}
}

/**
 * ---- Login button click (or radmac) ----
 */
if (!empty($_POST['accept']) || (($cpcfg['auth_method'] ?? '') === 'radmac')) {

	if (($cpcfg['auth_method'] ?? '') === 'radmac' && empty($_POST['accept'])) {
		$user = $clientmac;
		$passwd = $cpcfg['radmac_secret'] ?? '';
		$context = 'radmac';
	} elseif (!empty(trim($_POST['auth_user2'] ?? ''))) {
		$user = trim($_POST['auth_user2']);
		$passwd = ($_POST['auth_pass2'] ?? '');
		$context = 'second';
	} else {
		$user = trim($_POST['auth_user'] ?? '');
		$passwd = ($_POST['auth_pass'] ?? '');
		$context = 'first';
	}

	$pipeno = captiveportal_get_next_dn_ruleno('auth');
	if (is_null($pipeno)) {
		$replymsg = gettext("System reached maximum login capacity");
		if (($cpcfg['auth_method'] ?? '') === 'radmac') {
			ob_flush();
			return;
		} else {
			cp_flash_set([
				'redirurl' => $redirurl,
				'type' => 'error',
				'msg' => $replymsg,
				'mac' => $clientmac,
				'ip' => $clientip,
			]);
			log_error("Zone: {$cpzone} - WARNING! Captive portal has reached maximum login capacity");
			cp_redirect_self(['zone' => $cpzone]);
		}
	}

	$auth_result = captiveportal_authenticate_user($user, $passwd, $clientmac, $clientip, $pipeno, $context);

	if (!empty($auth_result['result'])) {
		captiveportal_logportalauth($user, $clientmac, $clientip, $auth_result['login_status'] ?? 'ACCEPT');
		portal_allow(
			$clientip,
			$clientmac,
			$user,
			$passwd,
			$redirurl,
			($auth_result['attributes'] ?? null),
			$pipeno,
			($auth_result['auth_method'] ?? null),
			$context
		);

		cp_flash_set([
			'redirurl' => $loginurl,
			'type' => 'connected',
			'msg' => (trim($_POST['auth_user'] ?? $user) . " online"),
			'mac' => $clientmac,
			'ip' => $clientip,
		]);
		cp_redirect_self(['zone' => $cpzone]);

	} else {
		captiveportal_free_dn_ruleno($pipeno);
		$replymsg = $auth_result['login_message'] ?? gettext("Invalid credentials specified.");
		captiveportal_logportalauth($user, $clientmac, $clientip, ($auth_result['login_status'] ?? 'FAILURE'), $replymsg);

		cp_flash_set([
			'redirurl' => $redirurl,
			'type' => 'error',
			'msg' => $replymsg,
			'mac' => $clientmac,
			'ip' => $clientip,
		]);
		cp_redirect_self(['zone' => $cpzone]);
	}
}

/**
 * ---- Anything else (GET default rendering) ----
 */
$isDisconnected = already_connected($clientip, $clientmac);

if ($isDisconnected === false) {

    // OS captive detection endpoints -> redirect to our portal login
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $is_os_probe = false;

    // Android / Chrome
    if ($host === "connectivitycheck.gstatic.com" && (strpos($uri, "/generate_204") === 0 || $uri === "/")) $is_os_probe = true;

    // Windows NCSI
    if ($host === "www.msftconnecttest.com" && (strpos($uri, "/connecttest.txt") === 0 || $uri === "/")) $is_os_probe = true;
    if ($host === "www.msftncsi.com" && (strpos($uri, "/ncsi.txt") === 0 || $uri === "/")) $is_os_probe = true;

    // Edge captive check
    if ($host === "edge-http.microsoft.com" && (strpos($uri, "/captiveportal/generate_204") === 0 || $uri === "/")) $is_os_probe = true;

    // Apple
    if ($host === "captive.apple.com" && (strpos($uri, "/hotspot-detect.html") === 0 || $uri === "/")) $is_os_probe = true;

    if ($method === 'GET' && $is_os_probe) {
        // 302 is fine here; 303 also OK
        header("Location: {$loginurl}", true, 302);
        exit;
    }

    portal_reply_page($redirurl, "login", "Welcome!", $clientmac, $clientip);
    ob_flush();
    exit;
}


// Still connected
$userid = trim($_POST['auth_user'] ?? '');
$session = already_connected($clientip, $clientmac);
$useridfromip = '';
if (is_array($session) && isset($session[5])) {
	$useridfromip = getusername($session[5]);
}

if ($userid === '' && $useridfromip === '') {
	portal_reply_page($redirurl, "login", "Welcome!", $clientmac, $clientip);
} else {
	portal_reply_page($redirurl, "connected", ($userid !== '' ? $userid : $useridfromip) . " online", $clientmac, $clientip);
}

ob_flush();

?>
