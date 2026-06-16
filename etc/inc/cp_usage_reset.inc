<?php
/*
 * cp_usage_reset.inc
 *
 * CREW WIFI 사용량 리셋의 "자가복구(self-healing) 날짜키" 계층.
 *
 * 배경:
 *   주기 리셋 크론(daily/weekly/halfmonthly/monthly)은 주기 경계(자정 등)에 1회 발화한다.
 *   표준 cron 은 catch-up 이 없어 NTP 시각 점프/재부팅/고부하로 그 분을 놓치면 해당 주기
 *   리셋이 통째로 누락된다(특히 monthly = 최대 한 달 누락). 더블-런(분 0,1)으로도 2분 내
 *   둘 다 놓치면 막지 못한다.
 *
 * 설계:
 *   - 유저별 "마지막 리셋 주기키"를 파일에 저장한다. config.xml 을 쓰지 않으므로 매분 writer
 *     크론(manual_routing/network_usage 등)과 동시 실행해도 lost-update 와 무관하다.
 *   - 경계 크론은 리셋 직후 cp_reset_mark_user() 로 현재 주기키를 마킹한다.
 *   - 자가복구 크론(crew_usage_reset_selfheal.php)이 주기적으로 돌며, 현재 주기키가 저장키보다
 *     "엄격히 최신"인데 마킹이 안 돼 있으면(= 경계 크론이 놓침) 즉시 보충 리셋한다.
 *   - 최초 관측(키 파일 없음)은 '이번 주기는 이미 처리된 것'으로 간주하고 마킹만 한다
 *     (배포 직후 진행 중 주기의 사용량을 잘못 0 으로 만들지 않기 위함).
 *   - 시각 역행(키가 저장키보다 과거)일 때는 리셋하지 않는다(오리셋 방지).
 *
 * 주기키 형식(타입 접두사 + zero-pad → 사전식 비교로 신/구 판정 가능):
 *   daily          : "D:YYYY-MM-DD"
 *   weekly         : "W:YYYY-MM-DD"   (해당 주의 일요일 날짜; 리셋 경계가 일요일이므로)
 *   monthly        : "M:YYYY-MM"
 *   monthly+half   : "H:YYYY-MM-H1" | "H:YYYY-MM-H2"
 *   기타(forever 등): ""  → 자가복구 비대상
 *
 * prepaid(crewpay-*) 제외:
 *   prepaid 월 리셋은 사용량뿐 아니라 maxtotaloctets='0'(할당 만료)까지 수행한다. 이는 config
 *   쓰기가 필요해 본 계층(파일 기반, usage-only)의 의미와 다르므로 자가복구 대상에서 제외한다.
 */

function cp_reset_state_dir(): string {
    return "/var/log/radacct/datacounter/reset-state";
}

function cp_reset_safe_user(string $u): string {
    return preg_replace('/[^0-9A-Za-z._:-]/', 'X', $u);
}

/**
 * 주어진 타입의 "현재 주기키"를 반환. 비주기 타입이면 빈 문자열.
 */
function cp_reset_period_key(string $pointoftime, string $halfperiod = '', ?int $now = null): string {
    $now  = $now ?? time();
    $pt   = strtolower(trim($pointoftime));
    $half = strtolower(trim($halfperiod));

    switch ($pt) {
        case 'daily':
            return 'D:' . date('Y-m-d', $now);

        case 'weekly':
            // 리셋 경계 = 일요일. 해당 주(오늘 포함)의 일요일 날짜를 키로 사용.
            $w        = (int)date('w', $now);                 // 0=일 .. 6=토
            $midnight = strtotime(date('Y-m-d', $now));
            $sunday   = strtotime("-{$w} days", $midnight);
            return 'W:' . date('Y-m-d', $sunday);

        case 'monthly':
            if ($half === 'half') {
                $day = (int)date('j', $now);
                return 'H:' . date('Y-m', $now) . ($day < 15 ? '-H1' : '-H2');
            }
            return 'M:' . date('Y-m', $now);

        default:
            return '';   // forever 등 비주기 → 대상 아님
    }
}

function cp_reset_state_file(string $username, string $pointoftime): string {
    return cp_reset_state_dir() . '/' . strtolower(trim($pointoftime)) . '-' . cp_reset_safe_user($username);
}

function cp_reset_get_marked_key(string $username, string $pointoftime): string {
    $f = cp_reset_state_file($username, $pointoftime);
    if (is_file($f) && is_readable($f)) {
        return trim((string)@file_get_contents($f));
    }
    return '';
}

function cp_reset_mark_key(string $username, string $pointoftime, string $key): bool {
    $dir = cp_reset_state_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $f   = cp_reset_state_file($username, $pointoftime);
    $tmp = $f . '.tmp';
    if (@file_put_contents($tmp, $key . "\n") === false) {
        return false;
    }
    return @rename($tmp, $f);   // 원자적 교체
}

/**
 * 경계 크론용: 리셋을 수행한 유저의 현재 주기키를 마킹.
 * 이렇게 마킹해 두면 자가복구 크론이 같은 주기를 중복 리셋하지 않는다.
 */
function cp_reset_mark_user(string $username, string $pointoftime, string $halfperiod = '', ?int $now = null): void {
    $username = trim($username);
    if ($username === '') {
        return;
    }
    $key = cp_reset_period_key($pointoftime, $halfperiod, $now);
    if ($key !== '') {
        cp_reset_mark_key($username, $pointoftime, $key);
    }
}

/**
 * 단일 유저 자가복구 판정 + 리셋.
 * config.xml 을 쓰지 않으므로 매분 writer 크론과 동시 실행해도 lost-update 무관.
 *
 * @return bool 실제로 리셋을 수행했으면 true.
 */
function cp_reset_user_if_due(array $userEntry, ?int $now = null): bool {
    $username = trim((string)($userEntry['varusersusername'] ?? ''));
    if ($username === '') {
        return false;
    }
    // prepaid 는 의미(할당 0)가 달라 자가복구(usage-only) 비대상 — 전용 크론이 처리.
    if (strncasecmp($username, 'crewpay-', 8) === 0) {
        return false;
    }

    $pt   = strtolower(trim((string)($userEntry['varuserspointoftime'] ?? '')));
    $half = strtolower(trim((string)($userEntry['varusershalftimeperiod'] ?? '')));

    $key = cp_reset_period_key($pt, $half, $now);
    if ($key === '') {
        return false;   // 비주기(forever 등) → 대상 아님
    }

    $marked = cp_reset_get_marked_key($username, $pt);

    if ($marked === '') {
        // 최초 관측: 진행 중 주기를 '이미 처리됨'으로 간주하고 마킹만(배포 직후 오리셋 방지).
        cp_reset_mark_key($username, $pt, $key);
        return false;
    }
    if ($marked === $key) {
        return false;   // 이번 주기 이미 처리됨(경계 크론 또는 이전 자가복구가 마킹).
    }
    if (strcmp($key, $marked) <= 0) {
        // 현재 키가 저장키보다 최신이 아님(시각 역행 등) → 오리셋 방지로 skip.
        return false;
    }

    // 주기 경계를 넘었는데 마킹이 안 됨 = 경계 크론이 놓침 → 자가복구 보충 리셋.
    if (!function_exists('captiveportal_reset_user_usage')) {
        return false;
    }
    $ok = captiveportal_reset_user_usage($username);
    if ($ok) {
        cp_reset_mark_key($username, $pt, $key);
        if (function_exists('cp_wireless_log')) {
            cp_wireless_log("SELF-HEAL reset (missed {$pt} boundary): {$username} [{$marked} -> {$key}]");
        }
        return true;
    }
    // 리셋 실패(예: 활성세션 잔존 가드) → 키 미갱신 → 다음 틱 재시도.
    return false;
}

/**
 * 전체 유저 자가복구 점검. 자가복구 크론에서 호출.
 * @return array 리셋된 username 목록.
 */
function cp_usage_reset_run_selfheal(?int $now = null): array {
    global $config, $cpzone;
    if (empty($cpzone)) {
        $cpzone = 'crew';
    }
    $done  = array();
    $users = $config['installedpackages']['freeradius']['config'] ?? null;
    if (!is_array($users)) {
        return $done;
    }
    foreach ($users as $u) {
        if (!is_array($u)) {
            continue;
        }
        if (cp_reset_user_if_due($u, $now)) {
            $done[] = trim((string)($u['varusersusername'] ?? ''));
        }
    }
    return $done;
}
