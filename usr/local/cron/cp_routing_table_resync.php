<?php
/*
 * cp_routing_table_resync.php
 *
 * cp_gw_* pfctl 라우팅 테이블 주기 재동기화(안전망, 매분 실행).
 *
 * 배경(과금 무결성):
 *   crew 사용자의 uplink 고정은 floating route-to/block 룰 + cp_gw_* pfctl 테이블로
 *   구현된다. 이 룰들은 테이블에 사용자 IP 가 있을 때만 동작한다.
 *   그런데 filter_configure()(게이트웨이 up/down 이 자동 트리거)가 빈 alias 기준으로
 *   테이블을 flush 하면, 그 순간 고정 사용자 트래픽이 기본 경로(반대 uplink)로 새어
 *   해당 uplink 사용량에 오과금된다.
 *   → 매분 CP 세션 DB 기준으로 테이블을 재적재해 "테이블이 절대 비지 않게" 유지한다.
 *
 * 안전성:
 *   - config.xml/룰 미수정, filter_configure 미호출 → pfctl 테이블만 갱신(저비용, lost-update 무관).
 *   - 세션 읽기 실패/빈 결과 시 아무 것도 하지 않음(전 테이블 flush 로 인한 전원 라우팅 붕괴 방지).
 *   - 버전 섞임 방어: 구버전 captiveportal.inc 면 함수 부재 → no-op.
 *
 * 한계(후속):
 *   - 매분 실행이라 flush 후 최대 ~60초의 누수창이 남는다(즉시화는 게이트웨이/filter 이벤트
 *     훅으로 후속 보강 가능).
 */

// ── 단일 인스턴스 가드 (#26) ──────────────────────────────────────────────────
// 이전 실행이 1주기 안에 안 끝났으면(디스크풀/느린 I/O 등) 즉시 종료 → 프로세스 누적/OOM 방지.
// 의존성 없는 self-contained(버전 섞임 안전). 락 fd 는 프로세스 종료 시 자동 해제.
$__cron_singleton_fp = @fopen('/tmp/cron_' . basename(__FILE__, '.php') . '.lock', 'c');
if ($__cron_singleton_fp === false || !@flock($__cron_singleton_fp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

require_once("captiveportal.inc");

global $config, $cpzone;
$cpzone = "crew";

if (!function_exists('cp_resync_pf_tables_only')) {
    // 구버전 captiveportal.inc — no-op
    exit(0);
}

cp_resync_pf_tables_only();

exit(0);
