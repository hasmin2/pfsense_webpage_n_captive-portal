# pfSense Captive Portal — 프로젝트 컨텍스트

## 배포 규칙 (절대 준수)

- **prod 브랜치**는 사용자의 명시적 명령 + 재확인 없이 절대 건드리지 않는다
- 작업 흐름: `develop` → (명시적 지시 시) `main` → (명시적 지시 시) `prod`
- 커밋은 항상 `develop`에 먼저 한다
- `main`, `prod`는 병합 명령이 있을 때만 실행한다

## 브랜치 현황

| 브랜치 | 커밋 | 설명 |
|---|---|---|
| `develop` | `92ae399` | **#1~#34 전부 포함**, 작업 기준 브랜치 (#18~#21: vnstat예외·게이트웨이flapping/과금누수·끊김진단/다국어/blank단락; #22: PW리셋 무작위미반영 — writer크론 lost-update 차단; #23: PW변경 무반영 진범=HUP가 rlm_files 미재로딩 — A응급=재시작 + radcheck(SQL) 이행도구 + step3-A dual-write(`b121dda`) + step3-B radcheck 권위화 구현(`de4daf7`, 플래그 게이트 기본 off + 토글도구); #24~26: 캡티브포털 무한 self-redirect 루프→25GB로그→ZFS풀full→전면장애(502/OOM) — 루프차단+무제한로깅차단+크론flock가드; #27: Main Panel 안테나 트래킹 나침반 — VSAT/FBB look-angle 시각화 + FULL HD 세로압축; #28: 항구 미니맵 WoW UI 전면 통합 — 544항구·292해역·존플레이트·시계배지·줌버튼·GPS회색처리·on-map점표시(`1775f85`); #29: time_offset 외부 API 의존 제거 — GPS→오프라인 시차격자 자동판정(`660727e`); #30: 위젯 stale write → 전원 mass-disconnect + 비CP계정 영구 kick 차단; #31: CNA Copy address 블록(기본 off); #32: voucher REST API 다건 CRUD 정합 + timeperiod 대소문자 방어; #33: 관리/Main Panel UI 보강; #34: API random PW / israndompw true/false / Topup delta / 3D돔 방향 수정; #35: 위성 커버리지 맵 — 월드맵은 항상 열되 커버리지 오버레이만 NexusWave(terminal_type=nexuswave_*) 시 + 비-NexusWave 안내 팝업(`2c23248`); #36: 3D 스카이돔 바닥 세계지도 dome 과 함께 yaw 회전(`82fc3d4`); #37: Release Note 사이드바 메뉴 + 패치노트 표시 페이지(`1f0c4da`) — 커밋 `1f0c4da`) |
| `main` | `369da8e` | **#1~#34 전부 반영 완료** (커밋 `369da8e`). 2026-06-16 develop→main 일괄 통합 |
| `prod` | `7a7195f` | **#1~#34 전부 반영** (커밋 `7a7195f`). 2026-06-16 main→prod 배포 |

> **develop 최근 작업 묶음(#13확장·#15~#17)**: 배포 시 구룰 자동 purge + 로그인 유지 마이그레이션
> (`4df5de3`), phantom CP zone 제거·즉시정리(`9bc6053`·`9476e47`), getsession 무효리셋 가드 +
> monthly resync + prepaid 중복 cron 제거(`28c0876`·`cde34a5`·`9b8cbf7`), 리셋 자가복구 날짜키
> (`aa0c759`). 상세는 아래 #13/#15/#16/#17 항목.

> **배포 버전 섞임 주의(중요)**: 선상 배포본이 repo보다 **파일별로 뒤처져 섞여 있는** 상태가
> 여러 번 관측됨(#11 `commit_change_pw` fatal, `cp_find_all_wan_gateways` undefined 등 전부 이 원인).
> 안정화하려면 `captiveportal.inc` / `freeradius.inc` / `index.php` / cron / `manage_crew_wifi_account.inc`
> 등을 **반드시 같은 리비전으로 일괄 배포**할 것. 일부만 배포하면 시그니처/함수 불일치로 fatal 발생.

## Repo 정보

- **Remote**: `hasmin2/pfsense_webpage_n_captive-portal-dev`
- **플랫폼**: pfSense 2.5.2, PHP-FPM + nginx, FreeRADIUS
- **PHP 버전**: **7.4** (pfSense 2.5.x 기본값) — PHP 8.x 전용 문법(`match`, `named args`, `enum` 등) 사용 금지

## 핵심 파일

| 파일 | 역할 |
|---|---|
| `usr/local/captiveportal/index.php` | 포털 메인 (PRG 패턴, 세션 관리) |
| `etc/inc/captiveportal.inc` | 인증·세션·과금 핵심 로직 |
| `usr/local/pkg/freeradius.inc` | FreeRADIUS 설정 생성 (datacounter_acct.sh embedded) |
| `usr/local/etc/raddb/scripts/datacounter_acct.sh` | RADIUS 회계 처리 (Start/Interim/Stop) |
| `usr/local/etc/raddb/scripts/datacounter_auth.sh` | RADIUS 인증 시 쿼터 확인 |
| `etc/inc/manage_crew_wifi_account.inc` | crew wifi 계정 CRUD·PW 변경 (admin GUI 백엔드) |
| `usr/local/cron/crew_*usage_reset_check.php` | 주기별 쿼터 reset 경계 크론 (모두 config writer) |
| `etc/inc/cp_usage_reset.inc` | 리셋 자가복구 날짜키 헬퍼 (#16, 파일 기반·config 미사용) |
| `usr/local/cron/crew_usage_reset_selfheal.php` | 리셋 누락 보충 크론 (#16, 분 15,45) |
| `usr/local/cron/cp_routing_setup.php` | pfctl 라우팅 초기세팅 + 배포 시 phantom 정리·구룰 purge |
| `usr/local/cron/cp_routing_table_resync.php` | cp_gw_* 테이블 매분 재적재 안전망 (#20, 과금 누수 차단) |
| `usr/local/sbin/cp_gateway_alarm.sh` | dpinger 알람 래퍼: 표준 핸들러 후 테이블 즉시 재적재 (#20, 0755) |
| `etc/inc/gwlb.inc` | 게이트웨이 모니터링(dpinger). `start_dpinger` 알람명령을 위 래퍼로 교체(#20) |
| `etc/inc/vlanstate.sh` | VLAN 상태 telnet 조회 셸 (vlan_state_timeperiod_check.php가 호출) |
| `usr/local/www/index.php` | 관리 GUI Main Panel (사이드바 "Main Panel"; #27 안테나 트래킹 나침반) |
| `etc/inc/server_module.inc` | influx 조회 + GEO look-angle 계산 (#27 `get_acu_pointing_info`/`get_fbb_pointing_info`) |
| `etc/inc/terminal_status.inc` | Main Panel 상태 문자열 빌더 (return_terminal_state 등) |
| `etc/inc/cp_geo_tz.inc` | 위경도→타임존 오프셋 오프라인 판정 (#29; 격자=`cp_tz_grid.inc`, 생성기=`tools/generate_cp_tz_grid.js`) |
| `usr/local/cron/cp_tz_offset_update.php` | 매시 7분 GPS 기반 time_offset 자동 갱신 크론 (#29) |

> **주의**: `freeradius.inc`의 `freeradius_datacounter_acct_resync()` /
> `freeradius_datacounter_auth_resync()` 함수가 `datacounter_acct.sh` /
> `datacounter_auth.sh`를 각각 nowdoc으로 **덮어씌워 생성**한다.
> 두 셸 스크립트를 수정하면 반드시 `freeradius.inc` 내 **임베디드 사본도 함께** 수정할 것.
> (검증법: freeradius.inc에서 nowdoc 블록 추출 후 standalone과 diff → 내용 동일해야 함.
> CRLF/말미개행 차이는 Windows 체크아웃 아티팩트이며 git이 LF로 정규화하므로 무시.)

## 아키텍처 — cron 배포 메커니즘

`usr/local/cron/firewall_cronlist` (JSON) 은 선상 박스에서 **직접 읽히지 않는다.**
배포 스크립트(Jenkins/update.sh)가 이 파일을 **`APIServiceCronWrite` REST API로 POST** →
pfSense가 `$config['cron']['item']`에 기록 + `cron_sync_package()` 호출 →
FreeBSD `/var/cron/tabs/root` 재생성 → `/usr/sbin/cron` 데몬이 실행.

```
firewall_cronlist (JSON)
    ↓  배포 시 POST /api/v1/service/cron
$config['cron']['item']  (config.xml)  ← APIServiceCronWrite.inc + cron_sync_package()
    ↓
/var/cron/tabs/root  (FreeBSD 시스템 crontab)
    ↓
/usr/sbin/cron
```

- **선상 등록 확인**: `crontab -l | grep <스크립트명>`
- **미등록 증상**: `firewall_cronlist`만 배포하고 API 호출 누락 시 → 파일은 있어도 cron 미발화
- `etc/inc/api/models/APIServiceCronRead.inc` / `APIServiceCronWrite.inc` 참고

## 아키텍처 — 방화벽 서브시스템

| 서브시스템 | 담당 | 호출 방식 |
|---|---|---|
| **ipfw dummynet** | 인증 테이블(`auth_up`/`auth_down`) + 대역폭 파이프 | `pfSense_ipfw_table()` C 확장 |
| **pf** | 라우팅 테이블(`cp_gw_*`) + state 관리 | `pfctl -t`, `pfctl -k` 셸 명령 |
| **FreeRADIUS** | 인증 + 쿼터 검사 | `datacounter_auth.sh` (exit 0/1) |
| **datacounter** | 사용량 누적 | `datacounter_acct.sh` (Start/Interim/Stop) |

## 아키텍처 — crew wifi 라우팅 (pfctl 테이블 방식, #1·#4·#12·#13 공통 배경)

- **사용자별 라우팅을 어떻게 하나**: 로그인한 crew 사용자의 IP를 단말타입(terminal_type=gateway)별
  pf 테이블 `cp_gw_{gwname}`(또는 미설정 시 `cp_gw_default`)에 넣고, floating rule이 그 테이블을
  source로 route-to 한다. 초기 alias/rule 세팅은 `usr/local/cron/cp_routing_setup.php`가 1회 생성.
- **신버전(현재) vs 구버전(레거시)**:
  - **신버전**: `add_crew_linked_rule($ip,$user)` = `pfctl -t cp_gw_* -T add` (config.xml 수정 없음,
    `filter_configure()` 없음 → 가볍고 빠름). `del_crew_linked_rule($ip,$user,$kill_states=true)` =
    `pfctl -t cp_gw_* -T delete` (+ 선택적 state kill). 로그인/로그아웃은
    `captiveportal_send_server_accounting('start'/'stop')` 안에서 호출됨.
  - **구버전(제거됨)**: 로그인마다 `$config['filter']['rule']`에 `"[User Rule] <id> auto generated rule"`을
    추가 + `write_config()` + `filter_configure()` 했음 → 로그아웃마다 무거운 filter reload =
    #1(19초 지연)·lost-update 원인. #13에서 이 레거시 룰을 일괄 purge.
- **deferred state-kill 메커니즘(#1·#12 핵심)**: `pfSense_kill_states()`/`pfctl -k {clientip}`를
  HTTP 응답 도중 실행하면 **포털 자신의 TCP 연결이 RST** → spawn-fcgi(=`fastcgi_finish_request()` 부재)
  에선 ~19초 지연. 그래서 kill 대상 IP를 `$GLOBALS['_cp_deferred_state_kills']`에 적재하고,
  `cp_flush_deferred_state_kills()`가 **`mwexec_bg("sleep 2; pfctl -k ...")` detached 백그라운드**로
  응답·연결 종료 **후** 실행한다(web/CLI 공용). 로그인 경로는 애초에 kill_states=false라 큐가 빔.
- **알려진 트레이드오프(미수정, 의도적)**: 위 `sleep 2` 지연 kill은 **2초 내 같은 IP 재로그인** 시
  새 세션 state까지 죽일 수 있음(로그아웃→즉시 재로그인 반복 테스트에서만 관측). 실사용 빈도 극히 낮아
  수용. 필요 시 kill 직전 `cp_gw_*` 테이블 멤버십 확인(=재인증됨)으로 skip 가드 추가 가능.

## 쿼터 파일 경로

```
/var/log/radacct/datacounter/{daily|weekly|monthly|forever}/
  max-octets-USERNAME          # 최대 허용량
  used-octets-USERNAME         # 누적 사용량
  used-octets-USERNAME-SID     # 세션별 현재값 (Interim 업데이트)
/var/run/datacounter-state/{timerange}/prev-USERNAME  # InfluxDB delta 계산용
```

## 이번 세션에서 수정된 주요 버그

### 1. 로그인/로그아웃 ~19초 지연 (develop + main 반영 완료)
- **원인**: `pfSense_kill_states()` 등이 HTTP 응답 전에 클라이언트 TCP RST
- **수정**:
  - state kill을 `$GLOBALS['_cp_deferred_state_kills']`에 적재 후 `fastcgi_finish_request()` + `exit` 이후 shutdown 함수에서 처리
  - `del_crew_linked_rule()`에 `$kill_states` 파라미터 추가 (로그인 경로: false)
  - `portal_reply_page()`: `return_gateways_status()` → config 메모리 탐색으로 교체 (18초 차단 제거)
  - RADIUS `addServer()` maxtries=1

### 2. 두 번째 로그인 "Unknown error" 실패 (develop 반영)
- **원인 A**: zero-Stop 시 SESSFILE 미삭제 → glob 오합산 → 쿼터 초과 오판정
- **원인 B**: RADIUS plain Access-Reject 시 PHP 원인 특정 불가
- **수정**:
  - zero-Stop 핸들러: SESSFILE → USEDFILE 누적 후 삭제
  - `captiveportal_check_quota_exceeded()` 신규: PHP에서 직접 쿼터 파일 확인
  - "Unknown error" → 정확한 메시지 또는 `[AUTH-UNKNOWN]` 로그

### 3. 의도치 않은 KICK 기능 제거 (develop 반영)
- **원인**: zero-Stop 또는 zero Interim 연속 수신 시 사용자 CP 세션 강제 종료
- **수정**: KICK 관련 코드 전체 삭제
  - `datacounter_acct.sh` + `freeradius.inc`: KICKDIR, zero_streak, STATEFILE 등 제거
  - `captiveportal.inc`: `datacounter_kicklog/disconnect_user/process_kick_spool` 함수 3개 제거

### 4. IP 변경 시 자동 로그인 미동작 (develop 반영)
- **원인**: `already_connected()`가 IP+MAC 정확 일치만 검사 → IP 바뀌면 재로그인 프롬프트
- **수정**:
  - `captiveportal_migrate_session_ip()`: ipfw/pf/DB를 신IP로 일괄 갱신 (sessionid 유지)
  - `captiveportal_try_migrate_session_by_mac()`: MAC 매칭 → 쿼터 재확인 → 마이그레이션
  - `index.php`: `already_connected()` 실패 시 MAC 매칭 마이그레이션 시도
- **동작**:
  - MAC+IP 변경 → 로그인 프롬프트 (신규 기기)
  - MAC 동일 + IP 변경 + 쿼터 OK → 자동 로그인
  - MAC 동일 + IP 변경 + 쿼터 초과 → 재인증 프롬프트

### 5. get_suspend_timeschedule() 오탐 (develop 반영)
- null/빈 loginId 조기 반환, 단일 schedule flat array 정규화, 진단 로그 추가

### 6. 포털 재오픈 시 오탐 "User is removed!" + 강제 disconnect (develop + main 반영 완료)
- **증상**: 로그인 중 어떤 경우엔 무조건 "REMOVING(User is removed!)"으로 빠져
  복구 안 됨. 실제로는 user id가 삭제되지 않았는데도 발생.
- **근본 원인**: `portal_reply_page(type="connected")`가 username을 flash로 받지
  않고 `already_connected()`로 **재조회**함. 이 재조회는 IP+MAC **정확 일치**만
  보므로(clientmac 공백·대소문자·타이밍) 실패 시 `$username` 공백 →
  freeradius config 매칭 실패(`$userindex=-1`) → "removed" 분기 + disconnect.
  즉 **username 회수 실패가 사용자 삭제로 오인**됨 (HTML에 로그인 id 미주입).
- **수정 (`index.php`)**:
  - connected flash 3곳(로그인 성공/종료부/passthrough)에 `username` **명시 전달**
    → `already_connected` 재조회 의존 제거. `$connectedUser` 확보.
- **수정 (`captiveportal.inc` `portal_reply_page`)**:
  - **빈 username 안전망**: removed 분기에서 username 공백이면 disconnect 없이
    **WELCOME 로그인 페이지** 표시.
  - **sessionid 복구**: `already_connected` 실패해도 username으로 `getsession()`
    복구 → quota 0% 오표시·오판정 방지.
  - **passthrough(freelogins) 게스트(`'unauthenticated'`) 전용 처리**: config
    조회/quota/removed 판정을 건너뛰고, **존 설정 `redirurl`(외부 URL)로 즉시
    redirect**. 대상 없으면 게스트 상태 페이지로 폴백(무한 루프 방지).
    → 게스트가 재오픈마다 강제 disconnect 되던 문제 해결.
- **주의**: voucher 미사용 환경이라 voucher 경로(동일 구조적 결함)는 미수정.
  voucher를 켜면 바우처 코드 username도 config에 없어 같은 오탐 발생 → 별도 처리 필요.
  passthrough 게스트 logout 버튼은 `logout_id='unauthenticated'`→`getsession()`이
  첫 게스트 세션 반환 → 동시 게스트 다수 시 다른 게스트가 끊길 수 있음(기존 전원
  disconnect보다는 개선). 정확한 per-게스트 로그아웃은 sessionid 기반 재설계 필요.

### 7. Interim 회계(집계) 누락 케이스 수정 (develop 반영)
- **핵심 통찰**: RADIUS Acct 카운터는 세션 내 **누적**이고 SESSFILE=현재 누적값이라,
  중간 Interim 1개가 빠져도 **다음 Interim/Stop이 누적값으로 자가복구**한다.
  따라서 영구 손실은 3가지로 압축: (A) 마지막 구간(최종 Interim→Stop) Stop 유실,
  (B) SESSFILE 역행(같은 SID에서 0<cur<기존이 덮어씀), (C) 동기 export 블로킹 연쇄장애.
- **L1 (export 분리 + mysql 타임아웃)** `datacounter_acct.sh` + `freeradius.inc`:
  - Interim 경로의 InfluxDB(로컬+중앙)/MySQL export를 **백그라운드 서브셸**(fire-and-forget)로 분리.
    `wait=yes` 동기 블로킹 → FreeRADIUS `max_request_time` 초과 → Accounting-Response 미응답
    → NAS 재전송 폭주 → **타 세션 Stop까지 연쇄 유실**을 차단. 쿼터(SESSFILE/PREVFILE)는
    백그라운드 진입 **전에 동기 기록**되므로 손실 없음.
  - `get_vessel_imo()` mysql 호출에 `--connect-timeout=2` 추가(블랙홀 호스트 hang 방지).
- **L2 (SESSFILE 단조성/high-water-mark)** `datacounter_acct.sh` + `freeradius.inc`:
  - 비-zero Interim에서 `CUR_TOTAL < 기존 SESSFILE`이면 **덮어쓰지 않음**(로그 REGRESS-KEEP).
    ipfw 카운터 리셋/룰 리로드/IP 마이그레이션으로 SESSFILE이 줄면 차액이 USEDFILE에
    접힌 적 없어 **조용히 쿼터 손실**되던 것을 차단. (zero-guard의 일반화.)
- **M4 (auth glob prefix 충돌)** `datacounter_auth.sh` + `freeradius.inc`:
  - 합산 glob `"$USED_FILE"*` → `"$USED_FILE" "$USED_FILE"-*` (대시).
    `crust1`이 `crust10` 사용량까지 합산하던 **과다계상** 차단. (PHP쪽은 이미 `-*`로 되어 있었음.)
- **M1 (interim 송신 신뢰성)** `captiveportal.inc` `captiveportal_prune_old()`:
  - 송신 조건을 modulo 창(`session_time % interval <= 59`)에서 **경과시간 threshold**
    ("마지막 송신 후 interval초 경과", `/var/run/cp_lastinterim_{zone}_{sid}` 마커 mtime)로 교체.
    minicron 드리프트로 60초 창을 통째로 건너뛰어 interim이 누락되던 문제 제거(→ L3 확률↓).
  - `captiveportal_disconnect()`에 세션 종료 시 마커 unlink 추가(누수 방지).
- **L3 (감수)**: 마지막 구간 Stop 유실 손실은 UDP/리부트 등 구조적이라 **감수**.
  M1으로 발생 확률을 최소화. (필요 시 부팅 시 고아 SESSFILE 회수 크론으로 추가 완화 가능.)

### 8. 파일 배포 시 '내부가 빈' captiveportal zone 1개 추가 생성 (develop 반영)
- **증상**: 파일 배포 후 pfSense CP zone 목록에 이름/내부가 비어있는 zone이 하나 더 생김.
- **근본 원인**: `APISystemToggleprepaidUpdate.inc`가
  `$config['captiveportal']['prepaid_enabled'] = ""`로 **zone 배열에 비-zone 스칼라 키**를 주입.
  `$config['captiveportal']`는 zone 전용 배열이라, zone을 순회하는 모든 코드
  (`captiveportal_configure()`, `captiveportal_init_rules_byinterface()`, 기본 UI zones 페이지)가
  `prepaid_enabled`를 **가짜 zone**으로 처리 → 빈 zone 표시 + CP configure 시 오작동 소지.
- **수정 (플래그를 `$config['system']['prepaid_enabled']`로 이전)**:
  - 쓰기 `APISystemToggleprepaidUpdate.inc`: system에 set/unset + **구 오염키 항상 unset**.
  - 읽기 4곳(Update/Read API, `common_ui.inc` `print_sidebar`, `captiveportal-crew.html`):
    신(system)/구(captiveportal) **동시 검사**로 활성상태 보존.
  - **self-heal** `common_ui.inc print_sidebar`: 구 키 발견 시 system 이전 + 구 키 제거 +
    `write_config` 1회 → 배포 후 첫 관리 UI 로드 시 **기존 가짜 zone 자동 제거**(이후 no-op).
- **교훈**: `$config['captiveportal']`는 **zone 전용**. 전역 플래그를 절대 그 아래 두지 말 것
  (zone 순회 오염). 시스템 전역 토글은 `$config['system']` 사용.

### 9. vlanstate.sh 간헐 미동작 (develop + main 반영)
- **증상**: `vlan_state_timeperiod_check.php`가 호출하는 내부 셸 `vlanstate.sh`가 간혹 동작 안 함.
- **근본 원인**: `timeout 5`인데 스크립트 내부 `sleep` 합계도 정확히 **5초** → telnet 연결/응답이
  조금만 지연돼도 timeout이 프로세스를 kill → `/etc/inc/<dev>.log`가 비거나 불완전.
- **수정**:
  - `vlanstate.sh`: `timeout 5` → `timeout 12` (여유 확보).
  - `vlan_state_timeperiod_check.php`: `trim(preg_replace(...) === '')` **괄호 오배치** →
    `trim(preg_replace(...)) === ''` 수정. `mwexec()`(동기)는 이미 완료 후 반환하므로 뒤의
    불필요한 `sleep(1)` 제거.

### 10. 사용자 PW 변경이 간헐적으로 반영 안 됨 (재시작해도) (develop + main 반영)
- **증상**: 계정 PW를 바꿔도 **무작위로 옛 PW 유지**. 대부분 pfSense 재시작하면 해결되나
  **가끔 재시작해도 안 됨**. 특히 **두 명의 PW를 거의 동시에 바꾸면 한 명만** 적용됨.
- 원인/수정이 **3개 층위**로 누적됨:

  **(A) 다중선택 PW: 마지막 1명만 적용** (`manage_crew_wifi_account.inc`)
  - `freeradius_update_user($user)` 호출이 inner `foreach($userlist)` **밖**(바깥 루프 레벨)에
    있어 `$user`가 항상 **userlist의 마지막 원소**로 고정 → 선택된 나머지는 런타임 users 파일
    미갱신(재시작 시 resync로만 반영).
  - 수정: 증분 호출 제거 → 루프 종료 후 `freeradius_users_resync()` **1회**(전체 재생성).
    `reset_wifi_user_pw`/`reset_random_wifi_user_pw`/`create_wifi_user` 모두.

  **(B) 적용수단(HUP↔재시작) + accounting**
  - rlm_files(users/authorize)는 **HUP로 재읽기됨** → 사용자 파일 변경 반영에 전체 재시작 불필요.
    반대로 **전체 재시작은 1813 listener를 닫아 Accounting Start/Interim/Stop 유실**
    (= "RADIUS ACCOUNTING FAILED") → 재시작은 금물.
  - 수정: `freeradius_reload_or_restart_radiusd($allow_hup=true)` **기본 HUP**(graceful), 재시작은
    HUP 전달 불가(데몬 미기동/PID 없음) 시 **fallback 전용**. `freeradius_users_resync()`도
    `restart_service` → **HUP**로 바꾸고, `/users`뿐 아니라 **활성 파일(`mods-config/files/authorize`)
    까지** 기록(`freeradius_get_target_user_files()`)해 증분 경로와 타깃 일치.
  - (이력: 중간에 HUP→restart로 과도교정했다가 accounting 회귀 발견 → **다시 HUP로 환원**. 최종 HUP.)

  **(C) lost-update 동시성 (재시작해도 안 되는 케이스의 진범)**
  - pfSense 전역 `$config`를 **여러 프로세스가 락 없이 read-modify-write** → 나중 writer가 자기
    **옛 스냅샷으로 config.xml 전체를 저장**하며 PW 변경을 되돌림. config.xml 자체가 reverted라
    재시작해도 복구 안 됨. "두 명 동시 → 한 명만"이 이 전형적 증상.
  - **C-1 reset 크론 가드**: `crew_{daily,weekly,halfmonthly,monthly}usage_reset_check.php`가 변경
    없어도 **무조건 `write_config()`/resync** → stale 스냅샷으로 PW를 덮어씀. `if ($changed)`
    가드로 **변경 시에만** 쓰도록(가능하면 resync도 가드). (prepaid는 이미 동일 패턴이라 무수정.)
  - **C-2 PW 진입점 전용 락 (P2a)**: 모든 PW 쓰기 경로를
    `lock('freeradius_user_config', LOCK_EX)` → `parse_config(true)` **재로딩** → 수정 →
    `write_config` → `unlock` 패턴으로. 두 번째 요청이 첫 번째 변경을 본 뒤 얹어 **둘 다 보존**.
    대상: `reset_wifi_user_pw`/`reset_random_wifi_user_pw`/`create_wifi_user`
    (manage_crew_wifi_account.inc) + `commit_change_pw`(captiveportal.inc 로그인 자가변경; 락 밖
    `freeradius_update_user($u, false)`로 중복 write=재clobber 방지).
- **교훈**:
  - `write_config()`는 **내부에서 `lock('config')`를 잡으므로** RMW를 감쌀 땐 반드시 **다른 락
    이름**을 쓸 것(같은 `'config'`면 self-deadlock).
  - 동시성 안전엔 **락 안에서 `parse_config(true)`로 최신본 재로딩 후 수정**해야 lost-update가
    사라짐(미리 읽어둔 값 기반 수정은 무효). 충돌하는 **모든** writer가 **같은 락**을 공유해야 효과.
  - 사용자 파일 반영은 **HUP(graceful)**; 전체 재시작은 accounting을 끊으므로 fallback 전용.
- **남은 갭(부분 적용, 후속)**: PW 변경과 **동시 실행 시** clobber 가능했던 writer 들 —
  - **매분/5분 writer 크론: `manual_routing`/`network_usage`/`vlan_state`/`openvpn_restart` → #22에서
    해소(develop `549681f`).** 같은 `lock('freeradius_user_config')` + 락 밖 느린 I/O 패턴 적용.
  - 형제 admin writer(미적용): `reset_wifi_user`(쿼터리셋)/`modify_wifi_user`/`del_wifi_user`
  - API 단건 생성(미적용): `APIFreeRADIUSUserCreate` 비-bulk; bulk는 `create_wifi_user` 경유라 이미 보호됨
  → 남은 둘도 같은 `lock('freeradius_user_config')` 패턴으로 단계 확대 예정.

### 11. `commit_change_pw` PHP fatal — `freeradius_update_user(NULL)` TypeError (develop 반영)
- **증상**: 로그인 화면에서 PW 변경 시도 시 PHP fatal error 발생.
  ```
  TypeError: Argument 1 passed to freeradius_update_user() must be of the type string,
  null given, called in /etc/inc/captiveportal.inc on line 2679
  ```
- **근본 원인**: username이 null로 `freeradius_update_user(string $username)`에 전달됨.
  전파 단절이 **3곳** 중첩:
  1. **폼 필드명 불일치**: `renderChangepwPortalHtml` 폼의 hidden이 `name="login_user"`인데
     `index.php`는 `$_POST['auth_user']`를 읽음 → `$auth_user` = 빈 값 → flash에 username 미포함.
  2. **데이터 키 불일치**: `portal_reply_page`가 `'loginUserValue'` 키로 넘기는데
     `renderChangepwPortalHtml`은 `$data['loginUser']`를 읽음 → 항상 빈 값.
  3. **`change_pw` flash에 username 미포함**: `index.php` change_pw 분기가 username을 안 실어
     변경 페이지 렌더 시 username을 모름.
- **배포 버전 불일치 주의**: 선상 에러 라인(2679)이 repo 라인(~2861)과 다름
  → `captiveportal.inc`가 구버전으로 배포된 상태. `freeradius.inc`(신버전: `string $username` 타입힌트)만
  배포되고 `captiveportal.inc`는 구버전이어서 충돌 발생.
- **수정 (적용 완료, develop `1ed69ad`)** — 3파일:
  - 방어 `freeradius.inc`: `freeradius_update_user(string)` → `?string $username` + null/빈값
    early-return false → **어떤 경로로 null이 와도 fatal 불가**(하드 가드).
  - 근본 `captiveportal.inc`: ① `renderChangepwPortalHtml` 폼 hidden `name="login_user"` →
    `name="auth_user"`, ② render 데이터 키 `$data['loginUser']` → `$data['loginUserValue']`(통일),
    ③ `renderLogoutPortalHtml`의 change_pw 폼에 `auth_user` hidden 추가 + `change_pw` 값을 `"true"`로.
  - 근본 `index.php`: change_pw flash에 `'username' => $auth_user` 추가.
  - **전파 흐름(수정 후)**: connected 페이지 change_pw 버튼(auth_user) → index.php `$auth_user` →
    change_pw flash → renderChangepwPortalHtml(loginUserValue) → hidden auth_user →
    commit_change_pw POST `$auth_user` → `freeradius_update_user(username)` ✓
  - **주의**: 이 fatal은 **배포 버전 섞임**(freeradius.inc만 신버전, captiveportal.inc 구버전)으로
    드러난 것 → 3파일 동시 배포 필요.

### 12. 로그아웃 ~19초 지연 (login은 #1로 해소됐으나 logout 잔존) (develop 반영)
- **증상**: 로그인 지연(~19초)은 #1에서 사라졌으나 **로그아웃은 여전히 ~19초**.
- **근본 원인**: 로그아웃만 client IP를 deferred state-kill 큐(`$GLOBALS['_cp_deferred_state_kills']`)에
  적재한다(로그인은 큐가 빔 — `add_crew_linked_rule`이 kill_states=false, disconnect 안 함).
  kill은 `register_shutdown_function`으로 미뤄지지만, **pfSense spawn-fcgi에는
  `fastcgi_finish_request()`가 없어** shutdown 함수가 **HTTP 응답/연결 종료 "전"에** 실행됨.
  거기서 `pfSense_kill_states()`/`pfctl -k {clientip}`가 **포털 자신의 TCP 연결을 RST** →
  지연 큰 선박 WiFi에서 splash 바이트 도달 전 끊겨 **일관되게 ~19초**.
  (#1은 login 경로만 deferral로 해결했고, logout의 in-process kill은 잔존했음.)
- **핵심 통찰**: spawn-fcgi에서 `register_shutdown_function` "지연"은 **연결 종료 전**이라
  불완전하다. RST를 피하려면 응답이 끝난 뒤 실행되는 **진짜 detached 백그라운드**가 필요.
- **수정 (`captiveportal.inc` + `index.php`)**:
  - 신규 `cp_flush_deferred_state_kills()`: 큐의 IP를 모아 **`sleep 2` 후 `pfctl -k`** 하는
    detached 백그라운드(`mwexec_bg`)로 분리 → 응답/연결 종료 후 state 정리.
  - `index.php` 전역 shutdown + `captiveportal.inc` disconnect shutdown 둘 다 이 헬퍼 호출로
    교체(인라인 `pfSense_kill_states`/`cp_kill_states_for_ip` 제거). 헬퍼가 큐를 비우므로
    두 핸들러 중복 실행돼도 두 번째는 no-op. web/CLI(prune) 공용.
- **기능 영향 없음**: 로그아웃 즉시 ipfw auth 테이블/DB에서 제거되어 신규 트래픽은 이미 차단;
  state kill은 기존 연결 종료용이라 2초 지연 무해.
- **잔여 시 후속 후보**: 그래도 느리면 동기 경로(accounting stop / XMLRPC HA sync). 단
  Stop 핸들러(datacounter_acct.sh)는 빠르고 login의 accounting start 정상이라 RADIUS 응답
  양호, HA sync도 login이 빠른 걸로 보아 미구성/정상 → state kill이 유일한 차이였음.
- **후속 관측(엣지 케이스, 미수정 수용)**: 1~2초 내 로그인↔로그아웃 **반복**(테스트) 시 지연.
  원인 = 이번 `sleep 2` 지연 kill이 2초 창 안의 **재로그인 새 세션 state를 kill**. 실사용 빈도
  극히 낮아 수용(가드는 위 "crew wifi 라우팅 아키텍처" 절 참고).

### 13. 구버전 per-user 로그인 룰 config.xml 잔존 → 일괄 purge (develop 반영)
- **증상/요구**: pfctl 테이블 방식 전환 후, 구버전이 로그인마다 만들던
  `"[User Rule] <id> auto generated rule"` 필터 룰이 마이그레이션 이전 유저들 것으로
  config.xml에 **고아로 남음**. 방화벽 룰에서 일일이 수동 삭제하기 번거로움.
- **위험**: 단순 클러터 아님 — 룰이 `source=옛IP, gateway=단말타입`이라 **DHCP로 그 IP가 다른
  유저에게 재할당되면 옛 게이트웨이로 오라우팅**될 수 있음 → 정리 필요.
- **선택지 비교**: 구 `del_crew_linked_rule`(config.xml 룰 제거판) 복원 ❌ — 로그아웃마다
  `filter_configure()`+`write_config()` 회귀(#1·#10). → **일괄 purge ✅** 채택.
- **수정 (`captiveportal.inc`)**:
  - 신규 `cp_purge_legacy_user_login_rules()`: `descr`가 `"[User Rule] "`로 시작하고
    `" auto generated rule"`로 끝나는 룰만 일괄 제거 → `write_config` 1회 + `filter_configure` 1회.
    제거할 게 없으면 no-op(멱등). **반환값=제거 개수**, 로그 남김.
  - `captiveportal_configure()` 진입 시 1회 호출(멱등 self-heal, #8 패턴) → 배포 후 첫
    CP 재구성/부팅 때 자동 정리, 이후 비용 0.
- **안전성**: 접미사 `" auto generated rule"`로만 매칭 → 다른 `[User Rule]`(ban-all-rule /
  allow only 'this' PC / enable_crew_wifi; `crew_internet_control.inc`·`toggle_captive_portal.widget.php`)은
  **건드리지 않음**.
- **배포 시 자동화 (`cp_routing_setup.php`, 커밋 `4df5de3`)**: `captiveportal_configure` 외에
  **배포마다 실행되는 `cp_routing_setup.php` 시작부에서도 purge 호출**(function_exists 가드).
  → 배포 시 **CP 설정 저장/재부팅 없이** 즉시 구룰 정리. `update.sh` 무수정(이미 cp_routing_setup 실행함).
- **로그인 유지 마이그레이션(중요)**: 구방식으로 **로그인된 채** 배포받으면 → cp_routing_setup 이
  ① 구룰 purge ② `cp_sync_routing_tables()`로 **세션 DB의 로그인 유저를 pfctl `cp_gw_*` 테이블로 이관**.
  **로그아웃 안 됨**(purge/cp_routing_setup 은 pf/pfctl 만 건드리고 **ipfw 인증 테이블 미손상**;
  `disconnect_all` 미호출). 기존 pf state 는 만료까지 옛 경로 유지 → 신규 연결부터 신방식(끊김 없는 점진 전환).
  단 게이트웨이 정확 배정은 `cp_get_terminaltype_for_user`의 username 매칭에 의존하는데 **아직 `===`
  (대소문자 구분)** → 불일치 시 `cp_gw_default` 로 이관됨(후속: getsession 처럼 CI 화 가능).

### (참고) prepaid_enabled 태그 이전 정합성 — 전수 확인 완료
- #8에서 `$config['captiveportal']['prepaid_enabled']` → `$config['system']['prepaid_enabled']`로 이전.
- **모든 참조 일관 확인**: 쓰기 1곳(`APISystemToggleprepaidUpdate.inc`: system set/unset + 구 키 항상 제거),
  읽기 3곳(`APISystemToggleprepaidRead.inc`·`common_ui.inc`·`captiveportal-crew.html`: 전부 신/구 동시 검사
  또는 self-heal). 구 위치만 보는 누락 참조 없음. zone `enable`(`$config['captiveportal'][zone]['enable']`)은
  **이동 안 됨**(표준 위치 일관).

## 아키텍처 — WIFI DATA RESET (쿼터 리셋) 흐름 (#14 배경)

- **2태그 메커니즘(역할 분리)**:
  - `varusersresetquota="true"` = **사용량 0 리셋** 트리거.
  - `varusersmodified="update"` = **강제 로그아웃** 트리거. (connected 페이지 재오픈 시 +
    매분 크론 `crew_usage_timeperiod_check.php`가 modified=update 유저를 disconnect.)
  - → 데이터한도만 바꾸는 `modify_wifi_user`는 modified만 set(resetquota 미set) → 로그아웃은 되지만
    사용량 유지. (의도된 설계.)
- **사용량 계산/리셋 단위**: 사용량 = `used-octets-{user}`(메인) + `used-octets-{user}-*`(세션) **합산**
  (`datacounter_auth.sh` / PHP `check_quota`). `max-octets-{user}` = 한도(보존). **리셋 = used-octets 삭제**.
- **리셋 명령 진입점(모두 위 2태그를 set)**: GUI `reset_wifi_user`(crew_account.php `resetdata`),
  원격 API `usersreset`(`APIFreeRadiusUserUpdate`), 주기 크론(daily/weekly/halfmonthly/monthly/prepaid),
  대시보드 위젯(`manage_freeradiususer.widget.php`), 계정 생성(create 시 resetquota=true).

### 14. WIFI DATA RESET — 태그만 달고 "차후 로그인" 리셋 → "즉시" 리셋+로그아웃 (develop 반영)
- **기존**: 리셋 명령은 2태그만 달고, 실제 사용량 0 리셋은 **차후 로그인** 시
  `captiveportal_authenticate_user`가 used-octets 파일 삭제로 수행.
- **요구/변경**: 태그는 유지하되 **명령 시점에 바로** (로그아웃 + 사용량 0).
- **신규 `captiveportal_reset_user_usage($username)` (`captiveportal.inc`)** — 순서가 핵심:
  1. 활성 세션이면 **먼저 `captiveportal_disconnect_client`(로그아웃)** → accounting Stop으로 세션 종료
     + ipfw 카운터 정리. (살아있는 세션 중 파일 삭제 시 다음 Interim/Stop이 다시 써서 0이 안 됨.
     Stop 핸들러는 `wait=yes` 동기 실행 → disconnect 반환 후 삭제 순서 안전.)
  2. `used-octets-{user}` + `used-octets-{user}-*` 삭제(daily/weekly/monthly/forever 전 디렉터리 →
     pointoftime↔maxtotaloctetstimerange 불일치에도 안전). `max-octets`(한도) 보존.
- **연결(즉시 리셋) — #14**: GUI `reset_wifi_user` + 원격 `usersreset`
  (API 분기 내 `captiveportal.inc` lazy require + `function_exists` 가드 → 다른 API 영향 없음, 실패 시
  fallback으로 degrade). 커밋 `d1c0f88`.
- **유지(fallback)**: `authenticate_user`의 차후-로그인 리셋은 **그대로 둠** → 명령 시 오프라인이던
  유저 / 레이스 / 미연결 경로를 계속 커버하는 안전망.
- **동작 변경(개선) 주의**: GUI `reset_wifi_user`는 **forever(one-time) 유저도 선택 시 즉시 0 리셋**
  (헬퍼가 forever 디렉터리도 처리). 기존엔 authenticate_user 화이트리스트가 forever 제외라 사실상
  no-op였음. (API `usersreset`는 자체적으로 `timerange !== 'forever'` 필터 유지.)

### 14b. 주기 reset 크론 + 위젯도 "즉시" 리셋 + daily/halfmonthly 복원 (develop 반영)
- **#14b 연결**: weekly/monthly/prepaid 크론 + 위젯(resetuser). 각 경로에서 플래그 set한 유저명을
  `$reset_targets`로 수집 → `write_config` 후 `captiveportal_reset_user_usage()` 호출(가드 포함). 커밋 `f0822ca`.
- **daily/halfmonthly 크론 복원(중요)**: 두 크론 루프가 reset 플래그를 **전혀 set 안 하는 gutted 상태**였음
  → **현재 daily/halfmonthly 리셋이 아예 안 되고 있었음.** 파일명·cron 스케줄(매일 00:00 / 1·15일)·의도상
  명백히 리셋 대상이라 weekly 패턴으로 복원:
  - **기본값 버그 교정**: 누락 필드 기본값 `'true'/'Update'` → `''`. (기존 기본값이면 "이미 리셋됨"으로
    오판해 플래그를 영영 안 달았음.)
  - 플래그 set + `$changed` 마킹 + 즉시 리셋 연결.
  - → daily/halfmonthly 사용자도 이제 실제 주기 리셋. (daily 유저 없으면 no-op.)
  - **주의**: 만약 daily/halfmonthly 리셋을 **의도적으로 비활성화**한 것이었다면 되돌릴 것.

## 운영·보안 참고

- **주석 정책**: 코드 주석은 **한국어 그대로 유지**. 일괄 영어 변환은 보류 — 문자열 리터럴(`"// ..."`,
  here-doc 내 `#`) 오변경 + 거대 diff 회귀 위험이 이득보다 큼. (Claude는 한/영 주석 동일하게 처리하므로
  AI 작업 편의상 변환할 이유 없음.)
- **하드코딩 비밀(보안 후속 권장)**: 소스 노출 대비 시 **주석 제거보다 코드 내 하드코딩 자격증명/엔드포인트
  정리가 훨씬 효과적**. 관측된 예: `Authorization: Basic YWRtaW46YWRtaW4=`(= **admin:admin**),
  InfluxDB `192.168.209.210`(db `acustatus`/`wifiusage`) 등 내부 IP·계정 하드코딩. → 환경설정/secret 이전 권장.

## 이번 세션에서 수정된 주요 버그 (추가)

### 15. 배포 시 phantom CP zone 2개 생성 (develop 반영)
- **증상**: 젠킨스 배포 후 pfSense CP zone 목록에 의도치 않은 zone이 1~2개 추가 생성됨.
- **Bug 1 (주범, 배포마다 발생)**: `cp_routing_setup.php`의
  `init_config_arr(['captiveportal','filter','aliases','gateways'])` 가
  중첩 경로 함수이므로 `$config['captiveportal']['filter']['aliases']['gateways'] = []` 를 생성.
  → `$config['captiveportal']['filter']` 배열 키 = phantom zone **'filter'**.
  첫 배포 시 `$changed=true` → `write_config()` → config.xml 영구 저장.
  **수정**: `init_config_arr(['filter','rule'])` + `init_config_arr(['aliases','alias'])` 분리.
- **Bug 2 (게이트웨이 차단 이벤트마다, #8 재발)**: `network_usage_timeperiod_check.php`의
  `captiveportal_add/remove_shutdown_gateway()`가 `$config['captiveportal']['shutdown_gateways']`
  에 문자열 직접 저장 → phantom zone **'shutdown_gateways'**.
  **수정**: `$config['system']['cp_shutdown_gateways']` 로 이전.
  `captiveportal.inc` 읽기 참조도 동일 이전.
- **self-heal 2중화** (커밋 `9476e47`): ① `common_ui.inc` print_sidebar — 관리 UI 첫 로드 시
  `filter`/`shutdown_gateways` 키 자동 제거(3개 dirty 플래그로 묶어 `write_config` **1회**로 통합).
  ② `cp_routing_setup.php` — **배포마다** 두 phantom 키 제거 + 기존 `$changed` 에 합산해 말미
  `write_config` 1회에 포함(추가 I/O 0) → GUI 방문 없이도 배포 시 정리.
- **교훈**: `init_config_arr`는 **중첩 경로** 함수 — 여러 top-level 키 초기화 시 각각 별도 호출 필요.
  `$config['captiveportal']`는 zone 전용 (#8과 동일 원칙). 커밋 `9bc6053`·`9476e47`.

### 16. CREW WIFI 리셋 자가복구(self-healing) 날짜키 (develop 반영)
- **문제**: 주기 리셋 크론(daily/weekly/halfmonthly/monthly)은 주기 경계에 **1회 발화**하는데
  표준 cron 은 catch-up 이 없어 **NTP 시각 점프(선박 위성 NTP)·재부팅·고부하**로 그 분을 놓치면
  해당 주기 리셋이 **통째로 누락**(monthly = 최대 한 달). 더블-런(분 `0,1`)도 2분 내 둘 다 놓치면
  무력 + 활성 유저 이중 disconnect 부작용.
- **핵심 설계**: 유저별 "마지막 리셋 주기키"를 **파일에 저장**(`/var/log/radacct/datacounter/reset-state/`)
  → `config.xml` 미사용이라 **매분 writer 크론과 동시 실행해도 lost-update 무관**(#10 C 회피).
  - 주기키: `D:YYYY-MM-DD` / `W:일요일날짜` / `M:YYYY-MM` / `H:YYYY-MM-H1|H2`. zero-pad 라 **사전식
    비교로 신/구 판정** 가능.
  - `cp_reset_user_if_due()`: 현재키가 저장키보다 **엄격히 최신인데 미마킹**이면(=경계 크론 놓침)
    즉시 보충 리셋. **최초관측은 마킹만**(배포 직후 진행중 주기 오리셋 방지), **시각역행 가드**
    (과거 키면 skip), **prepaid(`crewpay-`) 제외**(할당0 의미가 달라 usage-only 자가복구 부적합).
- **신규 파일**: `etc/inc/cp_usage_reset.inc`(헬퍼), `usr/local/cron/crew_usage_reset_selfheal.php`(점검 크론).
  `firewall_cronlist` **분 15,45(매30분, 경계 0~10분과 오프셋)**.
- **경계 크론 4종**: 리셋 직후 `cp_reset_mark_user()` 로 날짜키 마킹 → 자가복구가 같은 주기를
  **중복 리셋 안 함**(이중 disconnect 도 방지). `function_exists` 가드(버전섞임 방어).
- **검증**: 주기키 계산/사전식 신구판정/최초관측·중복방지·경계누락보충·이중리셋방지·시각역행·
  prepaid제외 전 시나리오 단위테스트 통과. 커밋 `aa0c759`.
- **한계(후속)**: 자가복구는 **usage-only**. monthly 의 forever 유저 삭제 / gateway usage 리셋
  side-logic 은 경계 크론에서만 수행(누락 시 미보충). prepaid 는 자체 크론(별도).

### 17. 리셋 견고성 보강 3종 (develop 반영)
- **(A) getsession 케이스 미스 → 무효 데이터 리셋** (커밋 `28c0876`): `reset_user_usage`가
  `getsession`(정확 일치)으로 활성 세션을 못 찾으면 disconnect 를 건너뛰고 used-octets 만 삭제 →
  **살아있는 세션의 다음 Interim 이 사용량을 되써서 리셋이 조용히 무효화**(로그는 success 오인).
  - `getsession()` → `strcasecmp` 케이스-무시(세션복구 #6 / index.php 로그아웃 경로 공통 이득).
  - `cp_get_sessionids_for_username_ci()` 신규: 다중 기기 동시 로그인의 **모든** 세션 반환.
  - `reset_user_usage()`: 매칭 세션 **전부** disconnect → **재확인 가드**(disconnect 후에도 활성
    세션 잔존 시 파일 삭제 건너뛰고 `DATA RESET aborted` 경고 + false → "조용한 실패" 가시화).
- **(B) monthly resync 누락** (커밋 `cde34a5`): monthly 크론이 forever 유저를 config 에서 삭제하나
  `freeradius_users_resync()` 미호출 → **삭제 유저가 radiusd 재로드 전까지 인증 통과**.
  daily/weekly/half/prepaid 와 동일하게 `$changed` 시 resync(HUP) 추가. (리셋 결과엔 무관, 삭제 전파용.)
- **(C) prepaid 중복 cron** (커밋 `9b8cbf7`): `firewall_cronlist` 에 `crew_prepaidmonthlyusage_reset_check.php`
  가 1일 00:00 **2개 항목** 중복 → 동시 실행(가드 없는 무조건 write → lost-update + 이중 resync/disconnect).
  중복 1개 제거(JSON 검증).

### 18. network_usage vnstat 오류 시 uncaught exception → graceful exit (develop `3cbc321`)
- **증상**: `network_usage_timeperiod_check.php` 가 PHP fatal(Type:1) "attempt to write a readonly database",
  간헐 발생.
- **원인**: `vnstat --json` 실행 시 vnstat 내부 SQLite `SQLITE_READONLY(8)` → vnstat 이 stdout 에
  `Error:...` 출력 → PHP 가 그 문자열을 `throw new Exception` 으로 받고 **uncaught → fatal → cron 전체 abort**.
- **간헐 이유**: 재부팅 직후 `/var`(tmpfs) vnstatd 초기화 race / 웹경로(`/usr/local/www`)+cron 동시 vnstat 호출 /
  vnstatd↔CLI write 경합.
- **수정**: throw 2곳 → `error_log + pclose + exit(0)`. vnstat DB 복구(부팅 후 vnstatd) 되면 다음 cron 부터 자동 정상화.

### 19. 게이트웨이 shutdown flapping → crew zone 전원 강제 로그아웃 (develop `a646380`·`bd317a2`)
- **증상**: 전 사용자 **동시 `INTERIM ZERO` + `REGRESS-KEEP` 폭주** + 반복 끊김.
- **사슬**: `network_usage` 게이트웨이 한도 판정이 **InfluxDB 1초 타임아웃**으로 사용량을 0(미달)으로 오독
  → shutdown 목록이 5분마다 등재/해제 토글 → `$isModified` 마다 **`captiveportal_disconnect_all()` 로 crew
  zone 전원 로그아웃**(+ ipfw 카운터 전부 리셋 → ZERO/REGRESS). disconnect 인자에 게이트웨이가 없어
  **무관한 단말까지** 끊김. (조회창이 "월초~현재"라 월말로 갈수록 무거워져 타임아웃 빈도↑.)
- **수정 3종(`a646380`)**: ① `get_datausage_from_db` 하드실패(타임아웃/비200) 시 `false` → 크론은 false 면
  상태 변경 없이 skip(flapping 차단). ② zone 전체 disconnect_all 제거 → "새로 shutdown 된 게이트웨이의,
  실제 차단되는 사용자(`antenna_allowed()=false`)만" 개별 disconnect(`cp_disconnect_users_blocked_by_shutdown`).
  ③ 조회 timeout 1→4초(크론만, UI 1초 유지).
- **불씨 제거(`bd317a2`)**: 게이트웨이 사용량 판정을 **원격 influx 합산 → 로컬 vnstat 이번달 누계**로 전환
  (`get_vnstat_month_to_date_map`/`get_gateway_monthly_usage_local`, `/var/db` 캐시 + stale-on-failure).
  influx 의 traffic 은 본래 이 박스 vnstat 의 원격 사본이라 왕복 불필요 → 원격 의존 제거 = 타임아웃·flapping
  원천 차단. influx 쓰기(대시보드)·UI 의 `get_datausage_from_db` 는 유지.
- **계측 영향(분석, 일부 미수정 수용)**: 카운터 리셋 반복 시 high-water 가드는 세션 중 쿼터만 지키고 Stop 은
  live(`CUR_TOTAL`)를 접어 **mid-session 리셋분 과소계상** 갭 존재. 게이트웨이/대시보드는 음수 delta·1초
  timeout drop 으로 손상 + flapping 과 피드백 루프.

### 20. crew 라우팅 과금 무결성 — cp_gw_* 테이블 재동기화(누수 차단) + 즉시화 (develop `0880718`·`7b1a4a7`)
- **정책 확정(B-1)**: 각 user id 는 `varusersterminaltype` 로 **고정 uplink** 에 route-to. **지정 uplink 다운 시
  블랙홀**(다른 uplink 로 절대 안 감 — 과금 오귀속 방지). **공란/'auto' 만 예외**(`cp_gw_default`=기본/자동
  라우팅; 이미 코드 반영: `cp_sync_routing_tables` 의 `null→cp_gw_default`).
- **누수 버그**: route-to/block 룰은 `cp_gw_*` pfctl 테이블에 IP 가 있을 때만 동작. `filter_configure()`
  (게이트웨이 up/down 이 자동 트리거)가 **빈 alias 기준으로 테이블 flush** → pass·block 둘 다 매칭 실패 →
  고정 사용자 트래픽이 **기본경로(반대 uplink)로 새어 오과금**. (block 룰=타 WAN egress 차단이 누수 본체
  차단이고, 재동기화가 "block 룰 항상 매칭" 보장. 기본경로가 항상 crew WAN 이라 "Skip rules when gw down"
  GUI 설정은 불필요.)
- **수정**: ① `cp_resync_pf_tables_only()` 신규 — CP 세션 DB 기준 테이블만 재적재(config/룰 미수정·filter
  미호출). **empty-guard**(세션 읽기 실패/빈 결과면 no-op → 전 테이블 flush 사고 방지) + **미해석-고정 가드**
  (terminal_type 설정됐는데 GW 매칭 실패면 cp_gw_default 흡수 안 하고 경고 로그). 매분 크론
  `cp_routing_table_resync.php` 등록. ② **즉시화**(`7b1a4a7`): dpinger 알람을
  `/usr/local/sbin/cp_gateway_alarm.sh` 래퍼로 교체(`gwlb.inc start_dpinger`, `file_exists` 폴백) — 표준
  `/etc/rc.gateway_alarm` 먼저 실행 후 `sleep 3` 백그라운드로 재적재 → 누수창 ~60초 → ~수초.

### 21. 끊김 진단 — 라우팅 버그 아님(사용 행태/단말 설정) + 완화 (develop `7561fe5`·`4e9dc25`·`04d603e`)
- **다척 로그 진단**: 옛 disconnect_all flapping(#19)은 **미발화**(REGRESS/대량 ZERO 없음). 현재 끊김의 주동인은
  **(a) Starlink↔VSAT uplink 전환/불안정, (b) 랜덤 MAC**(접속마다 변경 → `already_connected`/MAC
  마이그레이션 무력화 → 매 재접속 강제 재로그인), **(c) 쿼터 절약 WiFi on/off**(세션 plateau=무트래픽),
  **(d) 공용/빌려주기 기기 계정 handoff**, **(e) `noconcurrentlogins=last` 의 동시-2기기 kick-war**,
  **(f) blank 도배 단말**. → **전부 라우팅 패치로 안 잡힘**(라우팅 패치는 과금 누수만 해결).
  - 계정 정책: **동시 다중기기 불가**(noconcurrent=last 가 옛 세션 강퇴로 구현), **순차 다른기기(공용/빌려주기) 허용**.
- **완화 적용**:
  - **랜덤 MAC 끄기 로그인 안내문**(`captiveportal.inc renderLoginPortalHtml`, notice-warning, 영문 단문 `7561fe5`).
  - **다국어 i18n 1차**(`4e9dc25`): en/ko/tl/vi/id/zh/my 7종. `cp_supported_langs/cp_i18n_dict/cp_t/cp_resolve_lang`
    (captiveportal.inc). 언어결정 `?lang`→쿠키 `cp_lang`→`Accept-Language`(auto)→en. 로그인 페이지 전체 +
    셸(`captiveportal-crew.html`) `<html lang>`/title/상단 드롭다운(쿠키 저장 후 GET 재요청)/비번토글.
    ko 외 best-effort(현지검수 권장, 사전 한곳 수정). **2차(로그아웃/비번변경/JS 메시지) 미진행**.
  - **blank 폭주 완화**(`04d603e`): `index.php` 로그인 처리가 (line 497 가드가 `REQUEST_METHOD==='POST'` 로
    바뀌어) **로그인 의도 없는 POST**(OS 캡티브 탐지 자동 POST·빈 폼 재제출)에도 `authenticate_user('')` 호출 →
    `FAILURE("Username blank")` 로깅 + 빈 인증 spawn. → **빈 username 단락**: radmac 외 username 비면 인증·로깅
    없이 `portal_reply_page("login")` 만. 후속옵션(미적용): line 497 가드 `!empty($_POST['accept'])` 환원.
  - **운영 레버**: 만성 blank 도배 단말 식별(패스스루/차단) + 랜덤 MAC 끄기 안내 + dpinger 튜닝(uplink 전환↓)
    + DHCP 리스/idle-timeout 조정(WiFi 토글 시 무중단 복귀).

### 22. 관리자 PW 리셋 무작위 미반영 — 매분/5분 writer 크론 lost-update 차단 (develop `549681f`)
- **증상**: index.php(또는 admin GUI)에서 PW 리셋해도 **무작위로** 반영 안 됨. 재시작해도 안 되는 경우 잔존.
  (#10에서 PW 경로에 락을 깔았는데도 남아있던 케이스.)
- **근본 원인(진범 = 반대편 writer)**: PW 경로는 #10 C-2에서 `lock('freeradius_user_config')`로
  보호됐으나, **락을 공유하지 않는 writer 크론**들이 프로세스 시작 시점의 **stale `$config` 스냅샷**을
  들고 수 초간 블로킹 I/O(ping/telnet/curl/sleep) 후 `write_config()`로 **전체 트리를 통째 저장** →
  그 창에 끼어든 PW 변경을 되돌림. **config.xml 자체가 reverted라 재시작해도 복구 안 됨.**
  lost-update 는 대칭이라 **충돌하는 모든 writer 가 같은 락 + 최신 재로딩을 공유**해야 사라진다(#10 교훈).
- **진범 크론(위험순)**: `openvpn_restart`(매분, ping 5초 + VPN 끊김 중 매분 write) >
  `vlan_state`(5분, telnet 장치당 최대 12초) > `network_usage`(5분, curl) >
  `manual_routing`(매분, sleep 2, 만료 시만). 넷 다 동일 결함(스냅샷·락 미공유·재로딩 없음).
- **수정 (4개 크론, 동일 패턴)**: **느린 I/O 는 락 밖**에서 끝내고, 락 안에서 `parse_config(true)`로
  최신본 재로딩 후 **이 크론의 delta 만 재적용** → PW writer 와 같은 락 공유. 락은 짧게(I/O 제외).
  - `manual_routing`: `gateways` 의 `manualroute*` 키 unset, `sleep(2)`를 락 밖으로.
  - `openvpn_restart`: `openvpnrestart` 플래그 unset + **VPN 끊김 중 매분 불필요 write 제거**(플래그
    실제 존재 시에만 write → clobber 창 자체 축소), `$pingresult` 초기화.
  - `vlan_state`: `vlan_device.config=$newstate`, 두 `write_config`를 1개로 통합(net 결과 동일).
  - `network_usage`: `system.cp_shutdown_gateways` 만 재적용. **주의**: `$gateways`는
    `$config['gateways']['gateway_item']`의 **값 복사본**이라 루프 안 `rootinterface` 변경은
    config 에 반영 안 됨 → 실제 config delta 는 shutdown 목록 문자열 하나뿐.
- **무관 writer(무수정 확인)**: `gps_update`(write_config 주석처리), `crew_usage_timeperiod_check`(write_config 없음).
- **남은 갭(#10 후속, 미적용)**: 형제 admin writer(`reset_wifi_user`/`modify_wifi_user`/`del_wifi_user`) +
  API 단건 생성(`APIFreeRADIUSUserCreate` 비-bulk). 같은 락 패턴으로 확대 예정.

### 23. 비밀번호 변경 무반영의 진범 = HUP 가 rlm_files 를 재로딩 못 함 + radcheck(SQL) 이행 (develop `fd6ced2` + A 패치)
- **증상(선상 다발)**: 자가/관리자 비밀번호 변경이 **무작위로 안 먹힘**. 사용자 화면엔 "Password
  Changed Successfully" 가 뜨는데 실제로는 옛 비번만 통과. **radiusd 재시작해야만 새 비번 반영.**
  (#10/#22 의 lost-update 락을 다 깔았는데도 남아 있던 잔존 케이스의 진짜 원인.)
- **진단 사슬(이번 세션)**:
  1. `cp_routing_setup.php` 의 `cp_find_all_wan_gateways() undefined` fatal → **배포 버전 섞임**
     (captiveportal.inc 구버전 + cron 신버전). 코드 버그 아님, 일괄 재배포로 해소.
  2. `usr001` 로그인 거부(`[AUTH-UNKNOWN] attrs=` 에 **reply 속성 0개** = MS-CHAP 미실행) →
     "비번 틀림"이 아니라 **live radiusd 가 그 계정을 메모리에 안 갖고 있음**. `radiusd -X`
     재시작 후 정상화 → **HUP 가 users 파일 변경을 런타임 반영 못 한다**는 결정적 증거.
  3. 자가 비번변경 경로도 동일: `commit_change_pw`(captiveportal.inc:2886) →
     `freeradius_update_user($u, false)` → `freeradius_reload_or_restart_radiusd(true)` = **HUP**.
     `kill -HUP` 가 `ret==0`(시그널 전달)만으로 success 반환 → **silent-fail**(파일/ config 는
     새 비번, live 데몬은 옛 비번). 관리자 경로(`freeradius_users_resync`)도 HUP 라 동일.
- **근본 원인**: 이 박스에서 **`kill -HUP` 은 rlm_files(users/authorize)를 재로딩하지 않는다**(실측).
  on-disk 파일은 resync 가 정상 갱신(`freeradius_get_target_user_files()` = `/users` + active
  usersfile 둘 다)하므로 **재시작하면 고쳐짐** = HUP 무효가 유일 차이.
- **A 응급 패치(이번 커밋, freeradius.inc)** — 사용자 파일 변경 reload 를 HUP→**전체 재시작**:
  - `freeradius_update_user()` 의 reload: `freeradius_reload_or_restart_radiusd(true)` → **`(false)`**
    (= onerestart). 자가 비번변경(`commit_change_pw`)이 이 함수를 타므로 captiveportal.inc 무수정.
  - `freeradius_users_resync()` 의 reload도 동일하게 `(false)`. 관리자 PW/계정 변경 즉시 반영.
  - 함수 docblock 의 잘못된 설명("HUP 면 rlm_files 재읽기됨") 정정(향후 회귀 방지).
  - **트레이드오프(수용)**: 재시작 창 ~수초간 accounting(1813) 단절. PW 변경 빈도 낮고 NAS 재전송 +
    누적카운터 자가복구(#7)로 흡수. resync 는 `$changed` 가드 경로에서만 호출되어 불필요 재시작 최소.
  - (이력: #10 에서 accounting 보호 위해 HUP 로 갔으나, HUP 가 rlm_files 를 반영 못 하는 게
    드러나 PW 경로만 재시작으로 환원. 근본 해결은 아래 step3.)
- **B 근본 해결 = 인증 소스를 radcheck(SQL)로 (단계 이행)**: SQL 은 per-request 조회라 reload/
  재시작 자체가 불필요 → 즉시 반영 + accounting 무중단. authorize 템플릿은 이미
  **`files` 먼저 → 없으면 `sql`(radcheck)** 구조(serverdefault_resync, [3287~3296]).
  - **step1+2 스테이징 도구(`fd6ced2`, 비파괴적)**:
    - `usr/local/sbin/freeradius_migrate_users_to_radcheck.php` (step2): config.xml 사용자(비번
      check-item)를 radcheck 로 **멱등 이관**. 기본 DRY-RUN(/tmp 에 SQL 생성), `apply` 시만 DB 반영.
      비번 (attribute,value) 매핑은 `freeradius_build_single_user_stanza` 와 동일. 접속파라미터는
      `freeradiussqlconf` 에서 읽고(하드코딩 없음), 비번은 임시 defaults-extra-file 로 전달(argv 노출 방지).
    - `usr/local/sbin/freeradius_enable_sql_authorize.php` (step1): `varsqlconfenableauthorize=Enable`
      (+`includeenable=on`) 토글. `lock('freeradius_user_config')`+`parse_config(true)` 동시성 안전.
      적용 전 `serverdefault_resync`+`radiusd -C` 검증, 실패 시 플래그 롤백(재시작 안 함). 통과 시
      `freeradius_sqlconf_resync()`(GUI SQL 저장 경로)로 재생성+재시작. dry-run 기본, apply/disable 인자.
  - **step1+2 의 한계(중요)**: files 가 **먼저** 매칭되므로, files 에도 있는 기존 사용자는 radcheck
    의 비번 변경이 **즉시 반영 안 됨**(files 우선). 즉 step1+2 단독으론 PW 무반영을 **못 고친다**
    — radcheck-only 신규 사용자에게만 의미. **step3(컷오버)** 필요: PW writer(`commit_change_pw`/
    관리자)가 **radcheck 에 UPDATE** + authorize 에서 **sql 우선**(또는 files 쓰기 중단).
- **시간 한도(`Max-*-Session`) 현황(분석)**: GUI "시간량" 필드는 `Max-{Daily|Weekly|Monthly|Forever}-Session`
  check-item 생성([build_single_user_stanza:1089]). 이를 강제하는 `rlm_counter`(daily/weekly/...)는
  SQL accounting 켜짐 시 **삭제**(sqlcounter 와 배타적, [2941]), `sqlcounter`(dailycounter 등)는
  authorize SQL 꺼짐이라 **호출 안 됨** → **현재 세션시간 사용량 한도는 아무도 강제 안 함(무조건 통과)**.
  단 `Expiration`(만료일)/`Login-Time`(시간대)는 expiration/logintime 모듈이 authorize 상시 실행 →
  강제됨. 데이터(용량) 쿼터는 별개(파일기반 datacounter)로 정상. **운영상 시간량 필드 사용 사례
  없음 확인** → step1(SQL authorize 켜기) 시 sqlcounter 가 매 요청 돌아도 **전원 no-op**(거부 불가) = 안전.
- **권장 이행 순서**: A(응급, 본 커밋) 배포로 출혈 정지 → step2(radcheck 적재) → step1(SQL authorize) →
  step3(radcheck 권위화 컷오버)로 reload 의존 제거.
- **step3-A (구현 완료, develop `b121dda`): PW writer 가 radcheck 도 dual-write (동작변경 0)**:
  `freeradius.inc` 헬퍼 신규 — `freeradius_radcheck_conn_params`/`_password_item`/`_sql_str`/
  `_mysql_bin`/`_exec_sql`/`_sync_password`(단일)/`_sync_users`(배치)/`_delete_users`(배치). 접속
  파라미터는 freeradiussqlconf 에서 읽고, **mysql CLI + 임시 defaults-extra-file(비번 argv 노출 방지)
  + `--connect-timeout=4`, 실패 시 throw 안 하고 false(degrade)** → 기존 PW 흐름 안 깨짐. 배치는 단일
  SQL 트랜잭션. PW writer 5곳에서 **락 밖** 호출(`function_exists` 가드): `commit_change_pw`
  (captiveportal.inc) / `reset_wifi_user_pw` / `reset_random_wifi_user_pw` / `create_wifi_user`(생성
  일괄, return 을 finally 뒤로 이동해 락 밖 동기화) / `del_wifi_user`(radcheck 행 삭제).
  **files 가 여전히 권위 → auth 동작 변화 없음** — radcheck 를 항상 최신 유지(컷오버 토대).
  **3a 단독으론 즉시반영 효과 없음**(3b 가 있어야 효과).
- **step3-B 위험분석 (구현은 다음 항목 — 적용 판단 기준으로 유지)**: authorize 에서 sql 이 files 비번을 `:=` override → radcheck 권위화:
  계획 unlang(플래그 게이트, 기본 off): `files` → `if(ok||updated){update control{&Tmp-Integer-0:=1}}`
  (files 찾음 표시) → `sql1`(radcheck 비번으로 `:=` override, 없으면 no-op) →
  `if((notfound||noop) && Tmp-Integer-0!=1 && Auth-Type!=Accept){reject}`(files·sql 둘 다 못 찾을 때만).
  `serverdefault_resync` 에 **새 플래그 켜질 때만** 적용(코드 배포만으론 동작 무변경).
  - **위험도(보류 사유)**:
    - ① **untested unlang 로직 오류 → 유효 유저 오거부 = 치명(전면 outage)**. `radiusd -C` 는 문법만
      검증, "찾음 표시"·reject 분기 같은 로직 버그는 못 잡음.
    - ② **radcheck 가 비번 권위(신뢰 역전)** — radcheck stale/누락이면 **GUI엔 맞는데 로그인 안 됨**.
      3a 전수 커버 + 정기 reconcile(config→radcheck) 필요. (미커버 writer: API 단건 `APIFreeRADIUSUserCreate` 등.)
    - ③ **sql 이 매 인증마다 실행 = MySQL(192.168.209.210) 이 auth 임계경로** — DB 느림/불통 시 **전
      유저 로그인 지연/실패**. **심각도는 MySQL 위치에 종속**: 선내 로컬이면 낮음, **위성 너머 원격이면
      매우 높음**(#21 Starlink/VSAT 플래핑) → 그 경우 **3B 부적합**. sql `fail` rcode 시 files 비번으로
      graceful fallback + `radiusd -X` **DB-down 로그인 테스트** 필수.
    - ④ 비번 속성 충돌(files=Cleartext, radcheck=NT 등) → MS-CHAP/PAP 오작동. 동일속성 `:=` 통일로 완화.
    - ⑤ sqlcounter 매 요청 실행은 시간한도 미사용이라 no-op(분석 완료, 안전).
  - **안전장치**: **A안 dual-write 덕에 files 항상 최신 → 3B off(rollback) 시 즉시 정상복구**(B안이면
    files 비워 rollback 위험했을 것). 플래그 게이트(off 기본) → 코드 배포 안전.
  - **위험/효과 균형(중요)**: **#23 A(재시작)가 이미 "PW 변경 실제 반영"을 해결**함. 3B 의 추가효과는
    그 재시작 **수초 accounting 단절마저 없애는 최적화(polish)**, 필수 아님. PW 변경은 저빈도라 마진
    효과 대비 **core auth 복잡화 + SQL 의존** 비용이 커서 **보류**.
  - **재개 전제**: ① 플래그 off 기본 ② 한 척 `radiusd -X` + **DB-down 로그인 테스트** ③ **MySQL
    로컬/원격 확인**(원격이면 보류 유지) ④ 통과 후 플래그 ON → 며칠 관찰 → fleet 확대.
- **step3-B (구현 완료, 기본 off — 적용은 위 재개 전제 통과 후): radcheck 권위화 코드 + 토글 도구**:
  - `freeradius.inc serverdefault_resync`: `$config['system']['freeradius_radcheck_override']==='on'`
    && includeenable=on && enableauthorize=Enable 일 때**만** authorize 의 files/sql 블록을 교체 —
    `files` → `if(ok||updated){control Tmp-Integer-0:=1}`(files 매칭 표식) → `sql1{fail=1}`(radcheck
    `:=` override; **fail 의 기본액션 return 을 우선순위 1 로 바꿔** DB 불통에도 섹션 중단 안 함) →
    `if(fail){ok}`(**DB-down graceful fallback — files 비번으로 인증 계속**, 위험③ 코드 완화) →
    `elsif((notfound||noop)&&표식없음){ldap 폴백→reject}`(둘 다 없을 때만 기존대로 거부).
    radcheck 행은 step2/3-A 가 `op=':='` 로 적재하므로 files 비번을 덮어씀 = **비번 변경 즉시
    반영(재시작/accounting 단절 0)**. radcheck 미적재 사용자는 sql notfound → files 비번(안전 강등).
  - 플래그를 freeradiussqlconf 가 아닌 **system** 에 둔 이유: GUI 패키지 페이지 저장이 XML 미정의
    키를 떨굴 수 있어(#8/#15 원칙) — 떨궈져도 files 권위 복귀라는 **안전한 방향으로만 강등**.
  - **off 시 출력 바이트 동일 검증**: 실제 `serverdefault_resync` 를 pfSense 스텁 하네스로 구동해
    HEAD vs 수정본 생성물 diff 0 (sql-off / sql-on / 플래그만-on 3조합, CRLF 정규화) + flag-on
    생성물 구조 8항목·중괄호 균형 검사 전부 통과. → 코드 배포만으론 동작 무변경 보장.
  - 토글 도구 `usr/local/sbin/freeradius_enable_radcheck_override.php`: dry-run 기본 / `apply`
    (사전점검: step1 적용여부 + radcheck 커버리지(미적재 사용자 경고·보충 안내) + DB 도달성 +
    **버전섞임 감지**(재생성된 default 에 `Tmp-Integer-0` 마커 존재 확인) → `radiusd -C` → 실패 시
    플래그 자동 롤백·재시작 안 함) / `disable`(files 권위 즉시 복귀 — 3-A dual-write 가 files 를
    최신 유지하므로 **항상 안전한 롤백**). 락 `freeradius_user_config`+`parse_config(true)` (#10/#22).
  - **코드로 못 없앤 잔여 위험**: ①(unlang 로직 실기동 미검증 — `radiusd -C` 는 문법만)·③(MySQL
    이 auth 임계경로 — 위치 미확인) → **켜기 전 재개 전제(위) 그대로 수행할 것**.

### 24~26. 캡티브포털 무한 self-redirect 루프 → 25GB 로그 → ZFS 풀 full → 전면장애(502/OOM) (develop `f2f64aa`·`fce66ca` + 크론가드)
- **증상(선상 1척, 동일 환경 타척은 정상)**: 부팅 후 수시간 지나면 502 폭주 + webConfigurator/
  captiveportal/SSH 콘솔 메뉴까지 응답 불가. 재부팅하면 또 몇 시간 정상. `out of swap space` OOM
  로 php-fpm·radiusd 반복 사살. vnstat `readonly database(8)`, `failed to write temp file /var/...`.
- **진단 사슬(오래 헤맨 기록 — 교훈)**: 처음엔 vnstat readonly 만 보고 **디스크 배드섹터**로 오판 →
  `out of swap` 보고 **메모리(OOM)** 로 정정 → `top` 은 평상시 정상(스파이크가 너무 빨라 못 잡음) →
  `df -h` 에서 **ZFS 풀 full(/tmp 3.9G, 모든 데이터셋 Avail 20M)** 확인 → `ls -laS /tmp` 에서
  **`/tmp/cp_portal_error.log` ≈ 25GB**(ZFS 압축으로 디스크 3.9G) + `sess_*` 수천 개 발견 →
  로그 표본이 **같은 초에 sid 가 매번 다른 `REDIRECT(to self) url=/index.php?zone=crew&_ts=...` 수십 줄**
  = **무한 self-redirect 루프** 확정.
  - **교훈**: "한 척만 / 재부팅하면 몇 시간 / 208.x 차단 무효 / OOM / vnstat readonly" 가 전부 **단일
    진원(루프)** 의 갈래 증상이었다. 증상이 여러 서브시스템에 흩어져 보여도 **`df`(풀)·`ls -laS /tmp`·
    실제 로그 표본** 으로 내려가면 단일 원인에 수렴. 동일 코드 fleet 에서 **한 노드만** 터지면 그
    노드의 **부하/환경**(여기선 트래픽 최다 → 루프 회전수 최다)이 임계를 먼저 넘은 것.
- **#25 진원 = self-redirect 루프 (`index.php`, `fce66ca`)**: 미인증 GET(index.php:660)·연결 클라이언트
  GET(index.php:681)이 화면을 직접 안 그리고 **flash 저장 후 `cp_redirect_self()`** 로 자기 자신에 302 →
  "다음 요청에서 세션 flash 를 읽어" 렌더하는 구조. 이 PRG-via-session 패턴은 **세션쿠키(PHPSESSID)
  지속에 의존**. OS 캡티브탐지기/무쿠키 클라이언트는 쿠키를 안 돌려보내 **매 요청 새 세션(sid 매번 다름)
  → flash 못 읽음 → 또 self-redirect → 무한 루프**. 루프 1회전마다 ① 새 PHP 세션(`sess_*` 수천)
  ② `cp_log` 1줄 ③ php-fpm 부하(listen queue overflow).
  - **수정**: GET 진입은 `cp_render_from_flash([...])` 로 **직접 렌더**(login/connected). self-redirect 는
    **POST 의 PRG(재제출 방지)** 에만 남김. → GET 에서 리다이렉트가 사라져 쿠키 의존/루프 원천 소멸.
    POST flash 가 쿠키 미지속으로 유실돼도 후속 GET 이 이제 직접 렌더(graceful, 메시지만 생략).
- **#24 증폭 = 무제한 로깅 (`index.php`, `f2f64aa`)**: 상단이 `ini_set('error_log','/tmp/cp_portal_error.log')`
  + `cp_log()`(error_log 사용)라, **요청마다 모든 PHP warning + cp_log 를 /tmp 파일에 무제한 append**.
  루프와 만나 **25GB 폭발 → 풀 full → 모든 write 실패**(PHP세션/포털DB/vnstat/datacounter/로그) → 502 +
  ZFS dirty/스왑 무력화로 OOM.
  - **수정**: 프로덕션 기본 = **/tmp 리다이렉트 안 함**(시스템 관리 로그) + **fatal 계열만**(요청당 warning
    무적재). 디버그는 `touch /tmp/cp_portal_debug.on` 시에만, 그조차 **5MB 상한 초과 시 요청 시작에
    truncate**(하드 가드 — 디버그 중에도 디스크 못 채움). `cp_log()` 도 `CP_DEBUG` 게이트(프로덕션 no-op).
  - **방어 심층화**: #25 가 루프(원인)를, #24 가 무제한 로깅(증폭)을 각각 차단. 한쪽이 뚫려도 디스크
    폭주는 불가.
- **#26 안전망 = 크론 단일 인스턴스 가드 (per-minute 크론 7종)**: 풀 full 동안 크론들이 write 블로킹으로
  1주기 내 못 끝나 **매분 새 인스턴스가 떠 수십 개 누적 → RAM 고갈에 가세**(crew_usage·manual_routing·
  gps_update 각 ×2 등 관측). 의존성 없는 self-contained **flock(LOCK_EX|LOCK_NB)** 가드를 상단에 삽입 →
  이전 실행 미완료면 `exit(0)`. 대상: `crew_usage`/`manual_routing`/`gps_update`/`openvpn_restart`/
  `cp_routing_table_resync`(매분) + `vlan_state`/`network_usage`(5분). 락파일 `/tmp/cron_<name>.lock`(0바이트).
  - **효과**: 어떤 미지의 원인으로 디스크/부하가 다시 와도 크론이 누적→OOM 으로 **번지지 않음**(단일
    결함의 전면장애 전파 차단). 단 hang 한 1개는 남으므로 근본은 각 크론 I/O 타임아웃(별도).
- **선상 즉시 복구**: `: > /tmp/cp_portal_error.log`(열려있어도 truncate 라 회수) +
  `find /tmp -name 'sess_*' -mmin +60 -delete` → 풀 회수 → 본 패치 일괄 배포(버전 섞임 금지).
- **후속(미적용)**: OS probe 를 `session_start()` 전에 단락해 세션 생성 자체 회피 + 세션 GC 크론(연결성
  체크 폭주 환경의 `sess_*` 누적 완화). 크론 I/O 타임아웃 보강(hang 자체 제거).

### 27. Main Panel 안테나 트래킹 나침반 — VSAT/FBB look-angle 시각화 + FULL HD 세로압축 (develop `00f1bb1`)
- **요구**: Intellian ACU 레퍼런스 UI(나침반+선체+지향선+앙각게이지+HEADING/AZIMUTH/R.AZIMUTH/ELEVATION)
  분위기의 **동적 그림**을 Main Panel(`usr/local/www/index.php`) Satellite 타일의 ACU SIGNAL 위에 추가.
  (주의: 사용자가 "main_panel.php"로 지칭하는 파일 = 사이드바 "Main Panel" = `usr/local/www/index.php`.)
- **데이터 소스**: influx `acustatus`(vesselposition: Heading/Latitude/Longitude; satstatus: Longitude=위성
  궤도경도·"AGC/Signal"·TX_Mode) + `fbbstatus`(satstatus: Satellite 이름·Signal·GPS+방향컬럼).
  ACU 는 az/el 을 직접 보고하지 않으므로 **정지궤도 look-angle 공식으로 계산**:
  - 중심각: `cos β = cos φ · cos Δλ` (Δλ = 본선경도 − 위성경도)
  - 앙각: `tan el = (cos β − k) / sin β`, `k = Re/Rgeo = 6378/42164 ≈ 0.1513` (가시한계 β≈81.3°, el<0=수평선 아래=물리 불능)
  - 방위각: `Az = (180° + atan2(tan Δλ, sin φ)) mod 360` — atan2 형태라 남/북반구·적도 공용
  - 상대방위각: `R.Az = (Az − HDG + 360) mod 360` (스크린샷 검증: 94+6=100 ✓, 311+286=597→237 ✓)
- **신규 함수 (`server_module.inc`)**: `acu_influx_latest`(최신 1행 컬럼맵) / `cp_geo_look_angle` /
  `cp_format_satlon` / `get_acu_pointing_info` / `cp_fbb_satlon_from_name` / `get_fbb_pointing_info`.
  index.php 는 `function_exists` 가드(버전섞임 fatal 방지) + 기존 10초 `data_update` AJAX 에
  `acu_view`/`fbb_view` 필드 추가.
- **(a) 위성 궤도경도 W(서경) 부호 소실 — 실측으로 잡은 버그**: 선상에서 Az 256°/el **−61°** 오표시
  (el<0 이 결정적 단서). 본선 위치 역산(≈9°N 93°W) 결과 **위성 +55 입력 시 위젯 오표시값**, **−55(=55°W,
  Inmarsat I-5 F2) 입력 시 ACU 자체 화면(Az 100/El 45/R.Az 6)과 일치** → 공식이 아니라 입력 부호 문제로 확정.
  원인 = acureader(IntellianACUReader.java) satellite 파싱이 " W" 접미사를 부호 반영 없이 잘라냄.
  **원천 수정 완료(사용자, acureader 가 부호 보존 출력)** + pfSense 측 안전망: 계산 el<0 이면 반대
  반구(−satlon) 후보 채택(둘 다 −5° 미만이면 az/el 미표시=오표시 방지). **규약 확정: 음수=W, 양수=E,
  소수 1자리 고정 표시("55.0W")** — `cp_format_satlon` 한 곳에서 관리, 위젯(manage_server_module)·
  타일 문구 모두 적용. 부수효과: 1°W/2°W 위성이 에러 센티널("-1"/"-2" 비교)과 충돌하던 잠재 문제 제거.
  잔여: 함대 acureader 전체 갱신 후엔 W-플립 안전망 분기 제거 가능(주석 명시).
- **(b) FBB 나침반 표시**: FBB 단말은 궤도경도를 숫자로 보고하지 않고 **위성/해역 이름만** 보고하며
  표기가 단말기마다 다름(Sailor 웹스크랩 td/JRC dsb_inf_sat/FURUNO AT_ISATCUR+ISATINFO). 해석 3단:
  ① 이름 안 명시 경도 파싱("EMEA 25.0E"/"98W"/"25 deg E") → ② 해역명 키워드 매핑(부분일치):
  MEAS/MIDDLE=63.9E(I-4 F2), APAC/ASIA=143.5E(F1), AMER=−98(F3), EMEA/ALPHASAT=24.9E(Alphasat) —
  **순서 중요: MEAS/MIDDLE 을 ASIA 보다 먼저**("Middle East & Asia"가 ASIA 에 걸려 143.5 오매핑되던 것
  테스트로 잡음) → ③ 미매칭("None"/"IOR"/"I-6 F1" 등)이면 니들 미표시로 우아한 강등(새 표기는 map 한 줄 추가).
  FBB GPS 는 **부호 없는 값 + 방향컬럼**(lat-direction/lat_direction 혼재 → 둘 다 검사)이라 부호 복원,
  (0,0)=Sailor 리더 파싱실패 기본값 제외. 매핑 슬롯은 2026-06 기준 — 위성 재배치/I-6 편입 시 갱신 필요.
- **(c) terminal_status.inc 잠복 버그 교정**: FBB "No Signal" 판정이 `$vsat_status[1]`(VSAT 신호!)를
  보고 있었음 → `$fbb_status[1]` 로 교정(문구 재작성 중 발견).
- **UI 최종 사양 (반복 피드백 반영)**:
  - 상태줄 2개(같은 디자인): `VSAT : 55.0W (Signal : 142)`(민트, 추적 시 점 펄스) /
    `FBB : 98.0W (Signal : 61.2)`(파랑). 신호는 **반올림 없이 소수 그대로**. 기존 vsat_info/fbb_info
    텍스트 단락은 제거(백엔드 계산·JSON 필드는 구버전 캐시 페이지 호환 위해 유지).
  - 나침반: N/E/S/W 고정 + 눈금은 외륜 바깥, **내측 숫자(45~315)는 선수 기준 상대방위 다이얼로 선체와
    함께 회전**(접선 배치, 아래쪽 숫자 뒤집힘 = 레퍼런스 ACU UI 동일. CTM 검증: HDG94 에서 270→절대4°).
  - 니들: **선수선=검정 / VSAT=녹색 실선+도트 / FBB=파랑 실선**(점선·dash flow·펄스링 제거 — FBB 와
    동일 스타일로 통일). 앙각 게이지도 녹/파 두 바늘.
  - 메트릭 4박스: HEADING 은 공용(검정 고정), **AZIMUTH/R.AZIMUTH/ELEVATION 은 5초 로테이션**으로
    VSAT(녹색)↔FBB(파랑) 교대(한쪽만 있으면 고정, 1초 간격 12샘플로 토글 검증).
  - 회전 애니메이션: rAF 트윈 1.6s ease + **최단경로(wrap-around: 237→10 이 +133° 로)**, rotate 속성
    직접 갱신이라 SVG transform-origin 비의존. 탐색 스윕/선체 ±1.6° sway 는 SMIL(JS 부하 0).
  - 상태: tracking(민트)/searching(노랑 스윕)/blocked(빨강 니들)/nodata(흐림 — 단 FBB 가 살아있으면
    흐림 제외 `:not(.has-fbb)`)
- **FULL HD(1080) 세로압축**: index.php **인라인 CSS 페이지 한정 오버라이드**(전역 style.css 무수정 —
  다른 페이지 영향 0): 타일 padding 40→22, dd 여백 40+40→18+18, 아이콘 48→40, 나침반 300→260px,
  Server Status 위 여백 40→14/20→8. **utility.css 의 .mt40/.mt20 은 `!important` 라 덮을 때도
  `!important` 필요**(처음 안 먹던 원인). 실측(리포 CSS 6종 로드 하네스, 1920×1080): 콘텐츠 954→**822px**
  (최장 Satellite 타일 578→461px) → 1080 에서 무스크롤. 상태 배너 폰트는 축소했다가 **원복(30px)**.
- **수정 파일(=배포 묶음, 동일 리비전 필수)**: `usr/local/www/index.php` + `etc/inc/server_module.inc` +
  `etc/inc/terminal_status.inc` + `usr/local/www/widgets/widgets/manage_server_module.widget.php`
- **검증**: php -l 전부 통과 / FBB 이름 매핑 10케이스·satlon 포맷 14케이스·look-angle 5시나리오(반구
  플립 포함) 단위테스트 통과 / 브라우저 하네스(리포 실 CSS)로 4상태·이중니들·로테이션·높이 실측.

### 28. 항구 미니맵 — WoW 풍 원형 미니맵 + 최근접 3개 항구 방위/거리 화살표 (통합 완료)
- **요구**: GPS 판넬(Position 타일) 아래 원형 미니맵. 오프라인용 경량 지도 사전 저장, 전세계 주요 항구
  리스트, 현재 위치→최근접 3개 항구를 방위각 화살표+거리로 표시, GPS 갱신(약 1분)마다 재계산.
- **자산**: `usr/local/www/img/world_minimap.jpg` — NASA Blue Marble 등장방형 2048×1024, 233KB,
  퍼블릭 도메인. 타일서버/인터넷 불필요(위경도→픽셀 선형 매핑으로 background-position 패닝).
- **데모**: `minimap_demo.html`(리포 루트 — 배포 트리 밖, 선상 미배포). 항구 82개 내장(JS 배열),
  하버사인(nm)+대권 초기방위각, 금테 2중 링+4방위 다이아+중앙 본선 마커(선수방위 회전)+림 화살표
  3개(1~3위 크기·색 차등, 최단경로 트윈)+이름·거리·방위 리스트. 항해 시뮬레이션 버튼 포함.
  지리 정합 검증: 28.6N/119.8E 에서 대만 우하단, SHANGHAI 28°/KAOHSIUNG 176° ✓.
- **통합 (develop `303bb05`)**: index.php Position 타일 gps_info 아래 삽입 완료.
  - 백엔드: `get_acu_pointing_info`/`get_fbb_pointing_info` 가 수치형 `lat`/`lon`(소수 5자리) 추가
    반환 — (0,0)=GPS 미수신 기본값은 null 처리. 추가 influx 쿼리 0(기존 조회 재사용).
  - 프런트: `updatePortMinimap()` 이 acuLastVsat(VSAT GPS 우선)→acuLastFbb(폴백) 순으로 위치를
    골라 지도 패닝+본선 마커(선수방위)+최근접 3개 림 화살표+리스트 갱신. updateAcuCompass/
    updateFbbCompass 끝에서 호출 → 기존 10초 data_update AJAX 가 자동 트리거(GPS 는 약 1분 주기).
    위치 없으면 **회색 처리**(`no-gps` 클래스): 금테 링 유지 + 지도 대신 어두운 회색 디스크 +
    중앙 "NO GPS" + 화살표/마커 숨김 + 리스트 영역 min-height 62px 예약 → 0,0 대서양 오표시 방지
    겸 GPS 단속(부팅 직후 등)에도 타일 높이 불변(레이아웃 점프 없음). 회전은 나침반과 같은
    acuRotateTo 트윈(최단경로) 재사용.
  - 검증: 배포 JS 를 그대로 추출(node --check + DOM 스텁 하네스)해 E2E 스모크 — 초기 숨김 /
    VSAT 위치(동중국해→NINGBO·SHANGHAI·KAOHSIUNG) / FBB 폴백(파나마→BALBOA 16.3nm·COLON
    16.7nm) / 양측 소실 시 재숨김 전부 통과. php -l 통과.
  - 1080 영향: Position 타일 +약 296px → 최장 타일이 Satellite(461)→Position(~510)으로 바뀜.
    전체 콘텐츠 ~871px — 1080 뷰포트 내 유지.
- **on-map 항구 점 표시 (`1775f85`)**: 지도 표시 범위 내 항구는 림 화살표 대신 **지도 위 실제 위치에 점(●)+이름** 렌더.
  - **판정**: 줌 스케일(`MM_D/MM_SPANS[zoom]` = px/°)로 svgX/svgY 계산 → 원점(본선)으로부터 반경 102px(= 디스크 110 − 마진 8) 이내면 on-map. 경도 wrap-around(antimeridian) 처리.
  - **on-map 렌더**: SVG `mm_port_dots` 그룹에 글로우 원(opacity 0.22) + 솔리드 점(r=3.5) + paint-order stroke 이름 레이블(좌/우 text-anchor 자동). 림 화살표는 `display:none`.
  - **off-map**: 기존 림 화살표+`acuRotateTo` 그대로.
  - **리스트**: on-map = ●+거리+"on map" / off-map = ▲+거리+방위.
  - **GPS 소실 시**: 인라인 `display` 오버라이드 해제 → CSS `.no-gps` 규칙 정상 적용.
  - **줌 레벨별 가시 반경**: 8°≈220nm / 12°≈340nm / 18°≈510nm / 26°≈740nm / 36°≈1020nm.
  - 좌표 계산 8케이스(wrap 포함) 하네스 검증 전부 통과.

### 29. time_offset 외부 API 의존 제거 — GPS 기반 오프라인 타임존 자동판정 (develop 반영)
- **배경**: GMT 로컬타임 오프셋(`$config['time_offset_enabled']['time_offset']`)을 기존엔 **외부
  시스템이 REST API(`APIStatusSetTimeOffset`)로 푸시**했으나 효용 저하 → 박스 내장 판정으로 전환.
  **표시부(사이드바 "GMT n"/head.inc/CP 로컬시간/미니맵 시계)는 같은 config 키를 읽으므로 전부 무수정.**
- **시차 테이블(오프라인)**: 생성 시점에 인터넷에서 최신판을 받아 리포에 박제 — 런타임 위성통신 사용 0.
  - 소스: `@photostructure/tz-lookup` v11.5.0 (IANA timezone-boundary-builder; **해상은 nautical
    Etc/GMT±N**, 도서국 EEZ 부근은 해당국 zone — 예: 키리바시 수역 Pacific/Tarawa).
  - 생성기 `tools/generate_cp_tz_grid.js` (#28 cp_ports 생성기 패턴): **0.5° 격자**(셀 ~30nm,
    360행×720열) → 행별 RLE(base36 "zoneIdx:run") → `etc/inc/cp_tz_grid.inc` (PHP return array,
    66KB, zones 419개). 갱신 = npm 재설치 후 생성기 재실행.
- **런타임 `etc/inc/cp_geo_tz.inc`**: `cp_tz_zone_for_position`(격자 룩업, 경도 wrap 정규화) →
  `cp_tz_zone_offset_hours`(PHP `DateTimeZone` = OS/PHP tzdata 로 현재 오프셋, **DST 자동**) →
  실패 시 `cp_tz_nautical_offset_hours`(경도/15 반올림) **폴백 — 항상 성공, fatal 없음**.
  - **구 tzdata 별칭**: pfSense 2.5.2(PHP 7.4, ~2021 tzdata)가 모르는 개명 zone 재시도 매핑
    (Europe/Kyiv→Kiev, Pacific/Kanton→Enderbury, America/Ciudad_Juarez→Ojinaga, Nuuk→Godthab,
    Qostanay→Almaty). 그래도 실패 → nautical 폴백.
  - `cp_tz_format_offset`: 정수는 "9"/"-3"(기존 수동/API 값과 동일 표기), 반시간대만 "5.5"/"5.75".
- **크론 `usr/local/cron/cp_tz_offset_update.php`** (firewall_cronlist **매시 7분**):
  - #26 flock 단일 인스턴스 가드. cp_geo_tz.inc 미배포(버전 섞임) 시 조용히 종료.
  - **`gmtcheck`='1'(Manual Timezone Enable) 이면 절대 안 덮음** (기존 API 와 동일 규약; 락 안 재확인).
  - 위치: influx(선내 LAN, timeout 2s) `vesselposition`(VSAT) → `fbbstatus.satstatus`(FBB, 부호
    복원) 폴백 — 미니맵과 동일 정책. self-contained(서버모듈 비의존 — openvpn.inc 연쇄 회피).
    GPS 없으면 마지막 오프셋 유지(no-op).
  - **변경 시에만** write: `lock('freeradius_user_config')`+`parse_config(true)`+delta(키 1개)만
    재적용 (#10/#22 lost-update 패턴). exit-in-finally 함정 회피(플래그 방식). `TZ AUTO:` 로그.
- **검증**: Node 원본 vs PHP 격자 디코드 **2000 랜덤 좌표 교차검증 100% 일치** + 기지점 15케이스
  (부산 +9/뭄바이 +5.5/카트만두 +5.75/LA DST −7/런던 BST +1/난틱컬 해역/안티메리디안/경도 wrap)
  + 포맷 6/별칭/미지zone/비수치 입력 전부 통과. php -l/cronlist JSON 검증 통과.
- **한계(수용)**: 박스 PHP tzdata 가 ~2021 고정 → 이후 DST 규칙 변경국(이란/멕시코 2022, 이집트
  2023, 카자흐스탄 2024 등)은 해당 해역에서 1시간 오차 가능(주요 항로 EU/미주/아시아는 2007년
  이후 규칙 불변이라 무영향). CP 로컬시간 계산은 기존부터 정수 시간만 지원(+5.5 해역에선 30분
  절사 — 기존 동작 유지). **외부 푸시 API 는 잔존**(수동/원격 보정용) — 중앙서버 푸시는 중지
  권장(이중 writer 방지; 크론은 변경시에만 쓰므로 충돌해도 lost-update 는 없음).

### 31. CNA(OS 캡티브 미니브라우저) 로그인 창 — 로그인 폼 + "Copy address"(현재 기본 OFF)
- **⚙️ 현재 상태(중요)**: "Copy address" 블록은 `renderLoginPortalHtml` 안 `$cpShowCopyAddr` 플래그로
  **기본 `false`(미표시) = 이 스레드 이전 로그인 폼 모습으로 복구**. 코드는 `if ($cpShowCopyAddr)`
  안에 **그대로 보존** → `true` 한 줄이면 재노출. (사용자 지시로 raw 포털 주소 노출을 끔. 자동닫힘
  기계장치는 그 전에 index.php 에서 이미 완전 삭제 — 아래 🗑️ 항목.)
- **✅ 설계 의도 (재노출 시 = `$cpShowCopyAddr=true`, 영문 고정)**: CNA 가 로그인 후 창을 닫는 OS
  동작은 못 바꾸고, 자동닫힘 스푸핑은 삼성에서 불가(아래 제거 경위) → **CNA 에 로그인 폼을 그대로
  두고(원래 동작), 로그인 폼에 "Copy address" 버튼을 노출**해 3경로 커버:
  - **경로 1(기존)**: CNA 폼에 ID/PW → 로그인 → 창 자동 닫힘(성공 감지) = 온라인. 가장 빠름.
  - **경로 2**: 폼 입력 싫음 → "Copy address" 탭 → CNA 닫고 → 크롬 등에 붙여넣기 → 거기서 로그인.
  - **경로 3(S20 등 자동 안 닫힘)**: "Copy address" → 상단 "이 네트워크를 그대로 사용" 수동 닫기
    → 브라우저 붙여넣기 → 로그인. (복사 버튼 자체는 창을 안 닫음 — 닫기는 경로1=로그인성공뿐.)
  - **구현 (`etc/inc/captiveportal.inc` `renderLoginPortalHtml` 단일 추가)**: 로그인 폼 아래
    "Copy address" 블록 — 안내문/버튼 **영문 고정**(다국적 선원 공통 이해, i18n 미적용). 복사할
    주소 = JS `location.protocol+'//'+location.host`(302 후 실제 포털 호스트가 권위) + 서버측
    `$_SERVER['HTTP_HOST']` 폴백(무JS 시 수동 입력용, user-select:all). 복사 = HTTP(비보안)라
    `navigator.clipboard` 부재 → `document.execCommand('copy')` 동기 경로(#31 CNA 에서 동작 확인됨).
    "Copied!" 피드백. 일반 브라우저에도 노출(무해 — 공유/타브라우저 열기용).
  - **검증**: php -l + 렌더 하네스 10검사(버튼·영문문구·주소박스·서버폴백·execCommand·location·
    Copied!·한국어 미혼입·로그인폼/쿼터카드 유지) 전부 통과.
- **🗑️ (제거됨) 자동닫힘 접근 — 코드 일괄 삭제**: 처음엔 CNA 에 로그인 폼 대신 안내페이지를 띄우고
  "주소 복사" 후 ack 마커 + OS 프로브에 성공응답(204/Success) 스푸핑으로 **창을 자동으로 닫게**
  하려 했으나(guide/ack/probe-success/done 페이지 + probeMap + `CP_CNA_GUIDE_ENABLED` 게이트),
  **삼성 S20 Ultra 에서 동작 불가 확정** → `index.php` 에서 관련 코드 전부 삭제하고 **원래 동작
  (OS 프로브 → 302 로그인 / 미인증 GET → 로그인 페이지 / 세션 항상 생성)** 으로 복원.
  (`cp_detect_os_probe`/`cp_render_cna_*`/`cp_probe_*`/`cp_cna_*`/`CP_CNA_*` 전부 제거. 남은 건
  위 ✅ Copy address 버튼뿐.)
  - **삭제 이유 = 자동닫힘이 삼성에서 구조적 불가(재시도 금지 교훈)**: ① 삼성 CaptivePortalLogin
    webview 가 **페이지 내 top-frame 내비게이션을 차단** — 스크립트 `location.href` 도 실제 `<a>`
    앵커 탭도 **둘 다 무시**(2회 실측, ✓ 완료 페이지 안 뜸) → ack 마커 자체를 못 찍음. ② 설령
    찍어도 삼성 창 닫힘은 **HTTPS 프로브 검증**(`https://www.google.com/generate_204`)에 의존 —
    인증서·미인증 443 차단으로 **스푸핑 불가**. 즉 팝업을 유지하며 삼성 창을 자동으로 닫는 건
    **포털 측 어떤 방법으로도 불가능** → 자동닫힘 포기, Copy address(수동 경로)로 귀결.
  - **(미채택 대안) B = 전면 억제**: pfSense Allowed Hostnames 로 미인증에도 HTTP 프로브 통과 →
    팝업 자체 제거. 발견성(로그인 안내 팝업) 상실 트레이드오프로 미채택(필요 시 운영 설정으로 가능).

### 30. `varusersmodified="update"` 전원 의도치 않게 mass-set → 전원 동시 끊김 (develop 반영)

- **증상**: 특정 시각(00:01이 아닌 07:03 등 임의 시각)에 활성 사용자 **전원이 동시 DISCONNECT**
  (같은 PID 가 25명을 13초에 순차 처리 = `crew_usage_timeperiod_check.php` 루프). 이후 재접속
  시 정상화. `config.xml` 에 비CP계정(`synersat` 등)의 `varusersmodified="update"` 플래그가
  영구히 잔존해 해당 계정이 CP에 접속하면 **매분 kick** 반복.
- **진단 사슬**:
  1. `DISCONNECT` 25건이 **동일 PID** 로 연속 → `captiveportal_disconnect_all()` 아니라
     `crew_usage_timeperiod_check.php` 가 `varusersmodified="update"` 유저를 루프 disconnect.
  2. mass disconnect 가 **00:01(크론 직후)이 아닌 07:03** → reset 크론이 아니라 **관리자가
     위젯을 제출한 시각**.
  3. `manage_freeradiususer.widget.php` 의 POST 핸들러가 **페이지 로드 시점(T0) 의 stale
     `$config` 스냅샷으로 `write_config()`** → T0 당시 `varusersmodified="update"` 였던 모든
     사용자의 플래그가 복원(lost-update). reset 크론이 플래그를 세트 → 유저들이 로그인해
     authenticate_user 가 플래그를 지움 → 관리자가 위젯 제출(T0 스냅샷 덮어씀) → 플래그 복원.
  4. `synersat` 등 비CP계정은 `captiveportal_authenticate_user` 경로를 타지 않아 플래그가
     **자동으로 절대 지워지지 않음** → kick 크론이 매분 kick 시도.
- **버그 2종(중첩)**:
  - **A. 위젯 lost-update** (`manage_freeradiususer.widget.php`): 모든 POST 분기
    (deluser/resetuser/resetpw/createuser)가 lock/parse_config(true) 없이 stale `$config` 로
    `write_config()` → 다른 writer 의 변경을 통째 덮어씀.
  - **B. kick 후 플래그 미해제** (`crew_usage_timeperiod_check.php`): `captiveportal_disconnect_client`
    호출 후 `varusersmodified` 를 지우지 않아 비CP계정은 영구히 플래그 잔존 + 매분 kick.
    `captiveportal_authenticate_user` 의 `write_config("freeradius user update")` 도 동일한
    lost-update 위험(stale `$uconf` 참조로 write).
- **수정 3곳**:
  - **위젯** (`manage_freeradiususer.widget.php`): 4개 분기 모두 로그 등 느린 I/O 는 락 밖,
    실제 config 수정은 `lock('freeradius_user_config', LOCK_EX)` + `parse_config(true)` +
    delta 재적용 + `write_config` + `unlock` 패턴으로 전환(#22/#10 패턴 일관화).
    createuser 분기는 fresh config 기준으로 다음 username 도 재계산 → 중복 생성 방지 부수효과.
  - **kick 크론** (`crew_usage_timeperiod_check.php`): disconnect 후 `$flagsToClear` 수집 →
    루프 종료 후 `lock + parse_config(true)` + `varusersmodified = ''` + `write_config`.
    비CP계정 영구 플래그 잔존 해소. `varusersresetquota` 는 authenticate_user 폴백이 필요하므로
    유지(kick 크론은 modified 만 클리어).
  - **인증 경로** (`captiveportal.inc` `captiveportal_authenticate_user`): `unset($uconf['varusersmodified'])`
    + `write_config("freeradius user update")` 를 `lock + parse_config(true)` + fresh config 내
    사용자 재탐색(`strcasecmp`) + `unset` + `write_config` + `unlock` 으로 교체. stale `$uconf`
    참조 덮어씀 차단.
- **선상 즉시 조치**: 잔존 플래그 수동 클리어 (아래 — `synersat` 예시)
  ```sh
  php -r "
  require('/etc/inc/config.inc'); require('/etc/inc/util.inc'); parse_config();
  global \$config;
  foreach (\$config['installedpackages']['freeradius']['config'] as \$k => \$u) {
    if ((\$u['varusersmodified'] ?? '') === 'update') {
      \$config['installedpackages']['freeradius']['config'][\$k]['varusersmodified'] = '';
      echo 'cleared: ' . \$u['varusersusername'] . PHP_EOL;
    }
  }
  write_config('manual clear varusersmodified');
  "
  ```
- **배포 정합성**: `manage_freeradiususer.widget.php` + `crew_usage_timeperiod_check.php` +
  `captiveportal.inc` 3파일 일괄 배포.

### 32. 원격 voucher API ↔ crew_account.php 정합 (create/update/delete) — 미커밋
- **배경**: 원격 REST API(`/api/v1/freeradiususer`: **PUT=create / POST=update / DELETE=delete**)가
  웹 admin(crew_account.php)의 `create_wifi_user`/`modify_wifi_user`/`del_wifi_user` 와 시그니처·동작이
  어긋남(파라미터 누락 / 로직 누락 / 단건만 지원).
- **Create** (`etc/inc/api/models/APIFreeRadiusUserCreate.inc`): `create_wifi_user` 6번째 **필수** 인자
  `issimplefied` 누락 → ArgumentCountError fatal. 추가 + **기본 false 방어 정규화**(문자열 `"false"`/`"0"`
  등 PHP truthy 함정 차단). 단건 생성 경로에 `freeradius_users_resync()` 추가(bulk 는 create_wifi_user 내부).
- **Update** (`APIFreeRadiusUserUpdate.inc`): ① `timeperiod` 분해(modify_wifi_user 와 동일:
  `half-Monthly`→halftimeperiod/pointoftime/maxtotaloctetstimerange) ② `userlist`(배열/콤마문자열) +
  `freeradius_username`(단일/배열) **다건 수신·루프**(단일 하위호환), `varusersusername` 키 보호,
  used-octets 파일경로 per-user ③ `action()` 에 resync(+`require_once("freeradius.inc")`).
- **Delete** (`APIFreeRadiusUserDelete.inc`): **다건 수신** + 캐논 함수 `del_wifi_user($userlist)` **재사용**
  (다건+파일정리+resync+radcheck(#23)+로그 자동 정합; `delete_done` 플래그로 action 중복 write 방지).
- **`weekiy`→`weekly` 오타 수정**(`del_wifi_user` + API delete): 주간 datacounter 파일이 삭제 안 되던 버그.
- **`manage_crew_wifi_account.inc` 디버그 `echo($issimplefied)` 제거**(API JSON 응답 오염 차단).
- **timeperiod 대소문자 방어 정규화(입력 단계, Update+Create 양쪽)**: API 입력은 케이스 정규화 없이
  `explode('-')` 후 세그먼트를 **verbatim 저장**(timerange 만 소문자)이라, `Half-Monthly`/`HALF-MONTHLY`
  를 보내면 config.xml·관리테이블 표기가 웹(`half`/`Monthly`)과 어긋나고, **단건(create_userinfo) raw 복사
  경로는 timerange 도 소문자화 안 해** `Monthly` 저장 시 datacounter 디렉터리(`.../monthly/`) 불일치
  잠재버그. → 입력 단계에서 **half=소문자·pointoftime=`ucfirst(strtolower())`·timerange=소문자**로 캐논화:
  Update(분해 직후), Create-bulk(결합문자열 pre-norm 후 `create_wifi_user` 전달; 공유 함수 무수정),
  Create-single(`create_userinfo` 3필드 + validated_data 동기화). **기능은 원래도 정상**(소비측 전부
  `strtolower` 비교) — 미관/일관성 + 단건 timerange 잠재버그 차단. explode 의 `-` 분해 의미는 불변(회귀 0).
- 검증: php -l + 단위/런타임 하네스(다건 수집·키보호·방어정규화·timeperiod 분해/케이스수렴·del 재사용·폴백) 통과.
- **배포 정합성**: 5파일 일괄(APIFreeRadiusUser{Create,Update,Delete}.inc + manage_crew_wifi_account.inc + crew_account.php).

### 34. API random PW / israndompw true/false / Topup delta / 3D돔 방향 수정 (develop `92ae399` / main `369da8e` / prod `7a7195f`)
- **israndompw true/false 정규화 (API + 웹 공통)**: 기존 `"randpwd"` 문자열 값을 `true`/`false` 불리언으로 전환.
  PHP 함정(`"false"` 문자열은 truthy) 대비 명시 falsy 목록 `['','0','false','no','off']` 적용.
  구값 `"randpwd"` 는 truthy 라 **자동 하위호환** 유지.
  - `manage_crew_wifi_account.inc` `create_wifi_user`: `if ($israndompw === "randpwd")` → `$do_randpw` 정규화 판정.
  - `crew_account.php`: checkbox `value="randpwd"` → `value="true"`.
  - `APIFreeRadiusUserCreate.inc` 단건 경로: `__validate_username()` 내 `israndompw` 주입 로직 신규 추가
    (6자리 숫자 난수 생성, `create_wifi_user` 와 동일 알고리즘). bulk 경로는 `create_wifi_user` 내부가 처리.
  - `APIFreeRadiusUserUpdate.inc` `update_userinfo()`: `israndompw=true` → 사용자마다 독립 6자리 난수 생성,
    `israndompw=false` → 비밀번호를 `"1111"` 로 초기화(명시적 리셋), `israndompw` 미지정 → 비밀번호 무변경.
- **Update `freeradius_lastbasedata` timerange 폴백 + foreach 버그 수정** (`APIFreeRadiusUserUpdate.inc`):
  - 기존: `freeradius_lastbasedata`(MB 단위 파일 직접 쓰기) 블록이 `foreach($userentry)` 루프 **안**에 있어
    config 키(~20개) 순회마다 파일을 N번 반복 open·write. 또한 `maxtotaloctetstimerange` 가 페이로드에 없으면
    동작 안 됨.
  - 수정: 블록을 foreach **밖**(루프 종료 후)으로 이동. `timerange` 는 페이로드 미포함 시 **기존 config 값
    자동 폴백**(`varusersmaxtotaloctetstimerange`).
- **#23 step3-A radcheck 동기화 (Create 단건 + Update israndompw 경로)**:
  - Create 단건: `action()` 에서 `freeradius_radcheck_sync_users()` 호출(비밀번호 있을 때).
  - Update: `israndompw` 명시 시(true→난수/false→1111) `_radcheck_entries` 누적 → `action()` 에서 일괄 sync.
- **Topup delta 가감 (`APIFreeRadiusUserTopup.inc`)**:
  - 신규 필드 `freeradius_lastbasedata`(MB 단위 정수, 양수=+, 음수=-): used-octets **베이스 파일**만 직접 수정.
    세션 파일(`used-octets-{user}-{SID}`)은 건드리지 않음 → datacounter_auth.sh 합산이므로 delta 즉시 반영.
    하한 0(음수 방지). 기존 태그명 `freeradius_usageadjust` 에서 변경.
  - `freeradius_maxtotaloctets`(쿼터 증감)와 `freeradius_lastbasedata`(사용량 증감)를 **독립 필드**로 분리.
    둘 다 0이면 해당 블록 skip(로그에도 미포함).
  - **로그 0값 가드**: 두 필드 모두 값이 0이면 로그 문자열에서 제외(기존 `quota+0MB` 오출력 차단).
- **3D 안테나 스카이돔 좌우 반전 수정** (`usr/local/www/index.php`):
  - `pitch = +0.74`(남쪽 아래 시점 → 북이 화면 하단에 표시) → `pitch = -0.74`(북쪽 위 시점 → 북이 화면 상단).
  - `yaw = -0.5` → `yaw = 0`(초기 정면 정렬).
- **배포 정합성**: `APIFreeRadiusUserCreate.inc` + `APIFreeRadiusUserUpdate.inc` + `APIFreeRadiusUserTopup.inc`
  + `manage_crew_wifi_account.inc` + `crew_account.php` + `index.php` **6파일 일괄 배포**.
- 검증: php -l 전부 통과 / israndompw 정규화·6자리 난수·1111 리셋·lastbasedata foreach 버그·delta 가감·0값 가드
  런타임 하네스 통과.

### 37. Release Note 사이드바 메뉴 + 패치노트 표시 페이지 (develop `1f0c4da`)
- **요구**: 사이드바에 "Release Note" 메뉴를 넣고, 패치노트(릴리스노트)를 사용자가 보기 좋은 양식으로 표시.
- **데이터 소스 = 단일 마크다운(배포 트리 내)**: 배포 스크립트가 repo 에 없고 루트 파일은 배포 트리
  (`usr/`, `etc/`) **밖**이라 선상 미배포 → **단일 소스 `usr/local/www/release_note.md`** (배포 트리 내)
  하나만 두고 페이지가 이를 파싱. **이 파일만 편집·배포**하면 됨(루트 RELEASENOTE.md 는 제거 = A안).
  파일 없으면 "No release notes available." **graceful 폴백(fatal 없음)**.
- **수정/신규 파일**:
  - `etc/inc/common_ui.inc` `print_sidebar`: `$mk("release_note.php","Release Note","ic-lnb06")` 추가
    (Download Center 아래). 메뉴 하이라이트는 기존 `$mk` 의 `$inputlink===$file` 규칙으로 자동.
  - `usr/local/www/release_note.php` (신규): 릴리스노트 양식 파서(`rn_parse`) + 카드 렌더. 양식 =
    상단 메타(타이틀+설명) → `X.Y.Z (YYYY-MM-DD)` 버전헤더(날짜 괄호로 서브라인과 구분) →
    **자유 양식 서브라인**(헤더 직후 첫 비-불릿 줄을 verbatim 캡처 — `Beta … Stable: …` 등 무관) →
    `- TAG: text`(NEW/CHANGED/FIXED/REMOVED) 불릿(들여쓰기 연속줄은 직전 불릿에 이어붙임). 버전별
    흰 카드(버전+날짜+최신 `LATEST` 배지+서브라인, 색상 태그칩, ≤600px 반응형). 스타일 `<style>`
    인라인(관리자 라이트 테마). `htmlspecialchars` 이스케이프.
  - `usr/local/www/release_note.md` (신규, 단일 소스): 릴리스노트 데이터.
- **배포 정합성**: `common_ui.inc` + `release_note.php` + `release_note.md` **3파일 일괄**. `.md` 누락 시
  "No release notes" 표시. **유지보수 = `usr/local/www/release_note.md` 한 파일만 편집 후 배포**
  (커밋만으로는 선상 미반영 — 별도 배포 필요).
- 검증: php -l(release_note.php·common_ui.inc) 통과 / 파서 스모크(헤더 2줄·버전 2개 1.1.3/1.1.2·
  날짜·자유 서브라인·연속줄 이어붙임) 통과.

### 36. 3D 스카이돔 바닥 세계지도를 dome 과 함께 yaw 회전 (develop `82fc3d4`)
- **증상**: Antenna 3D 스카이돔(#33, Satellite 나침반 클릭 → 모달)을 드래그/자동궤도로 회전하면
  dome(와이어·위성·본선·NESW 라벨)만 돌고 **바닥 세계지도(`world_minimap.jpg`)는 안 돌아 따로 놂**.
- **원인**: `drawFloor()` 의 바닥 텍스처 `setTransform` 이 **yaw=0 고정**이었음(기존 주석 "바닥 지도는
  항상 N=위/E=오른쪽 고정 — 정방위 표시" = 의도적 고정). dome 좌표는 `P(az,el)` 에서 yaw 회전을 적용하나
  바닥만 회전에서 빠져 있었다.
- **수정 (`usr/local/www/index.php` `drawFloor()`)**: dome 투영 `P()` 와 **동일한 yaw 회전**을 바닥 아핀에
  적용. 이미지px(ix,iy)→본선기준 EN 오프셋→yaw 회전(E1,N1)→화면 `x=cx+E1*R, y=cy+N1*sin(pitch)*R`.
  본선(vx,vy)은 디스크 중심(cx,cy)에 고정되어 그 둘레로 회전. **yaw=0 이면 기존 변환과 동일(회귀 없음)**.
  - setTransform 6계수: `a=k·cosY, b=ks·sinY, c=k·sinY, d=-ks·cosY,
    e=cx−k(cosY·vx+sinY·vy), f=cy+ks(cosY·vy−sinY·vx)` (×dpr). `k=R/ppu, ks=R·sin(pitch)/ppu`.
- **검증**: node 수치 하네스 — 본선이 yaw 무관하게 정확히 중심 고정 + 지도상 방위 `a`·거리 `FLOOR_SPAN_HALF`(16°)
  지점이 dome 수평선 링 `P(a,0)` 와 **픽셀오차 0** 일치(yaw 0/0.5/−0.8/1.2), 북쪽점 yaw 추종 확인. php -l 통과.

### 35. 위성 커버리지 맵 — NexusWave gateway 존재 시에만 커버리지 노출 (develop `c72b1d2`→최종 `2c23248`)
- **요구(최종)**: #33 커버리지 맵(Position 미니맵 클릭 → `⤢ MAP` 모달)에서 **월드맵은 항상 열되**,
  **커버리지 오버레이는 NexusWave gateway 가 있을 때만** 렌더. 없으면 커버리지 미표시 + **안내 팝업**으로
  "현재 NEXUSWAVE 만 커버리지 맵 지원" 고지.
- **판정 기준 = terminal_type (사용자 선택)**: gateway 의 `terminal_type` 이 `nexuswave`(_pri/_sec/_thi/_fth)
  를 포함하면 활성. terminal_status.inc 의 기존 nexuswave 감지 로직과 동일 기준. (사용자가 지칭한
  `tcp_nexuswave` 리터럴은 코드베이스에 없음 — 실제 존재값은 `nexuswave_*`.)
- **이력(동작 변경)**:
  - **1차 `c72b1d2`**: NexusWave 없으면 **클릭 자체 비활성**(트리거/모달 미바인딩) + `⤢ MAP` 배지 숨김
    (`no-cov` 클래스). → 사용자 요구로 "월드맵은 열어야 함"으로 변경.
  - **최종 `2c23248`**: 월드맵은 항상 열고, **커버리지 오버레이만 게이트** + 비-NexusWave 안내 팝업.
- **수정 (`usr/local/www/index.php` 단일 파일)**:
  - **PHP** `$cp_has_nexuswave_gw`: `$gateways`(=`return_gateways_array()`) 순회 +
    `stripos($gw['terminal_type'],'nexuswave')` 매칭 1개라도 있으면 true (페이지 로드 1회 판정).
  - **JS** `CP_HAS_NEXUSWAVE` 주입 → coverage IIFE `covEnabled` 플래그.
    - `buildToggles()`: 비-NexusWave 면 토글 숨김 + `cov-disc` 에 "only NEXUSWAVE…" 안내문.
    - `initMap()`: 커버리지 폴리곤/폴백밴드를 `covEnabled` 일 때만 추가(타일·선박 마커는 항상).
    - `openCov()`: 비-NexusWave 면 안내 팝업 `#covnote-ov` 표시(닫기/Escape 핸들러 포함).
  - **HTML/CSS**: 안내 팝업 오버레이 `#covnote-ov`(기존 `.covwarn` 스타일 재사용, z-index 10001 로
    cov 모달 위). (1차의 `no-cov` 클래스/배지숨김 CSS 는 최종본에서 제거 — 배지·클릭 모두 복원.)
- **영향 없음**: #28 항구 미니맵(거리/방위/줌)은 별도 IIFE 라 그대로 동작. 가드는 PHP/JS 양쪽이라
  버전 섞임에도 fatal 없이 안전 강등(`CP_HAS_NEXUSWAVE` 미정의 시 `covEnabled=false`).
- 검증: php -l 통과.

### 33. 관리/Main Panel UI 보강 — 미커밋
- **crew_account.php 툴바 1줄(A안)**: `.list-top` nowrap + 검색박스 가변(`flex:1 1 auto; min-width:0`,
  입력 max 420px) + 버튼군 고정(`flex:0 0 auto`) + Search/Clear `flex:0 0 auto`(찌그러짐·겹침 차단,
  S20 실측 후 수정). Modify Voucher 폼에서 **미구현 `timelimit` 필드 제거**(`draw_wifi_userid_search_box` 포함).
- **common_ui.inc `print_sidebar`**: 사이드바 하단 푸터 — "This Web console is developed and maintained by
  [SynerSAT Korea](https://www.synersat.com). © since 2016 Powered by [PFSense](https://www.pfsense.org)."
  (다크 인라인 스타일, 전 커스텀 페이지 공통). main_dashboard.php 는 stock pfSense 대시보드라 무관.
- **index.php(Main Panel) #28 미니맵 반응형(B+D)**: 고정 220px → `width:100%; max-width:248px; aspect-ratio:1`
  + 디스크 인셋% + SVG 100%(viewBox 자동스케일) + **배경 패닝 px→% (px-독립; 검증: 5크기×5줌×8좌표 =
  200조합 동일)** + 배지(clock/zoom) 안쪽(`right:-12px`→`7%`). 좁은 타일(크롬100%) 우측 넘침 해소.
- **index.php 3D 안테나 스카이돔**(Satellite 나침반 클릭→모달, `⤢ 3D` 배지): **Canvas 2D 자체투영(외부
  라이브러리 0)**. 반구 와이어(수평선/el30·60/자오선/NESW/천정) + VSAT/FBB 위성 점·지향선(status 색,
  el<0·blocked 빨강) + **중앙 배**(heading 회전) + 드래그회전/idle 자동궤도. **바닥 = `world_minimap.jpg`**
  (el=0 평면은 (E,N)→화면이 아핀 → `setTransform` 1회 텍스처, 본선 중심·수평선 타원 클립). 데이터는
  `acuLastVsat`/`acuLastFbb`(az·el·heading) 재사용(백엔드 0). **GPS 없으면 바닥 흐린 채움(현재)**.
- **index.php 위성 커버리지 맵**(Position 미니맵 클릭→모달, `⤢ MAP` 배지): **인터넷 데이터 경고 게이트**
  (동의 세션 기억) → Leaflet(cdnjs, 동의 후 로드) + OSM 타일 + 선박 위치 마커. 커버리지 = **운영사
  이미지(B) 우선**(`../img/coverage_{oneweb,gx,fbb}.png`, `L.imageOverlay`) + **이미지 없으면 근사 위도대
  밴드 폴백**(±70/76/88° 점선+라벨, "approx"). **APPROXIMATE/INDICATIVE 경고 배너**(오해 방지). ⚠️ 이
  코드베이스 **최초의 인터넷 의존 기능**(동의 후에만 cdnjs/OSM 접속).
- 검증: php -l + 런타임 하네스(투영 좌표 유한 / 바닥 텍스처 setTransform·drawImage / 경고게이트→동의→
  지도초기화 / 이미지오버레이 우선·error 시 밴드폴백 / covBand 토글) 전부 통과.

## 다음 작업 대기 중

- [x] **#37 커밋 완료(develop)**: Release Note 사이드바 메뉴 + 패치노트 표시 페이지 — develop `1f0c4da`. (main/prod 미반영)
- [ ] #37 검증(선상): 사이드바 "Release Note" 메뉴 → 1.1.3/1.1.38 카드 정상 렌더 / **3파일 일괄 배포**
  (common_ui.inc + release_note.php + release_note.md; `.md` 누락 시 "No release notes") / 릴리스노트
  갱신 시 루트 RELEASENOTE.md ↔ usr/local/www/release_note.md 동기화.
- [x] **#36 커밋 완료(develop)**: 3D 스카이돔 바닥 세계지도를 dome 과 함께 yaw 회전 — develop `82fc3d4`. (main/prod 미반영)
- [ ] #36 검증(선상): 3D 돔 드래그/자동궤도 회전 시 바닥 세계지도가 와이어·위성·본선·NESW 와 **함께 회전**·정합 / GPS 없을 때 흐린 채움 유지.
- [x] **#35 커밋 완료(develop)**: 위성 커버리지 맵 — 월드맵은 항상 열되 커버리지 오버레이만 NexusWave(terminal_type=nexuswave_*) 시 + 비-NexusWave 안내 팝업 — develop `c72b1d2`→최종 `2c23248`. (main/prod 미반영 — 명시 지시 시 병합)
- [ ] #35 검증(선상): NexusWave gateway 있는 선박 → 미니맵 클릭 시 월드맵 + 커버리지 오버레이 정상 /
  없는 선박 → 월드맵은 열리되 커버리지 미표시 + "only NEXUSWAVE…" 안내 팝업(항구 미니맵·줌은 정상 동작).
- [x] **#34 커밋 완료**: develop `92ae399` → main `369da8e` → prod `7a7195f`. 2026-06-16 전 브랜치 배포.
- [ ] #34 검증(선상): API `israndompw:true` PUT(Create)/POST(Update) → 6자리 숫자 난수 비밀번호 생성 확인 /
  `israndompw:false` Update → 비밀번호 `1111` 초기화 / Topup `freeradius_lastbasedata:50` → used-octets +50MB /
  `freeradius_lastbasedata:-50` → -50MB(0 하한) / 3D 돔 열면 **북이 화면 상단**에 표시되는지 / 배포 6파일 일괄 확인.
- [ ] **이번 세션 미커밋 (커밋 대기)**: #33(UI: crew_account.php·manage_crew_wifi_account.inc·common_ui.inc·index.php)
  는 `10aeaea` 에 포함돼 prod 반영 완료. #32(voucher API 5파일)도 동일 배포 묶음에 포함.
- [ ] #32 검증(선상): 원격 API 로 voucher **다건** create(PUT)/update(POST)/delete(DELETE) → 웹과 동일
  결과 / `weekly` 사용량 파일 삭제 확인 / **배포 정합성 5파일 일괄**(버전 섞임 시 ArgumentCountError 등).
- [ ] #33 커버리지 맵: **운영사 이미지 필요(B)** — `usr/local/www/img/coverage_{oneweb,gx,fbb}.png`
  (등장방형/Mercator, 투명배경 + 커버리지 색). 넣기 전엔 근사 밴드 폴백. **cdnjs/OSM outbound 도달성**
  (선박 방화벽) 확인 필요 — 막히면 지도 안 뜸(이 코드베이스 최초 인터넷 의존).
- [ ] #33 3D 돔: GPS 없을 때 바닥 처리 결정(현재=흐린 채움 / B안=전세계지도+배숨김 미적용) / 패치 범위
  `FLOOR_SPAN_HALF`(현재 ±16°) 체감 조정.
- [x] **#31**: CNA 로그인 창 — 로그인 폼 유지(원래 동작). **자동닫힘 기계장치(guide/ack/probe/
  done/게이트)는 index.php 에서 전부 삭제**. 로그인 폼의 "Copy address" 블록은 `captiveportal.inc`
  `renderLoginPortalHtml` 에 코드 보존하되 `$cpShowCopyAddr=false` 로 **기본 미표시(이 스레드 이전
  로그인 폼 모습)** — `true` 로 재노출 가능. develop 반영.
- [ ] #31 검증(선상): 로그인 페이지에 "Copy address" 블록/raw 포털 주소 **미표시**(이 스레드 이전 모습) /
  로그인 폼에 ID/PW → 로그인 → 온라인 정상 / "자동으로 닫힙니다" 류 문구 소멸 / **배포 정합성: index.php
  + captiveportal.inc 같은 리비전 일괄 배포**(섞이면 `cp_wireless_auth`/`captiveportal_try_migrate_session_by_mac`
  등 undefined fatal — 실제 관측됨). 재노출 원하면 `$cpShowCopyAddr=true`.
- [x] **#29**: time_offset 외부 API 의존 제거 — GPS→오프라인 시차격자(0.5°, tz-lookup v11.5.0 박제)
  →DateTimeZone(DST 자동)→nautical 폴백, 매시 7분 크론, gmtcheck 수동모드 존중, 표시부 무수정
  — develop `660727e` (tip: `e229a70`)
- [ ] #29 검증(선상): 배포 후 1시간 내 사이드바 "GMT n" 이 현재 해역과 일치 / `clog /var/log/system.log |
  grep "TZ AUTO"` 로 갱신 로그 확인 / Manual Timezone Enable 체크 시 자동 갱신 정지 / 항해로 경계
  통과 시 다음 정시+7분에 오프셋 전환 / 중앙서버의 SetTimeOffset 푸시 중지(이중 writer 정리)
- [ ] #29 배포 정합성: `cp_geo_tz.inc` + `cp_tz_grid.inc` + `cp_tz_offset_update.php` +
  `firewall_cronlist` 4파일 일괄 배포 (라이브러리/격자 누락 시 크론이 조용히 no-op — fatal 없음)
- [x] **#27**: Main Panel 안테나 트래킹 나침반(VSAT/FBB look-angle) + 1080 세로압축 — develop `00f1bb1`
- [x] **#28 예시**: 항구 미니맵 데모 + 오프라인 월드맵 자산 — develop `6b5d7c5`
- [x] **#28 통합 + 보강 + 데이터 전면화 + WoW UI**: 항구 미니맵 전면 완성 — develop `303bb05`
  - **Position 타일 원형 미니맵**: GPS 우선(VSAT→FBB 폴백), 최근접 3개 항구 방위·거리 화살표, rAF 최단경로 트윈
  - **데이터**: 항구 `cp_ports.js`(NGA WPI L/M 544개) / 해역 `cp_searegions.js`(Natural Earth 292bbox 면적오름차순)
  - **해역 계층**: 운하/해협(30nm 이내) → NEARBY PORT → 해역 bbox → 대양 폴백 (15케이스 통과)
  - **WoW UI 3종**: 존 플레이트(금테 다크 바·해역명) / 시계 배지(time_offset 기준 로컬타임, GPS무관) / 줌 버튼(5단계, localStorage 보존)
  - **GPS 없는 상태**: 회색 디스크 + "NO GPS" (layout jump 없음, 마커·화살표 숨김)
  - **버전 섞임 안전**: `typeof CP_PORTS/CP_SEAREGIONS` 가드 → js 미배포 시 내장 82항구/25해역 폴백
  - **server_module.inc**: `get_acu_pointing_info`/`get_fbb_pointing_info` 에 수치형 `lat`/`lon` 추가(0,0 null 처리)
  - **배포 묶음에 추가**: `img/world_minimap.jpg` + `js/cp_ports.js` + `js/cp_searegions.js`
- [x] **#28 on-map 점 표시**: 지도 범위 내 항구 → 림 화살표 대신 지도 위 실제 위치에 점+이름 렌더 — develop `1775f85`
- [ ] #28 검증(선상): 미니맵 위치/항구 화살표가 실제와 일치 / **지도 범위 내 항구가 점으로 표시되는지** /
  GPS 1분 갱신 추종 / `world_minimap.jpg`
  포함 배포 확인(**선상 흰 디스크 = 이 이미지 미배포가 원인** — MORNING LILY 실측, PHP 만 복사하고
  이미지 누락 시 발생; 최신 코드는 회색 디스크로 강등) / 1080 무스크롤 유지 / 해역명 체감 확인 /
  자주 가는 항구 누락 시 PORTS 배열에 추가
- [ ] #27 검증(선상): 나침반 Az/R.Az/El 이 ACU 자체 UI 와 일치(1척 실측 완료: 101/6/45≈ACU 100/6/45) /
  FBB 파랑 니들·5초 메트릭 로테이션 동작 / 1080 모니터 무스크롤 / `FBB : info unavailable` 인 선박은
  influx fbbstatus 유무 확인 / 미매핑 FBB 이름 관측 시 `cp_fbb_satlon_from_name` map 에 한 줄 추가
- [ ] #27 배포 정합성: index.php + server_module.inc + terminal_status.inc + manage_server_module.widget.php
  **4파일 일괄 배포**(버전 섞임 금지; 가드는 있어 fatal 은 없으나 표시 강등됨)
- [ ] #27 후속(선택): 함대 acureader(부호 보존판) 전체 배포 완료 후 W-플립 안전망 분기 제거 /
  FBB 매핑 테이블 슬롯 재검증(위성 재배치·I-6 편입 시)
- [x] **#25 (진원)**: 캡티브포털 무한 self-redirect 루프 차단 — GET 진입 직접 렌더 — develop `fce66ca`
- [x] **#24 (증폭)**: 무제한 `/tmp/cp_portal_error.log` 차단(프로덕션 off + 디버그 5MB 상한) — develop `f2f64aa`
- [x] **#26 (안전망)**: per-minute 크론 7종 단일 인스턴스 flock 가드(누적→OOM 차단) — develop `67befdf`
- [ ] #24~26 검증: 배포 후 `df -h /tmp` 평탄 / `grep -c 'REDIRECT(to self)'`(디버그) 급증 없음 /
  미인증 단말이 로그인 페이지 즉시 표시 / `ls /tmp/sess_*|wc -l` 비폭증 / OOM·502 소멸 /
  `ls /tmp/cron_*.lock` 존재 + 크론 중복 인스턴스 없음(`ps ax|grep cron`)
- [ ] #24~26 후속(미적용): OS probe 를 `session_start()` 전 단락(세션 생성 회피) + 세션 GC 크론 /
  per-minute 크론 I/O 타임아웃 보강(hang 자체 제거)
- [ ] #24~26 배포 정합성: **버전 섞임 금지** — `index.php` + cron 7종을 최신 develop 일괄 배포
- [x] **#23 step1+2 도구**: radcheck 이관 스크립트 + SQL authorize 토글 — develop `fd6ced2`
- [x] **#23 A(응급)**: PW/계정 변경 reload 를 HUP→재시작 (silent-fail 차단) — develop `85bdf6c`
- [ ] #23 A 검증: 자가/관리자 비번변경 → **재시작 없이 즉시 새 비번 로그인** + `[AUTH-UNKNOWN]` 소멸 /
  `radiusd -X` 로 변경 후 옛 비번 거부 확인 / 재시작 빈도·accounting 영향 모니터링
- [x] **#23 step3-A (구현 완료)**: PW writer 5곳이 radcheck 도 dual-write (동작변경 0, 컷오버 토대) — develop `b121dda`
- [x] **#23 step3-B (구현 완료, 기본 off)**: authorize 에서 radcheck(SQL) 비번 `:=` override — 플래그
  게이트(`system/freeradius_radcheck_override`), off 시 생성물 바이트 동일(하네스 검증). DB-down 시
  files 비번 graceful fallback 내장. 토글 도구 `freeradius_enable_radcheck_override.php`
  (dry-run 기본 / apply 사전점검·radiusd -C·자동 롤백 / disable 즉시 복귀) — develop `de4daf7`
- [ ] #23 step3-B 적용(선상, 신중 — **켜기 전 전제 필수**): ① step1+2 적용 완료 ② **MySQL 로컬/원격
  확인**(위성 너머 원격이면 켜지 말 것) ③ 한 척 `radiusd -X` 로 기존 사용자 정상 인증 + 비번 변경
  즉시 반영 + 옛 비번 거부 ④ **DB-down 로그인 테스트**(MySQL 차단 후 files 비번으로 로그인 = fallback
  동작) → 통과 시 플래그 ON → 며칠 관찰 → fleet 확대. 문제 시 `disable` 로 즉시 복귀(항상 안전).
  ON 이후 #23 A 의 재시작은 dual-write 경로상 여전히 발생 — 안정 확인 후 reload 경로 완화는 별도 후속.
- [ ] #23 step1+2 적용(선상): `freeradius_migrate_users_to_radcheck.php apply` → `freeradius_enable_sql_authorize.php apply`
  → `radiusd -X` 로 files 사용자 정상 인증 + `dailycounter ... → noop` 확인 (시간량 미사용이라 안전)
- [ ] #23 배포 정합성: A 패치는 `freeradius.inc` 만이지만 **버전 섞임 금지** — captiveportal.inc/index.php/
  cron 등 최신 develop 일괄 배포(첫 질문의 `cp_find_all_wan_gateways` fatal 과 같은 뿌리)
- [x] **#21**: 끊김 진단(라우팅 버그 아님) + 완화 — 랜덤MAC 안내·i18n 1차·blank 단락
  — develop `7561fe5`·`4e9dc25`·`04d603e`
- [ ] #21 검증: 배포 후 `Username blank` 빈도 급감 / 로그인 페이지 언어 드롭다운·자동감지 동작 /
  ko 외 번역 현지검수 / blank 도배 단말 식별·정리
- [ ] #21 후속(미적용): index.php line 497 가드 `!empty($_POST['accept'])` 환원(영향확인 후) /
  i18n 2차(로그아웃·비번변경·JS 메시지)
- [x] **#20**: cp_gw_* 테이블 재동기화(과금 누수 차단) + dpinger 알람 즉시화 — develop `0880718`·`7b1a4a7`
- [ ] #20 검증: crew 로그인 상태에서 `pfctl -t cp_gw_starlink -T show` → filter reload(방화벽 저장/게이트웨이
  flap) 후에도 1분 내 자동 복구(즉시화면 수초) / dpinger `-C ".../cp_gateway_alarm.sh"` 로 기동(재시작 후) /
  `PINNED user ... unresolved` 경고 시 terminal_type 점검 / 기본경로가 항상 crew WAN 인지(block 커버리지)
- [x] **#19**: 게이트웨이 shutdown flapping 전원 로그아웃 차단(3종) + 판정 vnstat 로컬 전환
  — develop `a646380`·`bd317a2`
- [ ] #19 검증: `wireless.log` 대량 동시 `INTERIM ZERO`/`REGRESS-KEEP` 소멸 / 한도초과 시 해당 단말만 끊김 /
  크론 stdout `usage unavailable (vnstat+cache miss)`·`shutdown disconnect applied: N` / vnstat 월버킷 monthrotate=1 확인
- [x] **#18**: network_usage vnstat 오류 시 uncaught exception → graceful exit — develop `3cbc321`
- [ ] **main 반영 대기(갱신)**: #11~#21 은 아직 develop 만 (main 은 #1~#10). 명시 지시 시 병합.
  특히 #19·#20(라우팅/과금)·#21(blank/안내)은 **최신 develop 일괄 배포**가 전제(버전 섞임 금지).
- [x] **#17**: 리셋 견고성 3종 — getsession 무효리셋 가드 / monthly resync / prepaid 중복 cron 제거
  — develop `28c0876`·`cde34a5`·`9b8cbf7`
- [ ] #17 검증: (A) 케이스 다른 username 으로 리셋 → 즉시 0 + 활성세션 잔존 시 `DATA RESET aborted`
  / (B) monthly forever 삭제 유저가 radiusd 에서 즉시 인증 차단 / (C) prepaid cron 1회만 실행
- [x] **#16**: 리셋 자가복구 날짜키 (cron 발화 누락 시 다음 틱 보충) — develop `aa0c759`
- [ ] #16 검증: 경계 cron 강제 미발화(예: 시각 점프 모의) 후 `wireless.log` 에
  `SELF-HEAL reset (missed ...)` + 재로그인 시 0 / 정상 경계 시 자가복구 no-op(중복 없음)
- [x] **#15**: phantom CP zone 2개 제거 + 배포 시 즉시정리 — develop `9bc6053`·`9476e47`·`cfbf2e0`
- [ ] #15 검증: 배포 후 CP zone 목록에 `filter`/`shutdown_gateways` 소멸 + 정상 crew zone 유지
- [x] **#13 확장**: 배포마다 구룰 자동 purge(cp_routing_setup 통합) + 로그인 유지 마이그레이션 — develop `4df5de3`
- [x] **#14 / #14b**: WIFI DATA RESET 즉시화 (명령/크론/위젯 시점에 로그아웃+사용량 0) +
  daily/halfmonthly gutted 복원 — develop `d1c0f88`·`f0822ca`
- [ ] #14 검증: 온라인 유저 Reset Data → **즉시 로그아웃 + 사용량 0** / `wireless.log`에
  `DATA RESET applied (usage=0...)` / 재로그인 시 0부터
- [ ] #14b 검증: 각 주기 경계(특히 **daily 리셋 실제 동작**) + 위젯 reset 즉시 반영 /
  daily·halfmonthly 비활성화 의도였는지 사용자 확인
- [x] **#13**: 구버전 per-user 로그인 룰 일괄 purge (멱등 self-heal) — develop `77b4119`
- [ ] #13 검증: 배포 후 CP 설정 저장/재부팅 또는 배포 자체 → `[User Rule] ... auto generated rule`
  일괄 소멸 + `wireless.log`에 `Purged N legacy ...` + 다른 `[User Rule]` 유지 + 로그인 유저 끊김 없이 pfctl 이관
- [x] **#12**: 로그아웃 ~19초 지연 수정 완료 (deferred state kill → detached 백그라운드) — develop `2fdc155`
- [ ] #12 검증: 로그아웃 클릭 → 즉시 페이지 전환(19초 소멸) + ~2초 후 기존 연결 종료 확인
- [x] **#11**: `commit_change_pw` fatal 수정 완료 (username 전파 정합화 + `?string` 방어) — develop `1ed69ad`
- [ ] **배포 정합성**: 선상에 신버전 파일들(captiveportal.inc/freeradius.inc/index.php/cron/
  manage_crew_wifi_account.inc)을 **같은 리비전으로 일괄 배포** (버전 섞임이 #11·cp_find_all_wan_gateways
  fatal의 공통 원인)
- [ ] **main 반영 대기**: #11~#14b 는 아직 develop만 (main은 #1~#10). 명시 지시 시 병합
- [ ] 선박에서 수정사항 테스트 (특히 #2, #3, #4, #6, #7, #8, #10)
- [ ] #7: interim 집계 동작 확인 (REGRESS-KEEP 로그 / export 비차단 / interim 마커 갱신)
- [ ] #8: prepaid self-heal 확인 (배포 후 첫 관리 UI 로드 시 가짜 zone 자동 제거 + prepaid 상태 보존)
- [ ] #6: REMOVING 오탐 재현 안 됨 + passthrough 게스트 redirect 동작 확인
- [ ] #9: vlanstate.sh 간헐 미동작 해소 확인 (`.log` 정상 생성)
- [ ] #10 핵심: **두 명 동시 PW 변경 → 둘 다 적용** 확인 / radiusd 로그 `RADIUS ACCOUNTING FAILED` 없음 / 단건 PW 즉시 반영
- [ ] #10 가정 확인: `grep -R usersfile /usr/local/etc/raddb/mods-enabled/files` → radiusd 활성 파일이 resync 타깃과 일치 + 그 파일이 패키지 전량생성인지
- [x] **#22 (= #10 후속 일부)**: 매분/5분 writer 크론(manual_routing/network_usage/vlan_state/openvpn_restart)
  lost-update 차단 — develop `549681f`
- [ ] #22 검증: **두 명 동시 PW 변경 → 둘 다 적용** 유지 / VPN 끊김 중 `openvpn_restart` 매분 write_config
  안 함 / vlan 상태변경·게이트웨이 shutdown 토글 후에도 직전 PW 변경 생존 / config.xml revert 소멸
- [ ] #10 후속 잔여: 형제 admin writer(reset/modify/del_wifi_user) + API 단건 생성에 동일
  `lock('freeradius_user_config')` 확대
- [x] main 반영 완료 (#1~#10)
- [ ] prod 반영은 별도 명시적 명령 (main → prod 는 재확인 후)

## 명령어 가이드

```
"develop에 커밋해"          → 현재 변경사항을 develop에 커밋·푸시
"develop를 main에 병합해"   → develop → main 머지
"main을 prod에 반영해"      → main → prod (재확인 후 실행)
```
