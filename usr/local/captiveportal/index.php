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

// ── #31: CNA(OS 캡티브 미니브라우저) 안내 페이지 ─────────────────────────────
// CNA 는 로그인 성공 직후 OS 가 강제로 닫아버려(연결성 프로브 성공 감지) connected 페이지를
// 볼 수 없고, 기본 브라우저로 탈출시키는 공식 경로도 없다(window.open/커스텀 스킴 전부 차단).
// → 절충안: CNA 안에서는 로그인시키지 않고 "기본 브라우저를 열어 포털 주소로 접속"하라는
//   안내만 보여준다. 발견성(팝업이 떠서 로그인 필요함을 알림)은 유지된다.

// OS 캡티브 탐지 프로브(=CNA 가 로드하는 URL) 식별. 세션/존 설정에 의존하지 않는 순수 함수
// → session_start 전에 호출해 프로브 요청엔 PHP 세션 파일을 아예 만들지 않는다(#24~26 후속).
// OS 탐지 프로브 호스트 → 탐지 경로 목록. 전부 "프로브 전용" 호스트(사용자가 직접 브라우징할
// 일 없는 도메인)만 — www.google.com 류는 실브라우저 접근과 겹치므로 절대 넣지 말 것.
// 주의: www.msftconnecttest.com 의 "/redirect" 는 의도적으로 미포함 — Windows 는 그 경로를
// "기본 브라우저"로 열므로 로그인 폼을 그대로 줘야 한다(일반 렌더 경로로 떨어짐).
function cp_probe_map(): array {
	return [
		'connectivitycheck.gstatic.com'           => ['/generate_204'],
		'clients3.google.com'                     => ['/generate_204'], // 구형 Android
		'connect.rom.miui.com'                    => ['/generate_204'], // Xiaomi/MIUI
		'connectivitycheck.platform.hicloud.com'  => ['/generate_204'], // Huawei
		'www.msftconnecttest.com'                 => ['/connecttest.txt'],
		'www.msftncsi.com'                        => ['/ncsi.txt'],
		'edge-http.microsoft.com'                 => ['/captiveportal/generate_204'],
		'captive.apple.com'                       => ['/hotspot-detect.html'],
		'captive.g.aaplimg.com'                   => ['/hotspot-detect.html'], // Apple 대체 호스트
	];
}

// 요청 Host 를 프로브 호스트와 비교 가능한 형태로 정규화
function cp_normalized_host(): string {
	$host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
	return preg_replace('/:\d+$/', '', $host); // "host:포트" 방어
}

function cp_detect_os_probe(): bool {
	$host   = cp_normalized_host();
	$uri    = (string)($_SERVER['REQUEST_URI'] ?? '');
	$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

	$probeMap = cp_probe_map();
	if ($method !== 'GET' || !isset($probeMap[$host])) {
		return false;
	}
	if ($uri === '/') {
		return true;
	}
	foreach ($probeMap[$host] as $prefix) {
		if (strpos($uri, $prefix) === 0) {
			return true;
		}
	}
	return false;
}

// "주소 복사" 버튼이 이동해 오는 ack 내비게이션(프로브 호스트의 /?cp_cna_ack=1) 식별.
// 프로브와 마찬가지로 무쿠키 CNA 요청이라 세션 불필요 → session_start 전 판정.
function cp_detect_cna_ack(): bool {
	if ((string)($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
		return false;
	}
	if (!isset($_GET['cp_cna_ack'])) {
		return false;
	}
	return isset(cp_probe_map()[cp_normalized_host()]);
}

// ── ack 마커: "이 클라이언트는 안내를 보고 주소를 복사했다" ──────────────────
// 마커가 살아있는 동안 OS 탐지 프로브에 "성공" 응답 → OS 가 captive 해제로 판정해
// CNA 를 스스로 닫는다(로그인 성공 때와 같은 메커니즘) — Android 의 "현재 상태로 이
// 네트워크 사용" 메뉴 탐색이 불필요해짐. 방화벽 인증과는 무관(트래픽은 여전히 차단).
// TTL 내 미로그인 시 다음 OS 재검증에서 다시 captive → CNA 재등장(재안내). 파일은
// IP당 1개·수 바이트·tmpfs 라 디스크 위험 없음(#24 고려, 만료 시 조회 중 삭제).
define('CP_CNA_ACK_TTL', 600); // 10분

function cp_cna_ack_file(string $ip): string {
	return '/tmp/cp_cna_ack_' . preg_replace('/[^0-9a-fA-F\.\:]/', '', $ip);
}

function cp_cna_ack_set(string $ip): void {
	@file_put_contents(cp_cna_ack_file($ip), (string)time());
}

function cp_cna_ack_active(string $ip): bool {
	$f = cp_cna_ack_file($ip);
	$mt = @filemtime($f);
	if ($mt === false) {
		return false;
	}
	if ((time() - $mt) > CP_CNA_ACK_TTL) {
		@unlink($f);
		return false;
	}
	return true;
}

// OS 가 기대하는 "인터넷 정상" 프로브 응답을 호스트별로 반환 후 exit.
// (Android 계열=204 / MS NCSI=고정 텍스트 / Apple=Success HTML)
function cp_probe_success_response(): void {
	$host = cp_normalized_host();
	while (ob_get_level() > 0) {
		@ob_end_clean();
	}
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	if ($host === 'www.msftconnecttest.com') {
		$body = 'Microsoft Connect Test';
		header('Content-Type: text/plain');
		header('Content-Length: ' . strlen($body));
		echo $body;
	} elseif ($host === 'www.msftncsi.com') {
		$body = 'Microsoft NCSI';
		header('Content-Type: text/plain');
		header('Content-Length: ' . strlen($body));
		echo $body;
	} elseif ($host === 'captive.apple.com' || $host === 'captive.g.aaplimg.com') {
		$body = '<HTML><HEAD><TITLE>Success</TITLE></HEAD><BODY>Success</BODY></HTML>';
		header('Content-Type: text/html');
		header('Content-Length: ' . strlen($body));
		echo $body;
	} else {
		// gstatic/clients3/miui/hicloud/edge-http 계열: 204 No Content
		http_response_code(204);
		header('Content-Length: 0');
	}
	@ob_flush();
	@flush();
	if (function_exists('fastcgi_finish_request')) {
		fastcgi_finish_request();
	}
	exit;
}

// CNA/프로브는 쿠키를 안 돌려보내는 경우가 많아 Accept-Language 가 사실상 유일한 언어 신호.
// (#21 cp_resolve_lang 와 같은 우선순위(?lang → cp_lang 쿠키 → Accept-Language → en)지만,
//  단일 파일 배포(버전 섞임 안전)를 위해 captiveportal.inc 에 의존하지 않는 자족 사본을 둔다.
//  추가로 필리핀 기기의 "fil"(Filipino) 을 'tl' 로 매핑한다.)
function cp_cna_resolve_lang(): string {
	$supported = ['en', 'ko', 'tl', 'vi', 'id', 'zh', 'my'];
	foreach ([(string)($_GET['lang'] ?? ''), (string)($_COOKIE['cp_lang'] ?? '')] as $cand) {
		$cand = strtolower(trim($cand));
		if (in_array($cand, $supported, true)) {
			return $cand;
		}
	}
	$al = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
	foreach (explode(',', $al) as $tok) {
		$tok = trim(explode(';', $tok)[0]); // "ko-kr;q=0.9" → "ko-kr"
		if ($tok === '') {
			continue;
		}
		if (strpos($tok, 'fil') === 0 || strpos($tok, 'tl') === 0) {
			return 'tl'; // Filipino/Tagalog ("fi"(핀란드어) 와 충돌 방지 위해 2글자 절단 전에 검사)
		}
		$two = substr($tok, 0, 2);
		if (in_array($two, $supported, true)) {
			return $two;
		}
	}
	return 'en';
}

// CNA 안내 페이지 렌더 후 exit. 200 + HTML = OS 기대값(204/Success/connecttest.txt)이 아니므로
// OS 는 계속 captive 로 판정 → CNA 에 이 안내가 표시된다. 사용자가 기본 브라우저에서 로그인을
// 마치면 프로브가 방화벽을 직접 통과해 진짜 성공 응답을 받으므로 CNA 는 저절로 닫힌다.
// CNA 안내/완료 페이지 공용 i18n 사전. ko 외 번역은 best-effort — 현지 검수 권장 (#21 동일 정책)
function cp_cna_dict(): array {
	return [
		'en' => [
			'title'        => 'Crew WiFi Login',
			'heading'      => 'Please log in from your web browser',
			'lead'         => 'This window closes by itself right after login, so please use your web browser instead.',
			'step1'        => 'Tap “Copy address” below.',
			'hint_ios'     => 'If it stays open: tap “Done” at the top right (or “Cancel” → “Use Without Internet”).',
			'hint_android' => 'If it stays open: tap the ⋮ menu at the top right and choose “Use this network as is”.',
			'step2'        => 'This window will close by itself. (If not, close it.)',
			'step3'        => 'Open your web browser (Safari, Chrome, …) and paste the address into the address bar.',
			'note'         => 'Opening any http:// website will also take you to the login page.',
			'copy_btn'     => 'Copy address',
			'copied'       => 'Copied!',
			'done_heading' => 'Address copied!',
			'done_lead'    => 'This window will close by itself shortly. Open your web browser and paste the address into the address bar.',
		],
		'ko' => [
			'title'        => '선원 WiFi 로그인',
			'heading'      => '웹브라우저에서 로그인해 주세요',
			'lead'         => '이 창은 로그인 직후 자동으로 닫혀버리므로, 웹브라우저에서 로그인해 주세요.',
			'step1'        => '아래 “주소 복사” 버튼을 누르세요.',
			'hint_ios'     => '안 닫히면 오른쪽 위 “완료”를 누르세요. (또는 “취소” → “인터넷 연결 없이 사용”)',
			'hint_android' => '안 닫히면 오른쪽 위 ⋮ 메뉴에서 “현재 상태로 이 네트워크 사용”을 선택하세요.',
			'step2'        => '창이 자동으로 닫힙니다. (안 닫히면 직접 닫으세요.)',
			'step3'        => '웹브라우저(Safari, Chrome 등)를 열어 주소창에 붙여넣으세요.',
			'note'         => 'http:// 로 시작하는 아무 사이트에 접속해도 로그인 페이지로 이동합니다.',
			'copy_btn'     => '주소 복사',
			'copied'       => '복사됨!',
			'done_heading' => '주소가 복사되었습니다!',
			'done_lead'    => '이 창은 곧 자동으로 닫힙니다. 웹브라우저를 열어 주소창에 붙여넣으세요.',
		],
		'tl' => [
			'title'        => 'Crew WiFi Login',
			'heading'      => 'Mag-log in gamit ang web browser',
			'lead'         => 'Awtomatikong nagsasara ang window na ito pagkatapos mag-log in, kaya gamitin ang web browser.',
			'step1'        => 'I-tap ang “Kopyahin ang address” sa ibaba.',
			'hint_ios'     => 'Kung nananatiling bukas: i-tap ang “Done” sa kanang itaas (o “Cancel” → “Use Without Internet”).',
			'hint_android' => 'Kung nananatiling bukas: i-tap ang ⋮ menu sa kanang itaas at piliin ang “Use this network as is”.',
			'step2'        => 'Awtomatikong magsasara ang window na ito. (Kung hindi, isara ito.)',
			'step3'        => 'Buksan ang web browser (Safari, Chrome, …) at i-paste ang address sa address bar.',
			'note'         => 'Ang pagbukas ng kahit anong http:// na website ay dadalhin ka rin sa login page.',
			'copy_btn'     => 'Kopyahin ang address',
			'copied'       => 'Nakopya!',
			'done_heading' => 'Nakopya na ang address!',
			'done_lead'    => 'Magsasara ang window na ito sa ilang sandali. Buksan ang web browser at i-paste ang address sa address bar.',
		],
		'vi' => [
			'title'        => 'Đăng nhập WiFi thuyền viên',
			'heading'      => 'Vui lòng đăng nhập bằng trình duyệt web',
			'lead'         => 'Cửa sổ này sẽ tự đóng ngay sau khi đăng nhập, vì vậy hãy dùng trình duyệt web.',
			'step1'        => 'Chạm “Sao chép địa chỉ” bên dưới.',
			'hint_ios'     => 'Nếu vẫn mở: chạm “Xong” ở góc trên bên phải (hoặc “Hủy” → “Sử dụng mà không có Internet”).',
			'hint_android' => 'Nếu vẫn mở: chạm menu ⋮ ở góc trên bên phải và chọn “Sử dụng mạng này như hiện trạng”.',
			'step2'        => 'Cửa sổ này sẽ tự đóng. (Nếu không, hãy tự đóng.)',
			'step3'        => 'Mở trình duyệt web (Safari, Chrome, …) và dán địa chỉ vào thanh địa chỉ.',
			'note'         => 'Mở bất kỳ trang web http:// nào cũng sẽ đưa bạn đến trang đăng nhập.',
			'copy_btn'     => 'Sao chép địa chỉ',
			'copied'       => 'Đã sao chép!',
			'done_heading' => 'Đã sao chép địa chỉ!',
			'done_lead'    => 'Cửa sổ này sẽ sớm tự đóng. Mở trình duyệt web và dán địa chỉ vào thanh địa chỉ.',
		],
		'id' => [
			'title'        => 'Login WiFi Kru',
			'heading'      => 'Silakan login melalui browser web',
			'lead'         => 'Jendela ini akan tertutup sendiri segera setelah login, jadi gunakan browser web Anda.',
			'step1'        => 'Ketuk “Salin alamat” di bawah.',
			'hint_ios'     => 'Jika tetap terbuka: ketuk “Selesai” di kanan atas (atau “Batal” → “Gunakan Tanpa Internet”).',
			'hint_android' => 'Jika tetap terbuka: ketuk menu ⋮ di kanan atas lalu pilih “Gunakan jaringan ini apa adanya”.',
			'step2'        => 'Jendela ini akan tertutup sendiri. (Jika tidak, tutup saja.)',
			'step3'        => 'Buka browser web (Safari, Chrome, …) lalu tempel alamat di bilah alamat.',
			'note'         => 'Membuka situs http:// apa pun juga akan mengarahkan Anda ke halaman login.',
			'copy_btn'     => 'Salin alamat',
			'copied'       => 'Tersalin!',
			'done_heading' => 'Alamat telah disalin!',
			'done_lead'    => 'Jendela ini akan segera tertutup sendiri. Buka browser web lalu tempel alamat di bilah alamat.',
		],
		'zh' => [
			'title'        => '船员 WiFi 登录',
			'heading'      => '请使用网页浏览器登录',
			'lead'         => '此窗口在登录后会立即自动关闭，请改用网页浏览器登录。',
			'step1'        => '点按下方“复制地址”。',
			'hint_ios'     => '若未关闭：点按右上角“完成”（或“取消”→“不使用互联网而继续”）。',
			'hint_android' => '若未关闭：点按右上角 ⋮ 菜单，选择“按原样使用此网络”。',
			'step2'        => '此窗口将自动关闭。（如未关闭，请手动关闭。）',
			'step3'        => '打开网页浏览器（Safari、Chrome 等），将地址粘贴到地址栏。',
			'note'         => '打开任意 http:// 网站也会跳转到登录页面。',
			'copy_btn'     => '复制地址',
			'copied'       => '已复制！',
			'done_heading' => '地址已复制！',
			'done_lead'    => '此窗口很快会自动关闭。请打开网页浏览器，将地址粘贴到地址栏。',
		],
		'my' => [
			'title'        => 'Crew WiFi Login',
			'heading'      => 'ဝက်ဘ်ဘရောက်ဇာဖြင့် လော့ဂ်အင်ဝင်ပါ',
			'lead'         => 'ဤဝင်းဒိုးသည် လော့ဂ်အင်ဝင်ပြီးသည်နှင့် အလိုအလျောက် ပိတ်သွားမည်ဖြစ်၍ ဝက်ဘ်ဘရောက်ဇာကို သုံးပါ။',
			'step1'        => 'အောက်ရှိ “လိပ်စာ ကူးယူရန်” ကို နှိပ်ပါ။',
			'hint_ios'     => 'မပိတ်လျှင် ညာဘက်အပေါ်ရှိ “Done” ကို နှိပ်ပါ။ (သို့ “Cancel” → “Use Without Internet”)',
			'hint_android' => 'မပိတ်လျှင် ညာဘက်အပေါ် ⋮ မီနူးတွင် “Use this network as is” ကို ရွေးပါ။',
			'step2'        => 'ဤဝင်းဒိုးသည် အလိုအလျောက် ပိတ်သွားပါမည်။ (မပိတ်လျှင် ကိုယ်တိုင်ပိတ်ပါ။)',
			'step3'        => 'ဝက်ဘ်ဘရောက်ဇာ (Safari, Chrome) ကို ဖွင့်ပြီး လိပ်စာဘားတွင် ကူးထည့်ပါ။',
			'note'         => 'http:// ဝက်ဘ်ဆိုက် တစ်ခုခုကို ဖွင့်လျှင်လည်း လော့ဂ်အင်စာမျက်နှာသို့ ရောက်ပါမည်။',
			'copy_btn'     => 'လိပ်စာ ကူးယူရန်',
			'copied'       => 'ကူးယူပြီး!',
			'done_heading' => 'လိပ်စာ ကူးယူပြီးပါပြီ!',
			'done_lead'    => 'ဤဝင်းဒိုးသည် မကြာမီ အလိုအလျောက် ပိတ်သွားပါမည်။ ဝက်ဘ်ဘရောက်ဇာကို ဖွင့်ပြီး လိပ်စာကို ကူးထည့်ပါ။',
		],
	];
}

// OS 별 "창이 안 닫힐 때" 폴백 힌트 선택 (CNA UA: iOS=Safari 토큰 없는 WebKit /
// 프로브=CaptiveNetworkSupport)
function cp_cna_os_hint(array $d): string {
	$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
	if (preg_match('/iphone|ipad|ipod|macintosh|captivenetworksupport/i', $ua)) {
		return $d['hint_ios'];
	}
	if (stripos($ua, 'android') !== false) {
		return $d['hint_android'];
	}
	return '';
}

function cp_render_cna_guide(string $portal_url): void {
	$lang = cp_cna_resolve_lang();
	$dict = cp_cna_dict();
	$d = $dict[$lang] ?? $dict['en'];
	$hint = cp_cna_os_hint($d);

	$e = function (string $s): string {
		return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
	};
	$u        = $e($portal_url);
	$title    = $e($d['title']);
	$heading  = $e($d['heading']);
	$lead     = $e($d['lead']);
	$step1    = $e($d['step1']);
	$step2    = $e($d['step2']);
	$step3    = $e($d['step3']);
	$note     = $e($d['note']);
	$copyBtn  = $e($d['copy_btn']);
	$hintHtml = ($hint !== '') ? '<div class="hint">' . $e($hint) . '</div>' : '';
	// JS 문자열 리터럴은 json_encode 로 안전 주입 (포털 URL / "복사됨!" 라벨)
	$copyScript = cp_cna_copy_script(json_encode($portal_url), json_encode($d['copied']));

	$body = <<<HTML
<!doctype html>
<html lang="{$lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<style>
  html,body{height:100%;margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;}
  .wrap{min-height:100%;display:flex;align-items:center;justify-content:center;background:#0b1220;color:#fff;}
  .card{width:min(440px,92vw);padding:26px 22px;border-radius:16px;background:rgba(255,255,255,.08);box-shadow:0 10px 30px rgba(0,0,0,.35);}
  .h{font-size:19px;font-weight:700;margin:0 0 8px;text-align:center}
  .p{opacity:.9;margin:0 0 16px;font-size:14px;line-height:1.45;text-align:center}
  ol{margin:0 0 16px;padding-left:22px;font-size:14px;line-height:1.5}
  li{margin-bottom:10px}
  .hint{opacity:.75;font-size:12.5px;margin-top:3px;line-height:1.4}
  .url{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:17px;font-weight:700;text-align:center;background:rgba(255,255,255,.12);border:1px dashed rgba(255,255,255,.45);border-radius:10px;padding:12px 8px;margin:0 0 10px;word-break:break-all;-webkit-user-select:all;user-select:all;}
  .copybtn{display:block;width:100%;box-sizing:border-box;border:0;border-radius:10px;padding:13px 10px;font-size:15px;font-weight:700;background:#2f6fed;color:#fff;margin:0 0 14px;cursor:pointer;font-family:inherit;text-align:center;text-decoration:none;}
  .copybtn.ok{background:#16a34a}
  .small{opacity:.7;font-size:12px;text-align:center;margin:0;line-height:1.4}
</style>
</head>
<body>
  <div class="wrap"><div class="card">
    <p class="h">{$heading}</p>
    <p class="p">{$lead}</p>
    <ol>
      <li>{$step1}</li>
      <li>{$step2}{$hintHtml}</li>
      <li>{$step3}</li>
    </ol>
    <div class="url">{$u}</div>
    <a class="copybtn" id="cp_copy_btn" href="/?cp_cna_ack=1" role="button">{$copyBtn}</a>
    <p class="small">{$note}</p>
  </div></div>
{$copyScript}
</body>
</html>
HTML;

	cp_cna_send_html($body);
}

// "주소 복사" ack 후 완료 페이지. 이 페이지의 "로드 완료"가 CNA 의 재검증 프로브를 유발하고,
// ack 마커 덕에 프로브가 성공 응답을 받아 OS 가 창을 자동으로 닫는다. 안 닫히는 기기(OEM
// 변형) 대비 OS 별 폴백 힌트 + URL 재표시 + 복사 버튼(복사 실패 시 재시도용)을 유지한다.
function cp_render_cna_done(string $portal_url): void {
	$lang = cp_cna_resolve_lang();
	$dict = cp_cna_dict();
	$d = $dict[$lang] ?? $dict['en'];
	$hint = cp_cna_os_hint($d);

	$e = function (string $s): string {
		return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
	};
	$u        = $e($portal_url);
	$title    = $e($d['title']);
	$heading  = $e($d['done_heading']);
	$lead     = $e($d['done_lead']);
	$copyBtn  = $e($d['copy_btn']);
	$hintHtml = ($hint !== '') ? '<p class="hint2">' . $e($hint) . '</p>' : '';
	$copyScript = cp_cna_copy_script(json_encode($portal_url), json_encode($d['copied']));

	$body = <<<HTML
<!doctype html>
<html lang="{$lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<style>
  html,body{height:100%;margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;}
  .wrap{min-height:100%;display:flex;align-items:center;justify-content:center;background:#0b1220;color:#fff;}
  .card{width:min(440px,92vw);padding:26px 22px;border-radius:16px;background:rgba(255,255,255,.08);box-shadow:0 10px 30px rgba(0,0,0,.35);text-align:center;}
  .tick{width:54px;height:54px;border-radius:50%;background:#16a34a;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;}
  .h{font-size:19px;font-weight:700;margin:0 0 8px}
  .p{opacity:.9;margin:0 0 16px;font-size:14px;line-height:1.45}
  .url{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:17px;font-weight:700;background:rgba(255,255,255,.12);border:1px dashed rgba(255,255,255,.45);border-radius:10px;padding:12px 8px;margin:0 0 10px;word-break:break-all;-webkit-user-select:all;user-select:all;}
  .copybtn{display:block;width:100%;border:0;border-radius:10px;padding:13px 10px;font-size:15px;font-weight:700;background:#2f6fed;color:#fff;margin:0 0 14px;cursor:pointer;font-family:inherit;}
  .copybtn.ok{background:#16a34a}
  .hint2{opacity:.75;font-size:12.5px;margin:0;line-height:1.4}
</style>
</head>
<body>
  <div class="wrap"><div class="card">
    <div class="tick">&#10003;</div>
    <p class="h">{$heading}</p>
    <p class="p">{$lead}</p>
    <div class="url">{$u}</div>
    <button class="copybtn" id="cp_copy_btn" type="button">{$copyBtn}</button>
    {$hintHtml}
  </div></div>
{$copyScript}
</body>
</html>
HTML;

	cp_cna_send_html($body);
}

// 복사 컨트롤 동작 JS. 안내 페이지의 컨트롤은 실제 <a href="/?cp_cna_ack=1"> 앵커라
// "탭 = user-gesture 내비게이션"으로 ack 페이지로 이동한다(삼성 등 일부 CNA webview 가
// 스크립트 기반 location.href 이동을 무시·차단하는 문제 회피 — S20 실측). 클릭 핸들러는
// 동기 execCommand 복사만 수행하고 preventDefault 하지 않으므로, 복사 직후 앵커의 기본
// 내비게이션이 그대로 진행된다. 완료 페이지의 컨트롤은 href 없는 <button> 이라 라벨만 토글.
function cp_cna_copy_script(string $uJs, string $copiedJs): string {
	return <<<JS
<script>
(function(){
  var btn=document.getElementById('cp_copy_btn');
  if(!btn){return;}
  var txt={$uJs};
  var orig=btn.textContent;
  function ok(){
    btn.textContent={$copiedJs};btn.className='copybtn ok';
    setTimeout(function(){btn.textContent=orig;btn.className='copybtn';},2000);
  }
  // HTTP(비보안 컨텍스트)라 navigator.clipboard 가 보통 없음 → execCommand(동기) 가 주경로.
  // 동기라 직후 앵커 내비게이션이 일어나도 복사는 완료된다. iOS WKWebView 호환:
  // readonly textarea + select + setSelectionRange 패턴.
  function doCopy(){
    var done=false;
    try{
      var ta=document.createElement('textarea');
      ta.value=txt;ta.setAttribute('readonly','');
      ta.style.position='fixed';ta.style.left='-9999px';ta.style.fontSize='16px';
      document.body.appendChild(ta);
      ta.focus();ta.select();
      try{ta.setSelectionRange(0,txt.length);}catch(e){}
      done=document.execCommand('copy');
      document.body.removeChild(ta);
    }catch(e){}
    if(!done&&navigator.clipboard&&navigator.clipboard.writeText){
      try{navigator.clipboard.writeText(txt);done=true;}catch(e){}
    }
    return done;
  }
  btn.addEventListener('click',function(){
    var copied=doCopy();
    // 앵커(href 보유, 안내 페이지)면 기본 내비게이션이 뒤따른다 → ack 페이지로 이동.
    // 버튼(href 없음, 완료 페이지)이면 라벨만 "복사됨!"으로 토글.
    if(copied && !btn.getAttribute('href')){ok();}
  });
})();
</script>
JS;
}

// 공용 HTML 응답 전송. Content-Length 명시: spawn-fcgi(fastcgi_finish_request 부재) 환경에서
// 연결 종료 대기로 인한 지연 방지 (cp_splash_redirect 와 동일 패턴)
function cp_cna_send_html(string $body): void {
	while (ob_get_level() > 0) {
		@ob_end_clean();
	}
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

// ── #31 전체 기능 게이트 (CNA 안내 페이지 + 주소 복사 + ack 자동닫힘 시도) ──────────
// 기본 OFF = 이 스레드 이전의 "원래 동작" 복원:
//   · OS 캡티브 탐지 프로브 → 302 로그인 리다이렉트 (아래 $connectedSession==='' 블록)
//   · 미인증 GET → 로그인 페이지
//   · 세션도 원래대로 항상 생성 (프로브 포함)
// 켜려면(=안내/복사/자동닫힘 전부 활성):  박스에서  touch /tmp/cp_cna_guide.on
//   → 끄려면  rm /tmp/cp_cna_guide.on  (코드/함수는 그대로 유지 — 이 플래그만으로 런타임
//   동작을 통째로 on/off. 파일이 없으면 OFF = 원복.)
// (이유: S20 Ultra 등 삼성 CNA 가 ① 페이지 내 내비게이션을 차단(ack 미발화) + ② 창 닫힘을
//  HTTPS 검증에 의존(스푸핑 불가) → 자동닫힘이 주력 기종에서 동작 불가. 결정 전까지 OFF.)
define('CP_CNA_GUIDE_ENABLED', @file_exists('/tmp/cp_cna_guide.on'));

// OS 프로브/CNA 요청(ack 포함)은 쿠키(PHPSESSID) 미반환이라 세션 무의미 → session_start 를
// 건너뛰어 sess_* 누적 차단(#24~26 후속). 단 플래그 OFF 면 두 상수 모두 false → 원래대로
// 항상 session_start (프로브도 세션 생성).
define('CP_IS_OS_PROBE', CP_CNA_GUIDE_ENABLED && cp_detect_os_probe());
define('CP_IS_CNA_ACK', CP_CNA_GUIDE_ENABLED && cp_detect_cna_ack());
if (!CP_IS_OS_PROBE && !CP_IS_CNA_ACK && session_status() !== PHP_SESSION_ACTIVE) {
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

// #31 (절충안): OS 캡티브 탐지/CNA(미니 브라우저) 요청 → 로그인 폼 대신 "기본 브라우저로
// 접속하라"는 안내만 렌더. (과거: 302 → loginurl → CNA 안에서 로그인 → 성공 즉시 OS 가
// 창을 닫아 connected 페이지를 못 보던 문제. 함수 주석 참고.)
// 인증된 클라이언트의 프로브는 방화벽을 직접 통과(포털 미경유)하므로 이 분기에 오지 않는다.
// 포털 루트(/)는 zone 기본값 crew 로 index.php 가 처리하므로 짧은 주소만 안내한다.
if (CP_IS_CNA_ACK) {
	// "주소 복사" 버튼의 ack 내비게이션: 마커 기록 + 완료 페이지.
	// 완료 페이지 "로드 완료" → CNA 재검증 프로브 → (마커) 성공 응답 → OS 가 창 자동 닫음.
	cp_cna_ack_set($clientip);
	cp_render_cna_done("{$protocol}{$ourhostname}");
}
if (CP_IS_OS_PROBE) {
	if (cp_cna_ack_active($clientip)) {
		// ack 이후의 OS 탐지 프로브 → 기대 성공 응답(204/Success 등) = captive 해제.
		// (Android "현재 상태로 이 네트워크 사용" 메뉴 탐색 불필요 — 창이 스스로 닫힘)
		cp_probe_success_response();
	}
	cp_render_cna_guide("{$protocol}{$ourhostname}");
	// 각 렌더 함수가 출력 후 exit 한다.
}

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

// Case 2: 정확 일치(IP+MAC) 실패 시, 동일 MAC·다른 IP 세션을 신IP 로 마이그레이션한다.
// (DHCP 등으로 IP 만 바뀐 같은 기기 → 재인증 없이 자동 로그인)
// quota 초과 사용자는 마이그레이션 거부 → 아래 로그인 프롬프트로 떨어진다.
if ($connectedSession === '' && $macfilter && !empty($clientmac)) {
	$migrated = captiveportal_try_migrate_session_by_mac($clientip, $clientmac);
	if (is_array($migrated) && array_key_exists(5, $migrated)) {
		$connectedSession = (string)$migrated[5];
		$connectedUser = (string)($migrated[4] ?? '');
	}
}

if ($connectedSession==='') {
	// #31 OFF(기본) 시 원래 동작: OS 캡티브 탐지 프로브는 로그인 URL 로 302 리다이렉트.
	//   (#31 ON 이면 프로브는 위 CP_IS_OS_PROBE 분기에서 안내 페이지로 단락되어 여기 도달 안 함.)
	if (!CP_CNA_GUIDE_ENABLED && cp_detect_os_probe()) {
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

