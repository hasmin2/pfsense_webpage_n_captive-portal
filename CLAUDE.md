# pfSense Captive Portal — 프로젝트 컨텍스트

## 배포 규칙 (절대 준수)

- **prod 브랜치**는 사용자의 명시적 명령 + 재확인 없이 절대 건드리지 않는다
- 작업 흐름: `develop` → (명시적 지시 시) `main` → (명시적 지시 시) `prod`
- 커밋은 항상 `develop`에 먼저 한다
- `main`, `prod`는 병합 명령이 있을 때만 실행한다

## 브랜치 현황

| 브랜치 | 커밋 | 설명 |
|---|---|---|
| `develop` | `ee74616` | 모든 수정 포함, 작업 기준 브랜치 |
| `main` | `961a2a8` | develop보다 3커밋 뒤처짐 (아직 미반영) |
| `prod` | `f04c9a4` | 실제 배포 버전, 건드리지 않음 |

## Repo 정보

- **Remote**: `hasmin2/pfsense_webpage_n_captive-portal-dev`
- **플랫폼**: pfSense 2.5.2, PHP-FPM + nginx, FreeRADIUS

## 핵심 파일

| 파일 | 역할 |
|---|---|
| `usr/local/captiveportal/index.php` | 포털 메인 (PRG 패턴, 세션 관리) |
| `etc/inc/captiveportal.inc` | 인증·세션·과금 핵심 로직 |
| `usr/local/pkg/freeradius.inc` | FreeRADIUS 설정 생성 (datacounter_acct.sh embedded) |
| `usr/local/etc/raddb/scripts/datacounter_acct.sh` | RADIUS 회계 처리 (Start/Interim/Stop) |
| `usr/local/etc/raddb/scripts/datacounter_auth.sh` | RADIUS 인증 시 쿼터 확인 |

> **주의**: `freeradius.inc`의 `freeradius_datacounter_acct_resync()` 함수가
> `datacounter_acct.sh`를 **덮어씌워 생성**한다.
> `datacounter_acct.sh`를 수정하면 반드시 `freeradius.inc` 내 동일 위치도 함께 수정할 것.

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

## 다음 작업 대기 중

- [ ] 선박에서 수정사항 테스트 (특히 #2, #3, #4)
- [ ] 테스트 통과 후 → "develop를 main에 병합해"
- [ ] main 반영 확인 후 → prod는 별도 명시적 명령

## 명령어 가이드

```
"develop에 커밋해"          → 현재 변경사항을 develop에 커밋·푸시
"develop를 main에 병합해"   → develop → main 머지
"main을 prod에 반영해"      → main → prod (재확인 후 실행)
```
