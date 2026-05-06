<?php
require_once ("globals.inc");
require_once ("captiveportal.inc");
require_once ('functions.inc');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function format_bandwidth_limit_html($rawValue) {
    $value = trim((string)$rawValue);

    if ($value === '') {
        return '<span style="color:#111; font-weight:600;">Unlimited</span>';
    }

    return '<span style="color:#ff8a00; font-weight:700;">' . h($value) . ' Kbps</span>';
}

function get_userid_search_keyword() {
    $q = isset($_GET['userid_search']) ? trim((string)$_GET['userid_search']) : '';

    if ($q === '') {
        return '';
    }

    // USERID 검색용 허용 문자만 유지
    $q = preg_replace('/[^0-9a-zA-Z._:-]/', '', $q);

    if (function_exists('mb_substr')) {
        $q = mb_substr($q, 0, 50);
    } else {
        $q = substr($q, 0, 50);
    }

    return $q;
}
function draw_wifi_userid_search_box() {
    $q = get_userid_search_keyword();
    $action = h($_SERVER['PHP_SELF'] ?? 'crew_account.php');
    $order  = isset($_GET['order']) ? (string)$_GET['order'] : '';

    $clearUrl = $action;
    if ($order !== '') {
        $clearUrl .= '?order=' . rawurlencode($order);
    }

    $html = '';
    $html .= '<form method="get" action="' . $action . '" style="display:flex; align-items:flex-end; justify-content:flex-start; gap:8px; flex-wrap:nowrap; margin:0;">';

    if ($order !== '') {
        $html .= '<input type="hidden" name="order" value="' . h($order) . '">';
    }

    $html .= '<div style="display:flex; flex-direction:column; gap:6px; width:370px;">';
    //$html .= '  <label for="userid_search" style="font-weight:600; color:#333; line-height:1;">USERID</label>';
    $html .= '  <input type="text" id="userid_search" name="userid_search" value="' . h($q) . '" placeholder="User ID" ';
    $html .= '         style="height:34px; width:100%; padding:0 10px; border:1px solid #ccc; border-radius:6px; outline:none; box-sizing:border-box;">';
    $html .= '</div>';

    $html .= '<button type="submit" style="height:34px; padding:0 12px; border:1px solid #1976d2; background:#1976d2; color:#fff; border-radius:6px; cursor:pointer; white-space:nowrap;">Search</button>';

    if ($q !== '') {
        $html .= '<a href="' . h($clearUrl) . '" style="display:inline-flex; align-items:center; justify-content:center; height:34px; padding:0 12px; border:1px solid #bbb; background:#fff; color:#333; border-radius:6px; text-decoration:none; white-space:nowrap;">Clear</a>';
    }

    $html .= '</form>';

    return $html;
}

function build_usage_state_meta(array $r) {
    $used     = isset($r['Used(MB)']) ? (float)$r['Used(MB)'] : 0.0;
    $quota    = isset($r['Quota(MB)']) ? (float)$r['Quota(MB)'] : 0.0;
    $reset    = !empty($r['Reset']);
    $modified = (string)($r['Modified'] ?? '');
    $online   = !empty($r['SessionId']);

    $statusKey    = 'normal';
    $statusText   = number_format($used, 2, '.', ',');
    $statusDetail = 'Normal usage';

    if ($reset) {
        $statusKey    = 'init';
        $statusText   = 'Init';
        $statusDetail = 'Initializing...';
    } elseif ($modified !== '') {
        $statusKey    = 'update';
        $statusText   = 'Update';
        $statusDetail = ($modified === 'create')
            ? 'Creating...'
            : 'Updating...';
    } elseif ($quota > 0 && $used > $quota) {
        $statusKey    = 'depleted';
        $statusText   = 'Depleted';
        $statusDetail = 'Quota exceeded';
    }

    $paletteMap = [
        'normal' => [
            'text'         => '#333333',
            'border'       => '#d9d9d9',
            'header_bg'    => '#f6f7f9',
            'header_text'  => '#222222',
            'badge_bg'     => '#f0f0f0',
            'badge_text'   => '#333333',
            'accent'       => '#9aa0a6',
        ],
        'init' => [
            'text'         => '#1f78ff',
            'border'       => '#9ec5ff',
            'header_bg'    => '#eaf3ff',
            'header_text'  => '#0b57d0',
            'badge_bg'     => '#dbeafe',
            'badge_text'   => '#1d4ed8',
            'accent'       => '#1f78ff',
        ],
        'update' => [
            'text'         => '#ff8a00',
            'border'       => '#ffd08a',
            'header_bg'    => '#fff4e8',
            'header_text'  => '#b95f00',
            'badge_bg'     => '#ffedd5',
            'badge_text'   => '#c2410c',
            'accent'       => '#ff8a00',
        ],
        'depleted' => [
            'text'         => '#e5484d',
            'border'       => '#f3b4b7',
            'header_bg'    => '#fff1f2',
            'header_text'  => '#be123c',
            'badge_bg'     => '#ffe4e6',
            'badge_text'   => '#be123c',
            'accent'       => '#e5484d',
        ],
    ];

    $palette = $paletteMap[$statusKey];

    $remaining = $quota - $used;
    if ($remaining < 0) {
        $remaining = 0;
    }

    $periodText = '';
    if (!empty($r['Half'])) {
        $periodText .= (string)$r['Half'] . '-';
    }
    $periodText .= (string)($r['Timerange'] ?? '');

    $usageRate = '-';
    if ($quota > 0) {
        $rate = ($used / $quota) * 100;
        if ($rate < 0) {
            $rate = 0;
        }
        if ($rate >= 100) {
            $rate = 100;
        }
        $usageRate = number_format($rate, 2, '.', ',') . '%';
    }

    $tooltipRows = [
        'ID'          => (string)($r['ID'] ?? ''),
        'Description' => (string)($r['Description'] ?? ''),
        'Type'        => (string)($r['Type'] ?? ''),
        'Period'      => $periodText,
        'State'       => $statusDetail,
        'Remaining'   => number_format($remaining, 2, '.', ',') . ' MB',
        'Usage Rate'  => $usageRate,
        'Online'      => $online ? 'Y' : 'N',
    ];

    if (!empty($r['UsageTooltipExtra']) && is_array($r['UsageTooltipExtra'])) {
        foreach ($r['UsageTooltipExtra'] as $k => $v) {
            $tooltipRows[(string)$k] = (string)$v;
        }
    }

    return [
        'status_key'   => $statusKey,
        'status_text'  => $statusText,
        'status_color' => $palette['text'],
        'quota_text'   => number_format($quota, 0, '.', ',') . 'MB',
        'tooltip'      => $tooltipRows,
        'palette'      => $palette,
    ];
}

function build_usage_tooltip_html(array $meta) {
    $tooltipRows = $meta['tooltip'];
    $p = $meta['palette'];

    $html = '';
    $html .= '<div class="usage-tip-box" style="display:none; position:absolute; top:calc(100% + 8px); left:0; min-width:280px; max-width:380px; padding:0; border:1px solid ' . h($p['border']) . '; border-radius:10px; background:#fff; box-shadow:0 10px 28px rgba(0,0,0,0.14); z-index:9999; text-align:left; overflow:hidden;">';
    $html .= '<div style="height:4px; background:' . h($p['accent']) . ';"></div>';
    $html .= '<div style="position:absolute; top:-6px; left:16px; width:10px; height:10px; background:#fff; border-top:1px solid ' . h($p['border']) . '; border-left:1px solid ' . h($p['border']) . '; transform:rotate(45deg);"></div>';
    $html .= '<div style="padding:10px 12px; background:' . h($p['header_bg']) . '; border-bottom:1px solid ' . h($p['border']) . '; display:flex; align-items:center; justify-content:space-between; gap:8px;">';
    $html .= '<div style="font-weight:700; font-size:12px; color:' . h($p['header_text']) . ';">Usage details</div>';
    $html .= '<span style="display:inline-block; padding:3px 8px; border-radius:999px; background:' . h($p['badge_bg']) . '; color:' . h($p['badge_text']) . '; font-size:11px; font-weight:700;">' . h($meta['status_text']) . '</span>';
    $html .= '</div>';

    $html .= '<div style="padding:10px 12px;">';
    foreach ($tooltipRows as $label => $value) {
        $isStateRow = ($label === 'State');
        $isBandwidthRow = ($label === 'Upload Limit' || $label === 'Download Limit');

        // 일반 행만 빈 값이면 숨김
        if (!$isBandwidthRow && (string)$value === '') {
            continue;
        }

        $html .= '<div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:6px; font-size:12px; align-items:flex-start;">';
        $html .= '<span style="color:#666; flex:0 0 auto;">' . h($label) . '</span>';

        if ($isStateRow) {
            $html .= '<span style="color:' . h($p['badge_text']) . '; background:' . h($p['badge_bg']) . '; padding:2px 7px; border-radius:999px; text-align:right; word-break:break-word; font-weight:700;">' . h($value) . '</span>';
        } elseif ($isBandwidthRow) {
            $html .= '<span style="text-align:right; word-break:break-word;">' . format_bandwidth_limit_html($value) . '</span>';
        } else {
            $html .= '<span style="color:#111; text-align:right; word-break:break-word;">' . h($value) . '</span>';
        }

        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '</div>';
    return $html;
}

function render_usage_state_html(array $r) {
    $meta = build_usage_state_meta($r);
    $tooltipHtml = build_usage_tooltip_html($meta);

    $showJs   = "var t=this.querySelector('.usage-tip-box'); if(t){t.style.display='block';}";
    $hideJs   = "var t=this.querySelector('.usage-tip-box'); if(t){t.style.display='none';}";
    $toggleJs = "event.preventDefault(); event.stopPropagation(); var t=this.parentNode.querySelector('.usage-tip-box'); if(t){ t.style.display=(t.style.display==='block' ? 'none' : 'block'); }";

    $btnBorder = $meta['palette']['border'];
    $btnText   = $meta['palette']['accent'];
    $btnBg     = $meta['palette']['header_bg'];

    $html = '';
    $html .= '<div style="display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">';
    $html .= '  <span>';
    $html .= '      <span style="color:' . h($meta['status_color']) . '; font-weight:700;">' . h($meta['status_text']) . '</span>';
    $html .= '      <span> / ' . h($meta['quota_text']) . '</span>';
    $html .= '  </span>';
    $html .= '  <span style="position:relative; display:inline-block;" onmouseenter="' . h($showJs) . '" onmouseleave="' . h($hideJs) . '">';
    $html .= '      <button type="button" aria-label="Usage details" onclick="' . h($toggleJs) . '" style="width:18px; height:18px; border:1px solid ' . h($btnBorder) . '; border-radius:50%; background:' . h($btnBg) . '; color:' . h($btnText) . '; font-size:11px; line-height:16px; text-align:center; cursor:pointer; padding:0; font-weight:700;">?</button>';
    $html .=        $tooltipHtml;
    $html .= '  </span>';
    $html .= '</div>';

    return $html;
}
function schedule_days_to_text($days) {
    if (!is_array($days) || empty($days)) {
        return '-';
    }

    $map = [
        'sun' => 'Sun',
        'mon' => 'Mon',
        'tue' => 'Tue',
        'wed' => 'Wed',
        'thu' => 'Thu',
        'fri' => 'Fri',
        'sat' => 'Sat',
    ];

    $result = [];
    foreach ($days as $d) {
        $key = strtolower(trim((string)$d));
        $result[] = $map[$key] ?? ucfirst($key);
    }

    return implode(', ', $result);
}

function build_schedule_tooltip_html($scheduleRaw) {
    $rows = json_decode((string)$scheduleRaw, true);
    if (!is_array($rows)) {
        $rows = [];
    }

    $html = '';
    $html .= '<div class="sched-tip-box" style="display:none; position:absolute; top:calc(100% + 8px); left:0; min-width:320px; max-width:420px; padding:0; border:1px solid #d9d9d9; border-radius:10px; background:#fff; box-shadow:0 10px 28px rgba(0,0,0,0.14); z-index:9999; text-align:left; overflow:hidden;">';
    $html .= '<div style="height:4px; background:#9aa0a6;"></div>';
    $html .= '<div style="position:absolute; top:-6px; left:16px; width:10px; height:10px; background:#fff; border-top:1px solid #d9d9d9; border-left:1px solid #d9d9d9; transform:rotate(45deg);"></div>';
    $html .= '<div style="padding:10px 12px; background:#f6f7f9; border-bottom:1px solid #d9d9d9; display:flex; align-items:center; justify-content:space-between; gap:8px;">';
    $html .= '<div style="font-weight:700; font-size:12px; color:#222;">Suspension Schedule</div>';
    $html .= '</div>';
    $html .= '<div style="padding:10px 12px;">';

    $hasVisibleRow = false;
    $lineNo = 0;

    foreach ($rows as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }

        // 첫 row는 id 정보라 skip
        if ($idx === 0 && isset($item['id'])) {
            continue;
        }

        $lineNo++;
        $hasVisibleRow = true;

        $enabled = !empty($item['enabled']);
        $from = (string)($item['from'] ?? '00:00');
        $to   = (string)($item['to'] ?? '12:00');
        $daysText = schedule_days_to_text($item['days'] ?? []);

        $badgeBg   = $enabled ? '#dbeafe' : '#f0f0f0';
        $badgeText = $enabled ? '#1d4ed8' : '#666';
        $badgeWord = $enabled ? 'Active' : 'Off';

        $html .= '<div style="padding:8px 0; border-bottom:1px solid #f1f3f5;">';
        $html .= '  <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:6px;">';
        $html .= '      <span style="font-size:12px; font-weight:700; color:#333;">Line ' . h($lineNo) . '</span>';
        $html .= '      <span style="display:inline-block; padding:2px 7px; border-radius:999px; background:' . h($badgeBg) . '; color:' . h($badgeText) . '; font-size:11px; font-weight:700;">' . h($badgeWord) . '</span>';
        $html .= '  </div>';
        $html .= '  <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:4px; font-size:12px;">';
        $html .= '      <span style="color:#666;">Time</span>';
        $html .= '      <span style="color:#111; text-align:right;">' . h($from) . ' ~ ' . h($to) . '</span>';
        $html .= '  </div>';
        $html .= '  <div style="display:flex; justify-content:space-between; gap:12px; font-size:12px;">';
        $html .= '      <span style="color:#666;">Days</span>';
        $html .= '      <span style="color:#111; text-align:right; word-break:break-word;">' . h($daysText) . '</span>';
        $html .= '  </div>';
        $html .= '</div>';
    }

    if (!$hasVisibleRow) {
        $html .= '<div style="font-size:12px; color:#666;">No schedule data</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function render_duty_scheduler_html(array $r) {
    $scheduleJs  = $r['ScheduleJson'] ?? '""';
    $scheduleRaw = $r['ScheduleRaw'] ?? '[]';

    $tooltipHtml = build_schedule_tooltip_html($scheduleRaw);

    $showJs = "var t=this.querySelector('.sched-tip-box'); if(t){t.style.display='block';}";
    $hideJs = "var t=this.querySelector('.sched-tip-box'); if(t){t.style.display='none';}";

    $html = '';
    $html .= '<span style="position:relative; display:inline-block;" onmouseenter="' . h($showJs) . '" onmouseleave="' . h($hideJs) . '">';
    $html .= '  <a href="javascript:void(0)" onclick="popOpenScheduler(\'pop-set-scheduler\', true,' . $scheduleJs . ')">';
    $html .= '      <img src="img/icon/ic_scheduler.png" alt="Scheduler" style="width:24px; height:24px; display:block; margin:10px auto 0px;">';
    $html .= '  </a>';
    $html .=      $tooltipHtml;
    $html .= '</span>';

    return $html;
}

function build_wifi_rows() {
    global $config, $cpzone;
    $cpzone = "crew";

    $radiususers = $config['installedpackages']['freeradius']['config'] ?? [];
    if (!is_array($radiususers)) $radiususers = [];

    $usersByName = [];
    foreach ($radiususers as $u) {
        $uname = $u['varusersusername'] ?? '';
        if ($uname !== '') {
            $usersByName[$uname] = $u;
        }
    }

    $names = array_keys($usersByName);
    sort($names);

    $cpdb = captiveportal_read_db();
    $onlineByUser = [];
    if (is_array($cpdb)) {
        foreach ($cpdb as $cpent) {
            if (isset($cpent[4], $cpent[5])) {
                $onlineByUser[$cpent[4]] = $cpent[5];
            }
        }
    }

    $searchKeyword = get_userid_search_keyword();

    $rows = [];
    foreach ($names as $name) {
        if ($searchKeyword !== '' && stripos($name, $searchKeyword) === false) {
            continue;
        }

        $eachuser = $usersByName[$name];

        if (!preg_match("/[a-z]*[0-9]{5}/", $name)) continue;

        $used_quota = (float)check_quota($name, $eachuser['varusersmaxtotaloctetstimerange']);
        $maxtotal   = (float)($eachuser['varusersmaxtotaloctets'] ?? 0);

        $pointoftime = strtolower((string)($eachuser['varuserspointoftime'] ?? ''));
        if ($used_quota > $maxtotal && $pointoftime === 'forever') continue;

        $descr = (string)($eachuser['varusersdescription'] ?? '');

        $terminaltype    = ($eachuser['varusersterminaltype'] ?? '') === '' ? 'Auto' : $eachuser['varusersterminaltype'];
        $halftime        = (string)($eachuser['varusershalftimeperiod'] ?? '');
        $accountmodified = (string)($eachuser['varusersmodified'] ?? '');
        $accountreset    = (string)($eachuser['varusersresetquota'] ?? '');
        $timerangeperiod = $eachuser['varusersmaxtotaloctetstimerange'] ?? '';
        $timerange       = strtolower((string)$timerangeperiod) === "forever" ? "one-time" : $timerangeperiod;

        /*$schedule = get_scheduler($name);
        $scheduleJson = htmlspecialchars(json_encode($schedule, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');*/
        $scheduleRaw = get_scheduler($name);
        $scheduleJson = htmlspecialchars(json_encode($scheduleRaw, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

        $sessionId = $onlineByUser[$name] ?? null;

        $rows[] = [
            'ID'            => $name,
            'Description'   => $descr,
            'Timerange'     => $timerange,
            'Type'          => $terminaltype,
            'Half'          => $halftime,
            'Modified'      => $accountmodified,
            'Reset'         => $accountreset,
            'Used(MB)'      => $used_quota,
            'Quota(MB)'     => $maxtotal,
            'Online'        => $sessionId ? 'Y' : 'N',
            'ScheduleRaw'   => $scheduleRaw,
            'ScheduleJson'  => $scheduleJson,
            'SessionId'     => $sessionId,
            'UsageTooltipExtra' => [
                'Created At'     => (string)($eachuser['varuserscreatedate'] ?? '-'),
                'Upload Limit'   => (string)($eachuser['varusersmaxbandwidthup'] ?? ''),
                'Download Limit' => (string)($eachuser['varusersmaxbandwidthdown'] ?? ''),
            ],
        ];
    }

    return $rows;
}

function export_wifi_csv() {
    $rows = build_wifi_rows();
    global $config;
    $vesselname = $config['system']['vesselinfo']['vesselname'];
    $filename = $vesselname . "_" . date("Ymd_His") . ".csv";

    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";

    $out = fopen("php://output", "w");

    $headers = ['ID', 'Description', 'Type', 'Update', 'Used(MB)', 'Quota(MB)', 'Online'];
    fputcsv($out, $headers);

    $totalUsed  = 0.0;
    $totalQuota = 0.0;
    $totalCount = 0;

    foreach ($rows as $r) {
        $totalCount++;

        if ($r['Half'] === '') {
            $halftime = '';
        } else {
            $halftime = $r['Half'] . '-';
        }

        $timerange = $halftime . $r['Timerange'];

        if (!empty($r['Reset'])) {
            $used = 0.0;
        } else {
            $used = isset($r['Used(MB)']) ? (float)preg_replace('/[^0-9.\-]/', '', (string)$r['Used(MB)']) : 0.0;
        }

        $quota = isset($r['Quota(MB)']) ? (float)preg_replace('/[^0-9.\-]/', '', (string)$r['Quota(MB)']) : 0.0;

        $totalUsed  += $used;
        $totalQuota += $quota;

        fputcsv($out, [
            $r['ID'] ?? '',
            $r['Description'] ?? '',
            $r['Type'] ?? '',
            $timerange,
            $r['Used(MB)'] ?? '',
            $r['Quota(MB)'] ?? '',
            $r['Online'] ?? '',
        ]);
    }

    fputcsv($out, [
        $totalCount,
        'TOTAL',
        '',
        '',
        $totalUsed,
        $totalQuota,
        '',
    ]);

    fclose($out);
    exit;
}

function draw_wifi_contents() {
    $rows = build_wifi_rows();
    $rtnstr = '';

    $queryParams = [];

    if (isset($_GET['order']) && $_GET['order'] !== '') {
        $queryParams['order'] = (string)$_GET['order'];
    }

    $searchKeyword = get_userid_search_keyword();
    if ($searchKeyword !== '') {
        $queryParams['userid_search'] = $searchKeyword;
    }

    $orderQuery = '';
    if (!empty($queryParams)) {
        $orderQuery = http_build_query($queryParams) . '&';
    }

    foreach ($rows as $r) {
        $name  = htmlspecialchars($r['ID'], ENT_QUOTES, 'UTF-8');
        $descr = htmlspecialchars($r['Description'], ENT_QUOTES, 'UTF-8');

        $rtnstr .= '<tr>';
        $rtnstr .= '<td data-th="" data-th-width="0" data-width="100"><div class="check v1">';
        $rtnstr .= '<input type="checkbox" name="userlist[]" id="'.$name.'" value="'.$name.'">';
        $rtnstr .= '<label for="'.$name.'"></label></div></td>';
        $rtnstr .= '<td data-th="ID" data-th-width="100" data-width="100">'.$name.'</td>';
        $rtnstr .= '<td><form action="crew_account.php" method="post" style="display:flex; align-items:center; gap:10px; margin:0;">
          <input type="text" name="description" value="'.$descr.'" style="border:1px solid #ccc; outline:none; padding:5px;">
          <input type="hidden" name="userid" value="'.$name.'">
          <button type="submit" style="background:none; border:none; padding:0;">
            <img src="img/icon/ic_apply.png" alt="제출" style="width:24px; height:24px;">
          </button>
        </form></td>';

        /*$json = $r['ScheduleJson'];
        $rtnstr .= '<td data-th="Date create" data-th-width="100" data-width="100">
          <a onclick="popOpenScheduler(\'pop-set-scheduler\', true,'.$json.')">
            <img src="img/icon/ic_scheduler.png" alt="Scheduler" style="width:24px; height:24px; display:block; margin:10px auto 0px;">
          </a><br class="hide-mo">
        </td>';*/
        $dutyHtml = render_duty_scheduler_html($r);
        $rtnstr .= '<td data-th="Date create" data-th-width="100" data-width="100">' . $dutyHtml . '<br class="hide-mo"></td>';

        $rtnstr .= '<td data-th="Type" data-th-width="100" data-width="100">'.htmlspecialchars($r['Type'], ENT_QUOTES, 'UTF-8').'</td>';

        if ($r['Half'] === '') {
            $halftime = '';
        } else {
            $halftime = (string)$r['Half'] . '-';
        }

        $rtnstr .= '<td data-th="Update" data-th-width="100" data-width="100">'.htmlspecialchars($halftime.$r['Timerange'], ENT_QUOTES, 'UTF-8').'</td>';

        $usageHtml = render_usage_state_html($r);
        $rtnstr .= '<td data-th="Usage state" data-th-width="100" data-width="100">'.$usageHtml.'</td>';

        if (!empty($r['SessionId'])) {
            /////////////////////////////////////////////////////
            $sid = rawurlencode($r['SessionId']);
            $rtnstr .= '<td data-th="Online" data-th-width="100" data-width="100" class="txt-green">
  <a class="status-chip-link" href="?'.$orderQuery.'act=del&amp;zone=crew&amp;id='.$sid.'">Logout</a>
</td>';
            ////////////////////////////////////////////////////////////////////
        } else {
            $rtnstr .= '<td data-th="Online" data-th-width="100" data-width="100" class="txt-green"></td>';
        }

        $rtnstr .= '</tr>';
    }

    return $rtnstr;
}

function set_description($userid, $description){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        if (($userid === $userentry['varusersusername'])) {
            $config["installedpackages"]["freeradius"]["config"][$item]['varusersdescription'] = $description;
        }
    }
    write_config('set '.$userid.' description');
}

function get_scheduler($user){
    global $config;

    $rows = [];
    $rows[] = ["id" => $user];

    $dayMap = [
        '0' => 'sun',
        '1' => 'mon',
        '2' => 'tue',
        '3' => 'wed',
        '4' => 'thu',
        '5' => 'fri',
        '6' => 'sat',
    ];

    if (!isset($config["schedules"]["schedule"]) || !is_array($config["schedules"]["schedule"])) {
        $config["schedules"]["schedule"] = [];
    }

    foreach ($config["schedules"]["schedule"] as $eachSchedule) {
        if (($eachSchedule['name'] ?? '') === $user) {
            if (!isset($eachSchedule['timerange']) || !is_array($eachSchedule['timerange'])) {
                continue;
            }

            foreach ($eachSchedule['timerange'] as $schedule) {
                $from = "00:00";
                $to   = "12:00";

                if (!empty($schedule['hour']) && strpos($schedule['hour'], '-') !== false) {
                    $fromto = explode("-", $schedule['hour'], 2);
                    $from = $fromto[0] ?? "00:00";
                    $to   = $fromto[1] ?? "12:00";
                }

                $days = [];
                $positions = explode(',', (string)($schedule['position'] ?? ''));
                foreach ($positions as $pos) {
                    $pos = trim((string)$pos);
                    if ($pos !== '' && isset($dayMap[$pos])) {
                        $days[] = $dayMap[$pos];
                    }
                }

                $rows[] = [
                    "enabled" => (($schedule['rangedescr'] ?? '') === 'TRUE'),
                    "from"    => $from,
                    "to"      => $to,
                    "days"    => $days
                ];
            }
        }
    }

    while (count($rows) < 4) {
        $rows[] = [
            "enabled" => false,
            "from"    => "00:00",
            "to"      => "12:00",
            "days"    => ["sun", "mon", "tue", "wed", "thu", "fri", "sat"]
        ];
    }

    $rows = array_slice($rows, 0, 4);

    $result = json_encode($rows, JSON_UNESCAPED_UNICODE);
    return $result;
}

function set_scheduler($userid, $schedule)
{
    global $config;

    $userid = trim((string)$userid);

    if ($userid === '') {
        return false;
    }

    if (!is_array($schedule)) {
        $schedule = [];
    }

    /*
     * 요일 변환표
     * pfSense 저장용:
     * 일요일 0 ~ 토요일 6
     */
    $dayMap = [
        'sun' => '0',
        'mon' => '1',
        'tue' => '2',
        'wed' => '3',
        'thu' => '4',
        'fri' => '5',
        'sat' => '6',
    ];

    /*
     * 1. POST flat 배열을 scheduler row 배열로 변환
     *
     * 현재 POST:
     * from_hour_0, from_min_0, to_hour_0, to_min_0, day_0
     */
    $normalizedSchedule = [];

    for ($i = 0; $i < 3; $i++) {
        $fromHour = $schedule['from_hour_' . $i] ?? '00';
        $fromMin  = $schedule['from_min_' . $i] ?? '00';
        $toHour   = $schedule['to_hour_' . $i] ?? '00';
        $toMin    = $schedule['to_min_' . $i] ?? '00';

        $fromHour = sprintf('%02d', max(0, min(23, (int)$fromHour)));
        $fromMin  = sprintf('%02d', max(0, min(59, (int)$fromMin)));
        $toHour   = sprintf('%02d', max(0, min(23, (int)$toHour)));
        $toMin    = sprintf('%02d', max(0, min(59, (int)$toMin)));

        $days = $schedule['day_' . $i] ?? [];

        if ($days === '') {
            $days = [];
        }

        if (!is_array($days)) {
            $days = [$days];
        }

        /*
         * 요일 문자열을 숫자로 변환
         * sun -> 0
         * mon -> 1
         * tue -> 2
         * wed -> 3
         * thu -> 4
         * fri -> 5
         * sat -> 6
         */
        $convertedDays = [];

        foreach ($days as $day) {
            $day = strtolower(trim((string)$day));

            if (isset($dayMap[$day])) {
                $convertedDays[] = $dayMap[$day];
                continue;
            }

            /*
             * 혹시 이미 숫자값으로 넘어온 경우도 허용
             */
            if (preg_match('/^[0-6]$/', $day)) {
                $convertedDays[] = $day;
            }
        }

        /*
         * 중복 제거 후 순서 정렬
         */
        $convertedDays = array_values(array_unique($convertedDays));
        sort($convertedDays, SORT_NUMERIC);

        $normalizedSchedule[] = [
            'active' => isset($schedule['act_' . $i]) ? 1 : 0,
            'from'   => $fromHour . ':' . $fromMin,
            'to'     => $toHour . ':' . $toMin,
            'days'   => $convertedDays,
        ];
    }

    /*
     * 2. config schedules 구조 보정
     * 여기서 기존 config 값이 문자열이면 배열로 초기화
     */
    if (!isset($config) || !is_array($config)) {
        $config = [];
    }

    if (!isset($config['schedules']) || !is_array($config['schedules'])) {
        $config['schedules'] = [];
    }

    if (!isset($config['schedules']['schedule']) || !is_array($config['schedules']['schedule'])) {
        $config['schedules']['schedule'] = [];
    }

    /*
     * pfSense config에서 schedule이 단일 associative array로 들어간 경우 보정
     */
    if (isset($config['schedules']['schedule']['name'])) {
        $config['schedules']['schedule'] = [
            $config['schedules']['schedule']
        ];
    }

    /*
     * 3. 기존 동일 name schedule 제거
     * 문자열/깨진 항목은 건너뜀
     */
    $scheduleList = [];

    foreach ($config['schedules']['schedule'] as $schd) {
        if (!is_array($schd)) {
            continue;
        }

        if (($schd['name'] ?? '') === $userid) {
            continue;
        }

        $scheduleList[] = $schd;
    }

    /*
     * 4. 새 schedule 생성
     */
    $scheduledItemList = [
        'name'      => $userid,
        'descr'     => '',
        'timerange' => []
    ];

    foreach ($normalizedSchedule as $row) {
        if (!is_array($row)) {
            continue;
        }

        $enabled = !empty($row['active']);
        $from    = $row['from'] ?? '00:00';
        $to      = $row['to'] ?? '00:00';

        $days = $row['days'] ?? [];

        if (!is_array($days)) {
            $days = [$days];
        }

        /*
         * position에는 숫자 요일값 저장
         * 예:
         * 일,월,화 선택 시 0,1,2
         */
        $timerange = [
            'rangedescr' => $enabled ? 'TRUE' : 'FALSE',
            'hour'       => $from . '-' . $to,
            'position'   => implode(',', $days),
        ];

        $scheduledItemList['timerange'][] = $timerange;
    }

    /*
     * 5. 새 schedule 추가 후 저장
     */
    $scheduleList[] = $scheduledItemList;

    $config['schedules']['schedule'] = $scheduleList;

    write_config('Update/write ' . $userid . ' schedule');

    return true;
}


function check_wifi_account_password(){
    global $config;
    $passwordlist = array();
    foreach ($config['installedpackages']['freeradius']['config'] as $eachuser) {
        array_push($passwordlist, $eachuser['varuserspassword']);
    }
    return implode("|||", $passwordlist);
}

function check_wifi_account_id(){
    global $config;
    $passwordlist = array();
    foreach ($config['installedpackages']['freeradius']['config'] as $eachuser) {
        array_push($passwordlist, $eachuser['varusersusername']);
    }
    return implode("|||", $passwordlist);
}

function del_wifi_user($userlist){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user){
            if ($user === $userentry['varusersusername']) {
                unset($config["installedpackages"]["freeradius"]["config"][$item]);
                unlink_if_exists("/var/log/radacct/datacounter/daily/used-octets-$user*");
                unlink_if_exists("/var/log/radacct/datacounter/daily/max-octets-$user");
                unlink_if_exists("/var/log/radacct/datacounter/weekiy/used-octets-$user*");
                unlink_if_exists("/var/log/radacct/datacounter/weekiy/max-octets-$user");
                unlink_if_exists("/var/log/radacct/datacounter/monthly/used-octets-$user*");
                unlink_if_exists("/var/log/radacct/datacounter/monthly/max-octets-$user");
                unlink_if_exists("/var/log/radacct/datacounter/forever/used-octets-$user*");
                unlink_if_exists("/var/log/radacct/datacounter/forever/max-octets-$user");
                cp_wireless_log("Deleted user: ".$user);
            }
        }
    }
    freeradius_users_resync();
    write_config("Deleted Wifi user");
}

function modify_wifi_user($userlist, $modifydata){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
            cp_wireless_log("Modifydata: $user - to ".$modifydata['datalimit'].'MBytes');
            if ($user === $userentry['varusersusername']) {
                $halftimeperiod = explode('-', $modifydata['timeperiod']);
                if (count($halftimeperiod) == 2) {
                    $varusershalftimeperiod = $halftimeperiod[0];
                    $varuserstimeperiod = $halftimeperiod[1];
                } else {
                    $varusershalftimeperiod = "";
                    $varuserstimeperiod = $halftimeperiod[0];
                }

                if ($modifydata['datalimit'] != '') {
                    $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctets'] = $modifydata['datalimit'];
                }
                if ($modifydata['timeperiod'] != '') {
                    //$config["installedpackages"]["freeradius"]["config"][$item]['varusersamountoftime']=$modifydata['timelimit'];
                }

                $config["installedpackages"]["freeradius"]["config"][$item]['varusershalftimeperiod'] = $varusershalftimeperiod;
                $config["installedpackages"]["freeradius"]["config"][$item]['varuserspointoftime'] = $varuserstimeperiod;
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctetstimerange'] = strtolower($varuserstimeperiod);
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxbandwidthdown'] = $modifydata['downspeed'];
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxbandwidthup'] = $modifydata['upspeed'];
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersterminaltype'] = $modifydata['terminaltype'];
                $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
                cp_wireless_log("User setting changed: ".$userentry['varusersusername']);
            }
        }
    }
    freeradius_users_resync();
    write_config("Reset datausage Wifi user");
}

function reset_wifi_user($userlist){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
            if ($user === $userentry['varusersusername']) {
                $config['installedpackages']['freeradius']['config'][$item]['varusersresetquota'] = "true";
                $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
                cp_wireless_log("Reset Datausage: ".$userentry['varusersusername']);
            }
        }
    }
    freeradius_users_resync();
    write_config("Reset datausage Wifi user");
}

function reset_wifi_user_pw($userlist){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
            if ($user === $userentry['varusersusername']) {
                $config["installedpackages"]["freeradius"]["config"][$item]['varuserspassword'] = "1111";
                cp_wireless_log("Reset initial password: ".$userentry['varusersusername']);
            }
        }
        freeradius_update_user($user);
    }
    write_config("Reset password Wifi user");
}

function reset_random_wifi_user_pw($userlist){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
            if ($user === $userentry['varusersusername']) {
                $alphabet = '1234567890';
                $pass = array();
                $alphaLength = strlen($alphabet) - 1;

                for ($passwordchar = 0; $passwordchar < 6; $passwordchar++) {
                    $n = rand(0, $alphaLength);
                    $pass[] = $alphabet[$n];
                }
                $config["installedpackages"]["freeradius"]["config"][$item]['varuserspassword'] = implode($pass);
                cp_wireless_log("Reset random password: ".$userentry['varusersusername']);
            }
        }
        freeradius_update_user($user);
    }
    write_config("Reset password Wifi user");
}

function create_wifi_user($dataamount, $vouchernumber, $israndompw, $terminaltype, $timeperiod){
    global $config;

    $created_users = array();

    if (!isset($config['installedpackages']['freeradius']['config']) || !is_array($config['installedpackages']['freeradius']['config'])) {
        $config['installedpackages']['freeradius']['config'] = array();
    }

    $userprefix = strtolower($terminaltype) . 'user';
    $userpostfix = 0;

    foreach ($config['installedpackages']['freeradius']['config'] as $item) {
        if (!isset($item['varusersusername'])) {
            continue;
        }

        if (strpos($item['varusersusername'], $userprefix) === 0) {
            $curpostfix = intval(substr($item['varusersusername'], strlen($userprefix)));
            if ($curpostfix > $userpostfix) {
                $userpostfix = $curpostfix;
            }
        }
    }

    $userpostfix++;

    $vouchernumber = intval($vouchernumber);
    if ($vouchernumber > 100) {
        $vouchernumber = 100;
    }
    if ($vouchernumber < 1) {
        return array();
    }

    for ($usercount = $userpostfix; $usercount < $userpostfix + $vouchernumber; $usercount++) {
        $username = $userprefix . str_pad($usercount, 5, '0', STR_PAD_LEFT);

        $userexist = false;
        foreach ($config['installedpackages']['freeradius']['config'] as $item) {
            if (isset($item['varusersusername']) && $username === $item['varusersusername']) {
                $userexist = true;
                break;
            }
        }
        if ($userexist) {
            continue;
        }

        $curdate = date('Y/m/d H:i:s');
        $halftimeperiod = explode('-', $timeperiod);

        if (count($halftimeperiod) == 2) {
            $varusershalftimeperiod = $halftimeperiod[0];
            $varuserstimeperiod = $halftimeperiod[1];
        } else {
            $varusershalftimeperiod = "";
            $varuserstimeperiod = $halftimeperiod[0];
        }

        $userinfoentry = array(
            "sortable" => "",
            "varuserspasswordencryption" => "Cleartext-Password",
            "varusersmotpenable" => "",
            "varusersauthmethod" => "motp",
            "varusersmotpinitsecret" => "",
            "varusersmotppin" => "",
            "varusersmotpoffset" => "",
            "qrcodetext" => "",
            "varuserswisprredirectionurl" => "",
            "varuserssimultaneousconnect" => "",
            "description" => "",
            "varusersframedipaddress" => "",
            "varusersframedipnetmask" => "",
            "varusersframedroute" => "",
            "varusersframedip6address" => "",
            "varusersframedip6route" => "",
            "varusersvlanid" => "",
            "varusersexpiration" => "",
            "varuserssessiontimeout" => "",
            "varusershalftimeperiod" => $varusershalftimeperiod,
            "varuserslogintime" => "",
            "varusersamountoftime" => "",
            "varuserspointoftime" => $varuserstimeperiod,
            "varusersmaxtotaloctetstimerange" => strtolower($varuserstimeperiod),
            "varusersmaxbandwidthdown" => "",
            "varusersmaxbandwidthup" => "",
            "varusersacctinteriminterval" => "600",
            "varuserstopadditionaloptions" => "",
            "varuserscheckitemsadditionaloptions" => "",
            "varusersreplyitemsadditionaloptions" => "",
            "varusersterminaltype" => $terminaltype,
            "varusersresetquota" => "true",
            "varusersmodified" => "create",
            "varuserscreatedate" => $curdate,
        );

        $userinfoentry['varusersusername'] = $username;

        if ($israndompw === "randpwd") {
            $alphabet = '1234567890';
            $pass = array();
            $alphaLength = strlen($alphabet) - 1;

            for ($passwordchar = 0; $passwordchar < 6; $passwordchar++) {
                $n = rand(0, $alphaLength);
                $pass[] = $alphabet[$n];
            }

            $userinfoentry['varuserspassword'] = implode($pass);
        } else {
            $userinfoentry['varuserspassword'] = "1111";
        }

        if (is_numeric($dataamount)) {
            $userinfoentry['varusersmaxtotaloctets'] = $dataamount;
        } else {
            $userinfoentry['varusersmaxtotaloctets'] = 0;
        }

        $config['installedpackages']['freeradius']['config'][] = $userinfoentry;
        $created_users[] = $userinfoentry;
    }

    freeradius_update_user($username);
    write_config("Created Wifi user");

    return $created_users;
}
?>