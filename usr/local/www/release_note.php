<?php

include_once("auth.inc");
include_once("common_ui.inc");

/**
 * Release Note 페이지 — 릴리스노트 마크다운을 파싱해 사용자 친화 카드로 표시.
 *   소스: usr/local/www/release_note.md (배포 트리 내 사본). 개발 환경에선 repo 루트
 *   RELEASENOTE.md 로 폴백. 둘 다 없으면 안내 메시지.
 *   양식(RELEASENOTE.md): 상단 메타 → "X.Y.Z (YYYY-MM-DD)" 버전 헤더 →
 *   "Beta: ... · Stable: ..." 채널줄 → "- TAG: text"(NEW/CHANGED/FIXED/REMOVED) 불릿
 *   (들여쓰기 연속줄은 직전 불릿에 이어붙임).
 */

function rn_load_release_md() {
    $candidates = array(
        __DIR__ . '/release_note.md',          // 배포 트리 사본 (선상)
        __DIR__ . '/RELEASENOTE.md',           // 혹시 같은 폴더에 둔 경우
        __DIR__ . '/../../../RELEASENOTE.md',  // dev: repo 루트
    );
    foreach ($candidates as $p) {
        if (@is_file($p) && @is_readable($p)) {
            $c = @file_get_contents($p);
            if ($c !== false && trim($c) !== '') { return $c; }
        }
    }
    return '';
}

function rn_parse($md) {
    $lines = preg_split("/\r\n|\r|\n/", $md);
    $header = array();
    $versions = array();
    $cur = null;
    $reVer = '/^(\d+\.\d+(?:\.\d+)?)\s*\(([^)]+)\)\s*$/';

    foreach ($lines as $ln) {
        $t = rtrim($ln);
        $trim = trim($t);

        if (preg_match($reVer, $trim, $m)) {
            if ($cur !== null) { $versions[] = $cur; }
            $cur = array('version' => $m[1], 'date' => $m[2], 'channels' => '', 'items' => array());
            continue;
        }
        if ($cur === null) {
            if ($trim !== '') { $header[] = $trim; }
            continue;
        }
        // 채널줄 (버전 직후, 불릿 시작 전 1회)
        if (stripos($trim, 'Beta:') === 0 && $cur['channels'] === '' && empty($cur['items'])) {
            $cur['channels'] = $trim;
            continue;
        }
        // 불릿 "- TAG: text"
        if (preg_match('/^-\s+([A-Z][A-Z]+):\s*(.*)$/', $t, $bm)) {
            $cur['items'][] = array('tag' => strtoupper($bm[1]), 'text' => trim($bm[2]));
            continue;
        }
        // 들여쓰기 연속줄 → 직전 불릿에 이어붙임
        if ($trim !== '' && !empty($cur['items'])) {
            $n = count($cur['items']) - 1;
            $cur['items'][$n]['text'] = rtrim($cur['items'][$n]['text']) . ' ' . $trim;
            continue;
        }
    }
    if ($cur !== null) { $versions[] = $cur; }
    return array('header' => $header, 'versions' => $versions);
}

$RN = rn_parse(rn_load_release_md());

// 상단 메타 분류: 첫 줄 = 타이틀, 나머지 "Key: value" = 메타
$rn_title = '';
$rn_meta  = array();
foreach ($RN['header'] as $i => $h) {
    if ($i === 0 && strpos($h, ':') === false) { $rn_title = $h; continue; }
    $rn_meta[] = $h;
}
if ($rn_title === '') { $rn_title = 'Release Notes'; }

// 태그 색상: [글자색, 배경색]
$rn_tagColors = array(
    'NEW'     => array('#0f7b46', '#e6f7ee'),
    'CHANGED' => array('#1769aa', '#e7f1fb'),
    'FIXED'   => array('#9a6207', '#fdf3e1'),
    'REMOVED' => array('#b23b3b', '#fbecec'),
);

?>
<!DOCTYPE HTML>
<html lang="ko">

<head>
    <?php echo print_css_n_head(); ?>
    <style>
        .rn-wrap { max-width: 920px; margin: 0 auto; padding: 4px 8px 40px; }
        .rn-intro { color: #6b7785; font-size: 13px; line-height: 1.6; margin: 0 0 22px; }
        .rn-intro .rn-app { display:block; color:#2a3340; font-weight:700; font-size:14px; margin-bottom:3px; }
        .rn-card { background:#fff; border:1px solid #e3e8ee; border-radius:12px;
                   box-shadow: 0 1px 3px rgba(16,24,40,.06); padding: 18px 22px 20px;
                   margin-bottom: 18px; }
        .rn-card-head { display:flex; align-items:center; flex-wrap:wrap; gap:10px;
                        border-bottom:1px solid #eef1f5; padding-bottom:12px; margin-bottom:14px; }
        .rn-ver { font-size:20px; font-weight:800; color:#1f2937; letter-spacing:.3px; }
        .rn-date { font-size:12px; color:#8a94a2; font-weight:600; }
        .rn-latest { font-size:11px; font-weight:700; color:#fff; background:#16a34a;
                     border-radius:999px; padding:2px 9px; letter-spacing:.4px; }
        .rn-chan { width:100%; font-size:12px; color:#7a8595; font-weight:600; margin-top:2px; }
        .rn-list { list-style:none; margin:0; padding:0; }
        .rn-item { display:flex; gap:11px; align-items:flex-start; padding:7px 0;
                   font-size:13.5px; line-height:1.62; color:#37414f; }
        .rn-item + .rn-item { border-top:1px dashed #f0f2f6; }
        .rn-tag { flex:0 0 auto; min-width:72px; text-align:center; font-size:11px; font-weight:800;
                  letter-spacing:.5px; border-radius:6px; padding:3px 8px; margin-top:1px; }
        .rn-empty { background:#fff; border:1px solid #e3e8ee; border-radius:12px; padding:28px;
                    text-align:center; color:#8a94a2; font-size:14px; }
        @media (max-width: 600px) {
            .rn-item { flex-direction:column; gap:5px; }
            .rn-tag { min-width:0; align-self:flex-start; }
        }
    </style>
</head>
<body>
<div id="wrapper">
    <?php echo print_sidebar( basename($_SERVER['PHP_SELF']));?>
    <div id="content">
        <div class="headline-wrap">
            <div class="title-area">
                <p class="headline">Release Note</p>
            </div>
        </div>
        <div class="contents">
            <div class="rn-wrap">
                <p class="rn-intro">
                    <span class="rn-app"><?php echo htmlspecialchars($rn_title, ENT_QUOTES); ?></span>
                    <?php foreach ($rn_meta as $mline) {
                        echo htmlspecialchars($mline, ENT_QUOTES) . '<br>';
                    } ?>
                </p>

                <?php if (empty($RN['versions'])) { ?>
                    <div class="rn-empty">No release notes available.</div>
                <?php } else {
                    foreach ($RN['versions'] as $vi => $v) { ?>
                        <div class="rn-card">
                            <div class="rn-card-head">
                                <span class="rn-ver"><?php echo htmlspecialchars($v['version'], ENT_QUOTES); ?></span>
                                <span class="rn-date"><?php echo htmlspecialchars($v['date'], ENT_QUOTES); ?></span>
                                <?php if ($vi === 0) { echo '<span class="rn-latest">LATEST</span>'; } ?>
                                <?php if ($v['channels'] !== '') { ?>
                                    <span class="rn-chan"><?php echo htmlspecialchars($v['channels'], ENT_QUOTES); ?></span>
                                <?php } ?>
                            </div>
                            <?php if (empty($v['items'])) { ?>
                                <p style="color:#8a94a2;font-size:13px;margin:0;">No detailed changes listed.</p>
                            <?php } else { ?>
                                <ul class="rn-list">
                                    <?php foreach ($v['items'] as $it) {
                                        $tag = $it['tag'];
                                        $col = isset($rn_tagColors[$tag]) ? $rn_tagColors[$tag] : array('#5b6573', '#eef1f5');
                                    ?>
                                        <li class="rn-item">
                                            <span class="rn-tag" style="color:<?php echo $col[0]; ?>;background:<?php echo $col[1]; ?>;"><?php echo htmlspecialchars($tag, ENT_QUOTES); ?></span>
                                            <span class="rn-text"><?php echo htmlspecialchars($it['text'], ENT_QUOTES); ?></span>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </div>
                    <?php }
                } ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
