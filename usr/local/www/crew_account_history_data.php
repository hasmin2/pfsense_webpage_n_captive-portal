<?php
/*
 * crew_account_history_data.php (#50) — 한 계정의 변경 이력 조회 JSON 엔드포인트
 *
 * crew_account.php 의 per-user "History" 버튼 모달이 AJAX 로 호출.
 * radius.radacct_changehistory (#49) 를 username + 기간으로 필터해 반환.
 * 인증은 guiconfig.inc(관리 세션) 경유 — 미인증이면 로그인 리다이렉트로 JSON 파싱
 * 실패 → 모달이 오류 메시지 표시(무해).
 *
 * 파라미터(POST):
 *   username=<계정명>  (필수)
 *   mode=days + days=1|7|30  또는  mode=custom + from=YYYY-MM-DD + to=YYYY-MM-DD
 *   기본 = 최근 30일 (계정 변경은 GMT 변경보다 드물어 기본 창을 넓게).
 *
 * 응답: {ok, username, rows:[{id,timestamp,change_type,change_description}], from, to}
 *   ok=false = 라이브러리 미배포(버전섞임)/DB 불통/입력 오류 — fatal 없음.
 */

require_once('guiconfig.inc');

header('Content-Type: application/json');
$resp = array('ok' => false, 'username' => '', 'rows' => array(), 'from' => '', 'to' => '');

// 버전섞임 가드: 헬퍼 미배포면 ok=false
if (!function_exists('cp_account_history_fetch') && file_exists('/etc/inc/cp_account_history.inc')) {
    require_once('/etc/inc/cp_account_history.inc');
}
if (!function_exists('cp_account_history_fetch')) {
    echo json_encode($resp);
    exit;
}

$username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
if ($username === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
    echo json_encode($resp);
    exit;
}
$resp['username'] = $username;

$mode = isset($_POST['mode']) ? (string)$_POST['mode'] : 'days';

if ($mode === 'custom') {
    $fromD = isset($_POST['from']) ? trim((string)$_POST['from']) : '';
    $toD   = isset($_POST['to'])   ? trim((string)$_POST['to'])   : '';
    $reD = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($reD, $fromD) || !preg_match($reD, $toD)) {
        echo json_encode($resp);
        exit;
    }
    if (strcmp($fromD, $toD) > 0) {   // 역순 입력 관용 처리(스왑)
        $t = $fromD; $fromD = $toD; $toD = $t;
    }
    $from = $fromD . ' 00:00:00';
    $to   = $toD . ' 23:59:59';
} else {
    $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
    if ($days < 1) { $days = 1; }
    if ($days > 3660) { $days = 3660; }
    $from = gmdate('Y-m-d H:i:s', time() - $days * 86400);
    $to   = gmdate('Y-m-d H:i:s');
}

$rows = cp_account_history_fetch($username, $from, $to);
if ($rows !== false) {
    $resp['ok']   = true;
    $resp['rows'] = $rows;
    $resp['from'] = $from;
    $resp['to']   = $to;
}
echo json_encode($resp);
exit;
