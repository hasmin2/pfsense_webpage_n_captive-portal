<?php
/*
 * gmt_history_data.php (#48) — GMT time_offset 변경 이력 조회 JSON 엔드포인트
 *
 * 사이드바 "GMT n" 옆 history 버튼 모달(common_ui.inc print_sidebar)이 AJAX 로 호출.
 * 인증은 guiconfig.inc(관리 세션) 경유 — 미인증이면 로그인 페이지로 리다이렉트되어
 * JSON 파싱 실패 → 모달이 오류 메시지 표시(무해).
 *
 * 파라미터(POST):
 *   mode=days   + days=1|7|30 (기본; 최근 N일)
 *   mode=custom + from=YYYY-MM-DD + to=YYYY-MM-DD (UTC 날짜 — 저장 timestamp 가 박스 UTC)
 *
 * 응답: {ok, rows:[{id,timestamp,timefrom,timeto}...], from, to}
 *   ok=false = 라이브러리 미배포(버전섞임) 또는 DB 불통 — fatal 없음.
 */

require_once('guiconfig.inc');

header('Content-Type: application/json');
$resp = array('ok' => false, 'rows' => array(), 'from' => '', 'to' => '');

// 버전섞임 가드: 헬퍼 미배포면 ok=false 만 반환
if (!function_exists('cp_gmt_history_fetch') && file_exists('/etc/inc/cp_gmt_history.inc')) {
    require_once('/etc/inc/cp_gmt_history.inc');
}
if (!function_exists('cp_gmt_history_fetch')) {
    echo json_encode($resp);
    exit;
}

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
    $days = isset($_POST['days']) ? (int)$_POST['days'] : 1;
    if ($days < 1) { $days = 1; }
    if ($days > 3660) { $days = 3660; }
    $from = gmdate('Y-m-d H:i:s', time() - $days * 86400);
    $to   = gmdate('Y-m-d H:i:s');
}

$rows = cp_gmt_history_fetch($from, $to);
if ($rows !== false) {
    $resp['ok']   = true;
    $resp['rows'] = $rows;
    $resp['from'] = $from;
    $resp['to']   = $to;
}
echo json_encode($resp);
exit;
