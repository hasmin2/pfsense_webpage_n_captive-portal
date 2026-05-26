<?php
/**
 * cp_routing_setup.php
 *
 * pfctl 테이블 기반 라우팅 초기 설정 스크립트 (1회 실행).
 *
 * 수행 내용:
 *   1. pfSense Alias 생성: vsat_users, starlink_users (Host 타입)
 *   2. Floating Rule 생성:
 *      - pass in  LAN  route-to VSAT_GW      src: vsat_users
 *      - pass in  LAN  route-to STARLINK_GW  src: starlink_users
 *      - block out STARLINK_WAN              src: vsat_users
 *      - block out VSAT_WAN                  src: starlink_users
 *   3. config.xml 저장 및 filter 재로드
 *
 * 실행:
 *   /usr/local/bin/php /usr/local/cron/cp_routing_setup.php
 *
 * 중복 실행 안전: descr 기준으로 이미 존재하는 항목은 건너뜀.
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
 * terminal_type 으로 gateway 목록 조회.
 * VPN 게이트웨이는 제외.
 */
function find_gateways_by_type($type_keywords) {
    global $config;
    $result = [];

    foreach ($config['gateways']['gateway_item'] ?? [] as $gw) {
        $ttype = strtolower($gw['terminal_type'] ?? '');
        $name  = $gw['name'] ?? '';
        if ($name === '' || $ttype === '') continue;
        if (strpos($ttype, 'vpn') !== false) continue;

        foreach ((array)$type_keywords as $kw) {
            if (strpos($ttype, $kw) !== false) {
                $result[] = $gw;
                break;
            }
        }
    }

    return $result;
}

/**
 * gateway 목록에서 primary 우선, 없으면 첫 번째 반환.
 */
function pick_primary_gateway(array $gateways) {
    foreach ($gateways as $gw) {
        $ttype = strtolower($gw['terminal_type'] ?? '');
        if (strpos($ttype, '_pri') !== false) return $gw;
    }
    return $gateways[0] ?? null;
}

/**
 * gateway 목록에서 중복 없이 interface key 목록 반환.
 * (config interface key: 'wan', 'opt1', ... pfSense 기준)
 */
function get_iface_keys(array $gateways) {
    $ifaces = [];
    foreach ($gateways as $gw) {
        $iface = $gw['interface'] ?? '';
        if ($iface !== '' && !in_array($iface, $ifaces, true)) {
            $ifaces[] = $iface;
        }
    }
    return $ifaces;
}

/**
 * Alias가 이미 존재하는지 이름으로 확인.
 */
function alias_exists($name) {
    global $config;
    foreach ($config['aliases']['alias'] ?? [] as $alias) {
        if (($alias['name'] ?? '') === $name) return true;
    }
    return false;
}

/**
 * Floating Rule이 이미 존재하는지 descr 로 확인.
 */
function floating_rule_exists($descr) {
    global $config;
    foreach ($config['filter']['rule'] ?? [] as $rule) {
        if (isset($rule['floating']) && ($rule['descr'] ?? '') === $descr) return true;
    }
    return false;
}

/**
 * Alias 생성 후 config 배열에 추가.
 */
function create_alias($name, $descr) {
    global $config;

    if (alias_exists($name)) {
        setup_log("SKIP alias '{$name}' (already exists)");
        return false;
    }

    if (!is_array($config['aliases']['alias'])) {
        $config['aliases']['alias'] = [];
    }

    $config['aliases']['alias'][] = [
        'name'   => $name,
        'type'   => 'host',
        'address'=> '',
        'descr'  => $descr,
        'detail' => '',
    ];

    setup_log("CREATE alias '{$name}'");
    return true;
}

/**
 * Floating Pass Rule (route-to) 생성.
 * $iface   : LAN interface key (captiveportal crew interface)
 * $src_alias: alias 이름 (source)
 * $gateway : gateway name
 * $descr   : 룰 설명
 */
function create_pass_rule($iface, $src_alias, $gateway, $descr) {
    global $config;

    if (floating_rule_exists($descr)) {
        setup_log("SKIP pass rule '{$descr}' (already exists)");
        return false;
    }

    $rule = [
        'id'             => '',
        'tracker'        => (string)(time() + rand(1, 9999)),
        'type'           => 'pass',
        'floating'       => 'yes',
        'direction'      => 'in',
        'quick'          => 'yes',
        'interface'      => $iface,
        'ipprotocol'     => 'inet',
        'tag'            => '',
        'tagged'         => '',
        'statetype'      => 'keep state',
        'source'         => ['address' => $src_alias],
        'destination'    => ['any' => true],
        'gateway'        => $gateway,
        'descr'          => $descr,
        'updated'        => ['time' => (string)time(), 'username' => 'cp_routing_setup'],
        'created'        => ['time' => (string)time(), 'username' => 'cp_routing_setup'],
    ];

    // Floating pass rule 은 앞에 삽입 (우선순위)
    array_unshift($config['filter']['rule'], $rule);

    setup_log("CREATE pass rule: [{$iface}] in / src={$src_alias} / gw={$gateway}");
    return true;
}

/**
 * Floating Block Rule 생성.
 * $iface    : WAN interface key (차단할 출구)
 * $src_alias: alias 이름 (source)
 * $descr    : 룰 설명
 */
function create_block_rule($iface, $src_alias, $descr) {
    global $config;

    if (floating_rule_exists($descr)) {
        setup_log("SKIP block rule '{$descr}' (already exists)");
        return false;
    }

    $rule = [
        'id'             => '',
        'tracker'        => (string)(time() + rand(1, 9999)),
        'type'           => 'block',
        'floating'       => 'yes',
        'direction'      => 'out',
        'quick'          => 'yes',
        'interface'      => $iface,
        'ipprotocol'     => 'inet',
        'tag'            => '',
        'tagged'         => '',
        'source'         => ['address' => $src_alias],
        'destination'    => ['any' => true],
        'descr'          => $descr,
        'updated'        => ['time' => (string)time(), 'username' => 'cp_routing_setup'],
        'created'        => ['time' => (string)time(), 'username' => 'cp_routing_setup'],
    ];

    // Block rule 도 앞에 삽입
    array_unshift($config['filter']['rule'], $rule);

    setup_log("CREATE block rule: [{$iface}] out / src={$src_alias} BLOCKED");
    return true;
}

// ================================================================
// 메인 로직
// ================================================================

setup_log("=== cp_routing_setup 시작 ===");

// ---- 1. Gateway 탐색 ----

$vsat_gws     = find_gateways_by_type(['vsat', 'nexuswave']);
$starlink_gws = find_gateways_by_type(['starlink']);

if (empty($vsat_gws)) {
    setup_log("ERROR: VSAT gateway 를 찾을 수 없음 (terminal_type 확인 필요)");
    exit(1);
}
if (empty($starlink_gws)) {
    setup_log("ERROR: Starlink gateway 를 찾을 수 없음 (terminal_type 확인 필요)");
    exit(1);
}

$vsat_primary     = pick_primary_gateway($vsat_gws);
$starlink_primary = pick_primary_gateway($starlink_gws);

// route-to 차단 대상 인터페이스 목록
$vsat_ifaces     = get_iface_keys($vsat_gws);
$starlink_ifaces = get_iface_keys($starlink_gws);

// CP LAN interface (captiveportal crew 존재하면 사용, 없으면 'lan')
$lan_iface = $config['captiveportal']['crew']['interface'] ?? 'lan';

setup_log("VSAT primary gateway  : {$vsat_primary['name']} (ifaces: " . implode(',', $vsat_ifaces) . ")");
setup_log("Starlink primary gw   : {$starlink_primary['name']} (ifaces: " . implode(',', $starlink_ifaces) . ")");
setup_log("CP LAN interface      : {$lan_iface}");
echo "\n";

// ---- 2. Alias 생성 ----

$changed = false;

$changed |= create_alias(
    'vsat_users',
    'VSAT active users - managed by pfctl (cp_routing_table_sync)'
);
$changed |= create_alias(
    'starlink_users',
    'Starlink active users - managed by pfctl (cp_routing_table_sync)'
);

echo "\n";

// ---- 3. Floating Pass Rule (route-to) 생성 ----

$changed |= create_pass_rule(
    $lan_iface,
    'vsat_users',
    $vsat_primary['name'],
    '[CP Routing] vsat_users route-to ' . $vsat_primary['name']
);

$changed |= create_pass_rule(
    $lan_iface,
    'starlink_users',
    $starlink_primary['name'],
    '[CP Routing] starlink_users route-to ' . $starlink_primary['name']
);

echo "\n";

// ---- 4. Floating Block Rule 생성 ----
// vsat_users 가 Starlink WAN 으로 나가는 것 차단
foreach ($starlink_ifaces as $iface) {
    $changed |= create_block_rule(
        $iface,
        'vsat_users',
        "[CP Routing] block vsat_users on {$iface} (Starlink)"
    );
}

// starlink_users 가 VSAT WAN 으로 나가는 것 차단
foreach ($vsat_ifaces as $iface) {
    $changed |= create_block_rule(
        $iface,
        'starlink_users',
        "[CP Routing] block starlink_users on {$iface} (VSAT)"
    );
}

echo "\n";

// ---- 5. 저장 및 적용 ----

if ($changed) {
    write_config("cp_routing_setup: created pfctl routing aliases and floating rules");
    setup_log("config.xml 저장 완료");

    filter_configure();
    setup_log("filter_configure() 완료 (pf 룰셋 재로드)");

    // 초기 테이블 동기화
    cp_sync_routing_tables();
    setup_log("pfctl 테이블 초기 동기화 완료");
} else {
    setup_log("변경 사항 없음 (모두 이미 존재)");
}

setup_log("=== cp_routing_setup 완료 ===");
