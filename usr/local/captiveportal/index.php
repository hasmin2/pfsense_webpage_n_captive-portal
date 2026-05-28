<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/cp_portal_error.log');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);


function cp_safe_redirect_url(string $url): string
{
	$url = trim($url);

	// CRLF 인젝션 방지
	if ($url === '' || preg_match("/[\r\n]/", $url)) {
		return '/';
	}

	$p = @parse_url($url);
	if (!is_array($p)) {
		return '/';
	}

	$scheme = strtolower($p['scheme'] ?? '');
	if (!in_array($scheme, ['http', 'https'], true)) {
		return '/';
	}

	// (선택) 오픈리다이렉트 방지: 허용할 호스트만 통과시키고 싶으면 아래 사용
	// $host = strtolower($p['host'] ?? '');
	// $allow = ['google.com', 'www.google.com'];
	// if ($host === '' || !in_array($host, $allow, true)) return '/';

	return $url;
}
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
$auth_user = trim((string)($_POST['auth_user'] ?? ''));
$login_account_type = strtolower(trim((string)($_POST['login_account_type'] ?? '')));

if ($auth_user !== '' && $login_account_type === 'prepaid') {
	if (strpos($auth_user, 'crewpay-') !== 0) {
		$auth_user = 'crewpay-' . $auth_user;
	}
}

$quota_user = trim((string)($_POST['quota_user'] ?? ''));
$quota_account_type = strtolower(trim((string)($_POST['quota_account_type'] ?? '')));

if ($quota_user !== '' && $quota_account_type === 'prepaid') {
	if (strpos($quota_user, 'crewpay-') !== 0) {
		$quota_user = 'crewpay-' . $quota_user;
	}
}
function cp_redirect_self(array $query = []): void
{
	global $cpzone;

	$path = '/index.php'; // 포탈 스크립트 고정 (리라이트/host 문제 회피)
	if (!isset($query['zone'])) {
		$query['zone'] = $cpzone;
	}

	// 캐시/중복 방지용 파라미터(선택)
	$query['_ts'] = (string)time();

	$qs  = http_build_query($query);
	$url = $path . ($qs ? ('?' . $qs) : '');

	cp_log("REDIRECT(to self) url={$url}");

	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['logout_id']||$_POST['accept'])){
		cp_splash_redirect($url, 'Processing...', 'Login / Logout in progress');
	}

	if (headers_sent($file, $line)) {
		cp_log("REDIRECT ABORT headers already sent at {$file}:{$line}");
		exit;
	}

	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	header('Location: ' . $url, true, 302);
	header('Content-Length: 0');
	header('Connection: close');
	exit;
}

function cp_render_from_flash(array $flash): void {
	$ru  = $flash['redirurl'] ?? '';
	$ty  = $flash['type'] ?? 'login';
	$msg = $flash['msg'] ?? '';
	$mac = $flash['mac'] ?? null;
	$ip  = $flash['ip'] ?? null;
	$username  = $flash['username'] ?? null;
	$password  = $flash['password'] ?? null;

	if ($ty === 'redir' && !empty($ru)) {
		$safe = cp_safe_redirect_url($ru);
		cp_splash_redirect($safe, 'Redirecting…', 'Moving to external website');
	}
	else {
		portal_reply_page($ru, $ty, $msg, $mac, $ip, $username, $password);
	}
	ob_flush();
	exit;
}


function cp_splash_redirect(string $url, string $title = 'Connecting…', string $desc = 'Processing..'): void
{
	// 세션 먼저 저장/락 해제 (GET에서 flash 읽게)
	if (session_status() === PHP_SESSION_ACTIVE) {
		@session_write_close();
	}

	// 혹시 쌓인 출력 제거
	while (ob_get_level() > 0) {
		@ob_end_clean();
	}

	$u = htmlspecialchars($url, ENT_QUOTES);
	$t = htmlspecialchars($title, ENT_QUOTES);
	$d = htmlspecialchars($desc, ENT_QUOTES);

	// HTML을 문자열로 먼저 완성 → Content-Length 계산에 사용
	$body = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$t}</title>
<style>
  html,body{height:100%;margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;}
  .wrap{height:100%;display:flex;align-items:center;justify-content:center;background:#0b1220;color:#fff;}
  .card{width:min(420px,92vw);padding:26px 22px;border-radius:16px;background:rgba(255,255,255,.08);box-shadow:0 10px 30px rgba(0,0,0,.35);text-align:center;}
  .spin{width:42px;height:42px;border-radius:50%;border:4px solid rgba(255,255,255,.25);border-top-color:#fff;margin:0 auto 16px;animation:rot 1s linear infinite;}
  @keyframes rot{to{transform:rotate(360deg)}}
  .h{font-size:18px;font-weight:700;margin:0 0 6px}
  .p{opacity:.9;margin:0 0 14px;font-size:14px;line-height:1.35}
  .small{opacity:.75;font-size:12px}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="spin"></div>
      <p class="h">{$t}</p>
      <p class="p">{$d}</p>
      <div class="small" id="status">Wait for few seconds to complete..</div>
    </div>
  </div>

<script>
(function(){
  var url = "{$u}";
  // 단일 리다이렉트 — setInterval 중복 GET 제거
  // (중복 GET이 있으면 두 번째 GET이 세션 락을 기다리다 flash를 못 읽어 ~9초 hang)
  setTimeout(function(){ window.location.replace(url); }, 150);
})();
</script>

<noscript><meta http-equiv="refresh" content="0;url={$u}"></noscript>
</body>
</html>
HTML;

	// Content-Length 를 명시해야 브라우저가 TCP 연결 종료를 기다리지 않고
	// 바이트 수신 완료 즉시 JS 를 실행한다.
	// (pfSense spawn-fcgi 환경에서는 fastcgi_finish_request() 가 없어서
	//  PHP shutdown 함수들이 끝날 때까지 연결이 안 닫히면 ~19초 지연 발생)
	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Content-Length: ' . strlen($body));
	echo $body;
	@ob_flush();
	@flush();
	error_log("[CP_SPLASH] t=" . sprintf('%.6f', microtime(true)) . " before-fastcgi_finish  available=" . (function_exists('fastcgi_finish_request') ? 'YES' : 'NO'));
	if (function_exists('fastcgi_finish_request')) {
		fastcgi_finish_request();
		error_log("[CP_SPLASH] t=" . sprintf('%.6f', microtime(true)) . " after-fastcgi_finish");
	}
	error_log("[CP_SPLASH] t=" . sprintf('%.6f', microtime(true)) . " before-exit");
	exit;
}


function cp_log(string $msg): void {
	$t = sprintf('%.6f', microtime(true));
	$sid = session_id();
	error_log("[CP] t={$t} sid={$sid} {$msg}");
}

// ── Deferred pf state kill list ───────────────────────────────────────────────
// captiveportal_disconnect() 및 cp_kill_states_for_ip() 가 즉시 pfctl 을 호출하면
// 클라이언트 → 포털 TCP 연결이 RST 되어 HTTP 응답이 전달되기 전에 연결이 끊긴다.
// 대신 IP 를 이 배열에 적재하고, fastcgi_finish_request() + exit 이후 실행되는
// shutdown 함수에서 처리하면 응답 전달 후 안전하게 state 를 정리할 수 있다.
$GLOBALS['_cp_deferred_state_kills'] = [];
// ─────────────────────────────────────────────────────────────────────────────
// ── 진단: shutdown 함수가 얼마나 걸리는지 / PHP ini 설정 확인 ──────────────
$_cp_proc_start = microtime(true);
register_shutdown_function(function() use (&$_cp_proc_start) {
	$now = microtime(true);
	error_log(sprintf(
		"[CP_SHUTDOWN] t=%.6f  elapsed_since_proc_start=%.3fs"
		. "  max_exec=%s  socket_timeout=%s  fastcgi_finish=%s",
		$now,
		$now - $_cp_proc_start,
		ini_get('max_execution_time'),
		ini_get('default_socket_timeout'),
		function_exists('fastcgi_finish_request') ? 'YES' : 'NO'
	));
	// HTTP 응답 전송(fastcgi_finish_request) 이후 pf state 정리
	// → 로그아웃 시 포털 TCP 연결이 응답 도달 전에 RST 되는 것 방지
	foreach (($GLOBALS['_cp_deferred_state_kills'] ?? []) as $ip) {
		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			continue;
		}
		if (function_exists('pfSense_kill_states')) {
			@pfSense_kill_states(utf8_encode($ip));
			@pfSense_kill_srcstates(utf8_encode($ip));
		}
		if (function_exists('cp_kill_states_for_ip')) {
			cp_kill_states_for_ip($ip);
		}
		error_log("[CP_SHUTDOWN] deferred kill_states ip={$ip} done t=" . sprintf('%.6f', microtime(true)));
	}
});
// ─────────────────────────────────────────────────────────────────────────────

error_log("[CP] t=" . sprintf('%.6f', microtime(true)) . " sid=(none) R0a pre-session_start method=" . ($_SERVER['REQUEST_METHOD'] ?? '?'));
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
error_log("[CP] t=" . sprintf('%.6f', microtime(true)) . " sid=" . session_id() . " R0b post-session_start");
require_once("auth.inc");
require_once("util.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

header("Expires: 0");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Connection: close");





/**
 * ---- Init zone / cfg ----
 */
global $cpzone, $cpzoneid, $config;
if (!ob_get_level()) {
	ob_start();
}
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

cp_log("R1 portal_hostname_from_client_ip start");
$ourhostname = portal_hostname_from_client_ip($clientip);
cp_log("R2 portal_hostname_from_client_ip end hostname=$ourhostname");
$redirurl = "{$protocol}{$ourhostname}";
$loginurl = "{$protocol}{$ourhostname}/index.php?zone=" . urlencode($cpzone);

$macfilter = !isset($cpcfg['nomacfilter']);
$clientmac = null;

// ---- If we have a flash message (from previous POST), render it now (GET step) ----
cp_log("R3 flash check start method=" . ($_SERVER['REQUEST_METHOD'] ?? ''));
$flash = cp_flash_get();
if ($flash) {
	// Flash 를 읽은 즉시 세션 락 해제 → 동시 GET 요청이 블록되지 않게 함
	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	cp_log("R4 flash found type=" . ($flash['type'] ?? 'none') . " → entering cp_render_from_flash");
	cp_render_from_flash($flash);
}

/**
 * ---- Determine client MAC if needed ----
 */
if ($macfilter || isset($cpcfg['passthrumacadd'])) {
	cp_log("R5 pfSense_ip_to_mac start clientip=$clientip");
	$tmpres = pfSense_ip_to_mac($clientip);
	cp_log("R6 pfSense_ip_to_mac end result=" . (is_array($tmpres) ? json_encode($tmpres) : 'false'));
	if (!is_array($tmpres) || empty($tmpres['macaddr'])) {
		cp_wireless_auth("unauthenticated", "noclientmac", $clientip, "ERROR");
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
	captiveportal_disconnect_client(getsession($safe_logout_id));

	cp_flash_set([
		'redirurl' => $redirurl,
		'type' => 'login',
		'msg' => 'Logged out!!!',
		'mac' => $clientmac,
		'ip' => $clientip,
	]);
	cp_redirect_self(['zone' => $cpzone]);
}

if (!empty($_POST['commit_change_pw'])) {
	cp_flash_set([
		'redirurl' => $redirurl,
		'type' => 'commit_change_pw',
		'msg' => 'Changed PW',
		'mac' => $clientmac,
		'ip' => $clientip,
		'username' => $auth_user,
		'password' => $_POST['new_password'],
	]);
	cp_redirect_self(['zone' => $cpzone]);
}

if (!empty($_POST['check_quota'])) {
	cp_flash_set([
		'redirurl' => $loginurl,
		'type' => 'check_quota',
		'msg' => 'Quota details',
		'username' => $quota_user,
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
	cp_wireless_auth($clientmac, $clientmac, $clientip, "Blocked MAC address");
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
	cp_wireless_auth("unauthenticated", $clientmac, $clientip, "ACCEPT-PASSTHORUGH");
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
			cp_wireless_auth($voucher, $clientmac, $clientip, "Voucher login good for $timecredit min.");
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
		cp_wireless_auth($voucher, $clientmac, $clientip, "FAILURE", "voucher expired");
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
		cp_wireless_auth($voucher, $clientmac, $clientip, "FAILURE");
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
//if (!empty($_POST['accept']) || (($cpcfg['auth_method'] ?? '') === 'radmac')) {
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (($cpcfg['auth_method'] ?? '') === 'radmac')) {
	if (($cpcfg['auth_method'] ?? '') === 'radmac' && empty($_POST['accept'])) {
		$user = $clientmac;
		$passwd = $cpcfg['radmac_secret'] ?? '';
		$context = 'radmac';
	} elseif (!empty(trim($_POST['auth_user2'] ?? ''))) {
		$user = trim($_POST['auth_user2']);
		$passwd = ($_POST['auth_pass2'] ?? '');
		$context = 'second';
	} else {
		$user = trim($auth_user ?? '');
		$passwd = ($_POST['auth_pass'] ?? '');
		$context = 'first';
	}

	cp_log("T1 get_next_dn_ruleno start");
	$pipeno = captiveportal_get_next_dn_ruleno('auth');
	cp_log("T1 get_next_dn_ruleno end pipeno=" . var_export($pipeno, true));
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
	cp_log("T2 authenticate_user start user=$user");
	$auth_result = captiveportal_authenticate_user($user, $passwd, $clientmac, $clientip, $pipeno, $context);
	cp_log("T3 authenticate_user end result=" . var_export($auth_result['result'] ?? null, true));
	if (!empty($auth_result['result'])) {
		cp_log("T4 portal_allow start");
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
		cp_log("T5 portal_allow end");
		cp_flash_set([
			'redirurl' => $loginurl,
			'type' => 'connected',
			'msg' => (trim($user) . " online"),
			'mac' => $clientmac,
			'ip' => $clientip,

		]);
		cp_wireless_auth($user, $clientmac, $clientip, $auth_result['login_status'] ?? 'ACCEPT-LOGIN');
		cp_log("T6 cp_redirect_self (POST done)");
		cp_redirect_self(['zone' => $cpzone]);
		ob_flush();
		exit;
	} else {
		captiveportal_free_dn_ruleno($pipeno);
		$replymsg = $auth_result['login_message'] ?? gettext("Invalid credentials specified.");
		cp_wireless_auth($user, $clientmac, $clientip, ($auth_result['login_status'] ?? 'FAILURE'), $replymsg);
		cp_flash_set([
			'redirurl' => $redirurl,
			'type' => 'error',
			'msg' => $replymsg,
			'mac' => $clientmac,
			'ip' => $clientip,
		]);
		cp_redirect_self(['zone' => $cpzone]);
		ob_flush();
		exit;
	}
}

/**
 * 기존 연결 세션 확인
 */
$connectedSession = '';
$sessionInfo = already_connected($clientip, $clientmac);
if (is_array($sessionInfo) && array_key_exists(5, $sessionInfo)) {
	$connectedSession = (string)$sessionInfo[5];
}

if ($connectedSession==='') {

	$host   = $_SERVER['HTTP_HOST'] ?? '';
	$uri    = $_SERVER['REQUEST_URI'] ?? '';
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

	$probeMap = [
		'connectivitycheck.gstatic.com' => ['/generate_204'],
		'www.msftconnecttest.com'       => ['/connecttest.txt'],
		'www.msftncsi.com'              => ['/ncsi.txt'],
		'edge-http.microsoft.com'       => ['/captiveportal/generate_204'],
		'captive.apple.com'             => ['/hotspot-detect.html'],
	];

	$is_os_probe = false;
	if ($method === 'GET' && isset($probeMap[$host])) {
		if ($uri === '/') {
			$is_os_probe = true;
		} else {
			foreach ($probeMap[$host] as $prefix) {
				if (strpos($uri, $prefix) === 0) {
					$is_os_probe = true;
					break;
				}
			}
		}
	}

	if ($is_os_probe) {
		header("Location: {$loginurl}", true, 302);
		exit;
	}
	cp_flash_set([
		'redirurl' => $redirurl,
		'type' => $data['type'] ?? 'login',
		'msg' => $data['msg'] ?? 'Welcome!',
		'mac' => $clientmac,
		'ip' => $clientip,
	]);
	cp_redirect_self(['zone' => $cpzone]);
	ob_flush();
	exit;

}


if ($_SERVER['REQUEST_METHOD'] === 'POST'
	&& isset($_POST['update_speed_profile'])
	&& $_POST['update_speed_profile'] === '1') {
	$loginUser = trim((string)($_POST['login_user'] ?? ''));
	$speedProfile = trim((string)($_POST['speed_profile'] ?? ''));
}

cp_flash_set([
	'redirurl' => $redirurl,
	'type' => 'connected',
	'msg' => '',
	'mac' => $clientmac,
	'ip' => $clientip,
]);
cp_redirect_self(['zone' => $cpzone]);
ob_flush();
exit;
?>

