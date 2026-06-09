<?php
/**
 * freeradius_enable_sql_authorize.php  (#23 step 1)
 *
 * authorize 섹션에서 SQL(radcheck) 조회를 켠다. 생성되는 사이트 설정은 이미
 *   files  →  (못 찾으면) sql(radcheck)
 * 순서로 되어 있으므로(freeradius.inc serverdefault_resync 템플릿), 이 토글을 켜면
 * files 에 없는 사용자가 radcheck 로 인증된다. files 에 있는 사용자는 그대로 files 로
 * 인증되므로 비파괴적이다(스테이징).
 *
 * 무엇을 바꾸나:
 *   $config['installedpackages']['freeradiussqlconf']['config'][0]
 *     - varsqlconfincludeenable  = 'on'      (이미 accounting 이 sql 을 쓰므로 보통 이미 on)
 *     - varsqlconfenableauthorize = 'Enable'  (← 이게 핵심 토글)
 *   이후 freeradius_sqlconf_resync() (= GUI 의 SQL 설정 저장 경로) 로 전체 config 재생성 +
 *   radiusd 재시작.
 *
 * 주의(꼭 읽을 것):
 *   1) 이 변경은 사이트 설정(구조) 변경이라 HUP 로는 반영되지 않는다. radiusd 가
 *      재시작되며 1813(accounting) 리스너가 잠깐 닫힌다(수초). 유지보수 시점에 실행 권장.
 *   2) authorize 에 sql 이 켜지면 sqlcounter(dailycounter/monthlycounter/noresetcounter/
 *      expire_on_login)도 authorize 에서 매 요청 실행된다. radcheck 에 한도(Max-*) check-item 이
 *      없으면 no-op 이라 거부되지 않는다. (이 단계의 마이그레이션은 비밀번호만 적재하므로 안전.)
 *      → 적용 후 반드시 `radiusd -X` 로 기존 files 사용자가 정상 인증되는지 검증할 것.
 *   3) files 가 먼저 매칭되므로, files 에도 있는 사용자는 radcheck 의 비번 변경이 즉시
 *      반영되지 않는다(files 우선). radcheck 를 권위 소스로 만드는 컷오버(step 3: files 쓰기
 *      중단 또는 sql 우선)는 별도 작업이다.
 *
 * 동시성 안전(#10/#22):
 *   PW writer 들과 같은 lock('freeradius_user_config') 를 잡고 parse_config(true) 로 최신본을
 *   재로딩한 뒤 수정 → lost-update(다른 writer 의 stale 스냅샷 저장) 방지.
 *
 * 적용 전 검증/롤백:
 *   플래그 적용 → serverdefault_resync()(파일만 생성, 재시작 X) → radiusd -C 검증.
 *   검증 실패 시 플래그를 원복하고 재생성 후 중단(재시작 안 함) → 인증 중단 사고 방지.
 *
 * 사용:
 *   php /usr/local/sbin/freeradius_enable_sql_authorize.php          # dry-run(현재/예정값만 출력)
 *   php /usr/local/sbin/freeradius_enable_sql_authorize.php apply     # SQL authorize 켜기 + 재시작
 *   php /usr/local/sbin/freeradius_enable_sql_authorize.php disable   # 원복(Disable) + 재시작
 *
 * 종료코드: 0=성공, 1=오류/검증실패
 */

require_once("config.inc");
require_once("util.inc");
require_once("freeradius.inc");

global $config, $argv;

function fre_out($msg) { echo $msg . "\n"; }

$arg = isset($argv[1]) ? strtolower((string)$argv[1]) : '';
$APPLY   = ($arg === 'apply');
$DISABLE = ($arg === 'disable');

// ---------------------------------------------------------------------------
// 락 + 최신 config 재로딩 (lost-update 방지)
// ---------------------------------------------------------------------------
$cfglock = lock('freeradius_user_config', LOCK_EX);
parse_config(true);

if (!is_array($config['installedpackages']['freeradiussqlconf']['config'][0] ?? null)) {
    unlock($cfglock);
    fre_out("[ERROR] freeradiussqlconf 설정이 없습니다. SQL(MySQL) 연결 설정이 선행되어야 합니다.");
    fre_out("        (accounting 이 sql 을 쓰고 있다면 이 설정은 이미 존재해야 합니다 — 점검 필요)");
    exit(1);
}

$cur = $config['installedpackages']['freeradiussqlconf']['config'][0];
$old_include   = $cur['varsqlconfincludeenable']  ?? '';
$old_authorize = $cur['varsqlconfenableauthorize'] ?? '';

fre_out("==========================================================");
fre_out(" FreeRADIUS authorize SQL(radcheck) 토글");
fre_out("----------------------------------------------------------");
fre_out(" 현재 varsqlconfincludeenable   = '" . $old_include . "'");
fre_out(" 현재 varsqlconfenableauthorize = '" . $old_authorize . "'");

// ---------------------------------------------------------------------------
// DRY-RUN
// ---------------------------------------------------------------------------
if (!$APPLY && !$DISABLE) {
    unlock($cfglock);
    fre_out("----------------------------------------------------------");
    fre_out(" [DRY-RUN] 변경하지 않았습니다.");
    fre_out("   켜기 : php " . __FILE__ . " apply    (→ includeenable=on, enableauthorize=Enable, 재시작)");
    fre_out("   끄기 : php " . __FILE__ . " disable  (→ enableauthorize=Disable, 재시작)");
    fre_out("==========================================================");
    exit(0);
}

$target_authorize = $APPLY ? 'Enable' : 'Disable';

// 멱등 체크
if ($APPLY && $old_authorize === 'Enable' && $old_include === 'on') {
    unlock($cfglock);
    fre_out(" [NO-OP] 이미 SQL authorize 가 켜져 있습니다.");
    exit(0);
}
if ($DISABLE && $old_authorize === 'Disable') {
    unlock($cfglock);
    fre_out(" [NO-OP] 이미 SQL authorize 가 꺼져 있습니다.");
    exit(0);
}

// ---------------------------------------------------------------------------
// 플래그 적용 + write_config (락 안에서)
// ---------------------------------------------------------------------------
if ($APPLY) {
    $config['installedpackages']['freeradiussqlconf']['config'][0]['varsqlconfincludeenable']  = 'on';
}
$config['installedpackages']['freeradiussqlconf']['config'][0]['varsqlconfenableauthorize'] = $target_authorize;

write_config("FreeRADIUS: authorize SQL(radcheck) 조회 {$target_authorize} (#23 step1)");
unlock($cfglock);

fre_out(" 적용 varsqlconfenableauthorize = '{$target_authorize}'");
fre_out("----------------------------------------------------------");

// ---------------------------------------------------------------------------
// 적용 전 검증: 사이트 설정만 먼저 생성(재시작 X) 후 radiusd -C
// ---------------------------------------------------------------------------
freeradius_serverdefault_resync();

$config_ok = true;
if (function_exists('freeradius_validate_radiusd_config')) {
    $config_ok = freeradius_validate_radiusd_config();
}

if (!$config_ok) {
    fre_out(" [VALIDATE FAILED] 생성된 radiusd 설정이 유효하지 않습니다. 롤백합니다(재시작 안 함).");

    // 롤백: 락 재획득 → 최신 재로딩 → 옛 값 복원 → 저장 → 사이트 재생성
    $cfglock2 = lock('freeradius_user_config', LOCK_EX);
    parse_config(true);
    $config['installedpackages']['freeradiussqlconf']['config'][0]['varsqlconfincludeenable']  = $old_include;
    $config['installedpackages']['freeradiussqlconf']['config'][0]['varsqlconfenableauthorize'] = $old_authorize;
    write_config("FreeRADIUS: authorize SQL 토글 롤백(검증 실패) (#23 step1)");
    unlock($cfglock2);
    freeradius_serverdefault_resync();

    fre_out("==========================================================");
    exit(1);
}

// ---------------------------------------------------------------------------
// 정식 적용: 전체 config 재생성 + radiusd 재시작 (GUI SQL 저장 경로와 동일)
// ---------------------------------------------------------------------------
fre_out(" [VALIDATE OK] 전체 재생성 + radiusd 재시작 진행...");
freeradius_sqlconf_resync();

fre_out("==========================================================");
fre_out(" 완료. 검증 권장:");
fre_out("   1) service radiusd onestop ; radiusd -X  (또는 로그)");
fre_out("      → radcheck 전용 사용자 로그인 시 Access-Accept,");
fre_out("        기존 files 사용자도 정상 인증(쿼터 거부 없음) 확인");
fre_out("   2) 끝나면: service radiusd onestart");
fre_out("==========================================================");
exit(0);
