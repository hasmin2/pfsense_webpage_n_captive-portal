# pfSense Captive Portal — 프로젝트 컨텍스트

## 배포 규칙 (절대 준수)

- **prod 브랜치**는 사용자의 명시적 명령 + 재확인 없이 절대 건드리지 않는다
- 작업 흐름: `develop` → (명시적 지시 시) `main` → (명시적 지시 시) `prod`
- 커밋은 항상 `develop`에 먼저 한다
- `main`, `prod`는 병합 명령이 있을 때만 실행한다

## 브랜치 현황

| 브랜치 | 커밋 | 설명 |
|---|---|---|
| `develop` | `549681f`+ | #1~#23 포함, 작업 기준 브랜치 (#18~#21: vnstat예외·게이트웨이flapping/과금누수·끊김진단/다국어/blank단락; #22: PW리셋 무작위미반영 — writer크론 lost-update 차단; #23: PW변경 무반영 진범=HUP가 rlm_files 미재로딩 — A응급=재시작 + radcheck(SQL) 이행도구) |
| `main` | `8114d11` | #1~#10 전부 반영 완료 (merge 커밋). **#11~#17 미반영** |
| `prod` | `f04c9a4` | 실제 배포 버전, 건드리지 않음 |

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

> **주의**: `freeradius.inc`의 `freeradius_datacounter_acct_resync()` /
> `freeradius_datacounter_auth_resync()` 함수가 `datacounter_acct.sh` /
> `datacounter_auth.sh`를 각각 nowdoc으로 **덮어씌워 생성**한다.
> 두 셸 스크립트를 수정하면 반드시 `freeradius.inc` 내 **임베디드 사본도 함께** 수정할 것.
> (검증법: freeradius.inc에서 nowdoc 블록 추출 후 standalone과 diff → 내용 동일해야 함.
> CRLF/말미개행 차이는 Windows 체크아웃 아티팩트이며 git이 LF로 정규화하므로 무시.)

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

## 다음 작업 대기 중

- [x] **#23 step1+2 도구**: radcheck 이관 스크립트 + SQL authorize 토글 — develop `fd6ced2`
- [x] **#23 A(응급)**: PW/계정 변경 reload 를 HUP→재시작 (silent-fail 차단) — develop (본 패치)
- [ ] #23 A 검증: 자가/관리자 비번변경 → **재시작 없이 즉시 새 비번 로그인** + `[AUTH-UNKNOWN]` 소멸 /
  `radiusd -X` 로 변경 후 옛 비번 거부 확인 / 재시작 빈도·accounting 영향 모니터링
- [ ] **#23 step3 (근본, 미착수)**: 인증을 radcheck(SQL) 권위화 — `commit_change_pw`/관리자 PW writer 를
  **radcheck UPDATE** 로, authorize 에서 **sql 우선**(또는 files 쓰기 중단) → reload 의존 제거(즉시반영+accounting 무중단)
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
