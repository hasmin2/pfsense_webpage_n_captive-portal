<?php
// /restart_radiusd.php
header('Content-Type: text/html; charset=UTF-8');

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// POST만 허용
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo "<h3>Method Not Allowed</h3>";
    echo "<meta http-equiv='refresh' content='2;url=/index.php'>";
    echo "<p>2초 후 돌아갑니다. <a href='/index.php'>바로가기</a></p>";
    exit;
}

// 요청 값 체크
if (($_POST['do'] ?? '') !== 'restart_radiusd') {
    http_response_code(400);
    echo "<h3>Bad Request</h3>";
    echo "<meta http-equiv='refresh' content='2;url=/index.php'>";
    echo "<p>2초 후 돌아갑니다. <a href='/index.php'>바로가기</a></p>";
    exit;
}

// 실행 명령
$cmd = '/usr/sbin/service radiusd onerestart';

$descriptorspec = [
    0 => ["pipe", "r"], // stdin
    1 => ["pipe", "w"], // stdout
    2 => ["pipe", "w"], // stderr
];

$proc = proc_open($cmd, $descriptorspec, $pipes);

if (!is_resource($proc)) {
    http_response_code(500);
    echo "<h3>Failed to start process</h3>";
    echo "<meta http-equiv='refresh' content='2;url=/index.php'>";
    echo "<p>2초 후 돌아갑니다. <a href='/index.php'>바로가기</a></p>";
    exit;
}

// stdin 닫기
fclose($pipes[0]);

$stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
$exitCode = proc_close($proc);

// 결과 출력 (텍스트 유지)
$ok = ($exitCode === 0);

echo "<h3>" . ($ok ? "Restart OK" : "Restart FAILED") . "</h3>";
echo "<pre>";
echo "Command : " . h($cmd) . "\n";
echo "exitCode: " . h($exitCode) . "\n\n";

if ($stdout !== '') {
    echo "stdout:\n" . h($stdout) . "\n";
}
if ($stderr !== '') {
    echo "stderr:\n" . h($stderr) . "\n";
}
echo "</pre>";

// 2초 후 이동 (원하면 1로 바꾸세요)
echo "<meta http-equiv='refresh' content='2;url=/index.php'>";
echo "<p>2초 후 돌아갑니다. <a href='/index.php'>바로가기</a></p>";

// 실패면 상태코드도 500으로
if (!$ok) {
    http_response_code(500);
}
?>
