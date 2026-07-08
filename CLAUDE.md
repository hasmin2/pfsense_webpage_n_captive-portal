# pfSense Captive Portal — 프로젝트 컨텍스트

## 배포 규칙 (절대 준수)

- **prod 브랜치**는 사용자의 명시적 명령 + 재확인 없이 절대 건드리지 않는다
- 작업 흐름: `develop` → (명시적 지시 시) `main` → (명시적 지시 시) `prod`
- 커밋은 항상 `develop`에 먼저 한다
- `main`, `prod`는 병합 명령이 있을 때만 실행한다

## 브랜치 현황

| 브랜치 | 커밋 | 설명 |
|---|---|---|
| `develop` | `3f66d96` | **#1~#53 포함**(#48=`c473a8f`+`ebc29fa`, #49=계정 변경 이력+CREWPAY `3666f94`, #50=per-user History 뷰어 `9299f4f`+이력모달 10개 페이지네이션 `6c06890`, #51=FBB 신호 이름매핑 분리 + ACU state -1→Comm.Error `a848caa` + FBB "6"→EMEA `725e53c`, #52=crew→This Firewall 접근제한 `4c5c519`, #48 GMT모달 페이지네이션 `2155a19`, #41=테마 토글 쿠키 영속화 `763dd19`, #53=customer SET RANDOM PW 버튼 노출 `090e249`), 작업 기준 브랜치 (#18~#21: vnstat예외·게이트웨이flapping/과금누수·끊김진단/다국어/blank단락; #22: PW리셋 무작위미반영 — writer크론 lost-update 차단; #23: PW변경 무반영 진범=HUP가 rlm_files 미재로딩 — A응급=재시작 + radcheck(SQL) 이행도구 + step3-A dual-write(`b121dda`) + step3-B radcheck 권위화 구현(`de4daf7`, 플래그 게이트 기본 off + 토글도구); #24~26: 캡티브포털 무한 self-redirect 루프→25GB로그→ZFS풀full→전면장애(502/OOM) — 루프차단+무제한로깅차단+크론flock가드; #27: Main Panel 안테나 트래킹 나침반 — VSAT/FBB look-angle 시각화 + FULL HD 세로압축; #28: 항구 미니맵 WoW UI 전면 통합 — 544항구·292해역·존플레이트·시계배지·줌버튼·GPS회색처리·on-map점표시(`1775f85`); #29: time_offset 외부 API 의존 제거 — GPS→오프라인 시차격자 자동판정(`660727e`); #30: 위젯 stale write → 전원 mass-disconnect + 비CP계정 영구 kick 차단; #31: CNA Copy address 블록(기본 off); #32: voucher REST API 다건 CRUD 정합 + timeperiod 대소문자 방어; #33: 관리/Main Panel UI 보강; #34: API random PW / israndompw true/false / Topup delta / 3D돔 방향 수정; #35: 위성 커버리지 맵 — 월드맵은 항상 열되 커버리지 오버레이만 NexusWave(terminal_type=nexuswave_*) 시 + 비-NexusWave 안내 팝업(`2c23248`); #36: 3D 스카이돔 바닥 세계지도 dome 과 함께 yaw 회전(`82fc3d4`); #37: Release Note 사이드바 메뉴 + 패치노트 표시 페이지(`1f0c4da`) + 단일 소스화(A안: 루트 RELEASENOTE.md 제거, usr/local/www/release_note.md 단독)·사용자 양식 파서(`deb779c`); #38: terminaltype 미해석(현존 게이트웨이 없음)→로그인 차단+"antenna offline"(잠재 3경로 불일치 블랙홀 차단); #39: 같은 MAC·다른 ID(공유기 NAT/MAC클론) 세션 탈취·핑퐁→MAC 자동이관 폐지(1b)(#4 동작변경)(`c9bd917`); #40: OpenVPN 재시작 크론을 watchdog 으로 안정화 — per-client·hang reap·비블로킹 락(try_lock)·ping timeout 바운드·위성 디바운스·로그 가시화 — 커밋 `66ebfd7`; #41: 다크모드 System(OS)/GPS(일출일몰 civil twilight, 박스 UTC 판정)/Light/Dark 4-state·9페이지공통(print_css_n_head)·dark.css·cp_daynight.inc+크론·외부 day/night API 삭제(`ab95701`·`81c9423`·`e089710`); #42: Daily usage 막대그래프(InfluxDB 일별 rx+tx, This month 기본·MB meter, `ab95701`); #43: GMT 타임존 테마 팝업 + 30분(0.5) 단위 + cp_tz 가드 truthy(`ab95701`); #44: GMT 저장 시 전역 `$g` 오염→웹루트 숫자폴더+config.xml 덤프 버그 수정(`$g`→`$gmt_in`, 보안, `ab95701`)) |
| `main` | `d8165bf` | **#1~#53 전부 반영 완료** (커밋 `d8165bf`). 2026-07-03 develop→main 일괄 통합 (#48~#53 포함) |
| `prod` | `59b6594` | **#1~#53 전부 반영** (커밋 `59b6594`). 2026-07-03 main→prod 배포 (#48~#53 포함). 리모트 `hasmin2/pfsense_webpage_n_captive-portal.git` 의 `main` |

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
| `etc/inc/cp_gmt_history.inc` | GMT time_offset 변경 이력 → MariaDB `radius.gmt_history` 기록 헬퍼 (#48) |
| `etc/inc/cp_account_history.inc` | crew 계정 변경 이력 → MariaDB `radius.radacct_changehistory` 기록/조회 헬퍼 (#49/#50) |
| `usr/local/www/crew_account_history_data.php` | per-user 계정 변경 이력 조회 JSON 엔드포인트 (#50) |
| `etc/inc/cp_terminal_history.inc` | Terminal Status(Manual Override/Data Cutoff) 변경 이력 → MariaDB `radius.terminal_status_history` 기록/조회 헬퍼 (#57) |
| `usr/local/www/terminal_history_data.php` | Terminal Status 변경 이력 조회 JSON 엔드포인트 (#57) |

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
> ⚠️ **#39(1b)로 동작 변경됨**: 아래 MAC 기반 자동이관(`try_migrate_session_by_mac`)은 공유기 NAT/
> MAC클론 시 세션 탈취·핑퐁을 유발해 **폐지**됨. 현재 IP 변경 시 **자동로그인 안 하고 로그인 페이지로
> 유도**(재로그인 시 자기 세션). 상세는 #39.
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

### 38. terminaltype 미해석(현존 게이트웨이 없음) → 로그인 차단 + "antenna offline" (develop `c9bd917`)
- **배경(잠재 버그 발견)**: `starlinkuser00023` 로그인 불가 진단 중, **로그인은 성공**(RADIUS accept,
  쿼터 8%)인데 **트래픽 0**(INTERIM ZERO 2시간+ 지속)인 케이스 분석. ZERO/STOP-ZERO 의미는 "ipfw 인증
  파이프가 이 IP에 0바이트 집계"(거부 아님, `datacounter_acct.sh:407/372`). 원인 후보 = **고정(pinned)
  게이트웨이 미해석 시 라우팅 경로 3곳의 처리 불일치**.
- **3경로 불일치(잠재 버그)**: 유저 `varusersterminaltype`(=게이트웨이 이름)이 비어있지 않은데 현존
  WAN 게이트웨이(`cp_find_all_wan_gateways`: 활성·terminal_type 지정·비-VPN)로 해석 안 되면(disabled/
  삭제/rename/오타):
  - 로그인 `add_crew_linked_rule:5050`: null → `cp_gw_default` (**fail-open**, 통과)
  - 풀싱크 `cp_sync_routing_tables:4791`: null → `cp_gw_default` (**fail-open**)
  - 매분 크론 `cp_resync_pf_tables_only:4872`: pinned-unresolved → `continue`(어느 테이블에도 안 넣음,
    **fail-closed 블랙홀**)
  → 로그인 직후 <60초만 default로 통과 → 매분 크론이 테이블에서 빼서 **영구 트래픽 0**(조용한 블랙홀).
  로그인됨·RADIUS accept·CP "연결됨"으로 보여 오진 유발(증상이 "끊김/안됨"으로 흩어짐). 타이밍 의존이라
  "고장"이 아니라 "불안정"처럼 보임 — 유일 단서는 `wireless.log` 의 `PINNED ... unresolved` 한 줄.
- **수정(#38, fail-closed로 통일 + 가시화)** `captiveportal.inc`:
  - 신규 `antenna_gateway_online($username)`: terminaltype이 비어있지 않은데 현존 게이트웨이 리스트에
    없으면 false. 공란/'auto'/사용자없음 → true. **empty-guard**(게이트웨이 리스트 못 읽으면 차단 안 함
    = 구성 일시 미가용 시 전원 락아웃 방지 fail-open) + **strcasecmp**(케이스 드리프트 방어).
  - 인증 게이트 `[1982]`: `$antenna_allowed && $antenna_online && !shutdown && !suspend` 일 때만
    `authenticate_user`. (기존 `antenna_allowed`/`isPortalShutdown` 패턴과 동일 자리.)
  - 실패 메시지 `[2037]`: `"The antenna is offline, please try later."` (기존 connected-page `3115` 문구
    와 통일). 차단 시 `[CP Login] BLOCKED ... antenna offline` 로그(가시화).
- **terminaltype "Auto" 저장값 확인**: GUI 드롭다운 "Auto" = `<option value="">`(빈 문자열,
  `crew_account.php:138`). `manage_crew_wifi_account.inc:427` 의 `?: 'Auto'` 는 **목록 표시용 폴백**
  (저장 안 함). → 정상 Auto 유저는 `''` 저장 → 통과. 리터럴 "auto"/"Auto"는 API/수동편집으로만 가능하나
  strtolower 후 'auto' 처리 → 역시 통과. **Auto 유저는 절대 차단 안 됨**, pinned-unresolved 만 차단.
  라우팅(`cp_resync_pf_tables_only`)도 `''`/`'auto'` 둘 다 `cp_gw_default` 로 동일 취급.
- **검증**: php -l 통과 / 런타임 하네스 **8/8**(공란·auto·유저없음·현존·케이스차이현존 → 허용,
  게이트웨이없음 → 차단, 빈리스트 → fail-open, username 케이스무시 → 차단).
- **선상 판정**: `grep "PINNED.*<user>" wireless.log` 또는 config.xml varusersterminaltype 덤프로
  disabled/rename된 게이트웨이 식별 후 GUI에서 활성화/이름 정정 → 정상화.
- **범위 주의**: config에 "현존"(disabled/삭제/rename) 기준. dpinger상 down이지만 config엔 존재하는
  게이트웨이는 `cp_find_all_wan_gateways`가 포함하므로 #38로 안 막힘(그건 `cp_shutdown_gateways`/
  network_usage 담당). 로그인/풀싱크 경로의 default 흡수(fail-open)는 그대로 두되, #38이 로그인 자체를
  막아 블랙홀이 생기지 않게 함.

### 39. 같은 MAC·다른 ID(공유기 NAT/MAC클론) 세션 탈취·핑퐁 → MAC 자동이관 폐지(1b) (develop `c9bd917`)
- **배경/질문**: 공유기 NAT 뒤 여러 기기가 같은 MAC으로 보일 때 다른 username 접속 동작 분석.
  (#38 진단 로그의 `[MIGRATE]` 폭주 — MAC `3a:68:...` 고정·IP 5개 왕복 — 의 정체.)
- **토폴로지 2종**:
  - **Case A (진짜 NAT)**: 뒤 기기 전부 **IP 1개+MAC 1개** 공유 → pfSense 기기 구분 불가.
  - **Case B (브리지+MAC클론/랜덤MAC충돌)**: **IP는 다른데 MAC만 같음** (위 핑퐁 로그 = Case B).
- **Case A 동작** `portal_allow:3403`: 같은 IP면 기존 세션 sessionid **재사용**(`[3410]`) → 새 세션/
  INSERT/회계 start 블록은 `if(!isset($sessionid))`(`[3490]`)라 **skip**. → **선착순 1명만 세션 주인**,
  나머지는 무슨 ID로 로그인하든 그 세션 탑승(전원 트래픽이 1명 쿼터로 과금, 라우팅도 1명 terminaltype).
  옵션 1b와 **무관**(마이그레이션 미관여, IP·MAC 동일이라 구조적 분리 불가).
- **Case B 동작(버그)** `try_migrate_session_by_mac:4186`: index.php GET 경로(`[620]`)에서 **MAC만으로
  매칭**하고 **기존 세션 username 그대로 사용**(새 로그인 ID 파라미터 없음) → 다른 기기가 포털만 열어도
  남의 세션을 자기 IP로 이관 = ① 자격증명 없는 **세션 탈취** ② 세션이 기기 IP 사이 **핑퐁**(카운터 리셋
  INTERIM ZERO/REGRESS·끊김).
- **수정(1b)** `index.php`: IP+MAC 정확일치 실패 시의 **MAC 자동이관 호출 블록 제거**(`[616~]`).
  IP 바뀌면 `$connectedSession===''` → 로그인 페이지 → 각자 자기 자격증명으로 POST 로그인(`[519~602]`)
  → **자기 세션**. `captiveportal.inc try_migrate_session_by_mac` 함수는 보존(docblock에 "미호출/1b" 명시,
  향후 쿠키·토큰 매칭과 재활성 가능).
- **POST 로그인은 마이그레이션과 독립**: POST 경로(`519~602`)가 GET 마이그레이션 체크(`605~`)보다 먼저
  실행·exit. 이관 거부해도 **자기 ID 로그인은 안 막힘** — Case B에선 자기 IP라 portal_allow same-IP
  재사용에 안 걸려 **독립 세션 획득**(오히려 정상화).
- **트레이드오프(수용)**: 진짜 단일기기 IP변경(#4)도 **1회 재로그인** 필요. stale 옛 IP 세션은 재로그인
  시 `noconcurrentlogins='last'` 가 정리(`[3459~3470]`) → 누적 없음.
- **#4 동작 변경**: "IP 변경 시 자동 로그인"(#4)은 이제 1b로 **자동로그인 안 함**(재로그인 유도)으로 바뀜.
- **변형 후보(1a, 미채택)**: 쿠키/토큰 기반 이관 — 정상 단일기기 seamless 유지 + 탈취/핑퐁만 차단.
  쿠키 지속 의존(OS 프로브 무쿠키 케이스는 로그인 페이지로 강등). 함수 보존으로 재활성 경로 남겨둠.
- **검증**: php -l 통과(index.php·captiveportal.inc). 잔여 `$migrated` 참조 0.

### 40. OpenVPN 재시작 크론을 watchdog 으로 안정화 — "일부 선박 미재시작" 교정 (develop `66ebfd7`)
- **배경/증상**: `usr/local/cron/openvpn_restart_timeperiod_check.php`(cron, [firewall_cronlist:211~]
  등록됨)가 **일부 선박에서 VPN 재시작을 정상 수행하지 않음**. 로직 검증 결과 결함 다수.
  - **스케줄(2026-07-03 현재 = 매분 `minute` `*`)**: 2026-07-02 에 매분→매시(`0`)로 바꿨다가, 매시 cadence 가
    (A) 강제 플래그(경로전환) 재시작을 최대 ~59분 지연시키고 — 위젯/API 는 플래그만 set 하고 재시작은
    **오직 cron 발화에만 의존**(`manual_routing.widget.php:386` 빈 루프 + `APIStatusOpenVPNRestart.inc` 플래그
    set only) — (B) liveness 복구를 `OVWD_FAIL_THRESHOLD`(3)×매시 = **최대 ~3시간**으로 늘려서 **2026-07-03 에
    매분으로 환원**. 매분이면 상수(threshold 3=~3분·cooldown 5분·stale-reap 10분)가 원설계대로 맞고, 매분 부하는
    flock 단일 인스턴스 가드(#26)+hang reap 이 억제. (firewall_cronlist 항상 함께 커밋.)
- **이 크론의 2가지 용도**: ① liveness — 터널이 데이터 못 넘기면 재시작. ② 강제 플래그 —
  관리자/경로전환(`manual_routing.widget.php` "Automatic" 분기 + `APIStatusOpenVPNRestart.inc` 가
  `$config['openvpn']['openvpnrestart']=""` set)이 모든 client 즉시 재시작 → **Starlink↔VSAT 업링크
  전환 후 새 경로 재바인딩**(선박 환경 핵심 용도).
- **코어 함수 실측(pfSense 2.5.2 RELENG_2_5_2 소스 검증)**:
  - `openvpn_get_active_clients()` = 비활성 제외 **모든** 설정 client + 상태. `status`: `down`(초기)→
    `openvpn_get_client_status()`가 `up`/`connecting`/`waiting`/`reconnecting`. **`virtual_addr`는 연결
    시에만 존재**(끊기면 키 부재). (함수명과 달리 "active=연결됨"이 아니라 "설정된 전부".)
  - `openvpn_restart_by_vpnid($mode,$vpnid)` = `openvpn_get_settings`+`openvpn_restart`. 길게 block 안 함.
  - `try_lock($lock,$timeout=5)` = pfSense **비블로킹 락 변형**. `lock('freeradius_user_config')`(PW
    writer 가 쓰는 락)와 **같은 파일**(`$g['tmp_path']/{lock}.lock`)을 flock 하므로 상호배제 유지하며
    블로킹 회피 가능. FreeBSD `ping -t`=전체 타임아웃(TTL 아님), `-c3` exit 0 = 3개 중 1개라도 응답.
- **진단된 결함 → 수정**:
  - **① `<?` 짧은 태그(형제 크론 중 유일)** → `<?php`. `short_open_tag=Off` 박스에서 **파일 전체가 평문
    출력, 한 줄도 실행 안 됨**(silent 전체 사망) — "일부 선박"과 정확히 일치하는 잠재 증상.
  - **② 다중 client last-wins 덮어쓰기**: `foreach{ $pingresult = ... }`가 매 반복 덮어써 **마지막
    client 결과만** 반영. 첫 client down·마지막 up 이면 online 으로 오판→재시작 안 됨. → **per-client 판정**.
  - **down client 빈 `-S` malformed ping**: down 이면 `virtual_addr` 부재 → `ping -S '' host`(우연히
    offline 으로 잡히던 fragile 경로 + PHP 7.4 undefined-key Notice). → **status 기반 판정**(up+addr 일 때만 ping).
  - **③ 싱글톤 가드(#26) hang → 영구 starvation**: `flock(LOCK_NB)` 가드는 프로세스 누적/OOM 은 막지만,
    한 인스턴스가 hang 하면(`openvpn_restart_by_vpnid` wedge / 블로킹 `lock('freeradius_user_config')`에
    다른 느린 writer 가 걸림) **그 좀비가 싱글톤 락을 계속 보유 → 이후 매분 exit(0)** → watchdog 자체
    사망(재부팅 전까지 재시작 0회). **트레이드오프 = 과다실행 방지 ↔ 지속 재시도 보장의 상충.**
    → 가드 유지 + **hang 한 선행 인스턴스 reap**: 락 파일에 `pid 시작시각` 기록, 후속 run 이 보유자가
    `OVWD_STALE_HOLDER_SECS`(600s) 이상 '살아있고' 'ps command 가 이 스크립트'이면 TERM→KILL 후 락
    재획득(watchdog self-recovery). 회수 대상은 ping/restart 에 멈춘 것일 뿐 config write 중이 아니라 안전.
  - **블로킹 `lock('freeradius_user_config', LOCK_EX)`** → **`try_lock`(비블로킹, 10s)**. 못 잡으면 플래그
    정리를 다음 주기로 미룸 → 싱글톤 잡은 채 블로킹 회피. 정리는 `parse_config(true)`+delta(키 1개)만
    write → 동시 PW 변경 등 lost-update 안전(#22/#10 패턴 유지).
  - **외부 명령 hang**: ping 을 `/usr/bin/timeout 12` 로 하드 바운드(+`/sbin/ping`/`timeout` 경로 부재 시
    폴백 — 경로 없으면 `mwexec` rc=127 로 **모든 up client 거짓 실패→무더기 재시작** 사고 방지. ping `-t8`
    내부 타임아웃이 2중 안전).
  - **위성 단발 손실 false-restart(flapping)**: ping `-c3`(1개라도 응답=정상) + **연속
    `OVWD_FAIL_THRESHOLD`(3)회 실패**해야 재시작 + 재시작 후 `OVWD_RESTART_COOLDOWN`(300s) 동안 같은 client
    재시작 금지. 상태(fail 카운터·last-restart epoch)는 **`/var/run/openvpn_watchdog/` 파일**(config.xml
    미사용 → lost-update 무관, #16 패턴). cron 매분이라 ~3분 확정 실패 후 재시작.
  - **완전 silent(진단 불가가 곧 문제)**: `log_error("[openvpn-watchdog] …")` 로 재시작/reap/락실패 가시화.
    매분 스팸·디스크풀(#24) 방지 위해 **평시 per-client 상태는 `/tmp/openvpn_watchdog_debug.on` 있을 때만**.
- **⚠️ ping 대상 오류 = 터널 죽어도 무재시작(2026-07-03 교정, 진짜 진범)**: `OVWD_PING_HOST` 가
  `vpn-server.synersat.noc`(= VPN 서버 **공인 엔드포인트**, 클라이언트가 다이얼하는 그 이름)였음. 헬스체크
  `ping -S <virtual_addr> <공인IP>` 는 **`-S` 가 소스만 바꿀 뿐 FreeBSD 는 목적지 기준 라우팅** → 목적지가
  공인 IP 라 **WAN(Starlink)로 나가고 outbound NAT 로 응답까지 옴** → **터널이 완전히 죽어도 ping 성공 →
  `$healthy=true` → fail 카운터 0 리셋 → liveness 재시작 영영 안 함**. 실측: 선상에서 터널 데이터 경로
  (10.8.128.1) 사망인데 6시간+ 무재시작, 수동 재기동으로만 복구. 즉 워치독이 **터널 헬스가 아니라 WAN
  도달성**을 재던 것. **수정**: `OVWD_PING_HOST` → **`10.8.128.1`**(터널 내부 GW, 전 client 공통 — 사용자
  확인). 목적지가 터널 서브넷이라 라우팅이 터널 인터페이스로 강제 → 터널 사망 시 ping 실패 → 정상 재시작.
  (다중 client 동시 공유 시 목적지 라우팅이 한 터널로만 갈 수 있어 per-client 정밀도 저하 가능하나, 공유
  GW+통상 단일 활성 client 환경이면 무관.)
- **동작 흐름(수정 후)**: liveness=`status==up`+`virtual_addr` 일 때 터널 내부 GW(10.8.128.1) ping→연속 3실패
  +쿨다운 경과 시 **그 client 만** 재시작 / 강제 플래그=**모든 client 즉시**(쿨다운 무시·1회성) 후 플래그 정리.
- **⚠️ 의도된 동작 변경**: 기존 ping 1회 실패 시 즉시 전체 재시작 → 이제 liveness 는 **~3분 디바운스**
  (위성 flapping/#21 끊김 방지). 즉시성 원하면 `OVWD_FAIL_THRESHOLD=1`. **경로전환(플래그) 재시작은 변함없이
  즉시** → manual_routing/Starlink↔VSAT 전환 동작 영향 없음.
- **배포 정합성**: 이 크론은 **코어 함수에만 의존**(`openvpn_get_active_clients`/`openvpn_restart_by_vpnid`/
  `try_lock`/`log_error`/`parse_config`/`write_config`) → repo 다른 파일과 **동시 배포 불필요**(버전 섞임
  위험 없음). 함수 부재 시 로그 남기고 graceful exit.
- **검증**: php -l 통과. 선상: `crontab -l | grep openvpn_restart`(등록) /
  `clog /var/log/system.log | grep openvpn-watchdog`(RESTART/reap/락실패) / `touch
  /tmp/openvpn_watchdog_debug.on`(per-client ping rc 상세, 끝나면 삭제) / hang 재현 시 10분 후 stale reap.
- **튜닝 상수(파일 상단)**: `OVWD_FAIL_THRESHOLD`(3) · `OVWD_RESTART_COOLDOWN`(300) ·
  `OVWD_STALE_HOLDER_SECS`(600) · `OVWD_LOCK_WAIT`(10) · `OVWD_PING_HOST`(**`10.8.128.1`** = 터널 내부 GW;
  공인 엔드포인트 금지 — WAN 누수로 healthy 오판).
- **2026-07-03 후속(ping 대상 교정 + 매분 환원)**: `openvpn_restart_timeperiod_check.php`(`OVWD_PING_HOST`
  10.8.128.1) + `firewall_cronlist`(`minute` `*`) 일괄. 패치노트는 `2026-07-03 Update` 항목에 병합(FIXED).

### 41. 다크모드 — System/GPS(일출일몰)/Light/Dark 토글 (develop `ab95701`·`81c9423`·`e089710`)
- **요구**: 다크모드 버튼을 전 웹페이지 공통 적용. 이후 시스템 테마 연동(기본) + GPS 일출/일몰 연동으로 확대.
- **단일 진입점**: 모든 커스텀 SynerSAT 페이지(9개: index/crew_account/prepaid_account/network_control/
  download_center/terminal/lan_svrstatus/crew_status/release_note)가 `common_ui.inc` `print_css_n_head()`
  (CSS·jQuery emit) + `print_sidebar()` 를 거침 → **두 함수만 수정해 9페이지 동시 적용**. stock pfSense·
  캡티브포털은 별도 테마라 범위 제외.
- **CSS**: `usr/local/www/css/dark.css` **신규**(`html.dark` 스코프 오버라이드). 기존 CSS 6종은 색 하드코딩
  (변수 미사용)이라 구조요소(body·사이드바·타일·테이블·팝업·폼·버튼)별로 다크색 재지정. 라이트 모드 영향 0.
- **토글 4-state 순환**: `System`(OS `prefers-color-scheme`, 기본)→`GPS`(일출일몰)→`Light`→`Dark`.
  저장키 `cp_theme`(auto/gps/light/dark, 내부키는 auto 유지). 사이드바 메뉴 하단 버튼(유니코드 아이콘).
  - **저장소: 쿠키(주) + localStorage(미러)** — 선박 콘솔(앱 webview 등)에서 **localStorage 가 세션마다
    초기화되어 매번 기본값(auto)로 돌아가던 문제** 수정. 토글 시 `cp_theme` 쿠키(`path=/`·max-age 1년·
    samesite=lax) + localStorage 동시 기록. 읽기(FOUC 조기 스크립트 + `cpThemeMode`)는 **쿠키 우선 →
    localStorage 폴백 → auto**. 기존 localStorage 사용자는 첫 로드 시 쿠키로 1회 자동 이관. 9개 관리
    페이지 공용(같은 origin·path=/ 라 전 페이지 공유).
  - **FOUC 방지**: `print_css_n_head` 최상단 인라인 스크립트가 CSS 링크보다 먼저 실행 → 첫 페인트 전 `html.dark` 설정.
  - System 모드: `matchMedia change` 리스너로 OS 전환 실시간 반영. 표시 라벨은 "System"(내부키 auto)(`e089710`).
- **GPS 모드(오프라인 일출/일몰, civil twilight)**:
  - `etc/inc/cp_daynight.inc` 신규 — PHP7.4 내장 `date_sun_info()` 로 현재 위치 civil twilight(태양 -6°)
    begin/end 계산(극지 백야/극야는 polar='day'/'night'). 인터넷 0 (#29 철학).
  - `usr/local/cron/cp_daynight_update.php` 신규(매30분) — influx GPS(VSAT vesselposition→FBB 폴백) →
    civil times → `$config['daytimecheck']`(begin/end/**nbegin**=다음날 dawn) 캐시. 변경시만 write(#22 패턴),
    flock 가드(#26), (0,0)/불통 시 마지막값 유지. `firewall_cronlist` 등록.
  - `print_css_n_head` 가 daytimecheck 를 `CP_SUN`(begin/end/nbegin/polar/**now**)으로 주입 → 클라 판정.
    dusk(end) 이전엔 dawn(begin) 이후가 낮, dusk 이후엔 nbegin 전까지 밤(자정 넘김 wrap 처리).
  - **판정 기준시각 = 박스 UTC(`CP_SUN.now`=`time()`)** — 클라 `Date.now()`(선박 PC 타임존 오설정 흔함)
    대신 박스 권위 UTC + `performance.now()` 단조 경과. 절대 epoch 비교라 offset 가산 불필요(`81c9423`).
  - GPS 데이터 없으면(daytimecheck 빈값) System(OS) 폴백.
- **외부 day/night 푸시 API 삭제**: `APIServicesDayTimeCheck`(엔드포인트)/`APIServicesWriteDayTimeCheck`(모델)/
  URL 핸들러 3파일 제거(레포 소비처 없던 외부 푸시 → GPS 오프라인 계산으로 대체, #29 동형). 이중 writer 해소.
- **화면밝기/조도센서 연동은 불가**(웹 표준 부재·HTTP 컨텍스트) — 시스템 테마 연동이 한계.
- **검증**: php -l, 주입 JS 문법, 24h 사이클(부산 새벽 DARK/일출후 LIGHT/일몰후 DARK)·박스UTC 종단·극지 polar 통과.
- **배포 정합성**: `common_ui.inc`+`css/dark.css`+`cp_daynight.inc`+`cp_daynight_update.php`+`firewall_cronlist`
  (API로 cron 등록) 일괄. dark.css 누락 시 토글은 떠도 색 미변경(fatal 없음). 브라우저 Ctrl+F5(캐시).

### 42. Daily usage — Internet usage 타일 일별 사용량 막대그래프 (develop `ab95701`)
- **요구**: Main Panel(`index.php`) "Internet usage" 타일에 일별 사용량 버튼 → 막대그래프.
- **데이터**: `get_datausage_from_db` 와 동일 InfluxDB(`192.168.209.210:8086`, db=acustatus, measurement=traffic,
  필드 `{if}_rx`/`{if}_tx`)를 월합산 대신 `GROUP BY time(1d)` 로 일별 질의.
  - `terminal_status.inc` `read_daily_usage_multi($metrics,$days,$timeout,$monthMode)` 신규 — 여러 게이트웨이를
    **단일 쿼리**로(SELECT 순서=컬럼 위치 매핑), 하루 경계는 선박 GMT 오프셋(현지 자정), monthMode 는 현지 월(MM) 필터.
- **AJAX** `index.php` `if(isset($_POST['daily_usage']))` — wan_status 와 동일 비-vpn 게이트웨이 목록. `function_exists` 가드.
- **UI**: 모달 + 범위 토글 **This month(기본)/7d/14d/30d**, 좌측 **MB meter**(0/25/50/75/100% 눈금, 단위 적응
  MB<1000<GB), 순수 SVG 막대(외부 라이브러리 0), 게이트웨이별 독립 Y스케일, 막대 호버 툴팁, 버튼 중앙정렬.
- **검증**: php -l, 일자라벨/오프셋/컬럼매핑/month 경계 절삭/SVG 문법 통과.
- **배포**: `index.php`+`terminal_status.inc` 일괄(가드 있어 fatal 없으나 미배포 시 버튼 무동작).

### 43. GMT 타임존 — 네이티브 prompt → 테마 팝업 + 30분(0.5) 단위 (develop `ab95701`)
- **요구**: 사이드바 "GMT n" 클릭 시 뜨던 네이티브 `prompt()` 를 사이트 테마 팝업으로 교체 + **30분(.5) 단위** 지원.
- **수정**: `common.js` 핸들러가 `prompt`/`parseInt`(30분 절삭 원인) 제거 → 기존 `.popup` 컴포넌트 재사용
  (`pop-gmt`, common_ui.inc) + `-11~+12` 0.5단위 select(47개, JS 생성). `.popup` 재사용이라 다크 테마 자동 적용.
- **백엔드**(`index.php` gmt POST): 입력 정규화 — 숫자만 + 0.5 스냅 + 범위 클램프 + 정수 "9"/반시간대 "9.5"/"-3.5" 포맷.
- **연계 수정**: `cp_tz_offset_update.php` 수동모드 가드를 `gmtcheck==='1'` 엄격비교 → `!empty()` truthy 통일
  (사이드바 표시와 동일). 비-'1' truthy 값에도 자동 TZ 갱신 차단 → 수동 반시간대 선택이 정수로 덮이는 것 방지.
- **한계(수용)**: 반시간대 저장·사이드바 표시는 정상이나 CP 로컬시간/미니맵 시계 계산은 기존부터 정수시간만 지원(30분 절사, #29).
- **검증**: php -l, node --check, select 47개(9.5/-3.5 포함)·정규화·truthy 가드 통과.

### 44. GMT 저장 시 웹루트에 숫자폴더+config.xml 덤프 (전역 `$g` 오염, 보안) (develop `ab95701`)
- **증상**: 타임존 선택·저장 후 `/usr/local/www/` 에 **마지막 선택 오프셋 이름의 폴더**가 생기고, 내부에 전체
  config.xml 덤프(루트 태그가 `<pfsense>` 아닌 `<1>` 등 숫자)가 들어감. 웹루트라 비밀값(admin 해시·RADIUS
  secret·API key·VPN 키·MySQL creds) **노출 위험**.
- **근본 원인(진범)**: #43 입력검증 추가 시 지역변수를 `$g = trim($_POST['gmt'])` 로 명명 → **pfSense 전역
  경로배열 `$g`**(핸들러 상단 `global $config, $g;`)를 오프셋 문자열로 덮음. 직후 `write_config()` 가 깨진
  `$g` 로 경로 생성: PHP 에서 문자열에 `$g['cf_conf_path']` 등 문자열키 접근 → 정수0 캐스팅 → **첫 글자** 반환
  (`"1"['xml_rootobj']`→`"1"` = 루트 `<1>`; `"1"['cf_conf_path']`→`"1"` = CWD(웹루트) 아래 상대폴더 "1").
- **진단**: 멀티에이전트 워크플로우가 `$g` clobber 를 특정. 결정적 단서 = 덤프 루트 `<1>` = `$g` 가 문자열 "1" 임을 증명.
- **수정**: 지역변수명을 `$g` → `$gmt_in` 으로 변경(전역 오염 제거). write_config 2번째 인자(offset)도 제거(단일 인자화).
- **교훈**: pfSense PHP 에서 `$g`(경로 전역)·`$config` 는 예약 전역 — 지역변수로 절대 재사용 금지. 워크트리 참고 메모리화.
- **검증**: php -l, `$g` 대입 잔존 0. 선상: 웹루트 숫자폴더 삭제 + nginx 로그 확인 + (노출 시) 비밀값 교체 권장.

### 45. Crew/Prepaid Accounts — description 을 blank(빈 문자열)로 저장 시 미반영 (develop 미커밋)
- **증상**: Crew Accounts(및 Prepaid Accounts) 표의 인라인 description 편집에서 값을 **빈칸으로 지우고
  확정하면 저장이 안 됨**(옛 값 유지). 비어있지 않은 값은 정상 저장.
- **근본 원인**: 인라인 편집 폼(`manage_crew_wifi_account.inc:569~575`)은 `description`(텍스트, 빈값 가능)
  + hidden `userid` 를 POST 하는데, 핸들러가 **값의 truthiness** 로 게이트:
  `if ($_POST['description'] && $_POST['userid'])` → 빈 문자열은 falsy → `set_description()` 미도달 →
  조용히 무시.
- **수정 (2파일, 동일)**: 게이트를 `if (isset($_POST['description']) && !empty($_POST['userid']))` 로 변경.
  빈 description 은 정당한 값이므로 `isset` 로 판정, `userid` 는 여전히 필수(스케줄러 폼처럼 userid 만
  있고 description 없는 POST 와 구분). `crew_account.php:127` + `prepaid_account.php:112`.
  `set_description()`(`manage_crew_wifi_account.inc:616`)는 이미 빈 문자열을 정상 기록 → 게이트만이 원인.
- **미수정(범위 밖, 후속 후보)**: `set_description()` 가 lock/`parse_config(true)` 없이 stale 스냅샷
  `write_config()` → 동시 PW 변경 등과 lost-update 가능(#22/#30 패턴). 필요 시 동일 락 패턴으로 하드닝.
- **검증**: php -l 통과(crew_account.php·prepaid_account.php). 배포: 두 www 파일(+.inc 는 무변경).

### 46. GET `/api/v1/system/runtime` — fw_uptime(초)만 반환 (core_temp/core_uptime 은 메인서버 파이프라인 SSH) (develop 미커밋)
- **최종 설계**: `/api/v1/system/runtime` GET → **pfSense uptime(초, 정수 스칼라)만** 반환.
  `core_temp`/`core_uptime` 은 **API 가 다루지 않음** — 메인 서버 파이프라인이 코어 박스에 **직접 SSH** 로 취득(사용자 담당).
- **이력(폐기)**: ① SSH-in-API(sshpass) → FreeBSD 미지원으로 폐기. ② InfluxDB 경유(코어가 InfluxDB write,
  API 가 조회) → StreamSets 보안 제약(`.execute()`/파일읽기 차단)으로 코어측 writer 운용 불가 → **전면 폐기**.
  InfluxDB 조회 로직·상수·writer 스크립트(`tools/coresystem_influx_write.{sh,groovy}`)·`core_status` DB 접근 모두 제거.
- **구현 (pfSense-API 엔드포인트 3파일)**:
  - 모델 `etc/inc/api/models/APISystemGetRuntime.inc`: `action()` 이 `__get_uptime_seconds()`
    (=`sysctl -n kern.boottime` 의 `sec` 파싱 → `time()-boot`, 실패/음수 `0`) 를 **스칼라**로 반환. core 로직 없음.
  - 엔드포인트 `etc/inc/api/endpoints/APISystemRuntime.inc`: `url=/api/v1/system/runtime`, `get()` 만.
  - 웹루트 로더 `usr/local/www/api/v1/system/runtime/index.php`: `APISystemRuntime()->listen()`.
- **응답 예**: `{"code":200,"status":"ok","data":274353}` (초).
- **오토로드**: 프레임워크(`api/framework/*`, 박스 pfSense-API 패키지 제공)가 클래스명으로 모델 오토로드
  → 엔드포인트에서 모델 `require_once` 불필요.
- **인증**: pfSense-API 는 GET 도 `client-id`/`client-token` 필요 → 파이프라인이 URL 쿼리스트링으로 전달
  (`?client-id=<fw_id>&client-token=<fw_password>`). 프레임워크가 GET `$_GET` 에서 auth 를 읽음(API 코드 무변경).
- **파이프라인(리포 밖, 구현 반영)**: 메인 서버 SDC Groovy(`GroovyEvaluator_04`)가
  `SynerSAT.vessel_system_state (timestamp, vessel_imo, core_temp, core_uptime, fw_uptime)` 적재.
  - `timestamp` = 레코드 시각(`datetime.time`)을 **5분(300000ms) 경계로 내림** → `new Timestamp(...)`.
    ON DUPLICATE 도 timestamp 갱신(vessel_imo 유니크면 최신시각, `(vessel_imo,timestamp)` 유니크면 이력). **테이블에 `timestamp`(DATETIME) 컬럼 필요(사용자 관리).**
  - `fw_uptime` = runtime API(`data` 스칼라/객체 모두 방어).
  - **`core_temp`/`core_uptime` = 메인 서버에서 `sshpass -p P@ssw0rd ssh -p 21022 synersatroot@${vpnIp}` 로
    `sensors`(`Core N` 평균℃) + `cat /proc/uptime`(정수 초) 실행 → stdout 파싱**(`===UP===` 구분자).
    (SSH 포트 **21022**.)
    `sensors` 라벨은 `Core <정수>:` 만 채택(Voltage/Frequency 등 제외). 타임아웃 5초
    (`ConnectTimeout=5` + `waitForOrKill(5000)`), **미취득/실패 시 0 기본값**.
  - -1 센티널 폐기 → 모든 미취득값 0 → **unsigned INT 컬럼이어도 롤백 없음**(이전 "저장 안됨" 원인 해소).
  - 전제: **메인 SDC 호스트에 `sshpass` 설치** + SDC→각 선박 `vpnIp` SSH 도달. per-record 실행이라 SSH 최대 ~5초/척.
- **검증**: 모델 php -l 통과. InfluxDB/writer 관련 파일·로직 전면 제거 확인.
- **배포 정합성**: API 3파일(모델만 변경). **sshpass·InfluxDB·`core_status` DB·writer 스크립트 전부 불필요.**
- **timestamp**: 파이프라인이 `vessel_system_state.timestamp` = 레코드 시각 **5분(300000ms) 내림**으로 적재
  (`(datetime.time).intdiv(300000L)*300000L`, `setTimestamp`). **테이블에 `timestamp`(DATETIME) 컬럼 필요**
  (없으면 `Field 'timestamp' doesn't have a default value` — 신버전 파이프라인 재import 필요).
- **core_uptime "이상값" 오해 해소(선상 실측 결론)**: 한 선박 `core_uptime≈15,290,432`(≈177일)이 과대해 보였으나
  **버그 아님** — `cat /proc/uptime` 첫 필드(=커널 monotonic uptime) 그대로이며, `last reboot`(utmp, wall-clock)
  이 **부팅 2026-01-06 → 176일 23시간(≈15,290,880초)** 로 **/proc/uptime 과 초 단위까지 일치**. 현재 날짜
  2026-07-02 기준 실제로 ~177일 가동. (앞서 본 `10584`(3h)은 **다른 선박의, 방금 재부팅된 core 박스** — 전
  core 박스 hostname 이 `core` 라 혼동.) clocksource sysfs 부재는 컨테이너류 환경 탓이고 dmesg TSC 정상
  (`Switched to clocksource tsc`, unstable 경고 없음). → **파싱·박스·clock 모두 정상, core_uptime 은 초 단위 참값.**
- **남은 미해결(후속)**: `fw_uptime = 0` 전 행 — runtime API 미배포/미응답 추정. 한 척에서
  `curl "http://<vpnIp>/api/v1/system/runtime?client-id=<fw_id>&client-token=<fw_password>"` → 숫자면 정상,
  404=엔드포인트 미배포, 401/403=자격증명. API 3파일 배포 후 재확인 필요.
- **릴리스**: 이 배치(#41~#46) 패치노트 = 헤더 **`2026-07-02 Update`**(버전번호 제거, 날짜+제목) +
  서브라인 **`Beta 1.1.49-Beta · Stable: 1.1.3-Stable`**(베타/스테이블 버전은 서브라인에 유지). 이를 위해
  `release_note.php` 파서(`rn_is_version_header`)가 기존 `X.Y.Z (날짜)` 외 **`YYYY-MM-DD [제목]`** 헤더도
  인식하도록 확장(날짜형은 통째로 version 표시, date 빈값; 서브라인은 기존대로 헤더 다음 첫 줄).
  `usr/local/www/release_note.md` + `release_note.php` 일괄 배포.

### 47. 게이트웨이 저장 시 [CP Routing] 룰 자동 재동기화 (게이트웨이 이름 변경 대응) (develop 미커밋)
- **배경/요구**: 게이트웨이 이름을 바꾸면 `[CP Routing]` floating 룰이 **옛 이름(`cp_gw_{oldname}`)으로
  남아** 실제 게이트웨이 구성과 불일치. 그간 재구성은 배포 스크립트(리포 밖 `update.sh`)가 부르는
  `cp_routing_setup.php` 수동/배포 실행에만 의존. → **게이트웨이 저장(`system_gateways_edit.php`) 시점에
  자동 동기화**하도록 요청.
- **호출 함수 선택(중요)**: `cp_routing_setup.php`(배포 스크립트)는 **create-only**(존재하면 skip, 삭제
  안 함)라 rename 마다 옛 이름 alias·룰이 **고아로 누적**됨. 대신 **완전 재동기화** 함수
  `cp_sync_routing_tables()`(→ `cp_refresh_pass_rules()`)를 호출 — `array_diff` 로 `need_add`/`need_remove`
  계산해 **옛 이름 룰 제거 + 새 이름 룰 생성 + pfctl 테이블 재적재**([captiveportal.inc:4640·4834]).
- **수정 (`usr/local/www/system_gateways_edit.php`)**: 저장 핸들러에서 `save_gateway($_POST,$realid)` 직후
  (리다이렉트 전)에 `cp_sync_routing_tables()` 호출. `captiveportal.inc` lazy require +
  `function_exists`/`file_exists` 가드(버전 섞임·미탑재 시 fatal 없이 skip).
- **in-process 호출(핵심)**: 별도 `php` 프로세스로 띄우지 **않음**. `save_gateway()`가 이미
  `write_config("Gateway settings changed")`([gwlb.inc:2295])로 새 이름을 메모리 `$config`에 반영했으므로,
  같은 프로세스에서 그 위에 동작 → **stale 스냅샷 lost-update(#10/#22/#30) 회피**. 별도 프로세스면
  자기 `parse_config()` 스냅샷으로 동시 PW 변경 등을 되돌릴 위험이 있어 금지.
- **잦은 호출 안전성**: `cp_refresh_pass_rules()`는 **멱등**(기대셋==현재셋이면 조기 return, no-op),
  **변경 시에만 write_config**, 재귀 방지 위해 직접 `filter_configure()` 대신 **비동기 `send_event("filter
  reload")`** 사용([captiveportal.inc:4717·4767·4773]). 게이트웨이 저장은 관리자 저빈도 행위라 성능 무관.
- **범위(의도적 제외)**: 이번 변경은 **CP 룰 동기화만**. 유저 `varusersterminaltype`(옛 게이트웨이 이름
  바인딩) 이관은 **미포함**(사용자가 별도 처리). rename 실운영 시 유저 terminal_type 을 새 이름으로 바꾸지
  않으면 해당 유저는 #38(antenna offline)로 로그인 차단 + 라우팅 fail-closed 되므로 GUI 에서 별도 갱신 필요.
- **삭제 경로(의도적 미훅 — 사용자 결정)**: 게이트웨이 **삭제**는 목록 페이지 `system_gateways.php`
  (스톡, 리포 밖)에서 처리되어 이 훅이 안 걸린다. **삭제 즉시 동기화는 넣지 않음**. 다만
  `cp_refresh_pass_rules()`가 편집 대상이 아니라 **현존 게이트웨이 기준 전역 diff**(need_remove)라,
  삭제된 게이트웨이의 고아 `cp_gw_*` 룰은 **이후 아무 게이트웨이나 저장/편집하면** 함께 정리됨
  (+ CP 재구성/부팅 시 `captiveportal_configure` 훅도 정리). **엣지**: 마지막(관리대상) 게이트웨이까지
  삭제해 목록이 비면 `cp_refresh_pass_rules` 의 `if (empty($all_gws)) return;` 가드로 정리 안 됨
  (게이트웨이가 하나라도 다시 존재해야 정리).
- **검증**: `php -l` 통과.
- **배포 정합성**: `system_gateways_edit.php` + `captiveportal.inc`(cp_sync_routing_tables 정의) **같은
  리비전 일괄** 배포(가드 있어 fatal 은 없으나 미탑재 시 동기화 skip).

### 48. GMT time_offset 변경 이력 → MariaDB `radius.gmt_history` 기록 (수동 변경 포함 전 경로) (develop `c473a8f`)
- **요구**: mariadb://192.168.209.210:3306 (radius/radius, **MariaDB 5.5**) 의 `radius.gmt_history`
  테이블(`id` INT AUTO_INCREMENT PK / `timestamp` DATETIME / `timefrom` VARCHAR(10) / `timeto` VARCHAR(10)
  / `description` VARCHAR(255) / `gps` VARCHAR(32)) — **없으면 자동 생성** — 에 GMT time_offset 이
  **변경되는 모든 이벤트**(수동 변경 포함)를 기록. gps = 당시 좌표 "lat,lon"(소수 5자리),
  미수신/influx 불통이면 **'N/A'**.
- **컬럼 마이그레이션 없음(의도)**: description/gps 는 CREATE TABLE 에 처음부터 포함해 **6컬럼으로
  바로 생성** — #48 배포 전까지 이 테이블이 존재하는 박스가 없음(사용자 확인). 조건부 ALTER
  (information_schema+PREPARE) 방식은 검토 후 불필요로 제거. (만약 예외적으로 4컬럼 구버전 테이블이
  이미 생긴 박스가 발견되면 수동 `ALTER TABLE gmt_history ADD COLUMN ...` 1회 필요.)
- **writer 전수(#48 시점, grep 확인 = 이 3곳뿐)** — 각각 훅:
  - `usr/local/www/index.php` GMT 팝업 저장(#43) → `source=manual-web`, desc=**"Manual change from
    {REMOTE_ADDR}"**. **실제 값이 바뀐 경우만** 기록(`$gmt_prev !== $gv`; 동일값 재저장은 미기록 —
    write_config 는 기존대로 무조건). gps 는 record 가 influx 자동 취득.
  - `usr/local/cron/cp_tz_offset_update.php` GPS 자동 갱신(#29) → `source=auto-gps`, desc=**"Automatic
    change from GPS (src=… zone=…)"**, gps=**크론이 이미 조회한 좌표 그대로 전달**(influx 재조회 0).
    크론이 원래 변경 시에만 write 하므로 적용 성공(`$applied`) 후 **락 밖**에서 기록(#22 패턴).
  - `etc/inc/api/models/APIStatusSetTimeOffset.inc` 외부 REST 푸시 → `source=api-push`, desc=**"Remote
    change via API from {REMOTE_ADDR}"**. write_config 직후. gps 자동 취득.
- **신규 `etc/inc/cp_gmt_history.inc`** — `cp_gmt_history_record($timefrom, $timeto, $source,
  $description = '', $gps = '')`:
  - **mysql CLI + defaults-extra-file**(비번 argv 미노출) + `--connect-timeout=3` + `/usr/bin/timeout 8`
    하드 바운드(#40 패턴; timeout 부재 시 connect-timeout 만) — `freeradius_radcheck_exec_sql`(#23 3-A)
    동일 패턴이나 **freeradius.inc 비의존 self-contained**(크론/API 에서 무거운 include 회피).
  - CREATE TABLE IF NOT EXISTS(6컬럼) + INSERT 를 **한 배치**(멱등 — 테이블 없으면 첫 이벤트 때 생성).
  - `timestamp` = **박스 UTC**(`gmdate`, #41 박스 UTC 권위 원칙). timefrom/timeto 는 오프셋 문자열
    ("9"/"-3.5"; 미설정이면 '') — VARCHAR 클램프(10/255/32) + SQL escape.
  - **gps 자동 채움**: `$gps=''` 이면 `cp_gmt_history_current_gps()` — influx(선내 LAN, 2초 타임아웃)
    VSAT(vesselposition) 우선 → FBB(satstatus, 방향컬럼 부호 복원) 폴백, (0,0)/불통 = 'N/A'
    (#29 크론과 동일 정책, self-contained). 웹 경로 최악 지연 ~4초(influx 불통 시)는 수용.
  - **실패해도 throw 없이 false + log_error** (GMT 저장 흐름 불가침 degrade). 성공/실패 모두
    `GMT HISTORY:` 시스템 로그(빈도 낮아 스팸 없음; source 는 로그 가시화용 — 테이블 컬럼 아님).
  - 호출측 3곳 모두 `file_exists`+`function_exists` 가드 → **버전 섞임(inc 미배포) 시 fatal 없이 skip**.
    구 inc(3인자 record)에 신 호출(5인자)이 와도 PHP 는 초과 인자 무시 → **양방향 버전섞임 안전**.
- **주의(수용)**: `gmtcheck`(수동모드 토글) 자체는 오프셋 변경이 아니라 미기록. DB 접속정보는 코드 상수
  (기존 influx/mysql 하드코딩 관례 — 보안 후속 항목과 동일 범주).
- **이력 뷰어(사이드바 history 버튼 + 모달)**:
  - **버튼**: 사이드바 "GMT n"(`common_ui.inc print_sidebar` `#gmt-modify`) 옆 소형 `history` 버튼.
    클릭 버블은 JS `stopPropagation` 으로 차단(부모 클릭 = 타임존 설정 팝업 pop-gmt 와 분리).
    버튼 텍스트가 `#gmt-modify` innerText 에 섞여도 common.js `currentOffset()` 은 parseFloat 라 안전
    ("9.5 history" → 9.5). 사이드바 공용이라 **9개 관리 페이지 전부에서 동작**.
  - **모달**: "Daily internet usage"(#42) 모달과 동일 계열 스타일(다크 카드 + pill 버튼, `gmthist-*`
    ID/클래스 격리, 자체 색 고정 = 라이트/다크 테마 무관). 범위 = **1d(기본)/7d/30d/Custom**(네이티브
    `<input type="date">` 캘린더 2개 + Apply, 역순 입력은 서버가 스왑). 표 = Time (UTC) / Change
    ("GMT 9 → GMT 9.5", 미설정은 "(unset)") / **Description / GPS**(빈값 = "N/A" 표기, 모달 폭 760px).
    빈 결과 "No timezone changes", DB 불통 "History unavailable" — fatal 없음. 닫기 = X/배경클릭/ESC.
  - **클라이언트 페이지네이션(10개 단위, #50 과 동일 패턴)**: `render()` 가 전체 결과를 `lastRows` 에
    담고 `renderPage()` 가 `PAGE_SIZE=10` 슬라이스만 표시 + Prev/Next + "Page X / Y · N total"
    (`gmthist-pager`/`gh-page`). 총 10개 이하면 pager 미표시, 범위 전환마다 1페이지 리셋, 경계 disabled.
    **Export CSV 는 현재 페이지가 아니라 `lastRows` 전체**를 내보냄(변경 없음).
  - **Export CSV**: range 줄 우측 녹색 pill 버튼 — **현재 표시 중인 조회 결과**를 클라이언트에서
    CSV 생성해 다운로드(`gmt_history_YYYYMMDD_HHMMSS.csv`). 헤더
    `id,timestamp_utc,timefrom,timeto,description,gps`,
    RFC4180 인용부호 escape, CRLF, **BOM(U+FEFF) 프리픽스 = Excel 한글판 호환**(코드엔 이스케이프
    문자열로 기재 — 리터럴 BOM 금지, 에디터/인코딩에 취약). 결과 없거나 로딩 중엔 disabled.
    범위 pill 선택자는 `data-days/data-custom` 속성 기준이라 Export 버튼은 range 로직에서 제외.
  - **엔드포인트 `usr/local/www/gmt_history_data.php`(신규)**: guiconfig.inc 인증 경유 JSON.
    `mode=days&days=N`(1~3660 클램프) 또는 `mode=custom&from/to=YYYY-MM-DD`(정규식 검증, UTC 날짜).
    CSRF 는 렌더된 `#gmtForm` 의 `__csrf_magic` hidden 을 XHR 바디에 재사용.
  - **헬퍼 확장(`cp_gmt_history.inc`)**: 공통 실행부 `cp_gmt_history_exec_sql($sql,$flags,&$out)` 로
    리팩터(record/fetch 공유) + `cp_gmt_history_fetch($from,$to,$limit=1000)` — `-N -B`(헤더 생략·탭
    구분) SELECT, datetime 정규식 검증(비정상 입력 = mysql 호출 전 false), LIMIT 1~5000 클램프,
    CREATE TABLE IF NOT EXISTS 동배치(테이블 없어도 빈 결과).
- **검증**: php -l 전부 통과 / 생성 DDL 배치 출력 검수(MariaDB 5.5 호환) /
  주입 JS node --check + DOM/XHR 스텁 하네스 **34/34**(기본 1d 요청·CSRF 첨부·렌더/escape·(unset)·
  Description/GPS 컬럼·gps 빈값→N/A·pill 전환·Custom 캘린더·custom 요청·실패/빈 메시지·닫기 3종 +
  Export: 로딩중 disabled·렌더 후 활성·BOM·헤더 6컬럼·quote/comma escape·CRLF·파일명) /
  fetch 입력검증·mysql 부재 graceful false·구버전 3인자 record 호출 호환 통과.
- **배포 정합성**: `cp_gmt_history.inc` + `index.php` + `cp_tz_offset_update.php` +
  `APIStatusSetTimeOffset.inc` + `common_ui.inc` + `gmt_history_data.php` **6파일 일괄**
  (가드 있어 fatal 없음 — inc 누락 시 기록만 skip, 엔드포인트 누락 시 모달이 unavailable 표시).

### 49. crew 계정 변경 이력 → MariaDB `radius.radacct_changehistory` 기록 (PW리셋/사용량리셋/생성/삭제/수정 전 경로) (develop 미커밋)
- **요구**: Crew Account 의 user id 별 Password 리셋 / Data Usage 리셋 / 업데이트 등 **모든 계정 변경**을
  #48 과 동일 방식으로 mariadb://192.168.209.210:3306 의 `radius.radacct_changehistory`
  (`id` INT AUTO_INCREMENT PK / `timestamp` DATETIME / `change_type` VARCHAR(64) /
  `change_description` VARCHAR(1024)) 에 기록 — 없으면 자동 생성. **실제 비밀번호는 절대 미기재**
  ("(changed)"/"initial"/"random"/"(set)" 마스킹만).
- **신규 `etc/inc/cp_account_history.inc`** — `cp_account_history_record($change_type, $descriptions)`:
  - 실행부는 **#48 `cp_gmt_history_exec_sql` 재사용**(같은 DB/자격증명·defaults-extra-file·타임아웃).
    #48 inc 미배포 시 `function_exists` 가드로 조용히 skip(버전섞임 안전).
  - 다건은 배열로 → **단일 INSERT 배치, 사용자별 1행**. timestamp=박스 UTC(gmdate). desc 1024 클램프
    + 개행/탭 정리. 실패 시 false + `ACCT HISTORY:` log_error(흐름 불가침).
  - `cp_account_history_actor()`: `cp_admin_actor()`(관리 세션명→IP) 있으면 재사용, 없으면
    세션명→REMOTE_ADDR→'system' 폴백(API 컨텍스트에서 captiveportal.inc 없어도 동작).
- **prepaid 구분 = `cp_account_history_tag($username)`** — username 이 `crewpay-`(대소문자 무시,
  `/^crewpay-/i`)로 시작하면 desc 끝에 **` (CREWPAY)`** 태그, 아니면 ''. **진입점(crew/prepaid 페이지·
  위젯·API)이 아니라 "변경 대상 계정이 prepaid 인가"로 판정** — prepaid 계정은 코드베이스 전역에서
  `crewpay-` 접두사로 식별되므로(build_wifi_rows 필터/captiveportal index.php 로그인 시 접두사 부여/
  cp_usage_reset·prepaid 크론 제외 기준과 동일) username 만으로 정확·불변. **모든 per-user hook 이
  사용자별로 태그 적용**(혼합 배치면 crewpay- 사용자 행만 태그). **예외: 포털 자가 비번변경
  (`commit_change_pw`)은 태그 미적용**(사용자 지시 — prepaid 구분 불필요).
- **hook 전수(계정 변경 writer — grep 전수조사)**:
  - `manage_crew_wifi_account.inc`(crew/prepaid 웹 + API bulk-create/delete 공용, 8곳):
    `create_wifi_user`→user_create(quota/period/terminaltype/password=random|initial) /
    `del_wifi_user`→user_delete / `modify_wifi_user`→user_modify(새 값 요약) /
    `reset_wifi_user`→usage_reset / `reset_wifi_user_pw`→password_reset("initial value") /
    `reset_random_wifi_user_pw`→password_reset("random value") / `set_description`→description_change /
    `set_scheduler`→schedule_change(활성 행 "HH:MM-HH:MM(days)" 요약). PW 계열은 **락 밖**에서 기록(#22).
  - `manage_freeradiususer.widget.php`(대시보드 위젯 4분기, 인라인 구현이라 별도 훅): deluser/resetuser/
    resetpw/createuser → 동일 타입 + desc 에 "(widget)" 표기. 전부 락 밖.
  - API: `APIFreeRadiusUserUpdate`→user_modify(전송된 freeradius_* 필드 요약, **password=(changed)
    마스킹** — israndompw 주입 password 포함) + usersreset→usage_reset(터미널타입별) /
    `APIFreeRadiusUserTopup`→quota_topup(quota±MB, usage±MB) / `APIFreeRadiusUserCreate` 단건→
    user_create(password=(set)) — bulk 는 create_wifi_user 훅이 커버, Delete 는 del_wifi_user 재사용이라 자동 커버.
  - `captiveportal.inc commit_change_pw`(포털 자가 비번변경)→password_change(client IP 기재, lazy require).
- **의도적 제외**: 주기 리셋 크론(daily/weekly/halfmonthly/monthly/prepaid/selfheal)의 자동 리셋 —
  관리자 행위가 아니고 유저수×주기로 매일 쌓여 노이즈. 필요 시 같은 헬퍼 한 줄로 추가 가능.
- **검증**: php -l 전파일 통과(captiveportal.inc 의 `${var}` deprecated 경고는 기존 코드, PHP7.4 무관) /
  스텁 하네스 **14/14**(다건 단일배치·행수·quote escape·개행정리·timestamp 형식·단건 문자열 인자·
  빈 입력 false·1024 클램프·actor 폴백 2종) + **태그 하네스 9/9**(crewpay- 대소문자/정상/synersat/
  빈값/substring-not-prefix/null) + **CREWPAY 통합 하네스 11/11**(crew 무태그·prepaid 태그·혼합배치
  선별·API 마스킹+태그·record SQL 태그) / #48 미배포 시 graceful false 확인 /
  **멀티에이전트 적대검증 워크플로**(pw-writers·usage-reset·create-delete·pw-leak·prepaid-signal 5차원).
- **배포 정합성**: `cp_account_history.inc` + `cp_gmt_history.inc`(#48 실행부) +
  `manage_crew_wifi_account.inc` + `manage_freeradiususer.widget.php` + `APIFreeRadiusUser{Create,Update,Topup}.inc`
  + `captiveportal.inc` **8파일 일괄**(#48 묶음과 같이 배포 권장. 가드 전면 — inc 누락 시 기록만 skip).

### 50. crew_account.php per-user "History" 버튼 → 계정별 변경 이력 모달 (develop 미커밋)
- **요구**: MANAGE CREW ACCOUNT(crew_account.php)에 사용자마다 "History" 버튼 → 해당 계정의 Modify
  History(=#49 `radacct_changehistory`) 조회. 양식은 #42/#48 "Daily/GMT" 모달과 유사.
- **per-user 조회 설계 = `username` 컬럼 추가**: `radacct_changehistory` CREATE TABLE 에
  `username VARCHAR(64)` + `KEY idx_username` **바로 포함**(마이그레이션 없음 — 아직 미배포라
  새로 생성하면 됨). **모든 #49 훅 desc 가 `user=<username> ...` 로 시작**하므로 record() 가
  `cp_account_history_extract_username`(`/^user=(\S+)/`)로 **자동 추출·저장** → 16개 훅 무수정.
- **조회 헬퍼 `cp_account_history_fetch($username,$from,$to,$limit)`**: username 정규식 검증
  (`^[A-Za-z0-9._-]+$`) + escape + `=`(LIKE 아님, `_` 와일드카드 무해) + datetime 정규식 + LIMIT
  클램프(≤5000). `-N -B` 출력의 빈/불완전 행은 컬럼수<4 가드로 무시.
- **엔드포인트 `usr/local/www/crew_account_history_data.php`(신규)**: guiconfig 인증 JSON.
  `mode=days&days=1|7|30|3660(All)` 또는 `mode=custom&from/to`. 기본 30일. 입력 불량/DB 불통 = ok:false.
- **UI**:
  - 행별 "History" 버튼 — `draw_wifi_contents` 에 추가하되 **crew 페이지 전용**(`$isPrepaid!=='prepaid'`
    가드; prepaid 페이지는 컬럼 미추가라 정렬 안 깨짐). crew_account.php thead 에 `<th>History</th>` +
    colgroup 1열 추가(9열 정합).
  - 모달 = 공유 함수 `render_account_history_modal()`(manage_crew_wifi_account.inc, nowdoc) — 전역
    `openAcctHistory(username, displayname)` 정의. GMT 모달(#48)과 동일 다크카드(테마 무관 고정색),
    `accthist-*` 격리. 범위 pill 30d(기본)/7d/1d/All/Custom + Export CSV(BOM/CRLF/5열: id/ts/username/
    type/desc). 표=Time(UTC)/Type(색 chip)/Description. 닫기 X/배경/ESC. crew_account.php `</body>` 앞
    `function_exists` 가드로 echo.
  - **클라이언트 페이지네이션(10개 단위, 후속 추가)**: `render()` 가 전체 결과를 `lastRows` 에 담고
    `renderPage()` 가 `PAGE_SIZE=10` 슬라이스만 표시 + Prev/Next + "Page X / Y · N total" pager.
    총 10개 이하면 pager 미표시. 새 조회(범위 전환)마다 1페이지로 리셋. **Export CSV 는 현재 페이지가
    아니라 `lastRows` 전체를 내보냄**(변경 없음). pager 는 renderPage 재호출마다 재바인딩(경계에서 disabled).
  - CSRF = crew_account.php 폼에 csrf-magic 이 주입하는 `__csrf_magic` hidden 을 XHR 에 재사용
    (`window.csrfMagicToken` 우선 폴백). 기존 modify AJAX 와 동일 패턴.
- **검증**: php -l 4파일 / 백엔드 하네스(DDL·username 추출·INSERT·fetch SQL·주입방어·no-op 무시) /
  모달 DOM·XHR 하네스 **28/28**(열기·30d 기본·csrf·렌더/chip/escape·All·Custom·CSV 5열·실패/빈·닫기 3종) /
  #48·#49 회귀 없음(GMT 34/34, 다건 username 추출+CREWPAY+PW마스크) / 멀티에이전트 적대검증(prefix 완전성·
  엔드포인트 주입/인증/XSS·테이블 정합).
- **배포 정합성**: `cp_account_history.inc` + `crew_account_history_data.php` + `manage_crew_wifi_account.inc`
  + `crew_account.php` (+ #48 실행부 `cp_gmt_history.inc`) 일괄. `radacct_changehistory` 는 아직 미배포라
  **username 컬럼 포함 신 스키마로 그냥 새로 생성**(마이그레이션 불필요 — 사용자 방침).

### 51. FBB 신호 "No Signal" 오표시(이름매핑 종속) 분리 + ACU state -1 → "Comm. Error" (develop `a848caa`)
- **증상**: Main Panel Satellite 타일 안테나 나침반에서 **FBB 는 정상 데이터가 들어오는데 Signal 이
  "No Signal" 로 표시**됨(`FBB : 6 (No Signal)`). 실제 influx 에는 신호값이 있음.
- **근본 원인(FBB 신호)**: `get_fbb_pointing_info()`([server_module.inc:508~])가 신호를 `$out['signal']`
  로 읽은 **뒤** `cp_fbb_satlon_from_name($name)` 로 위성 궤도경도를 매핑하는데, 이름이 맵(MEAS/APAC/
  AMER/EMEA/ALPHASAT…)에 없으면(예: FBB 가 보고한 이름 "6") `null` → **조기 리턴**. 그런데 tracking
  판정(`signal>=1 → status='tracking'`)이 이 조기 리턴 **뒤**에 있어 status 가 'searching' 에 머묾.
  index.php JS(`updateFbbCompass`)는 **`status==='tracking'` 일 때만 신호를 표시** → 신호가 있어도
  "No Signal". **즉 FBB 신호 표시가 위성 이름 매핑 성공에 종속**된 구조 버그.
- **수정(FBB)** `server_module.inc get_fbb_pointing_info`: **tracking 판정을 신호값(≥1)만으로** 조기
  리턴 **앞**으로 이동. 이름 매핑(`cp_fbb_satlon_from_name`)은 **니들(az/el) 계산에만** 사용 —
  미매핑 시 니들만 생략하고 신호/상태·raw 이름은 유지. JS 무수정(이미 tracking 시 신호 표시).
  - 양쪽 안전: 신호가 진짜 0/빈값이면 status='searching' 유지 → 여전히 "No Signal"(정상).
  - **FBB "6" → EMEA/Alphasat(24.9E) 매핑 완료(`725e53c`)**: `cp_fbb_satlon_from_name` 에 숫자 ID
    **정확일치 맵(①-b, `$exact = ['6'=>24.9]`)** 추가 — 부분일치(②)보다 먼저 처리해 "16"/"63" 등
    오매칭 방지. 이제 "6" 보고 시 `FBB : 24.9E (Signal : n)` + 니들(GPS 있으면 az/el) 표시.
    다른 숫자 ID 관측 시 `$exact` 에 한 줄 추가.
- **ACU state code -1 → Comm. Error**: `get_acu_pointing_info()` 의 antstatus 해석에서 `-1` 이
  `0`(SEARCHING)과 함께 'searching' 으로 뭉뚱그려져 있었음. `-1`(IntellianACUReader = 통신 오류)을
  **별도 `commerror` 상태로 분리** → index.php 컴퍼스에 **"VSAT : Comm. Error"(빨강)** 표시.
  - `server_module.inc`: `-1` → `'commerror'`, `0` → `'searching'` 분리. status 주석에 commerror 추가.
  - `index.php`: labels 에 `commerror: 'VSAT : Comm. Error'` + CSS `[data-status="commerror"]`
    빨강 텍스트·도트·회색 el니들 + 3D 돔 `satColor` 빨강 처리.
  - **주의**: `terminal_status.inc` 의 `$vsat_status[0]=="-1"`="DB read error" 는 `check_vsat_status_influxdb`
    의 curl 실패 센티널(별개 필드)이라 **무관·미수정**. antstatus 코드 -1 과 혼동 금지.
- **상태 코드 매핑(확정)**: `1`=tracking / `0`=searching / `2`=blocked / `-1`=**commerror**(Comm. Error) /
  (antstatus 컬럼 없음)=`Longitude≠0`+`AGC/Signal≥1`이면 tracking, 아니면 searching. antstatus 컬럼명은
  고정 아님 — `/ant.*status/i` 정규식으로 탐색(파이프라인 구성별 상이). **인코딩(state code 저장)은
  레포 밖 acureader(IntellianACUReader.java)** — 레포엔 reader 만 존재.
- **검증**: php -l 2파일 통과.
- **배포 정합성**: `server_module.inc` + `index.php` 2파일(가드 있어 fatal 없음, 표시만 강등).

### 52. crew → This Firewall(자기 자신) 접근 제한 — DNS/DHCP/portal 외 전면 block (develop `4c5c519` → main `d8165bf` → prod `59b6594`)
- **배경/요구**: crew 로그인 시 pfctl 테이블 방식(`add_crew_linked_rule`, #1·#4·#12·#13 배경)으로 라우팅
  pass 룰이 **묵시적으로** 걸리는데, 그 [CP Routing] route-to pass 룰들의 **Destination 이 `any`** 라
  "This Firewall(박스 자기 IP)"까지 포함 → **crew 단말이 firewall 의 webGUI(443/80)·SSH(22) 등 관리
  서비스에 접근 가능**한 누수. crew 는 **DNS(udp/53) + portal(tcp/8002)** 만 firewall 에 접근하고
  나머지는 막고자 함(+ DHCP 갱신 udp/67 은 운영 필수라 함께 허용).
- **원인 확인(선상 GUI 스크린샷)**: CP 클라이언트 인터페이스 = `CREW`. floating 목록에 crew→self 를
  제한하는 룰이 **명시적으로 없음**. `[System Rule] Default allow for requesting to Firewall` 은 인터페이스가
  `MACHINE/NOC_VPN/BUSINESS/OpenVPN` 이라 CREW 무관(누수 원인 아님). 원인은 오직 `[CP Routing] route-to`/
  `cp_gw_default pass` 4룰의 `dest any`. (route-to 는 self 목적지엔 사실상 미적용이라 트래픽이 그냥 박스로 pass됨.)
- **결정: raw pfctl 아니라 config 기반 floating 룰** — 보안 block 은 `filter_configure()` flush(게이트웨이
  up/down 자동 트리거)에도 **살아있어야** 하므로 config 에 둔다. pfctl-only 앵커/테이블은 flush 되면
  block 이 사라져 **fail-open 노출**(#20 의 보안판). 라우팅 pass 가 flush 되면 최악이 오라우팅이지만
  self-admin block 이 flush 되면 관리콘솔 노출이라 훨씬 위험.
- **수정 (`captiveportal.inc` `cp_refresh_pass_rules()` 단일 함수)**:
  - `$expected[]` 에 self-protect 4룰 추가(floating·quick·direction=in·interface=`$lan_iface`(CREW)·
    **source=any**·**dest=`(self)`**): pass udp/53(DNS) / pass udp/67(DHCP 갱신 unicast) /
    pass tcp/8002(portal, `$portal_port` 변수) / **block any(나머지 전부)**. descr 접두 `[CP Routing] self-protect`.
  - 룰 빌더 확장: 엔트리에 `src_any`(source=any) / `dst_self`(dest=`(self)`) / `protocol` / `dstport` 옵션
    키 처리. **기존 route-to/block-out 빌더는 완전 하위호환**(미설정 시 src alias + dst any 유지).
  - **정렬 불변식 강제(핵심)**: add 루프의 `array_unshift` 는 순서를 뒤섞고, **게이트웨이 추가 시 새 route-to
    가 self-protect 위로** 올라갈 수 있음(quick first-match → self-protect block 이 route-to dest-any 밑으로
    가면 crew 가 다시 샘). → add 후 self-protect 룰을 **항상 [CP Routing] 최상단**으로 재배치(pass 3개 먼저,
    block 마지막; PHP7.4 usort 비안정이나 pass끼리 순서 무관). 변경(add/remove) 있을 때만 실행 → 불필요 write 없음.
- **DHCP(udp/67) 포함 사유**: crew 가 DHCP 면 **임대 갱신이 서버(=firewall) IP 로 unicast** → block 에
  걸려 주기적 IP 유실. 정적 IP 환경이면 `self-protect pass DHCP` 한 룰만 제거 가능.
- **범위/주의**: IPv4(`inet`)만(기존 라우팅 룰과 동일; crew IPv6→firewall 있으면 별도 필요). portal 포트
  8002 는 `$portal_port` 로 하드코딩(스크린샷 System Rule 12 와 일치); HTTPS 로그인 별도 포트면 pass 추가.
  source=any(=CREW in)이라 **인증 여부 무관 전 crew** 적용(미인증도 admin 차단, CP redirect 는 53/8002 로 정상).
- **Suricata 충돌 분석(질의 답)**: **룰 차원 충돌 없음**(Suricata 는 시그니처 판정, pf 룰/pfctl 테이블 미참조 —
  직교 레이어. self-protect 룰과 상호작용 0). 단 **모드 차원**: Legacy(Blocking, snort2c 테이블) 모드는 안전
  (다만 crew IP 오탐 차단 시 snort2c drop 이 pass 위에서 걸려 #19/#21 스타일 끊김으로 오진 가능 →
  `pfctl -t snort2c -T show` 진단). **Inline IPS(netmap) 모드는 CP 의 ipfw/dummynet + route-to 정책라우팅과
  잘 알려진 비호환** → CREW/route-to WAN 에 걸면 끊김/블랙홀. 권장: Legacy 모드 + WAN 한정 + crew 서브넷 Pass List.
- **검증**: php -l 통과(기존 `${var}` deprecated 경고만, 무관) / 독립 하네스 — self-protect 4룰 최상단·
  pass(53/67/8002) block 앞·각 룰 구조(proto/port/dst=(self)/src=any/no-gw)·route-to 하위호환(src alias/dst any/gw) 전부 통과.
- **적용(선상)**: `cp_refresh_pass_rules()` 발화 시 생성 → 배포 후 CP 재구성 / 게이트웨이 저장(#47 훅) /
  `cp_routing_setup.php` 중 하나로 트리거. GUI Floating 최상단 `[CP Routing] self-protect` 4줄 확인 →
  crew 에서 webGUI(443)/SSH(22) 차단 + DNS·포털·인터넷 정상. `captiveportal.inc` 단일 파일이나 버전 섞임 방지 일괄 배포 권장.

### 53. crew_account.php — customer 역할에도 "SET RANDOM PW" 버튼 노출 (develop `090e249` → main `d8165bf` → prod `59b6594`)
- **배경**: `crew_account.php` 상단이 `$adminlogin` 역할별로 툴바 버튼을 분기(admin/vesseladmin=
  Export CSV/Reset PW/**SET RANDOM PW**/Reset Data/Check PW/Delete; **customer=Reset PW 만**; 그 외=없음).
  customer 는 SET RANDOM PW 가 숨겨져 있었음.
- **수정**: `customer` 브랜치 `$controldisplay` 에 SET RANDOM PW 버튼 1개 추가
  (`onclick="confirm_setRandomPw()"`, Reset PW 와 동일 스타일). **다른 숨김 버튼(Reset Data/Delete/
  Check PW/Export CSV)은 그대로 숨김 유지**(의도).
- **동작 안전성 확인**: `confirm_setRandomPw()` JS 는 페이지 전역 `<script>` 에 역할 무관하게 정의
  ([crew_account.php:1040]), POST 핸들러 `if(isset($_POST['setrandompw'])){ reset_random_wifi_user_pw(...) }`
  ([:162])도 **역할 게이트 없음**(Reset PW 와 동일) → customer 에서 버튼→AJAX→백엔드 end-to-end 정상.
- **범위**: crew 전용(prepaid_account.php 는 customer 브랜치·SET RANDOM PW 자체가 없어 무관). `crew_account.php` 단일 파일.
- **검증**: php -l 통과.
- **되돌림(develop 미커밋)**: 사용자 지시로 customer 역할의 SET RANDOM PW 버튼을 다시 숨김 —
  `customer` 브랜치 `$controldisplay` 를 Reset PW 버튼만 남기고 원복(#53 이전 상태로 복귀).
  **주의**: #53 은 이미 `main`(`d8165bf`)·`prod`(`59b6594`) 까지 병합돼 있어, 이 되돌림을 develop
  에만 커밋하면 **customer 의 SET RANDOM PW 노출 여부가 develop 과 main/prod 사이에서 달라짐**
  (develop=숨김, main/prod=노출 유지) — main/prod 도 되돌리려면 별도 명시적 지시 필요.

### 54. Account History 모달 — Login/Logout + Session Usage 탭 추가 (Change | Login | Usage) (develop 미커밋)
- **요구**: crew_account.php per-user History 모달(#50, `radacct_changehistory` 단일 조회)에
  ① **로그인/로그아웃 이력**, ② **세션별 데이터 사용량**을 추가로 표시. 최종 모달은
  **Change / Login / Usage 3탭**, 셋 다 **동일한 기간 선택 UI(30d/7d/1d/All/Custom)** 공유
  (탭 전환 시 기간 유지, 기간 변경 시 탭 유지). Usage 탭 범위는 사용자 확인 결과 **완료(로그아웃
  완료)된 세션만**(진행 중 세션의 실시간 사용량은 미포함 — 별도 기능 영역).
- **시행착오(교훈, 중요)**: 처음엔 Login/Usage 를 위해 `radacct_changehistory`에 `client_ip`/
  `client_mac`/`session_id`/`session_duration`/`input_octets`/`output_octets` 6컬럼을 새로
  추가했었다(#48 GMT 이력 테이블처럼 "아직 미배포 박스 前提"로 마이그레이션 없이 CREATE TABLE
  단계에서 확장). 그런데 실사용 스크린샷으로 **이미 그 5컬럼 구버전 테이블이 배포된 박스가 최소
  1개 있음**이 확인되어(`CREATE TABLE IF NOT EXISTS`는 테이블이 있으면 무효과라 그대로 두면
  조용히 실패) `information_schema` 기반 런타임 self-heal(`cp_account_history_ensure_schema()`,
  MariaDB 5.5 는 `ADD COLUMN IF NOT EXISTS` 미지원이라 필요)을 한 차례 추가했었다. 이어서 사용자가
  ① Usage(세션 데이터량)는 별도로 기록하지 말고 **기존 표준 FreeRADIUS SQL accounting 테이블
  `radius.radacct`**를 직접 조회하라고 지시했고, ② 최종적으로 **"ALTER TABLE 자체를 아예 일으키고
  싶지 않다"**는 지시에 따라 **Login/Logout 도 새 컬럼 없이 원본 5컬럼 스키마(#49)만으로 기록**
  (IP/MAC/사유를 `change_description` 텍스트에 포함, 다른 change_type 이벤트와 동일한 방식)하도록
  최종 되돌림. **6컬럼 스키마 확장과 self-heal 함수는 전부 제거되고 현재 남아있지 않다** — 아래는
  최종 구현만 설명.
- **최종 구현 — Login/Change 탭 (`radacct_changehistory`, #49 원본 5컬럼 그대로)**:
  - 스키마 변경 없음(`cp_account_history_create_table_sql()` 은 #49 그대로: id/timestamp/
    username/change_type/change_description). `change_type` 에 `login`/`logout` 두 값 추가.
  - login/logout 이벤트도 기존 배치용 `cp_account_history_record($change_type, $description)`
    를 **그대로 재사용**(전용 함수를 따로 두지 않음) — description 을 다른 이벤트들과 동일하게
    `"user={username} ..."` 로 시작시켜 기존 `cp_account_history_extract_username()` 파싱에
    그대로 태움. IP/MAC/사유는 컬럼이 아니라 이 텍스트 안에 포함:
    `"user={username} logged in from ip={ip} mac={mac} via {authmethod}{tag}"` /
    `"user={username} logged out from ip={ip} mac={mac} reason={label}{tag}"`.
  - `captiveportal.inc` 신규 `cp_term_cause_label($code)` — RADIUS term_cause 숫자→사람이 읽는
    사유(1=LOGOUT/4=IDLE TIMEOUT/5=SESSION TIMEOUT/6=ADMIN RESET/10=QUOTA EXHAUSTED/
    13=CONCURRENT LOGIN/17=REAUTH FAILED). 코드베이스가 이미 몇몇 호출부(`captiveportal_
    disconnect_client($sid, "Usage-Exceed")`/`"No-Gateway"`)에서 **문자열을 term_cause 슬롯에
    그대로 넘기는 기존 관행**이 있어, 숫자가 아니면 그 문자열을 그대로 사유로 채택.
  - 신규 `cp_log_account_login($username, $ip, $mac, $sessionid, $authmethod)` /
    `cp_log_account_logout($dbent, $term_cause, $stop_time, $reason=null)` — 둘 다 게스트
    passthrough(`unauthenticated`)는 계정이 없어 스킵, lazy-require+`function_exists` 가드로
    버전섞임 안전. **`getVolume()` 호출 없음**(세션 바이트는 Usage 탭에서 radacct 로만 다룸 —
    이벤트 로그는 로그인/로그아웃 "발생 여부·사유"만 남긴다).
  - 호출 지점 4곳: ① `portal_allow()`의 **"진짜 신규 세션 생성" 지점**(동시로그인 재사용 경로는
    `if (!isset($sessionid))` 밖이라 자동 제외)에 로그인 기록. ② `captiveportal_disconnect()` —
    **모든 개별 세션 종료의 단일 관문**(명시적 로그아웃/idle·session timeout/quota exceeded/
    no-gateway/동시로그인 킥/데이터리셋 등 전부 경유)이라 여기 한 곳으로 대부분 커버. ③
    `captiveportal_radius_stop_all()`(대량 `captiveportal_disconnect_all()` 전용 — 개별
    disconnect()를 안 거치는 별도 경로) foreach 안에도 동일 훅 추가. ④ `captiveportal_prune_old()`
    의 quota-exceeded 호출부만 이미 만들어진 상세 문자열 `$logout_cause`(예: "QUOTA EXHAUSTED:
    used=120MB max=100MB")를 `$reason` 인자로 전달해 더 정확한 사유 보존 — 이 한 곳 외 다른
    `captiveportal_disconnect()` 호출부는 무수정(새 인자는 선택적 5번째 파라미터라 순수 추가).
  - **의도적으로 손대지 않음**: `captiveportal_disconnect_client()`의 죽은 파라미터
    `$logoutReason`은 그대로 방치 — 살리려면 term_cause 슬롯 문자열 관행과 우선순위 충돌 위험이
    있어 리스크 대비 이득이 작음. 결과적으로 `captiveportal_reset_user_usage()`(#14, 데이터리셋)
    로그아웃은 "DATA RESET" 대신 term_cause=6 매핑값인 **"ADMIN RESET"**으로 다소 뭉뚱그려 표시됨
    (기능상 문제 없음, 후속 개선 가능).
  - `cp_account_history_fetch($username,$from,$to,$limit,$tab)` — `$tab='change'`(기본,
    login/logout 제외) | `'login'`(login+logout). SELECT 는 원본 4컬럼(id/timestamp/change_type/
    change_description) 그대로.
- **최종 구현 — Usage 탭 (`radius.radacct`, 표준 FreeRADIUS SQL accounting 테이블 직접 조회)**:
  - 신규 `cp_account_history_fetch_usage($username,$from,$to,$limit)` (`cp_account_history.inc`)
    — `radacct` 를 `LOWER(username)` 매칭(대소문자 무시, #6/#17 관례) + **`acctstarttime` 기준
    기간 필터**(사용자 지시) + `acctstoptime IS NOT NULL`(완료된 세션만) 로 조회.
    `acctstarttime`/`acctstoptime`/`acctsessiontime`/`acctinputoctets`/`acctoutputoctets`/
    `framedipaddress`/`callingstationid` — `usr/local/etc/raddb/mods-config/sql/main/mysql/
    queries.conf` 의 표준 스키마와 동일. 응답 필드명은 모달 JS Usage 탭 렌더 규약(`client_ip`=
    framedipaddress, `client_mac`=callingstationid, `session_duration`=acctsessiontime,
    `input_octets`=acctinputoctets, `output_octets`=acctoutputoctets, `id`=radacctid,
    `timestamp`=acctstoptime)에 맞춰 매핑 — **radacct_changehistory 스키마와는 완전히 무관**
    (이 테이블은 손대지 않음, ALTER TABLE 불필요).
  - **주의(선상 전제)**: `radacct` 조회가 유효하려면 **해당 박스에서 FreeRADIUS SQL accounting 이
    활성화되어 실제로 `radacct` 테이블에 세션이 쌓이고 있어야** 함(`varsqlconfenableaccounting ==
    'Enable'`, #23 분석 참고). 비활성 박스에서는 Usage 탭이 "완료된 세션 없음"으로 빈 결과 표시
    (fatal 없음, graceful degrade).
- **엔드포인트(`crew_account_history_data.php`)**: POST `tab`(change/login/usage, 기본 change).
  change/login 은 `cp_account_history_fetch()`, usage 만 `cp_account_history_fetch_usage()`로 분기.
- **모달 UI(`manage_crew_wifi_account.inc` `render_account_history_modal()`)**: 탭바
  (Change/Login/Usage) 추가, range pill 은 공유(탭 전환 시 `lastRangeParams` 재사용, range 변경
  시 `curTab` 유지). 탭별 컬럼: **Change=Time/Type chip/Description · Login=Time/Event chip
  (LOGIN 녹색·LOGOUT 파랑)/Description(IP/MAC/사유는 텍스트에 포함, 별도 컬럼 없음) · Usage=
  Time/Duration/Data In/Data Out/Total/IP**(위젯 내 self-contained `formatBytes()`/
  `formatDuration()`, 외부 의존 없음). Export CSV·빈 결과 메시지·하단 안내문 전부 탭별 분기.
  기존 10개 클라이언트 페이지네이션 패턴 그대로 재사용. 모달 너비 860px→980px.
- **검증**: php -l 4파일 전부 통과. DB 레이어(`cp_account_history_record`/`fetch`/DDL 원본 5컬럼
  확인·`fetch_usage` radacct 타깃/컬럼매핑/NULL처리) PHP 스텁 하네스 30/30 + 21/21. captiveportal.inc
  헬퍼(term_cause 매핑·reason 우선순위 3단계·게스트 스킵·**getVolume 미호출 확인**) 스텁 하네스
  24/24. 모달 JS(탭 전환 시 기간 유지·Login 탭에 IP/MAC 별도 컬럼 없음 확인·Usage 탭 구조화 컬럼
  렌더·CSV 헤더 3종·빈결과/실패 메시지) DOM/XHR 스텁 하네스 24/24.
- **배포 정합성**: `cp_account_history.inc` + `captiveportal.inc` + `crew_account_history_data.php`
  + `manage_crew_wifi_account.inc` **4파일 일괄**(가드 있어 fatal 없음 — 구버전 섞여도 로그인/
  로그아웃 기록만 skip, 기존 Change 탭은 영향 없음).
- **알려진 범위 제한(수용)**: Usage 탭은 완료된 세션만(사용자 확인, 진행 중 세션 실시간 사용량
  미포함) / 데이터리셋 로그아웃 사유가 "ADMIN RESET"으로 다소 뭉뚱그려짐(위 참고) / crew_account.php
  전용(#50 과 동일하게 prepaid_account.php 는 History 버튼 자체가 없어 미포함) / `radacct` 조회는
  해당 박스의 SQL accounting 활성 여부에 종속(위 참고).
- **Usage 탭 기간 총합 표시(후속 추가)**: range pill 아래에 조회된 **전체 결과(lastRows, 현재
  페이지 10개가 아니라 필터링된 전체)** 기준 합계 배너 추가 — "Total for this period: **98.0 GB**
  (44.2 GB in / 53.8 GB out) across 627 sessions". Change/Login 탭이나 결과 없음(빈 배열)일 땐
  숨김. 탭 전환·range 변경 시 `load()` 시작 지점에서 즉시 숨겼다가(로딩 중 이전 총합 flash 방지)
  응답 도착 후 `render()`에서 재계산 — `noteEl` 갱신과 동일한 즉시성 패턴. 외부 라이브러리 없이
  기존 `formatBytes()` 재사용. DOM 하네스 9종(전체 합산·in/out 분리·session count·단복수 문구·
  탭/range 전환 시 즉시 숨김·빈결과 숨김) 포함 33/33 통과.

### 55. crew_account.php — "Export Credentials CSV" 버튼 (ID/할당량/비밀번호 CSV) (develop 미커밋)
- **요구**: crew_account.php 상단에 현재 CREW 의 ID/할당량/비밀번호를 CSV 로 뽑는 버튼 추가.
- **구현**: 기존 "Export CSV"(`export_wifi_csv()`, ID/Description/Type/Update/Used/Quota/Online)
  버튼 바로 옆에 **"Export Credentials CSV"** 버튼 신설. 신규 `export_wifi_credentials_csv($isPrepaid)`
  (`manage_crew_wifi_account.inc`) — `build_wifi_rows()` 재사용해 **ID, Quota(MB), Password 3컬럼만**
  CSV 로 출력(파일명 `{vessel}_credentials_{시각}.csv`, 기존 export 와 접미사로 구분해 오인 방지).
  `build_wifi_rows()` 행에 `'Password' => $user['varuserspassword']` 1줄 추가(기존 draw_wifi_contents
  는 명시적 키만 읽어 화면 테이블엔 영향 없음 확인).
- **비밀번호 노출 근거**: crew wifi 계정 비밀번호는 애초에 `config.xml`(`varuserspassword`,
  Cleartext-Password)에 평문 저장돼 있어(#10/#23 배경) 이 버튼이 새로운 노출면을 만드는 게
  아니라 이미 서버에 있는 평문값을 관리자 편의상 다운로드하게 해주는 것 뿐.
- **접근 제한**: 버튼은 admin/vesseladmin 역할에서만 노출(customer 는 없음, #53 과 동일 원칙).
  **일반 Export CSV 와 달리 GET 핸들러 자체에도 역할 체크 추가**(`$adminlogin==='admin'||
  'vesseladmin'`) — 일반 CSV 는 버튼 숨김과 무관하게 `?export=csv` 직접 접근이 항상 가능한
  기존(무해한) 특성이 있는데, 이건 평문 비밀번호가 포함되므로 URL 직접 접근으로 customer 가
  우회하지 못하도록 명시적으로 막음.
- **검증**: php -l 2파일 통과. CSV 출력 하네스(`build_wifi_rows` 스텁 대체) 6/6 — BOM/헤더 정확히
  3컬럼/따옴표 포함 비밀번호 CSV escape/Description·Used 등 다른 필드 미포함 확인.
- **배포 정합성**: `crew_account.php` + `manage_crew_wifi_account.inc` 2파일 일괄.
- **후속 수정 — 버튼 겹침 + 아이콘 구분(develop 미커밋)**: 버튼이 6개→7개로 늘면서 좁은 화면
  (`.list-top .btn-area` 가 `position:fixed` 로 전환되는 `@media (max-width:1440px)` 구간,
  `components.css`/`common.css` 공용 규칙)에서 버튼이 찌그러들며 텍스트가 옆 버튼과 겹치는 현상
  발생(스크린샷으로 확인). **crew_account.php 전용 인라인 `<style>`** (공유 CSS 파일은 다른 페이지도
  쓰므로 미수정)에 `.list-top .btn-area{overflow-x:auto}` + `.btn-area .btn{flex:0 0 auto;
  white-space:nowrap}` 추가 — 버튼이 축소/줄바꿈되지 않고 필요 시 가로 스크롤로 대체(겹침 원천 차단).
  또한 Export CSV/Export Credentials CSV 아이콘이 Reset PW 등과 동일한 `ic-reset` 이라 전부 같아
  보이던 것을, 신규 `.ic-doc`(서류 모양, 인라인 SVG data URI — 새 PNG 애셋 불필요) 로 교체해 두
  CSV 버튼만 시각적으로 구분(Reset PW/SET RANDOM PW/Reset Data 는 기존 `ic-reset` 유지, 요청
  범위 밖). SVG 2개(gray/disabled) DOMDocument 로 유효 XML 확인.
  **선상 확인 완료(사용자 스크린샷)**: 버튼 겹침 해소 + 서류 아이콘 정상 표시.
- **후속 수정 2 — 검색창 밀림 → 2줄 분리 → 드롭다운 통합으로 최종 정리(develop 미커밋)**: 버튼
  겹침 수정(`flex:0 0 auto` 로 버튼 shrink 금지) 부작용으로, 같은 줄을 공유하던 검색창이 늘어난
  버튼 그룹에 밀려 폭 0에 가깝게 찌그러짐(스크린샷 확인). **1차**: 검색창/버튼 툴바를 별도 줄로
  분리(`.list-top`을 `flex-direction:column`)했으나, 사용자가 "원래는 한 줄이었다"며 한 줄 유지를
  요청 → **최종**: 관련 액션을 드롭다운으로 묶어 **버튼 개수 자체를 7개→5개로 축소**하고 다시
  한 줄 레이아웃(`.list-top` row, `search-area flex:0 0 auto` 로 검색창 폭 고정 + `btn-area
  flex:1 1 auto; min-width:0` 로 남은 공간을 버튼 그룹이 차지, 필요 시 위 겹침수정의 가로 스크롤이
  안전망)으로 복귀.
  - **Export 드롭다운**: 트리거 버튼 "Export ▾"(서류 아이콘) 아래 Export CSV / Export Credentials
    CSV 2개 메뉴.
  - **Manage PW 드롭다운**: 트리거 버튼 "Manage PW ▾"(reset 아이콘) 아래 **Reset Random PW**(기존
    SET RANDOM PW, `confirm_setRandomPw()`) / **Reset Initial PW**(기존 Reset PW="1111"로 리셋,
    `confirm_resetPw()`) 2개 메뉴로 재명명.
  - Reset Data / Check PW / Delete 는 기존대로 단독 버튼 유지(병합 대상 아님).
  - 구현: 프레임워크 의존 없는 **순수 CSS/JS 드롭다운**(`.btn-dd`/`.btn-dd-menu`, `toggleBtnDd()` +
    바깥 클릭 시 전체 닫힘 `document` 클릭 리스너) — pfSense 스톡 UI(Bootstrap `dropdown-toggle`)
    와 무관한 이 페이지 전용 미니멀 버튼 프레임워크에 맞춤.
  - **주의**: 이 세션엔 브라우저 렌더링 확인이 불가(로컬 프리뷰 서버 없음, pfSense 박스 필요) —
    배포 후 한 줄 배치·드롭다운 열림/닫힘·각 메뉴 항목 클릭 시 기존 동작(AJAX/네비게이션) 정상
    확인 필요.
  - **버그 발견·수정(선상 확인)**: 드롭다운 메뉴 클릭해도 안 뜨는 문제 발생 — 원인은 이전 겹침
    수정(`49e5607`)에서 `.list-top .btn-area` 에 넣은 `overflow-x:auto; overflow-y:hidden;` 이
    범인. `.btn-dd-menu` 가 `position:absolute; top:100%` 로 버튼 아래로 펼쳐지는데, 조상인
    `.btn-area` 의 `overflow-y:hidden` 이 이를 잘라서 안 보이게 만듦(CSS 스펙상 `overflow-x` 를
    `visible` 아닌 값으로 두면 `overflow-y` 도 자동으로 `visible` 을 벗어나 `auto`/`hidden` 취급되는
    것도 한몫). **수정**: 그 규칙 제거(`.list-top .btn-area .btn{flex:0 0 auto;white-space:nowrap;}`
    만 유지) — 드롭다운 통합으로 버튼이 5슬롯으로 줄어 가로 스크롤 안전망 자체가 더 이상 필요 없음.

### 56. terminal.php — 안테나별 Cutoff 체크박스 + Allowance 입력 (system_gateways_edit.php 와 동일 효과) (develop 반영)
- **요구**: `system_gateways_edit.php`(게이트웨이 편집)의 "Monthly Data Allowance"/"Cutoff enable
  when allowance exceeded" 두 필드를 `terminal.php`(Terminal Status) 에서도 안테나(게이트웨이)마다
  설정할 수 있게 해달라는 요청. 이 두 필드는 `network_usage_timeperiod_check.php` 크론이 주기적으로
  읽어 월 사용량이 allowance 를 넘고 cutoff_enable 이 켜져 있으면 해당 게이트웨이를
  `cp_shutdown_gateways` 에 등재해 전면 차단한다(#19/#20 배경) — Gateways 편집 화면 접근 없이도
  terminal.php 에서 같은 효과를 낼 수 있어야 함.
- **수정 (`etc/inc/terminal_status.inc`)**: 신규 `cp_apply_gateway_cutoff_settings($allowance_map,
  $cutoff_map)` — 게이트웨이 이름을 키로 하는 allowance/cutoff_enable 맵을 받아 해당 게이트웨이
  항목에만 병합 갱신. `save_gateway()` 는 폼 전체($_POST) 기준으로 게이트웨이 배열을 통째로
  재구성하므로 allowance/cutoff_enable 두 필드만 있는 이 폼을 그대로 넘기면 interface/monitor 등
  나머지 필드가 날아간다 — 대신 `lock('freeradius_user_config')` + `parse_config(true)` 로 최신본을
  다시 읽어 델타만 재적용하는 기존 lost-update 패턴(#10/#22/#30/#47)을 그대로 재사용. 체크되지 않은
  체크박스는 HTML 폼 특성상 POST 자체에서 빠지므로, `$cutoff_map` 에 키가 없으면 off 로 취급.
- **수정 (`usr/local/www/terminal.php`)**: **UI 반복 4단계** — ① "Terminal Setting" 팝업 안에 넣음 →
  ② 사용자 피드백("이 변경 창에 넣지 말고 밖에서 바로 세팅되게")에 따라 팝업 밖, 상태 테이블 아래
  별도 테이블로 분리 → ③ 사용자 요청("한 테이블로 합쳐서 구현 가능?")에 따라 기존 안테나 상태
  테이블(Name/Info/GW/Net/Ext-Net)에 Monthly Allowance (GB)/Cutoff 두 컬럼을 별도 컬럼으로 추가해
  한 테이블로 병합(+컬럼 폭 축소, Cutoff 라벨 "Cutoff when exceeded"→"Enabled" 로 줄바꿈 수정) →
  ④ 사용자 지적("Info 의 usage/allowanceGB 와 Monthly Allowance 컬럼 내용이 중복")에 따라 **Monthly
  Allowance 컬럼을 아예 없애고 Info 컬럼 안에 "usage / [입력창] GB" 형태로 합침**(최종, 6컬럼:
  Name/Info/GW/Net/Ext-Net/Cutoff). Info 컬럼 폭을 30% 로 확장해 인라인 입력이 들어갈 공간 확보 +
  이 페이지 전용 `<style>` 로 `input[name^="allowance"]` 를 전역 `input[type=text]{width:100%}`
  대신 소형 인라인 박스(80px)로 오버라이드(전역 style.css 무수정).
  - VPN 이 아닌 게이트웨이마다 한 행에 상태 5컬럼(Name 포함) + Info 안의 Allowance 입력 + Cutoff
    체크박스(`.check.v1`)가 모두 존재, 테이블 하단 APPLY 버튼(`#cutoff_form`, `/terminal.php` 로
    POST). Setting 버튼/팝업 없이 페이지 진입 즉시 보이고 바로 편집 가능.
  - **핵심 문제(10초 AJAX 폴링과 입력 중인 값의 충돌)**: 기존 `#all_terminal_status` tbody 는 10초
    마다 서버가 만든 HTML 문자열로 **통째로 교체**됐다. Allowance/Cutoff 를 같은 tbody 행에 합치면
    관리자가 값을 입력하거나 체크하는 도중 폴링이 오면 그 입력이 사라진다.
  - **해결 = "상태만 부분 갱신"으로 폴링 방식 자체를 재설계**: 서버(`data_update` POST 핸들러)가
    더 이상 HTML 문자열을 반환하지 않고, 게이트웨이 이름을 키로 하는 **구조화 JSON**
    (`{rows: {GWNAME: {row_on, usage_text, gw_html, net_class, net_text, extnet_class, extnet_text}}}`)
    을 반환. 클라이언트(`refreshValue()`)는 `tr[data-gw='GWNAME']` 로 행을 찾아 `.cell-info-usage`
    (Info 안의 usage 숫자만, `input` 은 별개 형제 요소)/`.cell-gw`/`.cell-net p`/`.cell-extnet p`
    만 `.text()`/`.html()`/`.attr('class')` 로 갱신하고 **Name/Allowance 입력/Cutoff 체크박스는
    아예 건드리지 않는다** — usage 숫자를 Info 안으로 합친 뒤에도 입력 중 포커스·값·체크 상태가
    폴링에 영향받지 않도록 usage 표시(`<span class="cell-info-usage">`)와 입력(`<input>`)을 같은
    `<td>` 안에서 별도 요소로 분리해 둔 것이 핵심. PHP 측도 초기 렌더와 AJAX 응답이 **같은
    `$rowData` 배열**에서 나오므로 최초 로드와 이후 갱신이 항상 일관.
  - 폼 제출 시 `$_POST['allowance']` 존재 여부로 게이트웨이 편집(#22 lost-update 회피)과 Manual
    Override 팝업의 라우팅 라디오버튼 변경(`set_routing()`)을 독립적으로 처리 — 한쪽만 제출해도
    다른 쪽에 영향 없음. Allowance 는 system_gateways_edit.php 와 동일하게 공란(=무제한)/숫자만 허용.
  - **usage 표시 조건 수정(사용자 지적으로 후속 변경)**: 최초 병합 시엔 옛 Info 로직(allowance 공란/
    "0" 이거나 terminal_type 이 `vsat_sec` 면 `get_datausage_from_db()` 자체를 호출하지 않고 완전히
    숨김)을 그대로 보존했으나, 사용자가 스크린샷(FX_CREW=vsat_sec 행에 usage 숫자가 안 보임)으로
    지적 → 코드 대조 결과 **`usr/local/www/index.php`(Main Panel)의 동일 로직은 애초에 usage 를
    숨기지 않음**(allowance 없으면 "/allowance" 접미사만 생략하고 usage 숫자는 항상 표시)이 드러나,
    terminal.php 쪽이 Main Panel과 어긋난 구현이었던 것으로 판단 — **usage 는 allowance 설정 여부·
    terminal_type(vsat_sec 포함)과 무관하게 항상 표시**하도록 수정(Main Panel과 정책 통일).
    `get_datausage_from_db()` 실패(InfluxDB 타임아웃 등) 시 `false` 반환 → `strval()` 로 빈 문자열
    표시(fatal 없음, 기존과 동일한 degrade).
  - **게이트웨이 이름을 jQuery 선택자 문자열에 그대로 이어붙임(`tr[data-gw='" + gwname + "']`)**:
    pfSense 게이트웨이명은 `is_validaliasname()` 로 영문/숫자/언더스코어만 허용되어 따옴표 등
    선택자를 깨는 문자가 들어올 수 없음을 전제로 함(다른 경로로 이례적 이름이 생겼다면 위험 —
    현재 코드베이스 다른 곳(라디오버튼 id 등)도 동일 전제로 이스케이프 없이 써 왔음).
- **F5 재제출 경고 제거(Post/Redirect/Get)**: APPLY 로 폼을 제출하면 마지막 브라우저 히스토리
  항목이 POST 응답이 되어, 그 상태에서 F5 를 누르면 "양식 다시 제출 확인" 경고가 뜬다(사용자 스크린샷
  으로 재현 확인). 수정: `routing_radiobutton`/`allowance` 처리 후 302 없이 `<script>
  location.replace("processing.php?to=terminal.php");</script>` 를 출력하고 `exit`
  — **`processing.php`(기존 파일, `network_control.php` 가 이미 쓰는 관례)** 를 그대로 재사용한
  스플래시 경유 리다이렉트. `location.replace` 는 현재 히스토리 항목을 **대체**(추가 아님)하므로
  POST 응답 자체가 히스토리에서 사라지고, processing.php 도 자신의 `location.replace` 로 다시
  대체 → 최종적으로 히스토리 맨 위는 GET terminal.php 뿐이라 F5 시 경고가 뜨지 않는다. `data_update`
  (10초 AJAX 폴링)는 별도 POST 라 이 분기와 무관(정상 동작 유지).
- **적용 효과**: 저장 즉시 `cp_shutdown_gateways` 를 건드리지 않음(system_gateways_edit.php 저장과
  동일) — 다음 `network_usage_timeperiod_check.php` 크론 주기에 새 allowance/cutoff_enable 값을
  읽어 자동 반영. 별도 filter_configure/재시작 불필요.
- **검증**: php -l 통과(terminal.php, terminal_status.inc). 브라우저 실측 불가(로컬 프리뷰 서버
  없음) — 선상 확인 필요.
- **배포 정합성**: `terminal.php` + `terminal_status.inc` **같은 리비전 일괄 배포**(가드 없음 —
  terminal_status.inc 미배포 시 `cp_apply_gateway_cutoff_settings` undefined fatal).

### 57. terminal.php 변경 이력 → MariaDB `radius.terminal_status_history` 기록 + HISTORY 버튼 (develop 반영)
- **요구**: Terminal Status(#56, Manual Override 라우팅 변경 + Data Cutoff allowance/cutoff 변경)
  에 대한 변경사항을 **이전에 DB 에 저장하던 형식**(#48 GMT 이력, #49 계정 이력과 동일한 mariadb://
  192.168.209.210:3306, radius/radius)으로 기록. 테이블은 `id`(auto increment) / `timestamp` /
  변경 당시의 ID / IP / 변경내역을 모두 기록. APPLY 옆에 HISTORY 버튼을 추가해 조회 가능하게.
- **신규 `etc/inc/cp_terminal_history.inc`**: 테이블 `radius.terminal_status_history`
  (`id` INT AUTO_INCREMENT PK / `timestamp` DATETIME / `admin_id` VARCHAR(64) / `client_ip`
  VARCHAR(45) / `description` VARCHAR(1024)) — 없으면 자동 생성(#48/#49 와 동일 멱등 패턴).
  **실행부는 `cp_gmt_history.inc`(#48)의 mysql CLI 헬퍼(`cp_gmt_history_exec_sql`/`_sql_str`)를
  재사용**(#49 가 이미 쓰는 관례) — 동일 DB/자격증명, defaults-extra-file(비번 argv 미노출) +
  connect-timeout + timeout 바운드, self-contained. `cp_terminal_history_record($description)` /
  `cp_terminal_history_fetch($from,$to,$limit)`. 실패해도 throw 없이 false + log_error(터미널
  설정 저장 흐름 불가침).
  - **"변경 당시의 ID"**: `$adminlogin`(common_ui.inc) 은 role 카테고리(admin/customer/
    vesseladmin)일 뿐 "누구"인지 특정 못 함 — 대신 pfSense 세션의 실제 webConfigurator 계정명
    `get_config_user()` 를 사용(세션 없으면 `$_SESSION['Username']` → 빈 문자열 폴백).
  - **IP**: `$_SERVER['REMOTE_ADDR']`.
- **호출처(`usr/local/www/terminal.php`)**: POST 처리 블록에서 실제로 뭔가 바뀐 경우에만 1행 기록.
  - Manual Override: `set_routing()` 호출 시 "Manual Override: routing set to {게이트웨이명}
    (duration={분}m)" 또는 "...set to Automatic".
  - Data Cutoff: `cp_apply_gateway_cutoff_settings()` 에 새 3번째 참조 인자 `&$changeLog` 추가
    (`etc/inc/terminal_status.inc`) — 실제로 값이 바뀐 게이트웨이만 "GWNAME: allowance
    (unset)->100GB; cutoff off->on" 형태로 채워 넣음(변경 없는 게이트웨이는 로그에 안 남아
    노이즈 없음). 두 소스의 설명을 합쳐 `cp_terminal_history_record()` 1회 호출.
  - PRG 리다이렉트(#56 F5 경고 수정) 직전에 기록 — `exit` 이후에는 실행되지 않으므로 반드시
    `echo '<script>location.replace(...)'` 보다 먼저 호출.
- **HISTORY 버튼 + 모달**: APPLY 버튼 옆에 `HISTORY`(다크 버튼) 추가. 모달은 GMT 변경 이력 모달
  (common_ui.inc, `gmthist-*`)과 동일 계열 스타일(다크 카드 + pill 버튼 + 10개 클라이언트
  페이지네이션 + Export CSV)이나 **`termhist-*` 네임스페이스로 완전 격리**(같은 페이지에
  `gmthist-*` 가 없어 충돌 소지는 없지만 관례상 접두사 분리). 컬럼 = Time (UTC) / ID / IP /
  Description(4컬럼, GMT 이력의 GPS 컬럼 대신 ID+IP). 범위 1d(기본)/7d/30d/Custom. CSRF 토큰은
  이 페이지의 `#cutoff_form` 에 자동 주입된 `__csrf_magic` hidden 을 재사용(별도 hidden form
  불필요, GMT 모달의 `#gmtForm` 재사용과 동일 관례).
- **신규 엔드포인트 `usr/local/www/terminal_history_data.php`**: `gmt_history_data.php` 와 동일
  구조(guiconfig.inc 인증 경유 JSON, `mode=days&days=N` 또는 `mode=custom&from/to`).
- **검증**: php -l 4파일 전부 통과. `cp_terminal_history.inc` 스텁 하네스(실제 mysql 없이
  `cp_gmt_history_exec_sql`/`_sql_str` 스텁으로 SQL 캡처) 21/21 — CREATE TABLE/INSERT 구조,
  1024자 클램프, 개행/탭 제거, 따옴표 escape, 빈 설명 시 미전송, fetch 파싱·잘못된 날짜 거부·
  limit 5000 클램프. `cp_apply_gateway_cutoff_settings()` changeLog 스텁 하네스(가짜 config +
  lock/write_config 스텁) 12/12 — 실제 변경분만 로그, no-op 시 write_config 미호출, cutoff
  off 케이스, changeLog 인자 생략 시 무해. node --check 로 3개 `<script>` 블록(PRG 리다이렉트/
  refreshValue/HISTORY 모달) 문법 검증 통과.
- **배포 정합성**: `cp_terminal_history.inc` + `terminal_status.inc` + `terminal.php` +
  `terminal_history_data.php` **4파일 일괄**(가드 있어 fatal 없음 — cp_terminal_history.inc
  미배포 시 기록만 skip, terminal_history_data.php 없으면 HISTORY 모달이 "unavailable" 표시).

## 다음 작업 대기 중

- [ ] **#57 커밋 완료(develop)**: terminal.php 변경 이력 → MariaDB `radius.terminal_status_history`
  기록(신규 `cp_terminal_history.inc`, #48 실행부 재사용) + APPLY 옆 HISTORY 버튼/모달 +
  `terminal_history_data.php` 조회 엔드포인트. (main/prod 미반영)
- [ ] #57 검증(선상): Manual Override 라우팅 변경 → HISTORY 모달에 "Manual Override: routing set
  to {게이트웨이} (duration=Nm)" 행 생성(ID=로그인 관리자 계정명, IP=요청자 IP) / Data Cutoff
  Allowance·Cutoff 저장 → 실제로 바뀐 게이트웨이만 "GWNAME: allowance ...; cutoff ..." 행 생성
  (값이 그대로면 기록 안 됨, no-op 확인) / `SELECT * FROM radius.terminal_status_history ORDER BY
  id DESC LIMIT 20;` 로 DB 직접 확인 / HISTORY 버튼 클릭 → 1d/7d/30d/Custom 조회·Export CSV·
  10개 페이지네이션 정상(GMT 이력 모달과 동일 UX) / DB 불통 시에도 Terminal Status 저장 자체는
  정상 동작(fatal 없음) + `clog /var/log/system.log | grep "TERMINAL HISTORY"` / **배포 정합성:
  cp_terminal_history.inc + terminal_status.inc + terminal.php + terminal_history_data.php
  4파일 일괄**.
- [ ] **#56 커밋 완료(develop)**: terminal.php Cutoff 체크박스 + Allowance 입력 (system_gateways_edit.php
  와 동일 효과) — **최종적으로 Monthly Allowance 를 별도 컬럼이 아니라 Info 컬럼 안에 "usage /
  [입력창] GB" 형태로 합침**(중복 표시 제거, 사용자 지적 반영), Cutoff 는 별도 컬럼 유지 —
  Name/Info/GW/Net/Ext-Net/Cutoff **6컬럼 한 테이블**(팝업 아님, Setting 버튼 클릭 불필요). 10초
  AJAX 폴링을 tbody 전체 교체 방식에서 게이트웨이별 상태 셀만 부분 갱신하는 구조화 JSON 방식으로
  재설계(입력 중인 Allowance/Cutoff 값이 폴링에 의해 리셋되지 않도록 — Info 안에서도 usage 표시와
  입력을 별개 요소로 분리). 패치노트 기록 완료(`2026-07-08 Update`). (main/prod 미반영)
- [ ] #56 검증(선상): Terminal Status 페이지 진입 시 **한 테이블**에 Name/Info/GW/Net/Ext-Net/Cutoff
  6컬럼이 모두 보이고, Info 컬럼에 "usage / [입력창] GB" 형태로 표시·입력·APPLY 저장 정상 /
  system_gateways_edit.php 에서 수정 후 terminal.php 재방문 시 최신값 표시(반대 방향도 동일) / 저장
  후 다음 `network_usage_timeperiod_check.php` 크론 주기에 새 값으로 shutdown 판정 반영 / Manual
  Override(Setting 팝업의 라우팅) 변경과 독립적으로 동작(한쪽만 제출해도 다른 쪽 영향 없음) /
  **핵심**: Allowance 입력창에 타이핑 중이거나 Cutoff 체크박스를 막 클릭한 상태에서 10초 자동갱신
  타이밍이 겹쳐도 값이 리셋되지 않는지(Info 안의 usage 숫자·GW/Net/Ext-Net 상태만 갱신되고 나머지는
  그대로인지) / **allowance 가 공란이거나 terminal_type 이 vsat_sec(예: FX_CREW)인 게이트웨이도
  usage 숫자가 정상 표시되는지**(Main Panel index.php 와 동일하게 항상 표시로 변경됨) / 이 페이지
  전용 인라인 `<style>`(allowance 입력 소형화)이 다른 페이지에 영향 없는지 /
  **APPLY 클릭 후 F5 를 눌러도 "양식 다시 제출 확인" 브라우저 경고가 뜨지 않는지**(processing.php
  스플래시가 잠깐 보인 후 terminal.php 로 돌아오는지) / Manual Override(라우팅) 를 Apply 한 뒤에도
  동일하게 F5 경고 없는지 / **배포 정합성: terminal.php + terminal_status.inc 2파일 일괄**
  (processing.php 는 기존 파일 재사용이라 추가 배포 불필요).
- [ ] **#55 커밋 대기(미커밋)**: "Export Credentials CSV" 버튼 — ID/Quota(MB)/Password 3컬럼 CSV.
  develop 커밋 필요(+ push 까지, [[feedback_push_required_for_deploy]]).
- [ ] #55 검증(선상): admin/vesseladmin 로 crew_account.php 접속 → **Export ▾ 드롭다운**(Export
  CSV / Export Credentials CSV 2메뉴) · **Manage PW ▾ 드롭다운**(Reset Random PW / Reset Initial
  PW 2메뉴) 정상 열림·닫힘(다른 드롭다운 열면 이전 것 자동 닫힘, 바깥 클릭 시 닫힘) / 각 메뉴 클릭
  시 기존 동작(CSV 다운로드, AJAX PW 변경) 그대로 수행되는지 / **Export CSV 다운로드에 ID/Description
  /Type/Update/Used/Quota/Online, Export Credentials CSV 에 ID/Quota(MB)/Password 만** 있는지 /
  customer 로그인 시 두 드롭다운 모두 안 보이는지(Reset PW 단독 버튼만 노출) + `crew_account.php
  ?export=creds` 직접 접근 시도해도 다운로드 안 되는지(역할 체크) / **검색창(왼쪽)+버튼 5슬롯
  (오른쪽)이 한 줄에 겹침·찌그러짐 없이 배치되는지** / Export 버튼들에 서류 아이콘(`ic-doc`)이
  Reset 계열과 구분되게 표시되는지 — 이번 세션엔 브라우저 실측이 불가해 코드 리뷰만 마친 상태,
  선상 확인 필수 / 배포 정합성: `crew_account.php` + `manage_crew_wifi_account.inc` 2파일 일괄.
- [x] **#54 커밋 완료(develop, origin 에 push 완료 — 최종 커밋이 스키마확장/self-heal 시행착오를
  되돌린 상태)**: Account History 모달 Change/Login/Usage 3탭. **`radacct_changehistory` 는
  #49 원본 5컬럼 그대로**(신규 컬럼·self-heal 전부 제거됨, ALTER TABLE 불필요) — login/logout 은
  IP/MAC/사유를 `change_description` 텍스트에 포함해 기존 `cp_account_history_record()` 재사용
  (portal_allow/captiveportal_disconnect/captiveportal_radius_stop_all 훅). **Usage 탭은
  radius.radacct(표준 FreeRADIUS SQL accounting) 직접 조회**(`cp_account_history_fetch_usage()`)
  — 별도 기록 없음. 패치노트 기록 완료(`2026-07-06 Update`). (main/prod 미반영)
- [ ] #54 검증(선상): 실제 로그인 → Login 탭에 즉시 행 생성(설명에 IP/MAC/via 포함) / 로그아웃
  (명시적·idle timeout·session timeout·quota exceeded·동시로그인 킥) → Login 탭에 LOGOUT 행 표시
  (설명에 reason 포함) / **Usage 탭이 radius.radacct 에서 완료된 세션(acctstoptime IS NOT NULL)을
  Duration/Data In/Data Out/Total 로 정상 표시하는지**(SQL accounting 이 꺼진 박스에선 빈 결과가
  정상) / 탭 전환 시 기간 유지·기간 전환 시 탭 유지 / Export CSV 3종(Change/Login/Usage 헤더 상이,
  Login 은 ip/mac/session_id 컬럼 없이 id/timestamp_utc/username/event/description 만) / 게스트
  (passthrough `unauthenticated`) 로그인/로그아웃은 기록 안 됨(계정 없음, 의도) / 대량 "Disconnect
  All" 이후 로그아웃 행도 생성되는지(관리자 저빈도 작업) / **이 박스에 `radacct_changehistory` 가
  이미 있어도(5컬럼 구버전이든 뭐든) ALTER 없이 그대로 정상 동작해야 함**(스키마 절대 안 건드림이
  이번 최종 설계의 핵심) / **배포 정합성: cp_account_history.inc + captiveportal.inc +
  crew_account_history_data.php + manage_crew_wifi_account.inc 4파일 일괄**(가드 있어 fatal 없음,
  버전 섞이면 로그인/로그아웃 기록만 조용히 skip) / **커밋만으로는 배포 안 됨 — 반드시 `git push
  origin develop` 까지 해야 Jenkins 등 배포 파이프라인이 원격 develop 을 가져감**(이번 세션에서
  push 누락으로 실제 재현됨, [[feedback_push_required_for_deploy]]).
- **#54 Usage 탭 수동 검증용 SQL (`cp_account_history_fetch_usage()` 와 동일 쿼리, 계정명만 교체)**:
  ```sql
  SELECT radacctid, acctstoptime, acctsessiontime, acctinputoctets, acctoutputoctets,
         framedipaddress, callingstationid
  FROM radacct
  WHERE LOWER(username) = 'landlineuser00001'
    AND acctstoptime IS NOT NULL
    AND acctstarttime >= '2026-06-06 00:00:00'
    AND acctstarttime <= '2026-07-06 23:59:59'
  ORDER BY acctstarttime DESC
  LIMIT 1000;
  ```
  결과가 비면 그 계정의 완료 세션이 그 기간에 없거나(정상일 수 있음), 해당 박스에서 FreeRADIUS
  SQL accounting 자체가 꺼져 있어 `radacct` 가 애초에 안 쌓이는 경우 — `SELECT COUNT(*) FROM
  radacct;` 로 테이블에 아무 데이터가 있는지부터 확인. 컬럼 매핑: `acctstoptime`→timestamp(표시
  시각) · `acctsessiontime`→Duration(초) · `acctinputoctets`/`acctoutputoctets`→Data In/Out ·
  `framedipaddress`/`callingstationid`→IP/MAC. 기간 필터는 `acctstarttime` 기준(표시는
  `acctstoptime`)이라 세션 시작이 기간 밖이면 종료가 기간 안이어도 안 잡힐 수 있음(의도된 동작).
- [x] **#51 커밋 완료(develop `a848caa`+`725e53c`)**: FBB 신호 표시 이름매핑 분리 + ACU state -1 →
  Comm. Error + FBB "6"→EMEA(24.9E) 매핑. 패치노트 기록 완료(`2026-07-03 Update`,
  **Beta 1.1.53-Beta · Stable: 1.1.4-Stable**). (main/prod 미반영)
- [ ] #51 검증(선상): FBB 정상 신호 시 나침반에 `FBB : {이름} (Signal : n)` 표시(이름 미매핑이어도
  신호 노출) / **"6" 보고 시 `FBB : 24.9E` + 니들 표시** / ACU 통신오류(state -1) 시 `VSAT : Comm. Error`
  (빨강) 표시 — searching/blocked 와 구분 / `server_module.inc` + `index.php` 2파일 일괄 배포 + Ctrl+F5.
- [x] **#50 커밋 완료(develop `9299f4f`)**: crew_account.php per-user History 버튼 + 계정별 변경 이력 모달
  + `radacct_changehistory.username` 컬럼 + 조회 엔드포인트. 패치노트 기록 완료(`2026-07-03 Update`
  NEW 불릿, 버전 미정). (main/prod 미반영 — 명시 지시 시 병합)
- [ ] #50 검증(선상): crew_account 각 행 History 버튼 → 모달에 그 계정 변경만 표시(1d/7d/30d/All/Custom) /
  prepaid_account 는 History 컬럼 없음·정렬 정상 / Export CSV / `radacct_changehistory` 를 username 컬럼
  포함 신 스키마로 새로 생성(`DESCRIBE radius.radacct_changehistory`).

- [x] **#49 커밋 완료(develop `3666f94`)**: crew 계정 변경 이력 기록 — 신규 `cp_account_history.inc` +
  writer 훅(공용함수 8 + 위젯 4 + API 3 + 포털 자가변경) + prepaid(CREWPAY) 태그. 패치노트 기록 완료
  (`2026-07-03 Update`, 버전 미정). (main/prod 미반영 — 명시 지시 시 병합)
- [ ] #49 검증(선상): crew_account 에서 PW리셋/랜덤PW/데이터리셋/수정/생성/삭제/설명/스케줄 →
  `SELECT * FROM radius.radacct_changehistory ORDER BY id DESC LIMIT 20;` 사용자별 1행 + actor 정확 /
  위젯·API(update/topup/단건·다건 create/delete/usersreset)·포털 자가 비번변경 경로 기록 /
  **어떤 행에도 실제 비밀번호 미노출**(랜덤 6자리·1111 검색) / **prepaid(crewpay-) 계정 변경 행에만
  `(CREWPAY)` 태그**(crew 계정 행엔 없음), 포털 자가 비번변경엔 태그 없음 / DB 불통 시 계정 변경
  자체는 정상 + `clog /var/log/system.log | grep "ACCT HISTORY"`.
- [x] **#48 커밋 완료(develop `c473a8f`+확장 `ebc29fa`)**: GMT 이력 기록(신규 `cp_gmt_history.inc` +
  writer 3곳 훅) + 이력 뷰어(사이드바 history 버튼 + 모달 + `gmt_history_data.php`) + description/gps
  컬럼 + Export CSV. (main/prod 미반영 — 명시 지시 시 병합)
- [x] **#48~#53 패치노트 기록 완료(release_note.md)**: **같은 버전 `2026-07-03 Update` 에 병합** — 확정
  서브라인 **`Beta 1.1.53-Beta · Stable: 1.1.4-Stable`**. 항목: GMT 변경 이력 뷰어/계정 변경 이력/
  per-user History(NEW, #48~#50, 이력 모달 10개 페이지네이션 포함) + FBB 신호 표시 수정(FIXED)/ACU
  Comm. Error(CHANGED, #51) + crew→This Firewall 접근 제한(CHANGED, #52) + 테마 토글 쿠키 영속화
  (FIXED, #41) + customer SET RANDOM PW 버튼 노출(CHANGED, #53). **이후 2026-07-03 작업은 별도
  버전 안 만들고 이 항목에 병합**(사용자 지시).
- [ ] #48 검증(선상): GMT 팝업으로 오프셋 변경 → `SELECT * FROM radius.gmt_history ORDER BY id DESC LIMIT 5;`
  에 행 추가(timefrom/timeto/**description IP·gps 좌표** 정확, GPS 미수신 시 gps='N/A') /
  크론 자동 갱신·API 푸시 경로도 기록 / 동일값 재저장은 미기록 /
  DB 불통 시 GMT 저장은 정상 + `clog /var/log/system.log | grep "GMT HISTORY"` 실패 로그 /
  선상 박스에 mysql CLI 존재 확인(`command -v mysql`) / **history 버튼** → 모달 1d/7d/30d/Custom 조회·
  사이드바 있는 9페이지 공통 동작·history 클릭 시 타임존 설정 팝업 안 뜸(버블 차단) / Ctrl+F5 캐시.

- [x] **#41 커밋 완료(develop)**: 다크모드 — System(OS)/GPS(일출일몰 civil twilight)/Light/Dark 4-state,
  9페이지 공통(print_css_n_head), dark.css, 오프라인 일출일몰(cp_daynight.inc+크론), 박스 UTC 시각 판정,
  외부 day/night API 삭제 — develop `ab95701`·`81c9423`·`e089710`. (main/prod 미반영)
- [ ] #41 검증(선상): 토글 4단계 동작 / GPS 모드 낮=Light·밤=Dark(박스 UTC 기준) / System 모드 OS 추종 /
  `window.CP_SUN` 콘솔 확인(ok:true·now/begin/end) / `$config['daytimecheck']` 채워짐 +
  `clog /var/log/system.log | grep "DAYNIGHT AUTO"` / Ctrl+F5 캐시.
- [ ] #41 배포 정합성: `common_ui.inc`+`css/dark.css`+`cp_daynight.inc`+`cp_daynight_update.php`+
  `firewall_cronlist`(API로 cron 등록) 일괄. 페이지별 인라인 `<style>`(release_note 카드 등) 다크 폴리시 후속.
- [x] **#42 커밋 완료(develop)**: Daily usage 막대그래프(This month 기본/7/14/30d, MB meter) — develop `ab95701`.
- [ ] #42 검증(선상): 게이트웨이별 일별 막대 표시 / **일별 합이 월 타일 값과 대략 일치**(traffic rx/tx 델타 전제) /
  `index.php`+`terminal_status.inc` 일괄 배포.
- [x] **#43 커밋 완료(develop)**: GMT 타임존 테마 팝업 + 30분(0.5) 단위 + cp_tz 가드 truthy — develop `ab95701`.
- [ ] #43 검증(선상): "GMT n" 클릭 → 테마 팝업 / 9.5 등 반시간대 저장·표시 / 자동 TZ 가 수동 반시간대 안 덮음 /
  `index.php`+`common.js`+`common_ui.inc` 일괄 배포 + Ctrl+F5.
- [x] **#44 커밋 완료(develop)**: GMT 저장 시 전역 `$g` 오염 → 웹루트 숫자폴더+config.xml 덤프 버그 수정
  (`$g`→`$gmt_in`) — develop `ab95701`. (보안: 웹루트 config 노출)
- [ ] #44 검증(선상): 타임존 저장 후 `/usr/local/www/` 에 숫자폴더 미생성 / 기존 숫자폴더 삭제 완료 /
  nginx 접근로그로 외부 다운로드 흔적 확인 → 있으면 RADIUS secret/API key 등 교체.
- [x] **#38 커밋 완료(develop)**: terminaltype 미해석(현존 게이트웨이 없음) → 로그인 차단 +
  "The antenna is offline, please try later." (잠재 3경로 불일치 블랙홀을 로그인 단계서 차단) — develop `c9bd917`. (main/prod 미반영)
- [ ] #38 검증(선상): disabled/rename 게이트웨이에 pinned된 유저 로그인 시 "antenna is offline" 메시지 +
  `[CP Login] BLOCKED` 로그 / Auto(빈값)·정상 게이트웨이 유저는 정상 로그인 / 게이트웨이 정정 후 정상화 /
  `grep "PINNED.*<user>" wireless.log` 로 원인 게이트웨이 식별.
- [x] **#39 커밋 완료(develop)**: 같은 MAC·다른 ID(공유기 NAT/MAC클론) 세션 탈취·핑퐁 → MAC 자동이관
  폐지(1b) — develop `c9bd917`. (main/prod 미반영)
- [ ] #39 검증(선상): IP 변경 시 자동로그인 안 되고 로그인 페이지 표시(재로그인 시 자기 세션) /
  `[MIGRATE]` 핑퐁 로그 소멸 / 같은 IP+MAC 연결유지는 영향 없음 / #4 자동로그인 편의 상실 체감 확인 /
  공유기는 브리지/AP 모드 권장 안내.
- [ ] #38/#39 배포 정합성: **index.php + captiveportal.inc 같은 리비전 일괄 배포**(버전 섞이면
  undefined function fatal).
- [ ] #38/#39 main 반영 대기: 명시 지시 시 병합.
- [x] **#40 커밋 완료(develop)**: OpenVPN 재시작 크론을 watchdog 으로 안정화(per-client·hang reap·
  비블로킹 락·timeout 바운드·위성 디바운스·로그 가시화) — develop `66ebfd7`. (main/prod 미반영)
- [ ] #40 검증(선상): `crontab -l | grep openvpn_restart`(등록) / `clog /var/log/system.log | grep
  openvpn-watchdog`(RESTART/reap/락실패 로그) / 경로전환(manual_routing "Automatic") 시 모든 client 즉시
  재시작 / liveness 는 ~3분 후 재시작(즉시성 원하면 `OVWD_FAIL_THRESHOLD=1`) / hang 인스턴스 10분 후
  자동 reap / 디버그: `touch /tmp/openvpn_watchdog_debug.on` 로 per-client ping rc 확인(끝나면 삭제).
- [ ] #40 배포 정합성: **코어 함수에만 의존 → 단독 배포 가능**(repo 타 파일과 버전 섞임 무관).
- [ ] #40 튜닝(필요 시): 파일 상단 `OVWD_FAIL_THRESHOLD`/`OVWD_RESTART_COOLDOWN`/`OVWD_STALE_HOLDER_SECS`.
- [x] **#37 커밋 완료(develop)**: Release Note 사이드바 메뉴 + 패치노트 표시 페이지(`1f0c4da`) +
  단일 소스화(A안)·사용자 양식 파서(`deb779c`) — develop `deb779c`. (main/prod 미반영)
- [ ] #37 검증(선상): 사이드바 "Release Note" 메뉴 → 1.1.3/1.1.2 카드 정상 렌더 / **3파일 일괄 배포**
  (common_ui.inc + release_note.php + release_note.md; `.md` 누락 시 "No release notes").
- [ ] #37 유지보수: 패치노트 갱신은 **단일 소스 `usr/local/www/release_note.md` 한 파일만 편집**
  (루트 RELEASENOTE.md 는 제거됨). 양식 = 헤더(`X.Y.Z (날짜)` **또는** 날짜형 `YYYY-MM-DD [제목]` — `1.1.5(#46)`
  에서 파서 확장) + 선택적 서브라인(Beta/Stable 등) + `- TAG:`(NEW/CHANGED/FIXED/REMOVED) 불릿.
  **커밋만으로는 선상 미반영 — 별도 배포 필요**(deploy 가 usr/local/www/ 트리를 박스로 올림).
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
