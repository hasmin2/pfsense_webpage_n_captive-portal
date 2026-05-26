<?php
/**
 * cp_routing_setup.php
 *
 * pfctl 테이블 기반 라우팅 초기 설정 스크립트 (1회 실행).
 *
 * 수행 내용:
 *   1. pfSense Alias 생성: 게이트웨이당 1개 (cp_gw_{name}, Host 타입)
 *   2. Floating Rule 생성:
 *      - pass in  LAN   route-to {GW}        src: cp_gw_{name}
 *      - block out {타 WAN 인터페이스}        src: cp_gw_{name}
 *   3. config.xml 저장 및 filter 재로드
 *
 * 실행:
 *   /usr/local/bin/php /usr/local/cron/cp_routing_setup.php
 *
 * 중복 실행 안전: descr/name 기준으로 이미 존재하는 항목은 건너뜀.
 * 게이트웨이 추가/삭제 시 재실행하면 자동으로 alias/rule 이 갱신됨.
 *
 * 대상 게이트웨이 조건 (cp_find_all_wan_gateways 와 동일):
 *   - terminal_type 이 설정된 게이트웨이
 *   - disabled 가 아닌 것
 *   - terminal_type 에 'vpn' 이 없는 것
 */

require_once("captiveportal.inc");
require_once("filter.inc");
require_once("util.inc");
require_once("gwlb.inc");

init_config_arr(['captiveportal', 'filter', 'aliases', 'gateways']);

global $config;

// ----------------------------------------------------------------
// 헬퍼
// ----------------------------------------------------------------

function setup_log($msg) {
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$msg}\n";
}

/**
 * Alias가 이미 존재하는지 이름으로 확인.
 */
function alias_exists_local($name) {
    global $config;
    foreach ($config['aliases']['alias'] ?? [] as $alias) {
        if (($alias['name'] ?? '') === $name) return true;
    }
    return false;
}

/**
 * Floating Rule이 이미 존재하는지 descr 로 확인.
 */
function floating_rule_exists_local($descr) {
    global $config;
    foreach ($config['filter']['rule'] ?? [] as $rule) {
        if (isset($rule['floating']) && ($rule['descr'] ?? '') === $descr) return true;
    }
    return false;
}

/**
 * Alias 생성 후 config 배열에 추가.
 */
function create_alias_local($name, $descr) {
    global $config;

    if (alias_exists_local($name)) {
        setup_log("SKIP alias '{$name}' (already exists)");
        return false;
    }

    if (!is_array($config['aliases']['alias'])) {
        $config['aliases']['alias'] = [];
    }

    $config['aliases']['alias'][] = [
        'name'    => $name,
        'type'    => 'host',
        'address' => '',
        'descr'   => $descr,
        'detail'  => '',
    ];

    setup_log("CREATE alias '{$name}'");
    return true;
}

/**
 * Floating Pass Rule (route-to) 생성.
 */
function create_pass_rule_local($iface, $src_alias, $gateway, $descr) {
    global $config;

    if (floating_rule_exists_local($descr)) {
        setup_log("SKIP pass rule '{$descr}' (already exists)");
        return false;
    }

    $rule = [
        'id'          => '',
        'tracker'     => (string)(time() + rand(1, 9999)),
        'type'        => 'pass',
        'floating'    => 'yes',
        'direction'   => 'in',
        'quick'       => 'yes',
        'interface'   => $iface,
        'ipprotocol'  => 'inet',
        'tag'         => '',
        'tagged'      => '',
        'statetype'   => 'keep state',
        'source'      => ['address' => $src_alias],
        'destination' => ['any' => true],
        'descr'       => $descr,
        'updated'     => ['time' => (string)time(), 'username' => 'cp_routing_setup'],
        'created'     => ['time' => (string)time(), 'username' => 'cp_routing_setup'],
    ];
    // gateway 지정 시에만 route-to 추가 (비어있으면 기본 라우팅)
    if ($gateway !== '') {
        $rule['gateway'] = $gateway;
    }

    array_unshift($config['filter']['rule'], $rule);

    setup_log("CREATE pass rule: [{$iface}] in / src={$src_alias} / gw={$gateway}");
    return true;
}

/**
 * Floating Block Rule 생성.
 */
function create_block_rule_local($iface, $src_alias, $descr) {
    global $config;

    if (floating_rule_exists_local($descr)) {
        setup_log("SKIP block rule '{$descr}' (already exists)");
        return false;
    }

    $rule = [
        'id'          => '',
        'tracker'     => (string)(time() + rand(1, 9999)),
        'type'        => 'block',
        'floating'    => 'yes',
        'direction'   => 'out',
        'quick'       => 'yes',
        'interface'   => $iface,
        'ipprotocol'  => 'inet',
        'tag'         => '',
        'tagged'      => '',
        'source'      => ['address' => $src_alias],
        'destination' => ['any' => true],
        'descr'       => $descr,
        'updated'     => ['time' => (string)time(), 'username' => 'cp_routing_setup'],
        'created'     => ['time' => (string)time(), 'username' => 'cp_routing_setup'],
    ];

    array_unshift($config['filter']['rule'], $rule);

    setup_log("CREATE block rule: [{$iface}] out / src={$src_alias} BLOCKED");
    return true;
}

// ================================================================
// 메인 로직
// ================================================================

setup_log("=== cp_routing_setup 시작 ===");

// ---- 1. WAN 게이트웨이 목록 조회 ----

$all_gws = cp_find_all_wan_gateways();

if (empty($all_gws)) {
    setup_log("ERROR: WAN 게이트웨이를 찾을 수 없음");
    setup_log("       조건: terminal_type 설정됨 + disabled 아님 + terminal_type 에 'vpn' 없음");
    exit(1);
}

setup_log("발견된 WAN 게이트웨이 목록:");
foreach ($all_gws as $gw) {
    $table = cp_gw_table_name($gw['name']);
    setup_log("  - {$gw['name']} | interface={$gw['interface']} | terminal_type={$gw['terminal_type']} | table={$table}");
}

// CP LAN interface
$lan_iface = $config['captiveportal']['crew']['interface'] ?? 'lan';
setup_log("CP LAN interface: {$lan_iface}");
echo "\n";

// 모든 WAN 인터페이스 목록 (블록룰 생성용)
$all_ifaces = array_unique(array_column($all_gws, 'interface'));

$changed = false;

// ---- 2. Alias 생성 ----

setup_log("--- Alias 생성 ---");

// 기본 게이트웨이용 alias (terminaltype 미설정 사용자)
$changed |= create_alias_local(
    'cp_gw_default',
    'CP default gateway users (no terminaltype assigned)'
);

foreach ($all_gws as $gw) {
    $table = cp_gw_table_name($gw['name']);
    $changed |= create_alias_local(
        $table,
        "CP routing table for gateway {$gw['name']} (terminal_type={$gw['terminal_type']})"
    );
}
echo "\n";

// ---- 3. Floating Pass Rule 생성 ----

setup_log("--- Pass Rule 생성 ---");

// 기본 게이트웨이 pass 룰 (route-to 없음 → 시스템 기본 게이트웨이 사용)
$changed |= create_pass_rule_local(
    $lan_iface,
    'cp_gw_default',
    '',   // gateway 없음 = 기본 라우팅
    '[CP Routing] cp_gw_default pass (default gateway)'
);

foreach ($all_gws as $gw) {
    $table = cp_gw_table_name($gw['name']);
    $changed |= create_pass_rule_local(
        $lan_iface,
        $table,
        $gw['name'],
        "[CP Routing] {$table} route-to {$gw['name']}"
    );
}
echo "\n";

// ---- 4. 게이트웨이별 Floating Block Rule 생성 ----
// 각 게이트웨이 사용자가 자신의 WAN이 아닌 다른 WAN으로 나가는 것을 차단

setup_log("--- Block Rule 생성 (타 WAN 차단) ---");
foreach ($all_gws as $gw) {
    $table    = cp_gw_table_name($gw['name']);
    $gw_iface = $gw['interface'];

    foreach ($all_ifaces as $iface) {
        if ($iface === $gw_iface) continue; // 자신의 WAN 은 차단 안 함

        $changed |= create_block_rule_local(
            $iface,
            $table,
            "[CP Routing] block {$table} on {$iface}"
        );
    }
}
echo "\n";

// ---- 5. 저장 및 적용 ----

if ($changed) {
    write_config("cp_routing_setup: created aliases and floating rules for " . count($all_gws) . " gateways");
    setup_log("config.xml 저장 완료");

    filter_configure();
    setup_log("filter_configure() 완료 (pf 룰셋 재로드)");

    // 초기 테이블 동기화
    cp_sync_routing_tables();
    setup_log("pfctl 테이블 초기 동기화 완료");
} else {
    setup_log("변경 사항 없음 (모두 이미 존재)");
    // 테이블만 동기화
    cp_sync_routing_tables();
    setup_log("pfctl 테이블 동기화 완료");
}

echo "\n";
setup_log("=== 생성된 룰 요약 ===");
$pass_count  = 0;
$block_count = 0;
foreach ($config['filter']['rule'] ?? [] as $rule) {
    if (!isset($rule['floating'])) continue;
    if (strpos($rule['descr'] ?? '', '[CP Routing]') === false) continue;
    if ($rule['type'] === 'pass')  $pass_count++;
    if ($rule['type'] === 'block') $block_count++;
}
setup_log("  Pass  Rules: {$pass_count} 개");
setup_log("  Block Rules: {$block_count} 개");
setup_log("  합계        : " . ($pass_count + $block_count) . " 개");
echo "\n";
setup_log("=== cp_routing_setup 완료 ===");
