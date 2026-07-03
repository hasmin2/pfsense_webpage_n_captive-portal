<?php
require_once ("globals.inc");
require_once ("captiveportal.inc");
require_once ('functions.inc');
// #49: 계정 변경 이력 헬퍼 (radius.radacct_changehistory) — 미배포(버전섞임) 시 기록만 skip
if (file_exists('/etc/inc/cp_account_history.inc')) {
    require_once('/etc/inc/cp_account_history.inc');
}

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
    $html .= '<form method="get" action="' . $action . '" style="display:flex; align-items:flex-end; justify-content:flex-start; gap:8px; flex-wrap:nowrap; margin:0; width:100%; min-width:0;">';

    if ($order !== '') {
        $html .= '<input type="hidden" name="order" value="' . h($order) . '">';
    }

    $html .= '<div style="display:flex; flex-direction:column; gap:6px; flex:1 1 auto; min-width:120px; max-width:420px;">';
    //$html .= '  <label for="userid_search" style="font-weight:600; color:#333; line-height:1;">USERID</label>';
    $html .= '  <input type="text" id="userid_search" name="userid_search" value="' . h($q) . '" placeholder="User ID" ';
    $html .= '         style="height:34px; width:100%; padding:0 10px; border:1px solid #ccc; border-radius:6px; outline:none; box-sizing:border-box;">';
    $html .= '</div>';

    $html .= '<button type="submit" style="height:34px; padding:0 12px; border:1px solid #1976d2; background:#1976d2; color:#fff; border-radius:6px; cursor:pointer; white-space:nowrap; flex:0 0 auto;">Search</button>';

    if ($q !== '') {
        $html .= '<a href="' . h($clearUrl) . '" style="display:inline-flex; align-items:center; justify-content:center; height:34px; padding:0 12px; border:1px solid #bbb; background:#fff; color:#333; border-radius:6px; text-decoration:none; white-space:nowrap; flex:0 0 auto;">Clear</a>';
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

function build_wifi_rows(string $isPrepaid): array {
    global $config, $cpzone;
    $cpzone = "crew";

    // ── 1. RADIUS 유저 맵 구성 ──────────────────────────────
    $radiususers = $config['installedpackages']['freeradius']['config'] ?? [];
    if (!is_array($radiususers)) {
        $radiususers = [];
    }

    $usersByName = [];
    foreach ($radiususers as $u) {
        $uname = $u['varusersusername'] ?? '';
        if ($uname !== '') {
            $usersByName[$uname] = $u;
        }
    }
    ksort($usersByName); // sort($names) + array_keys 대신 직접 정렬

    // ── 2. 온라인 세션 맵 구성 ──────────────────────────────
    $cpdb = captiveportal_read_db();
    $onlineByUser = [];
    if (is_array($cpdb)) {
        foreach ($cpdb as $cpent) {
            if (isset($cpent[4], $cpent[5])) {
                $onlineByUser[$cpent[4]] = $cpent[5];
            }
        }
    }

    // ── 3. 필터 헬퍼 ────────────────────────────────────────
    $searchKeyword = get_userid_search_keyword();
    $isPrepaidMode = $isPrepaid === 'prepaid';

    $shouldSkip = function (string $name) use ($searchKeyword, $isPrepaidMode): bool {
        if ($searchKeyword !== '' && stripos($name, $searchKeyword) === false) {
            return true;
        }
        if ($isPrepaidMode) {
            return !preg_match('/^crewpay-/i', $name);         // prepaid: crewpay- 만 허용
        }
        return (bool)preg_match('/^(crewpay-|synersat)/i', $name); // 일반: 두 패턴 제외
    };

    // ── 4. 행 구성 ──────────────────────────────────────────
    $rows = [];
    foreach ($usersByName as $name => $user) {
        if ($shouldSkip($name)) {
            continue;
        }

        $timerangeperiod = (string)($user['varusersmaxtotaloctetstimerange'] ?? '');
        $pointoftime     = strtolower((string)($user['varuserspointoftime'] ?? ''));
        $used_quota      = (float)check_quota($name, $timerangeperiod);
        $maxtotal        = (float)($user['varusersmaxtotaloctets'] ?? 0);

        if ($used_quota > $maxtotal && $pointoftime === 'forever') {
            continue;
        }

        $terminaltype = ($user['varusersterminaltype'] ?? '') ?: 'Auto';
        $timerange    = strtolower($timerangeperiod) === 'forever' ? 'one-time' : $timerangeperiod;

        $scheduleRaw  = get_scheduler($name);
        $sessionId    = $onlineByUser[$name] ?? null;

        $rows[] = [
            'ID'          => $name,
            'Description' => (string)($user['varusersdescription'] ?? ''),
            'Timerange'   => $timerange,
            'Type'        => $terminaltype,
            'Half'        => (string)($user['varusershalftimeperiod'] ?? ''),
            'Modified'    => (string)($user['varusersmodified'] ?? ''),
            'Reset'       => (string)($user['varusersresetquota'] ?? ''),
            'Used(MB)'    => $used_quota,
            'Quota(MB)'   => $maxtotal,
            'Online'      => $sessionId ? 'Y' : 'N',
            'ScheduleRaw'  => $scheduleRaw,
            'ScheduleJson' => htmlspecialchars(
                json_encode($scheduleRaw, JSON_UNESCAPED_UNICODE),
                ENT_QUOTES, 'UTF-8'
            ),
            'SessionId'   => $sessionId,
            'UsageTooltipExtra' => [
                'Created At'     => (string)($user['varuserscreatedate']        ?? '-'),
                'Upload Limit'   => (string)($user['varusersmaxbandwidthup']    ?? ''),
                'Download Limit' => (string)($user['varusersmaxbandwidthdown']  ?? ''),
            ],
        ];
    }

    return $rows;
}

function export_wifi_csv($isPrepaid) {
    $rows = build_wifi_rows($isPrepaid);
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

function draw_wifi_contents($isPrepaid) {
    $rows = build_wifi_rows($isPrepaid);
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
    if($isPrepaid==='prepaid') {
        $redirurl = 'prepaid_account.php';
    }
    else{
        $redirurl = 'crew_account.php';
    }
    foreach ($rows as $r) {
        $rawId       = $r['ID'];
        $displayname = htmlspecialchars(
            strncasecmp($rawId, 'crewpay-', 8) === 0 ? substr($rawId, 8) : $rawId,
            ENT_QUOTES, 'UTF-8'
        );
        $name  = htmlspecialchars($r['ID'], ENT_QUOTES, 'UTF-8');
        $descr = htmlspecialchars($r['Description'], ENT_QUOTES, 'UTF-8');

        $rtnstr .= '<tr>';
        $rtnstr .= '<td data-th="" data-th-width="0" data-width="100"><div class="check v1">';
        $rtnstr .= '<input type="checkbox" name="userlist[]" id="'.$name.'" value="'.$name.'">';
        $rtnstr .= '<label for="'.$name.'"></label></div></td>';
        $rtnstr .= '<td data-th="ID" data-th-width="100" data-width="100">'.$displayname.'</td>';
        $rtnstr .= '<td><form action='.$redirurl.' method="post" style="display:flex; align-items:center; gap:10px; margin:0;">
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

        // #50: per-user 변경 이력 버튼 (crew 페이지 전용 — prepaid 는 컬럼 미추가)
        if ($isPrepaid !== 'prepaid') {
            $rtnstr .= '<td data-th="History" data-th-width="100" data-width="100">'
                . '<button type="button" class="accthist-open-btn" onclick="openAcctHistory(\''
                . $name . '\',\'' . $displayname . '\')">History</button></td>';
        }

        $rtnstr .= '</tr>';
    }

    return $rtnstr;
}

/**
 * #50: 계정 변경 이력 모달 (crew_account.php per-user "History" 버튼용).
 *   전역 JS 함수 openAcctHistory(username, displayname) 를 정의 — 버튼 onclick 에서 호출.
 *   crew_account_history_data.php(radacct_changehistory 조회) 를 AJAX 로 읽어 표시.
 *   스타일은 GMT 변경 이력 모달(#48)과 동일 계열(다크 카드, 테마 무관 고정색), accthist-* 격리.
 *   crew_account.php </body> 앞에서 echo. (미배포 시 버튼만 있고 데이터는 unavailable — fatal 없음.)
 */
function render_account_history_modal() {
    return <<<'HTML'
<style>
#accthist-ov{position:fixed;inset:0;background:rgba(8,12,20,.74);z-index:10002;display:none;align-items:center;justify-content:center;}
#accthist-ov.on{display:flex;}
.accthist-modal{width:min(860px,96vw);max-height:88vh;overflow:auto;background:#0d1726;border:1px solid #24405f;border-radius:14px;padding:18px 20px;box-shadow:0 18px 60px rgba(0,0,0,.55);}
.accthist-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.accthist-head h3{margin:0;font-size:16px;font-weight:800;color:#f2f7ff;}
.accthist-head h3 span{color:#7fd4ff;}
.accthist-x{background:none;border:0;color:#8ca6c6;font-size:22px;line-height:1;cursor:pointer;padding:0 2px;}
.accthist-x:hover{color:#fff;}
.accthist-range{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;}
.accthist-range .ah-btn{height:28px;padding:0 12px;border-radius:7px;font-size:12px;font-weight:700;background:#152238;border:1px solid #2c4363;color:#b9cbe4;cursor:pointer;}
.accthist-range .ah-btn.on{background:#1976d2;border-color:#1976d2;color:#fff;}
.accthist-range .ah-export{margin-left:auto;background:#12301f;border-color:#2f6b4f;color:#9fe0bd;}
.accthist-range .ah-export:disabled{opacity:.45;cursor:default;}
.accthist-custom{display:none;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap;}
.accthist-custom input[type=date]{height:28px;padding:0 8px;border-radius:7px;background:#0a192f;border:1px solid #2c4363;color:#e7eefc;font-size:12px;color-scheme:dark;}
.accthist-custom .ah-sep{color:#7e95b4;font-size:12px;}
.accthist-apply{height:28px;padding:0 14px;border-radius:7px;font-size:12px;font-weight:700;background:#1976d2;border:1px solid #1976d2;color:#fff;cursor:pointer;}
.accthist-tbl{width:100%;border-collapse:collapse;font-size:12px;}
.accthist-tbl th{color:#9fb6d4;text-align:left;font-weight:700;padding:6px 8px;border-bottom:1px solid #2c4363;white-space:nowrap;}
.accthist-tbl td{color:#e7eefc;padding:6px 8px;border-bottom:1px solid #1a2c49;vertical-align:top;}
.accthist-tbl td.ah-time{white-space:nowrap;color:#9fb6d4;}
.accthist-tbl td.ah-desc{color:#dbe6f5;}
.ah-chip{display:inline-block;font-size:10.5px;font-weight:800;letter-spacing:.3px;border-radius:6px;padding:2px 7px;white-space:nowrap;}
.accthist-msg{color:#cdd9ea;text-align:center;padding:32px 12px;font-size:13px;line-height:1.6;margin:0;}
.accthist-note{margin:10px 0 0;font-size:11px;color:#7e95b4;}
.accthist-open-btn{height:24px;padding:0 10px;border-radius:7px;font-size:11px;font-weight:700;background:#eef3f9;border:1px solid #b9c6d6;color:#37506e;cursor:pointer;}
.accthist-open-btn:hover{background:#e0e8f2;}
</style>
<div id="accthist-ov" role="dialog" aria-modal="true" aria-label="Account change history">
    <div class="accthist-modal">
        <div class="accthist-head">
            <h3>Account change history &mdash; <span id="accthist-user"></span></h3>
            <button type="button" class="accthist-x" id="accthist-x" aria-label="Close">&times;</button>
        </div>
        <div class="accthist-range" id="accthist-range">
            <button type="button" class="ah-btn on" data-days="30">30d</button>
            <button type="button" class="ah-btn" data-days="7">7d</button>
            <button type="button" class="ah-btn" data-days="1">1d</button>
            <button type="button" class="ah-btn" data-days="3660">All</button>
            <button type="button" class="ah-btn" data-custom="1">Custom</button>
            <button type="button" class="ah-btn ah-export" id="accthist-export" disabled>Export CSV</button>
        </div>
        <div class="accthist-custom" id="accthist-custom">
            <input type="date" id="accthist-from"> <span class="ah-sep">~</span> <input type="date" id="accthist-to">
            <button type="button" class="accthist-apply" id="accthist-apply">Apply</button>
        </div>
        <div id="accthist-body"></div>
        <p class="accthist-note">&#9432; Account modification log (times in UTC) &mdash; password / usage resets, create / delete / modify, top-up, description &amp; schedule changes.</p>
    </div>
</div>
<script>
(function(){
    var ov     = document.getElementById("accthist-ov");
    if (!ov) { return; }
    var body   = document.getElementById("accthist-body");
    var custom = document.getElementById("accthist-custom");
    var fromI  = document.getElementById("accthist-from");
    var toI    = document.getElementById("accthist-to");
    var userEl = document.getElementById("accthist-user");
    var pills  = document.getElementById("accthist-range").querySelectorAll(".ah-btn[data-days], .ah-btn[data-custom]");
    var expB   = document.getElementById("accthist-export");
    var curUser = "";
    var lastRows = [];

    var CHIP = {
        password_reset:   ["#9a6207","#fdf3e1"], password_change: ["#9a6207","#fdf3e1"],
        usage_reset:      ["#1769aa","#e7f1fb"], quota_topup:     ["#0f7b46","#e6f7ee"],
        user_create:      ["#0f7b46","#e6f7ee"], user_delete:     ["#b23b3b","#fbecec"],
        user_modify:      ["#1769aa","#e7f1fb"], description_change:["#5b6573","#eef1f5"],
        schedule_change:  ["#5b6573","#eef1f5"]
    };
    function esc(s){
        return String(s==null?"":s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }
    function prettyType(t){
        return String(t==null?"":t).replace(/_/g," ").replace(/\b\w/g,function(c){return c.toUpperCase();});
    }
    function csrf(){
        // pfSense csrf-magic 전역 우선, 없으면 페이지 폼의 hidden 사용
        if (window.csrfMagicToken) { return { name: (window.csrfMagicName||"__csrf_magic"), val: window.csrfMagicToken }; }
        var el = document.querySelector("input[name=__csrf_magic]");
        return el ? { name: "__csrf_magic", val: el.value } : null;
    }
    function render(rows){
        lastRows = rows || [];
        if (expB) { expB.disabled = (lastRows.length === 0); }
        if (!rows || !rows.length) {
            body.innerHTML = "<p class=\"accthist-msg\">No changes recorded for this account in this period.</p>";
            return;
        }
        var h = "<table class=\"accthist-tbl\"><thead><tr><th>Time (UTC)</th><th>Type</th><th>Description</th></tr></thead><tbody>";
        for (var i=0;i<rows.length;i++){
            var r = rows[i];
            var c = CHIP[r.change_type] || ["#5b6573","#eef1f5"];
            h += "<tr><td class=\"ah-time\">"+esc(r.timestamp)+"</td>"
               + "<td><span class=\"ah-chip\" style=\"color:"+c[0]+";background:"+c[1]+";\">"+esc(prettyType(r.change_type))+"</span></td>"
               + "<td class=\"ah-desc\">"+esc(r.change_description)+"</td></tr>";
        }
        body.innerHTML = h + "</tbody></table>";
    }
    function load(params){
        body.innerHTML = "<p class=\"accthist-msg\">Loading&hellip;</p>";
        lastRows = []; if (expB) { expB.disabled = true; }
        params.username = curUser;
        var tok = csrf();
        if (tok) { params[tok.name] = tok.val; }
        var q = [];
        for (var k in params) { q.push(encodeURIComponent(k)+"="+encodeURIComponent(params[k])); }
        var xhr = new XMLHttpRequest();
        xhr.open("POST","/crew_account_history_data.php",true);
        xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
        xhr.onreadystatechange = function(){
            if (xhr.readyState !== 4) { return; }
            var d = null;
            try { d = JSON.parse(xhr.responseText); } catch(e) {}
            if (!d || !d.ok) { body.innerHTML = "<p class=\"accthist-msg\">History unavailable (database unreachable).</p>"; return; }
            render(d.rows);
        };
        xhr.send(q.join("&"));
    }
    function setOn(b){ for (var i=0;i<pills.length;i++){ pills[i].classList.remove("on"); } b.classList.add("on"); }
    for (var i=0;i<pills.length;i++){
        (function(b){
            b.addEventListener("click", function(){
                setOn(b);
                if (b.getAttribute("data-custom")) { custom.style.display="flex"; return; }
                custom.style.display="none";
                load({ mode:"days", days:b.getAttribute("data-days") });
            });
        })(pills[i]);
    }
    var applyB = document.getElementById("accthist-apply");
    if (applyB) {
        applyB.addEventListener("click", function(){
            if (!fromI.value || !toI.value) { return; }
            load({ mode:"custom", from:fromI.value, to:toI.value });
        });
    }
    function csvField(v){ v=String(v==null?"":v); return (/[",\r\n]/.test(v)) ? "\""+v.replace(/"/g,"\"\"")+"\"" : v; }
    if (expB) {
        expB.addEventListener("click", function(){
            if (!lastRows.length) { return; }
            var lines = ["id,timestamp_utc,username,change_type,change_description"];
            for (var i=0;i<lastRows.length;i++){
                var r = lastRows[i];
                lines.push([r.id,r.timestamp,curUser,r.change_type,r.change_description].map(csvField).join(","));
            }
            var csv = "\uFEFF"+lines.join("\r\n")+"\r\n";
            var blob = new Blob([csv], {type:"text/csv;charset=utf-8;"});
            var a = document.createElement("a");
            var ts = new Date().toISOString().replace(/[-:]/g,"").replace("T","_").slice(0,15);
            a.href = URL.createObjectURL(blob);
            a.download = "account_history_"+curUser.replace(/[^A-Za-z0-9._-]/g,"_")+"_"+ts+".csv";
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            setTimeout(function(){ URL.revokeObjectURL(a.href); }, 1000);
        });
    }
    function closeOv(){ ov.classList.remove("on"); }
    document.getElementById("accthist-x").addEventListener("click", closeOv);
    ov.addEventListener("click", function(ev){ if (ev.target === ov) { closeOv(); } });
    document.addEventListener("keydown", function(ev){ if (ev.key === "Escape" && ov.classList.contains("on")) { closeOv(); } });

    // 전역 진입점: 행의 History 버튼 onclick 에서 호출
    window.openAcctHistory = function(username, displayname){
        curUser = String(username||"");
        if (userEl) { userEl.textContent = displayname || curUser; }
        var today = new Date().toISOString().slice(0,10);
        if (fromI && !fromI.value) { fromI.value = today; }
        if (toI && !toI.value)     { toI.value = today; }
        setOn(pills[0]);                 // 기본 30d
        custom.style.display = "none";
        ov.classList.add("on");
        load({ mode:"days", days:"30" });
    };
})();
</script>
HTML;
}

function set_description($userid, $description){
    global $config;
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        if (($userid === $userentry['varusersusername'])) {
            $config["installedpackages"]["freeradius"]["config"][$item]['varusersdescription'] = $description;
        }
    }
    write_config('set '.$userid.' description');
    // #49: 변경 이력 기록 (prepaid=crewpay- 이면 CREWPAY 태그)
    if (function_exists('cp_account_history_record')) {
        cp_account_history_record('description_change',
            "user={$userid} description set to \"{$description}\" by " . cp_account_history_actor()
            . cp_account_history_tag($userid));
    }
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

    // #49: 변경 이력 기록 — 활성 행만 "HH:MM-HH:MM(days)" 요약
    if (function_exists('cp_account_history_record')) {
        $sched_parts = array();
        foreach ($scheduledItemList['timerange'] as $tr) {
            if (($tr['rangedescr'] ?? '') === 'TRUE') {
                $sched_parts[] = $tr['hour'] . '(days=' . $tr['position'] . ')';
            }
        }
        $sched_txt = empty($sched_parts) ? 'all rows disabled' : implode(', ', $sched_parts);
        cp_account_history_record('schedule_change',
            "user={$userid} duty schedule updated ({$sched_txt}) by " . cp_account_history_actor()
            . cp_account_history_tag($userid));
    }

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
    $deleted_users = array();   // #23 step3-A
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user){
            if ($user === $userentry['varusersusername']) {
                unset($config["installedpackages"]["freeradius"]["config"][$item]);
                $deleted_users[] = $user;
                unlink_if_exists("/var/log/radacct/datacounter/daily/used-octets-$user*");
                unlink_if_exists("/var/log/radacct/datacounter/daily/max-octets-$user");
                unlink_if_exists("/var/log/radacct/datacounter/weekly/used-octets-$user*");
                unlink_if_exists("/var/log/radacct/datacounter/weekly/max-octets-$user");
                unlink_if_exists("/var/log/radacct/datacounter/monthly/used-octets-$user*");
                unlink_if_exists("/var/log/radacct/datacounter/monthly/max-octets-$user");
                unlink_if_exists("/var/log/radacct/datacounter/forever/used-octets-$user*");
                unlink_if_exists("/var/log/radacct/datacounter/forever/max-octets-$user");
                cp_wireless_log("Deleted user: ".$user." by ".cp_admin_actor());
            }
        }
    }
    freeradius_users_resync();
    write_config("Deleted Wifi user");
    // #23 step3-A: 삭제 계정의 radcheck 행도 제거(단일 SQL).
    if (function_exists('freeradius_radcheck_delete_users')) {
        freeradius_radcheck_delete_users($deleted_users);
    }
    // #49: 변경 이력 기록 (사용자별 1행)
    if (function_exists('cp_account_history_record') && !empty($deleted_users)) {
        $actor = cp_account_history_actor();
        $descs = array();
        foreach ($deleted_users as $u) { $descs[] = "user={$u} deleted by {$actor}" . cp_account_history_tag($u); }
        cp_account_history_record('user_delete', $descs);
    }
}

function modify_wifi_user($userlist, $modifydata){
    global $config;
    $modified_users = array();   // #49
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
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
                    cp_wireless_log("Modify allowed data: $user - to ".$modifydata['datalimit'].'MBytes');
                    $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctets'] = $modifydata['datalimit'];
                }
                if ($modifydata['timeperiod'] != '') {
                    cp_wireless_log("Modify allowed time: $user - to ".$modifydata['datalimit'].'minutes');
                    //$config["installedpackages"]["freeradius"]["config"][$item]['varusersamountoftime']=$modifydata['timelimit'];
                }
                $config["installedpackages"]["freeradius"]["config"][$item]['varusershalftimeperiod'] = $varusershalftimeperiod;
                $config["installedpackages"]["freeradius"]["config"][$item]['varuserspointoftime'] = $varuserstimeperiod;
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxtotaloctetstimerange'] = strtolower($varuserstimeperiod);
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxbandwidthdown'] = $modifydata['downspeed'];
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersmaxbandwidthup'] = $modifydata['upspeed'];
                $config["installedpackages"]["freeradius"]["config"][$item]['varusersterminaltype'] = $modifydata['terminaltype'];
                $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
                $modified_users[] = $user;   // #49

            }
        }
    }
    freeradius_users_resync();
    write_config("Reset datausage Wifi user");
    // #49: 변경 이력 기록 (사용자별 1행, 적용된 새 값 요약)
    if (function_exists('cp_account_history_record') && !empty($modified_users)) {
        $actor = cp_account_history_actor();
        $detail = 'datalimit=' . ($modifydata['datalimit'] ?? '') . 'MB'
            . ', timeperiod=' . ($modifydata['timeperiod'] ?? '')
            . ', down=' . ($modifydata['downspeed'] ?? '')
            . ', up=' . ($modifydata['upspeed'] ?? '')
            . ', terminaltype=' . ($modifydata['terminaltype'] ?? '');
        $descs = array();
        foreach (array_unique($modified_users) as $u) { $descs[] = "user={$u} modified ({$detail}) by {$actor}" . cp_account_history_tag($u); }
        cp_account_history_record('user_modify', $descs);
    }
}

function reset_wifi_user($userlist){
    global $config;
    $reset_targets = array();
    foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
        foreach ($userlist as $user) {
            if ($user === $userentry['varusersusername']) {
                // 태그는 그대로 유지(차후 로그인 fallback). 실제 리셋은 아래에서 "즉시" 수행.
                $config['installedpackages']['freeradius']['config'][$item]['varusersresetquota'] = "true";
                $config['installedpackages']['freeradius']['config'][$item]['varusersmodified'] = "update";
                cp_wireless_log("Reset Datausage: ".$userentry['varusersusername']." by ".cp_admin_actor());
                $reset_targets[] = $userentry['varusersusername'];
            }
        }
    }
    freeradius_users_resync();
    write_config("Reset datausage Wifi user");

    // 차후 로그인이 아니라 "이때 바로": 활성 세션 로그아웃 + 사용량 0
    foreach (array_unique($reset_targets) as $u) {
        captiveportal_reset_user_usage($u);
    }
    // #49: 변경 이력 기록 (사용자별 1행)
    if (function_exists('cp_account_history_record') && !empty($reset_targets)) {
        $actor = cp_account_history_actor();
        $descs = array();
        foreach (array_unique($reset_targets) as $u) { $descs[] = "user={$u} data usage reset by {$actor}" . cp_account_history_tag($u); }
        cp_account_history_record('usage_reset', $descs);
    }
}

function reset_wifi_user_pw($userlist){
    global $config;
    // 동시 PW 변경 lost-update 방지: 전용 락 + 락 안에서 config 최신본 재로딩 후 저장.
    // (write_config 는 내부적으로 lock('config') 를 쓰므로 다른 이름의 락이어야 데드락이 없다.)
    $cnf_lock = lock('freeradius_user_config', LOCK_EX);
    $radcheck_entries = array();   // #23 step3-A
    try {
        $config = parse_config(true);
        $changed = false;
        foreach ($config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
            foreach ($userlist as $user) {
                if ($user === $userentry['varusersusername']) {
                    $config["installedpackages"]["freeradius"]["config"][$item]['varuserspassword'] = "1111";
                    $changed = true;
                    $radcheck_entries[] = array('user'=>$user, 'pass'=>"1111", 'enc'=>($userentry['varuserspasswordencryption'] ?? 'Cleartext-Password'));
                    cp_wireless_log("Reset initial password: ".$userentry['varusersusername']." by ".cp_admin_actor());
                }
            }
        }
        if ($changed) {
            freeradius_users_resync();
            write_config("Reset password Wifi user");
        }
    } finally {
        unlock($cnf_lock);
    }
    // #23 step3-A: radcheck 동기화(락 밖, 단일 SQL). files 가 아직 권위라 동작변경 없음 — radcheck 최신 유지.
    if (function_exists('freeradius_radcheck_sync_users')) {
        freeradius_radcheck_sync_users($radcheck_entries);
    }
    // #49: 변경 이력 기록 (락 밖, 사용자별 1행 — 실제 PW 미기재)
    if (function_exists('cp_account_history_record') && !empty($radcheck_entries)) {
        $actor = cp_account_history_actor();
        $descs = array();
        foreach ($radcheck_entries as $rc) { $descs[] = "user={$rc['user']} password reset to initial value by {$actor}" . cp_account_history_tag($rc['user']); }
        cp_account_history_record('password_reset', $descs);
    }
}

function reset_random_wifi_user_pw($userlist){
    global $config;
    // 동시 PW 변경 lost-update 방지: 전용 락 + 락 안에서 config 최신본 재로딩 후 저장.
    $cnf_lock = lock('freeradius_user_config', LOCK_EX);
    $radcheck_entries = array();   // #23 step3-A
    try {
        $config = parse_config(true);
        $changed = false;
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
                    $newpass = implode($pass);
                    $config["installedpackages"]["freeradius"]["config"][$item]['varuserspassword'] = $newpass;
                    $changed = true;
                    $radcheck_entries[] = array('user'=>$user, 'pass'=>$newpass, 'enc'=>($userentry['varuserspasswordencryption'] ?? 'Cleartext-Password'));
                    cp_wireless_log("Reset random password: ".$userentry['varusersusername']." by ".cp_admin_actor());
                }
            }
        }
        if ($changed) {
            freeradius_users_resync();
            write_config("Reset password Wifi user");
        }
    } finally {
        unlock($cnf_lock);
    }
    // #23 step3-A: radcheck 동기화(락 밖, 단일 SQL). files 가 아직 권위라 동작변경 없음 — radcheck 최신 유지.
    if (function_exists('freeradius_radcheck_sync_users')) {
        freeradius_radcheck_sync_users($radcheck_entries);
    }
    // #49: 변경 이력 기록 (락 밖, 사용자별 1행 — 실제 PW 미기재)
    if (function_exists('cp_account_history_record') && !empty($radcheck_entries)) {
        $actor = cp_account_history_actor();
        $descs = array();
        foreach ($radcheck_entries as $rc) { $descs[] = "user={$rc['user']} password reset to random value by {$actor}" . cp_account_history_tag($rc['user']); }
        cp_account_history_record('password_reset', $descs);
    }
}

function create_wifi_user($dataamount, $vouchernumber, $israndompw, $terminaltype, $timeperiod, $issimplefied){
    global $config;

    // 동시 생성/변경 lost-update 방지: 전용 락 + 락 안에서 config 최신본 재로딩.
    $cnf_lock = lock('freeradius_user_config', LOCK_EX);
    try {
    $config = parse_config(true);

    $created_users = array();

    if (!isset($config['installedpackages']['freeradius']['config']) || !is_array($config['installedpackages']['freeradius']['config'])) {
        $config['installedpackages']['freeradius']['config'] = array();
    }
    if($issimplefied){
        if ($terminaltype == "") {
            $userprefix = 'usr';
        }
        else{
            $userprefix = getSyllableAbbr(strtolower($terminaltype));
        }
    }
    else{
        $userprefix = strtolower($terminaltype) . 'user';
    }
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
        if($issimplefied){
            $username = $userprefix . str_pad($usercount, 3, '0', STR_PAD_LEFT);
        }
        else{
            $username = $userprefix . str_pad($usercount, 5, '0', STR_PAD_LEFT);
        }
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

        // israndompw: true/false(bool) 또는 "true"/"false"(string) 수용. "randpwd" 구값도 truthy 라 자동 하위호환.
        $do_randpw = ($israndompw === true) || (is_string($israndompw) && !in_array(strtolower(trim($israndompw)), array('', '0', 'false', 'no', 'off'), true));
        if ($do_randpw) {
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

    freeradius_users_resync();
    write_config("Created Wifi user");
    } finally {
        unlock($cnf_lock);
    }
    // #23 step3-A: 생성 계정을 radcheck 에 일괄 동기화(락 밖, 단일 SQL). files 가 아직 권위라 동작변경 없음.
    if (function_exists('freeradius_radcheck_sync_users')) {
        $rc_entries = array();
        foreach ($created_users as $cu) {
            $rc_entries[] = array(
                'user' => $cu['varusersusername'] ?? '',
                'pass' => $cu['varuserspassword'] ?? '',
                'enc'  => $cu['varuserspasswordencryption'] ?? 'Cleartext-Password',
            );
        }
        freeradius_radcheck_sync_users($rc_entries);
    }
    // #49: 변경 이력 기록 (락 밖, 생성 사용자별 1행 — 실제 PW 미기재)
    if (function_exists('cp_account_history_record') && !empty($created_users)) {
        $actor = cp_account_history_actor();
        $do_randpw_log = ($israndompw === true) ||
            (is_string($israndompw) && !in_array(strtolower(trim($israndompw)), array('', '0', 'false', 'no', 'off'), true));
        $descs = array();
        foreach ($created_users as $cu) {
            $descs[] = 'user=' . ($cu['varusersusername'] ?? '')
                . ' created (quota=' . ($cu['varusersmaxtotaloctets'] ?? '') . 'MB'
                . ', period=' . trim(($cu['varusershalftimeperiod'] ?? '') . '-' . ($cu['varuserspointoftime'] ?? ''), '-')
                . ', terminaltype=' . ($cu['varusersterminaltype'] ?? '')
                . ', password=' . ($do_randpw_log ? 'random' : 'initial')
                . ') by ' . $actor . cp_account_history_tag($cu['varusersusername'] ?? '');
        }
        cp_account_history_record('user_create', $descs);
    }
    return $created_users;
}

function getSyllableAbbr(string $word, int $maxLen = 4): string {
    // CamelCase 감지 (대문자가 2개 이상이면)
    preg_match_all('/[A-Z]/', $word, $matches);
    if (count($matches[0]) > 1) {
        return implode('', $matches[0]);
    }

    // 음절 단위로 쪼개기 (자음* + 모음 패턴)
    preg_match_all('/[^aeiou]*[aeiou]+/i', $word, $syllables);
    if (empty($syllables[0])) {
        return strtoupper(substr($word, 0, $maxLen));
    }

    $abbr = implode('', array_map(function($s) { return strtoupper($s[0]); }, $syllables[0]));
    return strtolower(substr($abbr, 0, $maxLen));
}

?>