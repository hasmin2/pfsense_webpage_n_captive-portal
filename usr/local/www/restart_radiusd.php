<?php
// /restart_radiusd.php
header('Content-Type: text/html; charset=UTF-8');

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// 공통 CSS (processing.php와 동일 톤)
function renderPage($title, $icon, $statusClass, $lines, $redirectUrl = '/index.php', $redirectSec = 2) {
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= h($title) ?></title>
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

            :root {
                --bg:        #eef0f3;
                --card:      #ffffff;
                --primary:   #1abc9c;
                --secondary: #2980b9;
                --navy:      #1a2332;
                --text:      #2c3e50;
                --text-dim:  #6c8093;
                --ok:        #1abc9c;
                --fail:      #e74c3c;
                --warn:      #e67e22;
                --mono: 'Courier New', Courier, monospace;
                --sans: 'Trebuchet MS', 'Lucida Sans Unicode', sans-serif;
            }

            body {
                background: var(--bg);
                color: var(--text);
                font-family: var(--mono);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            /* 격자 배경 */
            body::before {
                content: '';
                position: fixed;
                inset: 0;
                background-image:
                        linear-gradient(rgba(26,188,156,0.08) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(26,188,156,0.08) 1px, transparent 1px);
                background-size: 40px 40px;
                animation: gridMove 8s linear infinite;
                pointer-events: none;
            }
            @keyframes gridMove {
                from { transform: translateY(0); }
                to   { transform: translateY(40px); }
            }

            /* 상단 컬러 띠 */
            body::after {
                content: '';
                position: fixed;
                top: 0; left: 0; right: 0;
                height: 3px;
                background: linear-gradient(90deg, var(--navy), var(--primary), var(--secondary));
                pointer-events: none;
            }

            /* 코너 데코 */
            .corner {
                position: fixed;
                width: 36px; height: 36px;
                border-color: rgba(26,188,156,0.3);
                border-style: solid;
            }
            .corner.tl { top:16px; left:16px;   border-width: 1px 0 0 1px; }
            .corner.tr { top:16px; right:16px;  border-width: 1px 1px 0 0; }
            .corner.bl { bottom:16px; left:16px;  border-width: 0 0 1px 1px; }
            .corner.br { bottom:16px; right:16px; border-width: 0 1px 1px 0; }

            /* 카드 */
            .card {
                position: relative;
                background: var(--card);
                border-radius: 10px;
                padding: 2.5rem 3rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 1.5rem;
                z-index: 10;
                box-shadow: 0 2px 6px rgba(26,35,50,0.08), 0 8px 30px rgba(26,35,50,0.07);
                min-width: 380px;
                max-width: 560px;
                width: 90vw;
            }
            .card.ok   { border-top: 3px solid var(--ok); }
            .card.fail { border-top: 3px solid var(--fail); }
            .card.warn { border-top: 3px solid var(--warn); }

            /* 아이콘 뱃지 */
            .badge {
                width: 64px; height: 64px;
                border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-size: 2rem;
            }
            .badge.ok   { background: rgba(26,188,156,0.12); color: var(--ok); }
            .badge.fail { background: rgba(231,76,60,0.1);   color: var(--fail); }
            .badge.warn { background: rgba(230,126,34,0.1);  color: var(--warn); }

            /* 제목 */
            .title {
                font-family: var(--sans);
                font-size: 1.3rem;
                font-weight: 600;
                letter-spacing: 0.15em;
                text-transform: uppercase;
                color: var(--navy);
                text-align: center;
            }

            /* 로그 박스 */
            .logbox {
                width: 100%;
                background: #f4f6f8;
                border: 1px solid rgba(26,188,156,0.15);
                border-radius: 6px;
                padding: 1rem 1.1rem;
                font-size: 0.72rem;
                color: var(--text-dim);
                line-height: 1.7;
                white-space: pre-wrap;
                word-break: break-all;
            }
            .logbox .key  { color: var(--text-dim); }
            .logbox .val  { color: var(--text); }
            .logbox .ok   { color: var(--ok); font-weight: 600; }
            .logbox .fail { color: var(--fail); font-weight: 600; }

            /* 카운트다운 바 */
            .bar-wrap {
                width: 100%;
                background: #e2e5ea;
                border-radius: 2px;
                height: 3px;
                overflow: hidden;
            }
            .bar-fill {
                height: 100%;
                border-radius: 2px;
                background: linear-gradient(90deg, var(--secondary), var(--primary));
                animation: fillBar linear forwards;
            }

            @keyframes fillBar { from { width: 0%; } to { width: 100%; } }

            /* 링크 */
            .back-link {
                font-size: 0.7rem;
                color: var(--text-dim);
                letter-spacing: 0.1em;
                text-decoration: none;
            }
            .back-link:hover { color: var(--primary); }
        </style>
    </head>
    <body>

    <div class="corner tl"></div>
    <div class="corner tr"></div>
    <div class="corner bl"></div>
    <div class="corner br"></div>

    <div class="card <?= h($statusClass) ?>">

        <div class="badge <?= h($statusClass) ?>"><?= $icon ?></div>

        <div class="title"><?= h($title) ?></div>

        <?php if (!empty($lines)): ?>
            <div class="logbox"><?php
                foreach ($lines as $line) {
                    echo $line . "\n";
                }
                ?></div>
        <?php endif; ?>

        <div class="bar-wrap">
            <div class="bar-fill" style="animation-duration: <?= (int)$redirectSec ?>s;"></div>
        </div>

        <a class="back-link" href="<?= h($redirectUrl) ?>">
            ► <?= (int)$redirectSec ?>초 후 이동 &nbsp;|&nbsp; 바로가기
        </a>

    </div>

    <script>
        setTimeout(function() {
            location.replace(<?= json_encode($redirectUrl) ?>);
        }, <?= (int)$redirectSec * 1000 ?>);
    </script>
    </body>
    </html>
    <?php
}

// ── POST 체크 ──────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    renderPage(
        'Method Not Allowed',
        '&#x26A0;',
        'warn',
        ['<span class="key">허용 메서드 :</span> <span class="val">POST only</span>'],
        '/index.php', 2
    );
    exit;
}

// ── 파라미터 체크 ────────────────────────────────────────
if (($_POST['do'] ?? '') !== 'restart_radiusd') {
    http_response_code(400);
    renderPage(
        'Bad Request',
        '&#x2715;',
        'fail',
        ['<span class="key">reason :</span> <span class="val">invalid parameter</span>'],
        '/index.php', 2
    );
    exit;
}

// ── 명령 실행 ────────────────────────────────────────────
$cmd = '/usr/sbin/service radiusd onerestart';

$descriptorspec = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"],
];

$proc = proc_open($cmd, $descriptorspec, $pipes);

if (!is_resource($proc)) {
    http_response_code(500);
    renderPage(
        'Process Error',
        '&#x2715;',
        'fail',
        ['<span class="key">reason :</span> <span class="val">proc_open() failed</span>'],
        '/index.php', 2
    );
    exit;
}

fclose($pipes[0]);
$stdout   = stream_get_contents($pipes[1]); fclose($pipes[1]);
$stderr   = stream_get_contents($pipes[2]); fclose($pipes[2]);
$exitCode = proc_close($proc);

$ok = ($exitCode === 0);

// ── 로그 라인 구성 ───────────────────────────────────────
$lines = [];
$lines[] = '<span class="key">command  :</span> <span class="val">' . h($cmd) . '</span>';
$lines[] = '<span class="key">exitCode :</span> <span class="' . ($ok ? 'ok' : 'fail') . '">' . h($exitCode) . ' — ' . ($ok ? 'SUCCESS' : 'FAILED') . '</span>';

if ($stdout !== '') {
    $lines[] = '';
    $lines[] = '<span class="key">stdout :</span>';
    $lines[] = '<span class="val">' . h(trim($stdout)) . '</span>';
}
if ($stderr !== '') {
    $lines[] = '';
    $lines[] = '<span class="key">stderr :</span>';
    $lines[] = '<span class="' . ($ok ? 'key' : 'fail') . '">' . h(trim($stderr)) . '</span>';
}

if (!$ok) { http_response_code(500); }

renderPage(
    $ok ? 'Radiusd Restarted' : 'Restart Failed',
    $ok ? '&#x2714;' : '&#x2715;',
    $ok ? 'ok' : 'fail',
    $lines,
    '/index.php',
    2
);
?>