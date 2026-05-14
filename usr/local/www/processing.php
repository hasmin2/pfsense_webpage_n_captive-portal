<?php
$redirect = isset($_GET['to']) ? $_GET['to'] : 'index.php';
$delay = isset($_GET['delay']) ? (int)$_GET['delay'] : 1000;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #eef0f3;
            --card:     #ffffff;
            --primary:  #1abc9c;
            --secondary:#2980b9;
            --navy:     #1a2332;
            --text:     #2c3e50;
            --text-dim: #6c8093;
            --border:   rgba(26,188,156,0.2);
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

        /* 카드 */
        .card {
            position: relative;
            background: var(--card);
            border-radius: 10px;
            padding: 3rem 3.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.8rem;
            z-index: 10;
            box-shadow:
                    0 2px 6px rgba(26,35,50,0.08),
                    0 8px 30px rgba(26,35,50,0.07);
            border-top: 3px solid var(--primary);
            min-width: 340px;
        }

        /* 헥사곤 스피너 */
        .hex-ring {
            position: relative;
            width: 110px;
            height: 110px;
        }

        .hex-outer {
            position: absolute;
            inset: 0;
            animation: rotateCW 3s linear infinite;
        }

        .hex-inner {
            position: absolute;
            inset: 18px;
            animation: rotateCCW 2s linear infinite;
        }

        .hex-core {
            position: absolute;
            inset: 38px;
            background: var(--primary);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            animation: pulse 1.2s ease-in-out infinite;
            box-shadow: 0 0 16px rgba(26,188,156,0.5);
        }

        @keyframes rotateCW  { to { transform: rotate(360deg); } }
        @keyframes rotateCCW { to { transform: rotate(-360deg); } }

        @keyframes pulse {
            0%, 100% { opacity: 1;   transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(0.82); }
        }

        /* 텍스트 */
        .label {
            font-family: var(--sans);
            font-size: 1.6rem;
            font-weight: 600;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--navy);
        }

        .sublabel {
            font-size: 0.62rem;
            letter-spacing: 0.2em;
            color: var(--text-dim);
            margin-top: -1.3rem;
        }

        /* 프로그레스 바 */
        .bar-wrap {
            width: 260px;
            background: #e2e5ea;
            border-radius: 2px;
            height: 4px;
            position: relative;
            overflow: hidden;
        }

        .bar-wrap::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            transform: translateX(-100%);
            animation: fillBar DELAY_MS cubic-bezier(0.4,0,0.2,1) forwards;
            border-radius: 2px;
        }

        .bar-wrap::after {
            content: '';
            position: absolute;
            top: 0; bottom: 0;
            width: 50px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
            animation: shimmer DELAY_MS linear forwards;
        }

        @keyframes fillBar { to { transform: translateX(0); } }
        @keyframes shimmer { from { left: -50px; } to { left: 100%; } }

        /* 로그 라인 */
        .log {
            font-size: 0.62rem;
            color: var(--text-dim);
            letter-spacing: 0.1em;
            height: 1rem;
            overflow: hidden;
        }

        .log span {
            display: block;
            animation: logCycle DELAY_MS steps(1) forwards;
        }

        @keyframes logCycle {
            0%   { transform: translateY(0);      }
            33%  { transform: translateY(-1rem);  }
            66%  { transform: translateY(-2rem);  }
            100% { transform: translateY(-3rem);  }
        }

        /* 코너 데코 */
        .corner {
            position: fixed;
            width: 36px;
            height: 36px;
            border-color: rgba(26,188,156,0.3);
            border-style: solid;
        }
        .corner.tl { top: 16px; left: 16px;  border-width: 1px 0 0 1px; }
        .corner.tr { top: 16px; right: 16px; border-width: 1px 1px 0 0; }
        .corner.bl { bottom: 16px; left: 16px;  border-width: 0 0 1px 1px; }
        .corner.br { bottom: 16px; right: 16px; border-width: 0 1px 1px 0; }
    </style>
</head>
<body>

<div class="corner tl"></div>
<div class="corner tr"></div>
<div class="corner bl"></div>
<div class="corner br"></div>

<div class="card">

    <div class="hex-ring">
        <svg class="hex-outer" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <polygon points="50,4 96,28 96,72 50,96 4,72 4,28"
                     stroke="#1abc9c" stroke-width="1" stroke-dasharray="6 3" fill="none" opacity="0.7"/>
        </svg>
        <svg class="hex-inner" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <polygon points="50,8 92,30 92,70 50,92 8,70 8,30"
                     stroke="#2980b9" stroke-width="1.5" fill="none" opacity="0.8"/>
        </svg>
        <div class="hex-core"></div>
    </div>

    <div style="text-align:center;">
        <div class="label">Processing</div>
        <div class="sublabel">INITIALIZING SEQUENCE</div>
    </div>

    <div class="bar-wrap"></div>

    <div class="log">
        <span>► AUTHENTICATING SESSION...</span>
        <span>► LOADING USER DATA...</span>
        <span>► REDIRECTING TO PORTAL...</span>
        <span>► DONE</span>
    </div>

</div>

<?php
// delay 값을 CSS에 주입
$css_delay = $delay . 'ms';
?>
<style>
    .bar-wrap::before { animation-duration: <?= $css_delay ?>; }
    .bar-wrap::after  { animation-duration: <?= $css_delay ?>; }
    .log span         { animation-duration: <?= $css_delay ?>; }
</style>

<script>
    setTimeout(function() {
        location.replace("<?= htmlspecialchars($redirect) ?>");
    }, <?= $delay ?>);
</script>
</body>
</html>