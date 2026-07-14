<?php
/*
 * openvpn_restart_timeperiod_check.php
 *
 * OpenVPN 클라이언트 watchdog (매분 cron).
 *
 * 목적(2가지):
 *   1) liveness — 터널이 데이터 경로를 못 넘기면(연속 실패) 해당 client 만 재시작.
 *   2) 강제 재시작 플래그 — 관리자/경로전환(manual_routing "Automatic", APIStatusOpenVPNRestart)이
 *      $config['openvpn']['openvpnrestart'] 를 세우면 모든 client 를 즉시 재시작
 *      (Starlink↔VSAT 등 업링크 전환 후 새 경로로 재바인딩).
 *
 * liveness 판정(근본 재설계 — 하드코딩 ICMP 제거):
 *   과거엔 client 마다 `ping -S <virtual_addr> <host>` 를 직접 쐈다. 그러나 FreeBSD 는 -S(소스)와
 *   무관하게 목적지 기준 라우팅이라, host 를 공인 엔드포인트로 두면 패킷이 WAN 으로 새 healthy 오판
 *   (→ 무재시작), 터널 내부 IP 로 두면 ICMP 정책/경로 미설치 시 healthy 인데 실패 오판(→ 상시 재시작)
 *   이 되는 양방향 취약성이 있었다. 어느 쪽이든 단일 하드코딩 IP·ICMP 정책·소스라우팅 전제에 매달린다.
 *   → 두 개의 전제 없는 신호로 대체:
 *     (a) 제어플레인: OpenVPN management `state` 가 'up'(CONNECTED) 이 아니면(reconnecting/down/...)
 *         불건전. 서버 도달 불가(업링크 전환 후 흔한 케이스)를 외부 probe 없이 직접 감지.
 *     (b) 데이터플레인: 'up' 이어도 해당 client 의 VPN 게이트웨이를 pfSense dpinger 가 'down' 으로
 *         보면 불건전(제어플레인은 붙었는데 데이터가 안 흐르는 wedged 케이스). dpinger 는 게이트웨이
 *         인터페이스에 바인딩돼 상시 감시하므로 WAN 누수·-S/목적지라우팅 전제가 없다(=올바른 ICMP).
 *     (c) 안전 강등: dpinger 판정이 없으면(게이트웨이 미모니터/미매핑) 제어플레인 'up' 을 신뢰하고
 *         재시작하지 않는다 → 오재시작 불가. (wedged 케이스 커버는 VPN 게이트웨이 모니터링에 의존.)
 *
 * 안정성 설계(기존 로직의 결함 교정):
 *   - per-client 판정(과거 last-wins 덮어쓰기 버그 제거).
 *   - 위성 링크의 단발 손실로 인한 불필요 재시작(flapping) 방지: 연속 OVWD_FAIL_THRESHOLD 회 불건전
 *     해야 재시작(dpinger 자체 디바운스 위에 한 겹 더). 재시작 후 OVWD_RESTART_COOLDOWN 동안 같은
 *     client 재시작 금지. (상태는 /var/run 파일 — config.xml 미사용이라 lost-update 무관, #16)
 *   - 단일 인스턴스 가드(#26)는 유지하되, hang 한 선행 인스턴스를 감지해 회수(reap)한다 → 가드가
 *     watchdog 자체를 영구히 죽이는 starvation(과거 #3 위험) 제거.
 *   - 플래그 정리는 try_lock('freeradius_user_config') 비블로킹 + parse_config(true) + delta 만 저장
 *     → PW writer 와 lost-update 안전(#22/#10), 락 못 잡으면 다음 주기로 미룸(블로킹 안 함).
 *   - 결정/사건을 system log 에 남겨 가시화(과거엔 완전 silent 라 "일부 선박 미동작"을 진단 불가했음).
 *     단 매분 스팸 방지를 위해 per-client 평시 상태는 디버그(/tmp/openvpn_watchdog_debug.on) 에서만.
 */

define('OVWD_DEBUG',            file_exists('/tmp/openvpn_watchdog_debug.on'));
// liveness 는 dpinger(게이트웨이 모니터) 판정 + OpenVPN management state 로 판정한다(하드코딩 ICMP
// 없음). dpinger 는 이미 게이트웨이 인터페이스에 바인딩돼 상시 감시하므로 -S/목적지라우팅/ICMP 정책
// 전제가 없다. VPN 게이트웨이가 dpinger 로 'down' 이면 그 client 만 재시작한다.
define('OVWD_FAIL_THRESHOLD',   3);     // 연속 불건전 N회 후 liveness 재시작 (cron 매분 → ~3분)
define('OVWD_RESTART_COOLDOWN', 300);   // 같은 client 재시작 최소 간격(초) — 재연결 시간 확보
define('OVWD_STATE_DIR',        '/var/run/openvpn_watchdog');
define('OVWD_STALE_HOLDER_SECS', 600);  // 선행 인스턴스가 이 시간 이상 살아있으면 hang 으로 보고 회수
define('OVWD_LOCK_WAIT',        10);    // 플래그 정리용 try_lock 대기(초)

// log_error(syslog) 가능하면 사용, 아니면 error_log 폴백. (require 전 가드에서 호출되면 후자.)
function ovwd_log($m) {
    if (function_exists('log_error')) { log_error("[openvpn-watchdog] " . $m); }
    else { error_log("[openvpn-watchdog] " . $m); }
}
function ovwd_dbg($m) { if (OVWD_DEBUG) { ovwd_log($m); } }
function ovwd_read_int($f) { $v = @file_get_contents($f); return ($v === false) ? 0 : (int)trim($v); }
function ovwd_write_int($f, $v) { @file_put_contents($f, (string)$v, LOCK_EX); }

// VPN client(vpnid) → 데이터플레인 상태('up'|'down'|'unknown') 맵을 pfSense dpinger 판정에서 구성.
//   하드코딩 ICMP 대신, pfSense 가 게이트웨이 인터페이스에 바인딩해 상시 감시 중인 결과를 재사용한다.
//   OpenVPN client 는 인터페이스가 ovpnc{vpnid} 이므로, 그 인터페이스에 매인 게이트웨이의 dpinger
//   status 를 vpnid 로 접는다.
//   - 'up'      : dpinger online (데이터 흐름 정상)
//   - 'down'    : dpinger down (제어플레인이 붙었어도 데이터플레인 사망 = wedged) — 단 force_down
//                 (관리자 강제 비활성)은 재시작 대상 아님 → 'unknown'
//   - 'unknown' : 미모니터/pending/none/미매핑 → 판정 보류(호출측이 제어플레인만으로 안전 강등)
//   gwlb/interfaces 함수가 없으면(버전 섞임) 빈 맵 → 전부 'unknown' 취급.
function ovwd_build_vpn_gw_health() {
    $map = array();
    if (!function_exists('return_gateways_array') || !function_exists('return_gateways_status')) {
        return $map;
    }
    $gws    = return_gateways_array();
    $gwstat = return_gateways_status(true);
    if (!is_array($gws)) { return $map; }
    foreach ($gws as $gwname => $gw) {
        // 이 게이트웨이가 매인 실인터페이스가 ovpnc{N} 인지 확인 → N = vpnid.
        $cands = array();
        if (function_exists('get_real_interface') && !empty($gw['interface'])) {
            $cands[] = (string)get_real_interface($gw['interface']);
        }
        if (isset($gw['interface']))     { $cands[] = (string)$gw['interface']; }
        if (isset($gw['friendlyiface'])) { $cands[] = (string)$gw['friendlyiface']; }
        $vid = null;
        foreach ($cands as $c) {
            if (preg_match('#ovpnc(\d+)#', $c, $m)) { $vid = (int)$m[1]; break; }
        }
        if ($vid === null) { continue; }   // openvpn client 게이트웨이 아님

        $state = 'unknown';
        $st = isset($gwstat[$gwname]) ? $gwstat[$gwname] : null;
        if (is_array($st) && isset($st['status'])) {
            $sub = isset($st['substatus']) ? (string)$st['substatus'] : '';
            if (stripos($st['status'], 'online') !== false) {
                $state = 'up';
            } elseif (stripos($st['status'], 'down') !== false) {
                $state = ($sub === 'force_down') ? 'unknown' : 'down';
            }
        }
        // 한 client 에 게이트웨이가 여럿이면 'down' 을 우선(보수적).
        if (!isset($map[$vid]) || $state === 'down') { $map[$vid] = $state; }
    }
    return $map;
}

// 가드에서만 쓰는 네이티브-PHP 전용 헬퍼(아직 openvpn.inc 미로드 → mwexec/log_error 사용 금지).
function ovwd_pid_alive($pid) {
    $pid = (int)$pid;
    if ($pid <= 0) { return false; }
    $o = array(); $rc = 1;
    @exec("/bin/kill -0 " . $pid . " 2>/dev/null", $o, $rc);
    return $rc === 0;
}
function ovwd_pid_is_script($pid, $needle) {
    $o = array();
    @exec("/bin/ps -p " . (int)$pid . " -o command= 2>/dev/null", $o);
    return strpos(implode(' ', $o), $needle) !== false;
}

// ── 단일 인스턴스 가드(#26) + hang 한 선행 인스턴스 회수(watchdog self-recovery) ──────────────
// 정상 실행은 수십 초 내 종료한다. 락을 못 잡았는데 보유자가 OVWD_STALE_HOLDER_SECS 이상
// '살아있고' '이 스크립트'이면 확실히 멈춘 것 → TERM/KILL 로 회수 후 락 재획득.
// (회수 대상은 ping/restart 에 멈춘 프로세스일 뿐 config write 중이 아님 → 안전. write 는 본 스크립트
//  말미 try_lock 구간에서만, 거기서 멈출 일은 없음.)
$__self     = basename(__FILE__);
$__lockpath = '/tmp/cron_' . basename(__FILE__, '.php') . '.lock';
$__fp = @fopen($__lockpath, 'c+');
if ($__fp === false) { exit(0); }

$__reaped_pid = 0; $__reaped_age = 0;
$__got = @flock($__fp, LOCK_EX | LOCK_NB);
if (!$__got) {
    @rewind($__fp);
    $__meta  = trim((string)@stream_get_contents($__fp));
    $__parts = $__meta === '' ? array() : preg_split('/\s+/', $__meta);
    $__hpid  = isset($__parts[0]) ? (int)$__parts[0] : 0;
    $__hstart = isset($__parts[1]) ? (int)$__parts[1] : 0;
    $__age   = $__hstart ? (time() - $__hstart) : 0;
    if ($__hpid > 0 && $__age >= OVWD_STALE_HOLDER_SECS &&
        ovwd_pid_alive($__hpid) && ovwd_pid_is_script($__hpid, $__self)) {
        @exec("/bin/kill -TERM " . $__hpid . " 2>/dev/null");
        sleep(2);
        if (ovwd_pid_alive($__hpid)) { @exec("/bin/kill -KILL " . $__hpid . " 2>/dev/null"); sleep(1); }
        for ($i = 0; $i < 3 && !$__got; $i++) {
            $__got = @flock($__fp, LOCK_EX | LOCK_NB);
            if (!$__got) { sleep(1); }
        }
        if ($__got) { $__reaped_pid = $__hpid; $__reaped_age = $__age; }
    }
    if (!$__got) { exit(0); }   // 정상 실행 중이거나 회수 실패 → 양보
}
// 락 확보 — 회수 판정용 메타(pid 시작시각) 기록. fd 는 종료 시 자동 unlock(닫지 않음).
@ftruncate($__fp, 0); @rewind($__fp); @fwrite($__fp, getmypid() . ' ' . time()); @fflush($__fp);

require_once("openvpn.inc");
require_once("gwlb.inc");        // return_gateways_array / return_gateways_status (dpinger 판정)
require_once("interfaces.inc");  // get_real_interface (게이트웨이 인터페이스 → ovpnc{vpnid})
global $config;

if (!empty($__reaped_pid)) {
    ovwd_log("STALE holder pid={$__reaped_pid} age={$__reaped_age}s reaped — watchdog self-recovery");
}

// 버전 섞임 방어: 코어 함수 없으면 조용히 종료(fatal 방지).
if (!function_exists('openvpn_get_active_clients') || !function_exists('openvpn_restart_by_vpnid')) {
    ovwd_log("openvpn.inc functions unavailable (deployment version mismatch?) — abort");
    exit(0);
}

@mkdir(OVWD_STATE_DIR, 0755, true);
if (!is_dir(OVWD_STATE_DIR) || !is_writable(OVWD_STATE_DIR)) {
    // 상태 저장 불가 시 liveness debounce 가 무력화(플래그 강제재시작은 계속 동작). 가시화만.
    ovwd_log("WARN state dir not writable: " . OVWD_STATE_DIR . " (liveness debounce degraded)");
}

$flag_set = isset($config['openvpn']['openvpnrestart']);   // 관리자/경로전환 강제 재시작 요청
$clients  = openvpn_get_active_clients();

if (!is_array($clients) || count($clients) === 0) {
    ovwd_dbg("no active openvpn clients configured");
    if ($flag_set) { ovwd_clear_flag(); }   // 재시작할 client 없어도 플래그는 정리
    exit(0);
}

$now         = time();
$restart_set = array();   // vpnid => reason

// VPN 게이트웨이 데이터플레인 상태(dpinger) 맵: vpnid => 'up'|'down'|'unknown'.
$gwhealth = ovwd_build_vpn_gw_health();

foreach ($clients as $client) {
    $vpnid = isset($client['vpnid']) ? preg_replace('/[^0-9A-Za-z_-]/', '', (string)$client['vpnid']) : '';
    if ($vpnid === '') { continue; }
    $status = isset($client['status']) ? (string)$client['status'] : 'down';

    $failfile = OVWD_STATE_DIR . "/fail-" . $vpnid;
    $rstfile  = OVWD_STATE_DIR . "/restart-" . $vpnid;

    // 헬스 판정(하드코딩 ICMP 제거):
    //   (a) 제어플레인: management state 가 'up'(CONNECTED) 이 아니면 불건전.
    //   (b) 데이터플레인: 'up' 이어도 이 client 의 VPN 게이트웨이가 dpinger 로 'down' 이면 불건전(wedged).
    //   (c) dpinger 판정이 'unknown'(미모니터/미매핑)이면 제어플레인 'up' 을 신뢰 → 재시작 안 함(안전 강등).
    $gw_state = isset($gwhealth[(int)$vpnid]) ? $gwhealth[(int)$vpnid] : 'unknown';
    if ($status !== 'up') {
        $healthy = false;
        ovwd_dbg("client {$vpnid} control-plane state={$status} → unhealthy");
    } elseif ($gw_state === 'down') {
        $healthy = false;
        ovwd_dbg("client {$vpnid} state=up but vpn gateway dpinger=down → unhealthy (wedged)");
    } else {
        $healthy = true;
        ovwd_dbg("client {$vpnid} state=up gw={$gw_state} → healthy");
    }

    if ($healthy) {
        if (ovwd_read_int($failfile) !== 0) { ovwd_write_int($failfile, 0); }
        continue;
    }

    // 실패 누적 + 임계/쿨다운 판정
    $fails = ovwd_read_int($failfile) + 1;
    ovwd_write_int($failfile, $fails);
    $last_restart = ovwd_read_int($rstfile);
    $cooldown_ok  = ($now - $last_restart) >= OVWD_RESTART_COOLDOWN;

    if ($fails >= OVWD_FAIL_THRESHOLD && $cooldown_ok) {
        $restart_set[$vpnid] = "liveness (state={$status}, gw={$gw_state}, fails={$fails})";
    } else {
        ovwd_dbg("client {$vpnid} unhealthy fails={$fails} cooldown_ok=" . ($cooldown_ok ? '1' : '0')
               . " (threshold " . OVWD_FAIL_THRESHOLD . ")");
    }
}

// 강제 재시작 플래그 → 모든 client 즉시(쿨다운 무시; 플래그는 1회성이라 폭주 없음).
if ($flag_set) {
    foreach ($clients as $client) {
        $vpnid = isset($client['vpnid']) ? preg_replace('/[^0-9A-Za-z_-]/', '', (string)$client['vpnid']) : '';
        if ($vpnid === '') { continue; }
        if (empty($restart_set[$vpnid])) { $restart_set[$vpnid] = 'flag (route change/admin)'; }
    }
}

// 실행
foreach ($restart_set as $vpnid => $reason) {
    ovwd_log("RESTART client vpnid={$vpnid} reason={$reason}");
    openvpn_restart_by_vpnid('client', $vpnid);
    ovwd_write_int(OVWD_STATE_DIR . "/restart-" . $vpnid, $now);
    ovwd_write_int(OVWD_STATE_DIR . "/fail-" . $vpnid, 0);
}

// 플래그 정리(lost-update 안전): 실제 플래그가 있을 때만 락 진입.
if ($flag_set) { ovwd_clear_flag(); }

exit(0);

// 강제 재시작 플래그 제거: try_lock(비블로킹) + parse_config(true) + delta 만 write → 동시 PW 변경 등을
// 덮지 않음(#22/#10). 락 못 잡으면 이번 주기 건너뜀(다음 주기 정리; 블로킹 안 함).
function ovwd_clear_flag() {
    global $config;
    $cl = function_exists('try_lock') ? try_lock('freeradius_user_config', OVWD_LOCK_WAIT)
                                      : lock('freeradius_user_config', LOCK_EX);
    if (!$cl) {
        ovwd_log("could not lock freeradius_user_config to clear openvpnrestart flag — retry next cycle");
        return;
    }
    try {
        $fresh = parse_config(true);
        if (isset($fresh['openvpn']['openvpnrestart'])) {
            unset($fresh['openvpn']['openvpnrestart']);
            $config = $fresh;
            write_config("openvpn_restart watchdog: clear restart flag");
        }
    } finally {
        unlock($cl);
    }
}
