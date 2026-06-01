# pfSense Captive Portal — 프로젝트 컨텍스트

## 배포 규칙 (절대 준수)

- **prod 브랜치**는 사용자의 명시적 명령 + 재확인 없이 절대 건드리지 않는다
- 작업 흐름: `develop` → (명시적 지시 시) `main` → (명시적 지시 시) `prod`
- 커밋은 항상 `develop`에 먼저 한다
- `main`, `prod`는 병합 명령이 있을 때만 실행한다

## 브랜치 현황

| 브랜치 | 커밋 | 설명 |
|---|---|---|
| `develop` | `2fdc155` | #1~#12 전부 포함, 작업 기준 브랜치 |
| `main` | `8114d11` | #1~#10 전부 반영 완료 (merge 커밋) |
| `prod` | `f04c9a4` | 실제 배포 버전, 건드리지 않음 |

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
| `usr/local/cron/crew_*usage_reset_check.php` | 주기별 쿼터 reset 크론 (모두 config writer) |
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
- **남은 갭(미적용, 후속)**: PW 변경과 **동시 실행 시** 여전히 clobber 가능 —
  - 형제 admin writer: `reset_wifi_user`(쿼터리셋)/`modify_wifi_user`/`del_wifi_user`
  - 매분 writer 크론: `manual_routing`/`network_usage`/`vlan_state` (curl 대기를 락 밖으로 빼는
    구조 재배치 필요 — 락을 길게 잡으면 안 됨)
  - API 단건 생성(`APIFreeRADIUSUserCreate` 비-bulk; bulk는 `create_wifi_user` 경유라 이미 보호됨)
  → 같은 `lock('freeradius_user_config')` 패턴으로 단계 확대 예정.

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
- **수정 방향 (미적용)**:
  - 방어: `freeradius_update_user`: `?string $username` nullable + 빈값 early-return → fatal 제거.
  - 근본: username 전파 정합화 — 폼 hidden `name="login_user"` → `name="auth_user"`,
    render 데이터 키 `loginUser` → `loginUserValue` 통일, change_pw flash에 username 포함.
  - **가장 빠른 조치**: `captiveportal.inc` 최신본(repo main) 배포 → 버전 불일치 해소.

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

## 다음 작업 대기 중

- [x] **#12**: 로그아웃 ~19초 지연 수정 완료 (deferred state kill → detached 백그라운드) — develop `2fdc155`
- [ ] #12 검증: 로그아웃 클릭 → 즉시 페이지 전환(19초 소멸) + ~2초 후 기존 연결 종료 확인
- [x] **#11**: `commit_change_pw` fatal 수정 완료 (username 전파 정합화 + `?string` 방어) — develop `1ed69ad`
- [ ] 선박에서 수정사항 테스트 (특히 #2, #3, #4, #6, #7, #8, #10)
- [ ] #7: interim 집계 동작 확인 (REGRESS-KEEP 로그 / export 비차단 / interim 마커 갱신)
- [ ] #8: prepaid self-heal 확인 (배포 후 첫 관리 UI 로드 시 가짜 zone 자동 제거 + prepaid 상태 보존)
- [ ] #6: REMOVING 오탐 재현 안 됨 + passthrough 게스트 redirect 동작 확인
- [ ] #9: vlanstate.sh 간헐 미동작 해소 확인 (`.log` 정상 생성)
- [ ] #10 핵심: **두 명 동시 PW 변경 → 둘 다 적용** 확인 / radiusd 로그 `RADIUS ACCOUNTING FAILED` 없음 / 단건 PW 즉시 반영
- [ ] #10 가정 확인: `grep -R usersfile /usr/local/etc/raddb/mods-enabled/files` → radiusd 활성 파일이 resync 타깃과 일치 + 그 파일이 패키지 전량생성인지
- [ ] #10 후속(B/C): 형제 admin writer(reset/modify/del_wifi_user) → 매분 writer 크론 → API 단건 생성에 동일 `lock('freeradius_user_config')` 확대
- [x] main 반영 완료 (#1~#10)
- [ ] prod 반영은 별도 명시적 명령 (main → prod 는 재확인 후)

## 명령어 가이드

```
"develop에 커밋해"          → 현재 변경사항을 develop에 커밋·푸시
"develop를 main에 병합해"   → develop → main 머지
"main을 prod에 반영해"      → main → prod (재확인 후 실행)
```
