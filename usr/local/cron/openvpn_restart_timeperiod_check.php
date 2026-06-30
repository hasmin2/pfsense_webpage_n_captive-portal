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
 * 안정성 설계(기존 로직의 결함 교정):
 *   - per-client 판정(과거 last-wins 덮어쓰기 버그 제거). virtual_addr 부재 시 malformed ping 대신
 *     status 로 판정.
 *   - 위성 링크의 단발 패킷손실로 인한 불필요 재시작(flapping) 방지: ping 3패킷 중 1개라도 응답하면
 *     정상으로 보고, 연속 OVWD_FAIL_THRESHOLD 회 실패해야 재시작. 재시작 후 OVWD_RESTART_COOLDOWN
 *     동안 같은 client 재시작 금지. (상태는 /var/run 파일 — config.xml 미사용이라 lost-update 무관, #16)
 *   - 외부 명령(ping)은 timeout 으로 하드 바운드 → 본문이 hang 하지 않음.
 *   - 단일 인스턴스 가드(#26)는 유지하되, hang 한 선행 인스턴스를 감지해 회수(reap)한다 → 가드가
 *     watchdog 자체를 영구히 죽이는 starvation(과거 #3 위험) 제거.
 *   - 플래그 정리는 try_lock('freeradius_user_config') 비블로킹 + parse_config(true) + delta 만 저장
 *     → PW writer 와 lost-update 안전(#22/#10), 락 못 잡으면 다음 주기로 미룸(블로킹 안 함).
 *   - 결정/사건을 system log 에 남겨 가시화(과거엔 완전 silent 라 "일부 선박 미동작"을 진단 불가했음).
 *     단 매분 스팸 방지를 위해 per-client 평시 상태는 디버그(/tmp/openvpn_watchdog_debug.on) 에서만.
 */

define('OVWD_DEBUG',            file_exists('/tmp/openvpn_watchdog_debug.on'));
define('OVWD_PING_HOST',        'vpn-server.synersat.noc');
define('OVWD_FAIL_THRESHOLD',   3);     // 연속 실패 N회 후 liveness 재시작 (cron 매분 → ~3분)
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

// ping 명령 구성(경로 부재 방어): timeout/ping 바이너리가 없으면 폴백 — 거짓 실패(rc=127)로
// 멀쩡한 client 를 무더기 재시작하는 사고 방지. ping 자체의 -t8 이 내부 타임아웃이라 timeout 없어도 안전.
$ping_bin    = file_exists('/sbin/ping') ? '/sbin/ping' : 'ping';
$timeout_pfx = file_exists('/usr/bin/timeout') ? '/usr/bin/timeout 12 ' : '';

foreach ($clients as $client) {
    $vpnid = isset($client['vpnid']) ? preg_replace('/[^0-9A-Za-z_-]/', '', (string)$client['vpnid']) : '';
    if ($vpnid === '') { continue; }
    $status = isset($client['status']) ? (string)$client['status'] : 'down';
    $addr   = isset($client['virtual_addr']) ? trim((string)$client['virtual_addr']) : '';

    $failfile = OVWD_STATE_DIR . "/fail-" . $vpnid;
    $rstfile  = OVWD_STATE_DIR . "/restart-" . $vpnid;

    // 헬스 판정: 'up' + virtual_addr 있을 때만 터널 경유 ping. 3패킷 중 1개라도 응답하면 정상(ping
    // exit 0). timeout 12 로 외부 하드바운드(-t8 이중 안전). 그 외 상태(down/connecting/...)는 미연결.
    $healthy = false;
    if ($status === 'up' && $addr !== '') {
        $cmd = $timeout_pfx . $ping_bin . " -c3 -t8 -S " . escapeshellarg($addr) . " "
             . escapeshellarg(OVWD_PING_HOST) . " > /dev/null 2>&1";
        $rc = mwexec($cmd);
        $healthy = ($rc === 0);
        ovwd_dbg("client {$vpnid} status=up addr={$addr} ping rc={$rc} healthy=" . ($healthy ? '1' : '0'));
    } else {
        ovwd_dbg("client {$vpnid} status={$status} addr='{$addr}' → not up");
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
        $restart_set[$vpnid] = "liveness (status={$status}, fails={$fails})";
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
