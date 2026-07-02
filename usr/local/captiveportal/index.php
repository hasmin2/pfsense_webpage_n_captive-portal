<?php
// ── 캡티브포털 에러/디버그 로깅 (#24) ────────────────────────────────────────
// 과거: 요청마다 모든 PHP warning 을 /tmp/cp_portal_error.log 에 "무제한" append →
//       요청 폭주 시 25GB 까지 폭발 → ZFS 풀 포화 → 502/OOM/vnstat readonly 등 전면장애.
// 변경:
//   - 프로덕션 기본: /tmp 무제한 파일로 리다이렉트하지 않음(시스템 관리 로그로). 요청당
//     warning 은 적재하지 않고 fatal 계열만 → 무제한 증가 자체가 불가.
//   - 디버그가 필요하면:  touch /tmp/cp_portal_debug.on  (이후 요청부터 활성)
//     이 경우에도 5MB 상한을 넘으면 요청 시작 시 truncate 하여 디스크를 절대 못 채움.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('CP_DEBUG', @file_exists('/tmp/cp_portal_debug.on'));
if (CP_DEBUG) {
	$cp_errlog = '/tmp/cp_portal_error.log';
	// 누적 폭발(디스크 풀) 원천 차단: 상한 초과 시 비우고 다시 시작
	if (@filesize($cp_errlog) > 5 * 1024 * 1024) { // 5MB 상한
		@file_put_contents($cp_errlog, '');
	}
	ini_set('error_log', $cp_errlog);
	error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} else {
	// 프로덕션: error_log 미설정(시스템 관리 로그) + fatal 계열만 → /tmp 무제한 적재 없음.
	error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
}


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
	if (function_exists('fastcgi_finish_request')) {
		fastcgi_finish_request();
	}
	exit;
}

function cp_log(string $msg): void {
	// #24: 디버그 모드에서만 기록(프로덕션에선 no-op) → 핫패스 무제한 로깅 차단
	if (!CP_DEBUG) {
		return;
	}
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
// HTTP 응답 전송(fastcgi_finish_request) 이후 pf state 정리
// → 로그아웃 시 포털 TCP 연결이 응답 도달 전에 RST 되는 것 방지
register_shutdown_function(function() {
	// 즉시 kill 하면 spawn-fcgi(=fastcgi_finish_request 부재) 환경에서 응답/연결 종료 전에
	// 포털 TCP 가 RST 되어 ~19초 지연. detached 백그라운드(짧은 sleep)로 분리한다.
	if (function_exists('cp_flush_deferred_state_kills')) {
		cp_flush_deferred_state_kills();
	}
});

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

$ourhostname = portal_hostname_from_client_ip($clientip);
$redirurl = "{$protocol}{$ourhostname}";
$loginurl = "{$protocol}{$ourhostname}/index.php?zone=" . urlencode($cpzone);

$macfilter = !isset($cpcfg['nomacfilter']);
$clientmac = null;

// ---- If we have a flash message (from previous POST), render it now (GET step) ----
$flash = cp_flash_get();
if ($flash) {
	// Flash 를 읽은 즉시 세션 락 해제 → 동시 GET 요청이 블록되지 않게 함
	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	cp_render_from_flash($flash);
}

/**
 * ---- Determine client MAC if needed ----
 */
if ($macfilter || isset($cpcfg['passthrumacadd'])) {
	$tmpres = pfSense_ip_to_mac($clientip);
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
		'username' => $auth_user,   // change_pw 폼의 auth_user hidden 필드에서 옴
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
		'username' => 'unauthenticated',
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

	// 빈 username 단락(short-circuit): OS 캡티브 탐지/빈 폼 재제출 등 "로그인 의도 없는" 요청은
		// username 이 비어 있다. 인증 + FAILURE("Username blank") 로깅 시 로그 폭주 + 빈 인증 spawn.
		// → username 비면 인증·로깅 없이 로그인 페이지만 재표시(radmac 은 MAC 인증이라 제외).
		if ($context !== 'radmac' && trim((string)$user) === '') {
			portal_reply_page($redirurl, "login", null, $clientmac, $clientip);
			ob_flush();
			exit;
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
			'msg' => (trim($user) . " online"),
			'mac' => $clientmac,
			'ip' => $clientip,
			'username' => trim($user),

		]);
		cp_wireless_auth($user, $clientmac, $clientip, $auth_result['login_status'] ?? 'ACCEPT-LOGIN');
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
$connectedUser = '';
$sessionInfo = already_connected($clientip, $clientmac);
if (is_array($sessionInfo) && array_key_exists(5, $sessionInfo)) {
	$connectedSession = (string)$sessionInfo[5];
	$connectedUser = (string)($sessionInfo[4] ?? '');
}

// Case 2 (제거됨 — 1b): 과거에는 IP+MAC 정확일치 실패 시 "동일 MAC·다른 IP" 세션을
// 신IP 로 자동 이관해 재인증 없이 통과시켰다(#4 IP변경 자동로그인). 그러나 공유기 NAT /
// MAC 클로닝 / 랜덤 MAC 충돌로 "같은 MAC = 서로 다른 기기·유저"가 되면, MAC 만으로 매칭하는
// 자동이관이 ① 남의 인증 세션을 자격증명 없이 탈취하고 ② 세션을 기기 IP 사이에서 핑퐁시켜
// 카운터 리셋·끊김을 유발했다. MAC 만으로는 "같은 기기 IP변경" vs "다른 기기"를 구분할 수
// 없으므로 자동이관을 폐지한다. IP 가 바뀌면 아래 $connectedSession==='' 분기로 떨어져
// 로그인 페이지가 뜨고, 각 사용자는 자기 자격증명으로 POST 로그인해 자기 세션을 받는다.
// (기존 stale 세션은 idle_timeout / noconcurrentlogins='last' 재로그인 시 정리됨.)
// 재활성화하려면 captiveportal_try_migrate_session_by_mac() 를 쿠키/토큰 매칭과 함께 쓸 것.

if ($connectedSession==='') {

	$host   = $_SERVER['HTTP_HOST'] ?? '';
	$uri    = $_SERVER['REQUEST_URI'] ?? '';
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

	// OS 캡티브 탐지 프로브(=내장 로그인 창이 로드하는 URL)는 로그인 URL 로 302 리다이렉트.
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
	// #25: 미인증 GET 은 로그인 페이지를 "직접" 렌더한다.
	// (과거: flash 저장 후 cp_redirect_self → 세션쿠키 지속에 의존. OS 캡티브탐지/무쿠키
	//  클라이언트는 쿠키를 안 돌려보내 매 요청 새 세션 → flash 못 읽음 → 무한 self-redirect
	//  루프 → 세션파일/로그(cp_log) 폭주 → 디스크풀/OOM(#24). self-redirect 는 POST 의
	//  PRG[재제출 방지]에만 쓴다.)
	cp_render_from_flash([
		'redirurl' => $redirurl,
		'type'     => 'login',
		'msg'      => 'Welcome!',
		'mac'      => $clientmac,
		'ip'       => $clientip,
	]);
	// cp_render_from_flash() 가 렌더 후 exit 한다.
}


if ($_SERVER['REQUEST_METHOD'] === 'POST'
	&& isset($_POST['update_speed_profile'])
	&& $_POST['update_speed_profile'] === '1') {
	$loginUser = trim((string)($_POST['login_user'] ?? ''));
	$speedProfile = trim((string)($_POST['speed_profile'] ?? ''));
}

// #25: 연결된 클라이언트의 GET 은 connected 페이지를 "직접" 렌더한다(self-redirect 루프 방지).
cp_render_from_flash([
	'redirurl' => $redirurl,
	'type'     => 'connected',
	'msg'      => '',
	'mac'      => $clientmac,
	'ip'       => $clientip,
	'username' => $connectedUser,
]);
?>

