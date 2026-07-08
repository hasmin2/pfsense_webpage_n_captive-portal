<?php
require_once("common_ui.inc");
require_once("terminal_status.inc");
require_once('guiconfig.inc');
global $config, $g;
$vesselinfo = $config['system']['vesselinfo'];
// #57 변경 이력(radius.terminal_status_history) — 버전섞임 가드(미배포 시 record()가 조용히 skip)
if (!function_exists('cp_terminal_history_record') && file_exists('/etc/inc/cp_terminal_history.inc')) {
    require_once('/etc/inc/cp_terminal_history.inc');
}
/*
 * 폼 제출(Manual Override 의 routing_radiobutton 또는 Data Cutoff 의 allowance)을 처리한
 * 뒤에는 같은 URL 로 302 가 아니라 JS location.replace 로 리다이렉트한다(Post/Redirect/Get).
 * 안 그러면 브라우저 히스토리 맨 위가 POST 응답으로 남아 F5 시 "양식 다시 제출 확인" 경고가
 * 뜬다. network_control.php 가 이미 쓰는 관례(processing.php 스플래시 경유, location.replace
 * 는 히스토리 항목을 대체 — POST 가 히스토리에서 사라짐)를 그대로 재사용.
 * data_update(10초 폴링) 는 별도 AJAX 호출이라 이 분기와 무관.
 */
$didProcessPost = false;
$historyDescs = array();
if ($_POST['routing_radiobutton']) {
    set_routing($_POST['routing_radiobutton'], $_POST['routeduration']);
    $didProcessPost = true;
    $historyDescs[] = ($_POST['routing_radiobutton'] === 'automatic')
        ? 'Manual Override: routing set to Automatic'
        : 'Manual Override: routing set to ' . $_POST['routing_radiobutton']
            . ' (duration=' . $_POST['routeduration'] . 'm)';
}
if (isset($_POST['allowance']) && is_array($_POST['allowance'])) {
    $cutoffChangeLog = array();
    cp_apply_gateway_cutoff_settings($_POST['allowance'], isset($_POST['cutoff_enable']) ? $_POST['cutoff_enable'] : array(), $cutoffChangeLog);
    $didProcessPost = true;
    if (!empty($cutoffChangeLog)) {
        $historyDescs[] = 'Data Cutoff: ' . implode(' | ', $cutoffChangeLog);
    }
}
if ($didProcessPost) {
    if (!empty($historyDescs) && function_exists('cp_terminal_history_record')) {
        cp_terminal_history_record(implode(' / ', $historyDescs));
    }
    echo '<script>location.replace("processing.php?to=terminal.php");</script>';
    exit;
}

$gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);
/*
 * 게이트웨이(안테나)별 상태 셀 데이터. 예전에는 tbody 전체를 HTML 문자열로 만들어
 * 10초마다 통째로 덮어썼는데, Allowance 입력/Cutoff 체크박스를 같은 행에 합치면서
 * 그 방식으로는 편집 중인 값이 폴링마다 리셋된다. 그래서 상태가 바뀌는 셀(Info/GW/
 * Net/Ext-Net)만 게이트웨이 이름으로 식별해 부분 갱신하도록 구조화했다 — Name/
 * Allowance/Cutoff 셀은 애초에 건드리지 않는다.
 */
$rowData = array();
foreach ($gateways as $gname => $gateway) {
    if (startswith($gateway['terminal_type'], 'vpn')) {
        continue;
    }
    $defaultgw = get_defaultgw($gateway);
    foreach ($config['interfaces'] as $ifname => $ifcfg) {
        if ($gateways[$gname]['interface'] !== $ifcfg['if']) {
            continue;
        }
        // 사용량은 allowance 설정/terminal_type 과 무관하게 항상 표시(Main Panel index.php 와
        // 동일 정책 — 그쪽은 allowance 없어도 usage 숫자는 항상 보여주고 "/allowance" 만 조건부).
        // get_datausage_from_db() 실패(InfluxDB 타임아웃 등) 시 false 반환 → strval 로 빈 문자열.
        $usageText = strval(get_datausage_from_db($ifcfg['if']));
        $netStatus = get_net_status($gateways_status[$gname]);
        $extnetStatus = get_extnet_status($gateways_status[$gname]);
        $rowData[$gname] = array(
            'row_on'       => ($defaultgw==1),
            'monitor'      => $gateway['monitor'],
            'usage_text'   => $usageText,
            'gw_html'      => ($defaultgw ? get_routingduration() : '').'<br><span>'.get_speed_from_db($ifcfg['if']).'</span>',
            'net_class'    => $netStatus[0],
            'net_text'     => $netStatus[1],
            'extnet_class' => $extnetStatus[0],
            'extnet_text'  => $extnetStatus[1],
        );
        break;
    }
}

if ($_POST['data_update']) {
    echo json_encode(array('rows' => $rowData));
    exit(0);
}
?>
<!DOCTYPE HTML>
<html lang="ko">
<head>
    <?php echo print_css_n_head(); ?>
    <style>
        /* Info 셀의 "usage / allowance" 를 한 줄로 붙여 보여주기 위해 이 페이지에서만
           allowance 입력을 전역 input[type=text] 의 block/100% 폭에서 인라인 소형 박스로
           덮어씀(전역 style.css 무수정 — 다른 페이지 영향 없음). */
        #all_terminal_status input[name^="allowance"] {
            display: inline-block;
            width: 80px;
            height: 26px;
            padding: 0 6px;
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div id="wrapper">
    <?php echo print_sidebar( basename($_SERVER['PHP_SELF']));?>
    <div id="content">
        <div class="headline-wrap">
            <div class="title-area">
                <p class="headline">Terminal Status</p>
            </div>

            <div class="etc-area">
                <button class="btn-setting" onclick="popOpenAndDim('pop-set-terminal', true)">Setting</button>
            </div>
        </div>

        <div class="contents">
            <div class="container">
                <div class="terminal-wrap">
                    <div class="list-wrap v1">
                        <div class="sort-area">
                            <div class="inner">
                                <select name="" id="" class="select v1">
                                    <option value="">Name</option>
                                    <option value="">Info</option>
                                    <option value="">GW</option>
                                    <option value="">Net</option>
                                    <option value="">Ext-Net</option>
                                    <option value="">Cutoff</option>
                                </select>
                                <button class="btn-ic btn-sort"></button>
                            </div>
                        </div>
                        <form action="/terminal.php" method="post" id="cutoff_form">
                        <table>
                            <colgroup>
                                <col style="width: 17%;">
                                <col style="width: 30%;">
                                <col style="width: 17%;">
                                <col style="width: 13%;">
                                <col style="width: 13%;">
                                <col style="width: 10%;">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Name<button class="btn-ic btn-sort"></button></th>
                                <th>Info (Usage / Monthly Allowance GB)<button class="btn-ic btn-sort"></button></th>
                                <th>GW<button class="btn-ic btn-sort"></button></th>
                                <th>Net<button class="btn-ic btn-sort"></button></th>
                                <th>Ext-Net<button class="btn-ic btn-sort"></button></th>
                                <th>Cutoff</th>
                            </tr>
                            </thead>
                            <tbody id="all_terminal_status">
                                <?php foreach ($rowData as $gname => $d):
                                    $gid = htmlspecialchars($gname);
                                    $gateway = $gateways[$gname];
                                    ?>
                                <tr data-gw="<?php echo($gid); ?>" class="<?php echo($d['row_on'] ? 'on' : ''); ?>">
                                    <td data-th="Name" data-th-width="100" data-width="100">
                                        <?php echo($gid); ?><br>
                                        <span><?php echo(htmlspecialchars($d['monitor'])); ?></span>
                                    </td>
                                    <td data-th="Info" data-th-width="100" data-width="100">
                                        <span class="cell-info-usage"><?php echo(htmlspecialchars($d['usage_text'])); ?></span> /
                                        <input type="text" name="allowance[<?php echo($gid); ?>]" value="<?php echo(htmlspecialchars($gateway['allowance'] ?? '')); ?>" placeholder="Blank = unlimited"> GB
                                    </td>
                                    <td data-th="GW" data-th-width="100" data-width="100" class="cell-gw">
                                        <?php echo($d['gw_html']); ?>
                                    </td>
                                    <td data-th="Net" data-th-width="100" data-width="100" class="cell-net">
                                        <p class="<?php echo($d['net_class']); ?>"><?php echo($d['net_text']); ?></p>
                                    </td>
                                    <td data-th="Ext-Net" data-th-width="100" data-width="100" class="cell-extnet">
                                        <p class="<?php echo($d['extnet_class']); ?>"><?php echo($d['extnet_text']); ?></p>
                                    </td>
                                    <td data-th="Cutoff" data-th-width="100" data-width="100">
                                        <div class="check v1">
                                            <input type="checkbox" name="cutoff_enable[<?php echo($gid); ?>]" id="cutoff_<?php echo($gid); ?>" value="1" <?php echo(!empty($gateway['cutoff_enable']) ? 'checked' : ''); ?>>
                                            <label for="cutoff_<?php echo($gid); ?>" style="white-space: nowrap;">
                                                <p>Enabled</p>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="btn-area mt20" style="text-align: right;">
                            <button type="button" class="btn md fill-dark" id="termhist-btn"><i class="ic-reset"></i>HISTORY</button>
                            <button type="submit" class="btn md fill-mint"><i class="ic-submit"></i>APPLY</button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="popup layer pop-set-terminal">
    <div class="pop-head">
        <p class="title">Terminal Setting</p>
    </div>
    <div class="pop-cont">
        <form action="/terminal.php" method="post" id="override_form">
        <p class="tit v1">Manual Override</p>
        <div class="override-list scroll-y">
            <ul>
                <li>
                    <div class="radio v1">

                        <input type="radio" name="routing_radiobutton" id="automatic" value="automatic" >
                        <label for="automatic">
                            <p class="txt-mint">Automatic</p>
                        </label>
                    </div>
                </li>
                <?php
                $gateways = return_gateways_array();
                foreach ($gateways as $gname => $gateway):
                    if (!startswith($gateway['terminal_type'], 'vpn') ):
                        ?>
                        <li>
                            <div class="radio v1">
                                <input type="radio" name="routing_radiobutton"  value="<?php echo($gname);?>" id="<?php echo($gname);?>">
                                <label for="<?php echo($gname);?>">
                                    <p><?php echo($gname);?></p>
                                </label>
                            </div>
                        </li>
                    <?php
                    endif;
                endforeach;
                ?>
            </ul>
        </div>
        <hr class="line v1 mt30">

        <p class="tit v1 mt30">Time duration</p>

        <select name="routeduration" id="routeduration" class="select v1 mt10">
            <option value="5">5 minutes</option>
            <option value="60">60 minutes</option>
            <option value="300">5 hours</option>
            <option value="86400">1 day</option>
            <option value="864000000">Permanent</option>
        </select>
    </div>
    <div class="pop-foot">
        <button type="submit" class="btn md fill-mint" onclick="popClose('pop-set-terminal')"><i class="ic-submit"></i>APPLY</button>
        <button type="button" class="btn md fill-dark" onclick="popClose('pop-set-terminal')"><i class="ic-cancel"></i>CANCEL</button>
    </div>
    </form>
</div>

<!-- 20241223 수정 -->
<div class="popup layer pop-login">
    <div class="pop-head">
        <p class="title">Login</p>
    <div class="pop-cont">
        <div class="form">
            <div class="form-tit">
                <p class="tit">ID</p>
            </div>
            <div class="form-cont">
                <input type="text" name="" id="">
            </div>
        </div>
        <div class="form mt20">
        </div>
            <div class="form-tit">
                <p class="tit">Password</p>
            </div>
            <div class="form-cont">
                <input type="text" name="" id="">
            </div>
        </div>
    </div>
    <div class="pop-foot">
        <button class="btn md fill-mint" onclick="popClose('pop-login')"><i class="ic-submit"></i>LOGIN</button>
        <button class="btn md fill-dark" onclick="popClose('pop-login')"><i class="ic-cancel"></i>CANCEL</button>
    </div>
</div>
<!--// 20241223 수정 -->

<!-- ---- Terminal Status 변경 이력 모달 (#57, radius.terminal_status_history) ----
     GMT 변경 이력 모달(common_ui.inc, gmthist-*)과 동일 계열 스타일(다크 카드 + pill 버튼) —
     자체 색 고정이라 라이트/다크 테마 모두 무관. ID/클래스는 termhist-* 로 격리(gmthist-* 와 충돌 없음). -->
<style>
#termhist-ov{position:fixed;inset:0;background:rgba(8,12,20,.74);z-index:10001;display:none;align-items:center;justify-content:center;}
#termhist-ov.on{display:flex;}
.termhist-modal{width:min(820px,96vw);max-height:88vh;overflow:auto;background:#0d1726;border:1px solid #24405f;border-radius:14px;padding:18px 20px;box-shadow:0 18px 60px rgba(0,0,0,.55);}
.termhist-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.termhist-head h3{margin:0;font-size:16px;font-weight:800;color:#f2f7ff;}
.termhist-x{background:none;border:0;color:#8ca6c6;font-size:22px;line-height:1;cursor:pointer;padding:0 2px;}
.termhist-x:hover{color:#fff;}
.termhist-range{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;}
.termhist-range .th-btn{height:28px;padding:0 12px;border-radius:7px;font-size:12px;font-weight:700;background:#152238;border:1px solid #2c4363;color:#b9cbe4;cursor:pointer;}
.termhist-range .th-btn.on{background:#1976d2;border-color:#1976d2;color:#fff;}
.termhist-range .th-export{margin-left:auto;background:#12301f;border-color:#2f6b4f;color:#9fe0bd;}
.termhist-range .th-export:disabled{opacity:.45;cursor:default;}
.termhist-custom{display:none;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap;}
.termhist-custom input[type=date]{height:28px;padding:0 8px;border-radius:7px;background:#0a192f;border:1px solid #2c4363;color:#e7eefc;font-size:12px;color-scheme:dark;}
.termhist-custom .th-sep{color:#7e95b4;font-size:12px;}
.termhist-apply{height:28px;padding:0 14px;border-radius:7px;font-size:12px;font-weight:700;background:#1976d2;border:1px solid #1976d2;color:#fff;cursor:pointer;}
.termhist-tbl{width:100%;border-collapse:collapse;font-size:12px;}
.termhist-tbl th{color:#9fb6d4;text-align:left;font-weight:700;padding:6px 8px;border-bottom:1px solid #2c4363;}
.termhist-tbl td{color:#e7eefc;padding:6px 8px;border-bottom:1px solid #1a2c49;}
.termhist-tbl td.th-desc{color:#b9cbe4;}
.termhist-tbl td.th-id, .termhist-tbl td.th-ip{color:#9fb6d4;white-space:nowrap;}
.termhist-msg{color:#cdd9ea;text-align:center;padding:32px 12px;font-size:13px;line-height:1.6;margin:0;}
.termhist-note{margin:10px 0 0;font-size:11px;color:#7e95b4;}
.termhist-pager{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:12px;}
.termhist-pager .th-page{height:26px;padding:0 12px;border-radius:7px;font-size:12px;font-weight:700;background:#152238;border:1px solid #2c4363;color:#b9cbe4;cursor:pointer;}
.termhist-pager .th-page:disabled{opacity:.4;cursor:default;}
.termhist-pager .th-pageinfo{font-size:12px;color:#9fb6d4;white-space:nowrap;}
</style>
<div id="termhist-ov" role="dialog" aria-modal="true" aria-label="Terminal Status change history">
    <div class="termhist-modal">
        <div class="termhist-head">
            <h3>Terminal Status change history</h3>
            <button type="button" class="termhist-x" id="termhist-x" aria-label="Close">&times;</button>
        </div>
        <div class="termhist-range" id="termhist-range">
            <button type="button" class="th-btn on" data-days="1">1d</button>
            <button type="button" class="th-btn" data-days="7">7d</button>
            <button type="button" class="th-btn" data-days="30">30d</button>
            <button type="button" class="th-btn" data-custom="1">Custom</button>
            <button type="button" class="th-btn th-export" id="termhist-export" disabled>Export CSV</button>
        </div>
        <div class="termhist-custom" id="termhist-custom">
            <input type="date" id="termhist-from"> <span class="th-sep">~</span> <input type="date" id="termhist-to">
            <button type="button" class="termhist-apply" id="termhist-apply">Apply</button>
        </div>
        <div id="termhist-body"></div>
        <p class="termhist-note">&#9432; Manual Override / Data Cutoff change log (times in UTC).</p>
    </div>
</div>
<!--// Terminal Status 변경 이력 모달 -->
</body>
<script>
    function refreshValue() {
        $.ajax({
            url: "./terminal.php",
            data: {data_update: "true"},
            type: 'POST',
            dataType: 'json',
            success: function (result) {
                $.each(result.rows, function (gwname, d) {
                    var $row = $("#all_terminal_status tr[data-gw='" + gwname + "']");
                    if ($row.length === 0) {
                        return;
                    }
                    $row.toggleClass('on', !!d.row_on);
                    $row.find('.cell-info-usage').text(d.usage_text);
                    $row.find('.cell-gw').html(d.gw_html);
                    $row.find('.cell-net p').attr('class', d.net_class).text(d.net_text);
                    $row.find('.cell-extnet p').attr('class', d.extnet_class).text(d.extnet_text);
                });
            }})
    }
        setInterval(refreshValue, 10000); // 밀리초 단위이므로 5초는 5000밀리초
</script>
<script>
    // Terminal Status 변경 이력 모달 (#57) — GMT 변경 이력 모달(common_ui.inc)과 동일 구조,
    // termhist-* 네임스페이스로 격리. CSRF 토큰은 이 페이지의 #cutoff_form 에 자동 주입된
    // __csrf_magic hidden 을 재사용(별도 폼 불필요).
    (function(){
        var btn = document.getElementById("termhist-btn");
        var ov  = document.getElementById("termhist-ov");
        if (!btn || !ov) { return; }
        var body   = document.getElementById("termhist-body");
        var custom = document.getElementById("termhist-custom");
        var fromI  = document.getElementById("termhist-from");
        var toI    = document.getElementById("termhist-to");
        var pills  = document.getElementById("termhist-range").querySelectorAll(".th-btn[data-days], .th-btn[data-custom]");
        var expB   = document.getElementById("termhist-export");
        var lastRows = [];   // 현재 표시 중인 조회 결과(= CSV export 대상)
        var PAGE_SIZE = 10;
        var curPage = 1;

        function esc(s){
            return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
        }
        function csrfToken(){
            var el = document.querySelector("#cutoff_form input[name=__csrf_magic]");
            return el ? el.value : "";
        }
        function render(rows){
            lastRows = rows || [];
            if (expB) { expB.disabled = (lastRows.length === 0); }
            if (!lastRows.length) {
                body.innerHTML = "<p class=\"termhist-msg\">No changes in this period.</p>";
                return;
            }
            curPage = 1;
            renderPage();
        }
        function renderPage(){
            var total = lastRows.length;
            var pages = Math.ceil(total / PAGE_SIZE) || 1;
            if (curPage > pages) { curPage = pages; }
            if (curPage < 1)     { curPage = 1; }
            var start = (curPage - 1) * PAGE_SIZE;
            var slice = lastRows.slice(start, start + PAGE_SIZE);

            var h = "<table class=\"termhist-tbl\"><thead><tr><th>Time (UTC)</th><th>ID</th><th>IP</th><th>Description</th></tr></thead><tbody>";
            for (var i = 0; i < slice.length; i++) {
                var r = slice[i];
                h += "<tr><td>" + esc(r.timestamp) + "</td>"
                   + "<td class=\"th-id\">" + esc(r.admin_id == null || r.admin_id === "" ? "(unknown)" : r.admin_id) + "</td>"
                   + "<td class=\"th-ip\">" + esc(r.client_ip == null || r.client_ip === "" ? "-" : r.client_ip) + "</td>"
                   + "<td class=\"th-desc\">" + esc(r.description == null ? "" : r.description) + "</td></tr>";
            }
            h += "</tbody></table>";

            if (pages > 1) {
                h += "<div class=\"termhist-pager\">"
                   + "<button type=\"button\" class=\"th-page\" id=\"termhist-prev\"" + (curPage <= 1 ? " disabled" : "") + ">&lsaquo; Prev</button>"
                   + "<span class=\"th-pageinfo\">Page " + curPage + " / " + pages + " &middot; " + total + " total</span>"
                   + "<button type=\"button\" class=\"th-page\" id=\"termhist-next\"" + (curPage >= pages ? " disabled" : "") + ">Next &rsaquo;</button>"
                   + "</div>";
            }
            body.innerHTML = h;

            var pv = document.getElementById("termhist-prev");
            var nx = document.getElementById("termhist-next");
            if (pv) { pv.addEventListener("click", function(){ if (curPage > 1)     { curPage--; renderPage(); } }); }
            if (nx) { nx.addEventListener("click", function(){ if (curPage < pages) { curPage++; renderPage(); } }); }
        }
        function load(params){
            body.innerHTML = "<p class=\"termhist-msg\">Loading&hellip;</p>";
            lastRows = [];
            if (expB) { expB.disabled = true; }
            var tok = csrfToken();
            if (tok) { params.__csrf_magic = tok; }
            var q = [];
            for (var k in params) { q.push(encodeURIComponent(k) + "=" + encodeURIComponent(params[k])); }
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "/terminal_history_data.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function(){
                if (xhr.readyState !== 4) { return; }
                var d = null;
                try { d = JSON.parse(xhr.responseText); } catch(e) {}
                if (!d || !d.ok) {
                    body.innerHTML = "<p class=\"termhist-msg\">History unavailable (database unreachable).</p>";
                    return;
                }
                render(d.rows);
            };
            xhr.send(q.join("&"));
        }
        function setOn(b){
            for (var i = 0; i < pills.length; i++) { pills[i].classList.remove("on"); }
            b.classList.add("on");
        }
        for (var i = 0; i < pills.length; i++) {
            (function(b){
                b.addEventListener("click", function(){
                    setOn(b);
                    if (b.getAttribute("data-custom")) { custom.style.display = "flex"; return; }
                    custom.style.display = "none";
                    load({ mode: "days", days: b.getAttribute("data-days") });
                });
            })(pills[i]);
        }
        var applyB = document.getElementById("termhist-apply");
        if (applyB) {
            applyB.addEventListener("click", function(){
                if (!fromI.value || !toI.value) { return; }
                load({ mode: "custom", from: fromI.value, to: toI.value });
            });
        }
        // Export CSV — 현재 표시 중인 결과를 그대로 다운로드(클라이언트 생성, BOM = Excel 호환)
        function csvField(v){
            v = String(v == null ? "" : v);
            return (/[",\r\n]/.test(v)) ? "\"" + v.replace(/"/g, "\"\"") + "\"" : v;
        }
        if (expB) {
            expB.addEventListener("click", function(){
                if (!lastRows.length) { return; }
                var lines = ["id,timestamp_utc,admin_id,client_ip,description"];
                for (var i = 0; i < lastRows.length; i++) {
                    var r = lastRows[i];
                    lines.push([r.id, r.timestamp,
                        (r.admin_id == null ? "" : r.admin_id),
                        (r.client_ip == null ? "" : r.client_ip),
                        (r.description == null ? "" : r.description)].map(csvField).join(","));
                }
                var csv = String.fromCharCode(0xFEFF) + lines.join("\r\n") + "\r\n";
                var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
                var a = document.createElement("a");
                var ts = new Date().toISOString().replace(/[-:]/g, "").replace("T", "_").slice(0, 15);
                a.href = URL.createObjectURL(blob);
                a.download = "terminal_history_" + ts + ".csv";
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(function(){ URL.revokeObjectURL(a.href); }, 1000);
            });
        }
        function openOv(){
            var today = new Date().toISOString().slice(0, 10);
            if (fromI && !fromI.value) { fromI.value = today; }
            if (toI && !toI.value)     { toI.value = today; }
            setOn(pills[0]);
            custom.style.display = "none";
            ov.classList.add("on");
            load({ mode: "days", days: "1" });
        }
        function closeOv(){ ov.classList.remove("on"); }
        btn.addEventListener("click", function(ev){
            ev.preventDefault();
            openOv();
        });
        document.getElementById("termhist-x").addEventListener("click", closeOv);
        ov.addEventListener("click", function(ev){ if (ev.target === ov) { closeOv(); } });
        document.addEventListener("keydown", function(ev){
            if (ev.key === "Escape" && ov.classList.contains("on")) { closeOv(); }
        });
    })();
</script>
</html>
