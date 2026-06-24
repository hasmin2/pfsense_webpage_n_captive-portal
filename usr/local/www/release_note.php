<?php

include_once("auth.inc");
include_once("common_ui.inc");

/**
 * Release Note 페이지 — 릴리스노트 마크다운을 파싱해 사용자 친화 카드로 표시.
 *   소스(단일): usr/local/www/release_note.md (배포 트리 내 — 이 파일만 편집·배포).
 *   없으면 안내 메시지.
 *   양식(RELEASENOTE.md): 상단 메타 → "X.Y.Z (YYYY-MM-DD)" 버전 헤더 →
 *   "Beta: ... · Stable: ..." 채널줄 → "- TAG: text"(NEW/CHANGED/FIXED/REMOVED) 불릿
 *   (들여쓰기 연속줄은 직전 불릿에 이어붙임).
 */

function rn_load_release_md() {
    // 단일 소스: 같은 폴더의 release_note.md (배포 트리). 이 파일만 편집·배포한다.
    $p = __DIR__ . '/release_note.md';
    if (@is_file($p) && @is_readable($p)) {
        $c = @file_get_contents($p);
        if ($c !== false && trim($c) !== '') { return $c; }
    }
    return '';
}

function rn_parse($md) {
    $lines = preg_split("/\r\n|\r|\n/", $md);
    $header = array();
    $versions = array();
    $cur = null;
    $awaitSub = false;   // 버전 헤더 직후 = 하위 버전/채널 줄(자유 양식)을 기다림

    foreach ($lines as $ln) {
        $t = rtrim($ln);
        $trim = trim($t);
        $isBullet  = (bool) preg_match('/^-\s+([A-Z][A-Z]+):\s*(.*)$/', $t, $bm);
        $isHeader  = ($trim !== '' && !$isBullet && rn_is_version_header($trim));

        if ($cur === null) {
            if ($isHeader) {
                list($ver, $date) = rn_split_version_date($trim);
                $cur = array('version' => $ver, 'date' => $date, 'subline' => '', 'items' => array());
                $awaitSub = true;
            } elseif ($trim !== '') {
                $header[] = $trim;
            }
            continue;
        }

        // 버전 헤더 바로 다음의 (불릿 아닌) 첫 줄 = 하위 버전/채널 줄. 예:
        //   "Beta 1.1.40-Beta Stable: 1.1.3-Stable" / "Beta: 1.1.38-Beta (develop) · Stable: ..."
        if ($awaitSub) {
            if ($trim === '') { continue; }
            if (!$isBullet && !$isHeader) { $cur['subline'] = $trim; $awaitSub = false; continue; }
            $awaitSub = false;   // 서브라인 없음 → 정상 처리로 폴백
        }

        // 새 버전 헤더
        if ($isHeader) {
            $versions[] = $cur;
            list($ver, $date) = rn_split_version_date($trim);
            $cur = array('version' => $ver, 'date' => $date, 'subline' => '', 'items' => array());
            $awaitSub = true;
            continue;
        }
        // 불릿 "- TAG: text"
        if ($isBullet) {
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

// 버전 헤더 줄: "X.Y[.Z][-suffix] (날짜)" — 날짜 괄호가 있어야 서브라인과 구분됨
function rn_is_version_header($s) {
    return (bool) preg_match(
        '/^\d+\.\d+(?:\.\d+)?(?:-[A-Za-z0-9]+)?\s*\([^)]+\)\s*$/', trim($s));
}

// 헤더 줄에서 "(날짜)" 분리 → array(version, date)
function rn_split_version_date($s) {
    if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', trim($s), $m)) {
        return array(trim($m[1]), trim($m[2]));
    }
    return array(trim($s), '');
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
                                <?php if (!empty($v['date'])) { ?>
                                    <span class="rn-date"><?php echo htmlspecialchars($v['date'], ENT_QUOTES); ?></span>
                                <?php } ?>
                                <?php if ($vi === 0) { echo '<span class="rn-latest">LATEST</span>'; } ?>
                                <?php if ($v['subline'] !== '') { ?>
                                    <span class="rn-chan"><?php echo htmlspecialchars($v['subline'], ENT_QUOTES); ?></span>
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
