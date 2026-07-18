# used-octets 과소계상 조사 — 확정 결론 + 수정안 + 극한 케이스 (2026-07-18)

## 1. 문제 / 최초 관측
같은 유저에 대해 세 저장소가 크게 다름 (예: starlinkuser00002, 2026-07):
- **used-octets**(파일, quota/과금) = **14.14G**
- **InfluxDB delta**(`datacounter_interim_delta.total_bytes` 합) = **56.2G**
- **MySQL radacct** = ~15M (덮어쓰기라 무의미)

## 2. 세 값의 원천 (전수조사)
- 세 값은 **한 곳(datacounter)** 이 아니라 **서로 다른 3개 정산 알고리즘**이 만든다.
- `datacounter_acct.sh` 는 **InfluxDB(delta) + used-octets 파일** 두 곳에만 쓴다. MySQL 은 read-only(vesselinfo).
- radacct(MySQL)는 별도 rlm_sql(`queries.conf`)이 SET(덮어쓰기, high-water/역행 방어 없음)로 씀 → 정산 무의미.
- **회계는 로컬**: CP가 ipfw 카운터를 `getVolume()`로 읽어 octets/gigawords 로 **로컬 분할**(`captiveportal.inc:4079-4092`, `remainder()`/`gigawords()` = bytes%2^32 / bytes//2^32) 후 **내부 FreeRADIUS**로 전송. → **위성 재정렬 없음, gigaword 누락 불가**(구성상 항상 일관).

## 3. 확정 결론 (선상 물리 데이터로 4중 검증)
**used-octets가 ~3.8배 과소, InfluxDB(delta)≈WAN 이 정확. FIX 필요.**

선상 실측(vessel 10.8.130.92, 2026-07):
| 지표 | 값 | 의미 |
|---|--:|---|
| WAN vnstat(vtnet0) | **516G** | 물리 회선(부풀 수 없음). 외부 WAN 시스템도 ~500G 확인. |
| Σ InfluxDB delta | 498G (96.5% WAN) | 실사용에 정확 |
| Σ used-octets | 134G (26% WAN) | **과소** |
| REGRESS-KEEP (00002) | **49+** | 봉우리(14.14G) 아래 interim 수(로그 로테이션분). "49회 리셋"이 아니라 리셋 후 저-에포크 interim들 |
| **7월 재부팅**(Bootup complete) | **10회** | Jul 1·1·2·5·9·10·11·14·15·17 → **리셋의 실체 = 박스 재부팅** (§4b) |
| first_point(00002, 7월) | **11** | ≈ 재부팅 10회 (1:1). 리셋 이벤트 = 재부팅 |
| SESSFILE(00002) | 14.14G, **7/9 동결** | 이후 안 큼 |
| 7/9 이후 delta(00002) | **19.75G** | 동결 후에도 실사용 계속 → 전부 미계상 |
| first_point max CUR | **127M** | first_point는 작은 신선 에포크에서 발화 → **D 부풀림 아님** |
| DATACOUNTER STOP(00002) | **0** | 세션이 Stop 미도래 → USEDFILE 은행 안 됨(base=0) |
| session_changed | **0** (함대) | per-user PREV 핑퐁 부풀림 없음 |
| reset(필드) | **0** (함대) | **마스킹임**: 리셋(재부팅) 순간 `/var/run`(tmpfs) 소거로 PREVFILE 부재 → first_point 로 기록. 진짜 리셋 횟수 = **first_point ≈ 재부팅 수** |
| MIGRATE(00002) | **0** | IP 마이그레이션 아님 → 카운터 리셋 원인에서 제외 |

### 과소 기전 (이중 손실)
1. **리셋 climb 손실**: ipfw 카운터가 자주 리셋(=**재부팅**, 월 10회). SESSFILE high-water(REGRESS-KEEP)가 **단일 최대 에포크(14.14G)만** 남기고 나머지 에포크 climb 버림.
2. **Stop 미도래**: 세션이 Acct-Stop을 안 보냄(395h+ 연속) → USEDFILE(base) 영영 0.

## 4b. "리셋"의 정의 = **박스 재부팅** (확정, 로그 대조 2026-07-18)
- **7월 `Bootup complete` = 10회**(Jul 1·1·2·5·9·10·11·14·15·17) ≈ **first_point = 11** → 리셋 이벤트 = **재부팅** (1:1).
- **다른 후보 전부 제외**: MIGRATE=0(IP 마이그레이션 아님) · stopstart=interimupdate(zerocnt 미발화) · filter reload/dpinger flapping 은 **pf 룰만 갈고 ipfw CP 카운터는 안 건드림** → **재부팅만이** getVolume 카운터를 0으로.
- **기전**: 재부팅 → ipfw dummynet `{zone}_auth_up/down` 테이블 재생성(카운터 0) + `/var/run`(tmpfs) 소거로 PREVFILE 삭제. **세션(SID)·radacct 행은 유지**(Acct-Stop 없음) → **톱니(sawtooth) 카운터**.
- **SESSFILE 7/9 동결 설명**: 7/5 05:20 → 7/9 19:09(~4.5일 연속가동) 동안 14.14G 축적. **7/9 이후 재부팅이 1~2일마다(9·10·11·14·15·17)로 잦아져** 이후 어느 에포크도 14.14G 를 못 넘음 → high-water 동결. → **재부팅 빈도↑ = 과소↑**.
- **성격**: 재부팅이 ipfw 카운터를 0으로 만드는 것은 **stock pfSense/ipfw 정상 동작**(getVolume 표준코드 확인). 문제는 **이 fleet 의 비정상 재부팅 빈도(월 10회, ~3.5일에 1번; #24 OOM/ZFS-full·#26·Peplink↔dpinger flapping)** + high-water 정산이 에포크 1개만 보존하는 조합.
- **대책 2축**: ① **근원** = 재부팅 빈도 감소(fleet 안정화). ② **정산 정확성** = v2 delta 누적(재부팅 몇 번이든 매 에포크 climb 을 faithful 적산 → InfluxDB≈WAN 수렴). **재부팅은 위성환경상 근절 불가 → ②가 billing 정확성의 본질적 수정.**

## 4. 확정 수정안 (v2 = delta 누적 + 가드)
핵심: **InfluxDB에 저장되는 검증된 delta(`total_bytes`, D≈WAN)를 quota 누적기에도 넣는다.**
- **리셋 climb 포착** = high-water 대신 **DELTA 누적**. (InfluxDB가 이미 맞게 계산 → 검증됨)
- **never-Stop 대응** = **매 interim delta 를 SESSFILE에 누적**(Stop 안 기다림). auth 글롭이 합산 → 세션 안 끝나도 계상. USEDFILE 은행은 정리용.
- **누적값 = DELTA_TOTAL 그대로**(InfluxDB와 동일). first_point가 작으므로(127M) first_point delta(=CUR)를 **0으로 죽이지 말고 그대로 더할 것**(→ §5 E10).
- **크로스모델 must-fix 가드**(§5).

## 5. 극한 케이스 재검토 (실측 대조)
| # | 극한 케이스 | 프로덕션 상태 | 필요한 가드 | 심각도 |
|---|---|---|---|---|
| E1 | 동시 Stop↔Interim(무락) → PREVFILE 삭제 후 Interim first_point 재가산 | first_point CUR 작음(127M)→재가산 소량; D≤WAN | interim RMW를 Stop과 **같은 LOCK** + **STOPPED 센티넬** | MED-HIGH |
| E2 | per-user 키잉 → 동시 같은유저 세션 SESSION_CHANGED 무한재가산 | **sesschg=0**(미발화), 유저당 SID 1개 | **per-SID 키잉** | MED |
| E3 | 재정렬 → 가짜 리셋 과집계 | reset=0(CUR<PREV 미관측)+로컬 in-order | (선택) belt Interim만 | LOW |
| E4 | NTP 점프 → belt/리셋크론 오작동 | **시스템 UTC 절대**; belt 후진점프 안전(실측) | belt은 **Interim만**(Stop 드롭 금지); 리셋크론 date-key | LOW-MED |
| E5 | **리셋(크론)이 활성세션 PREVFILE 삭제 → 다음 interim이 월누적 통째 재가산 → 오탐 락아웃** | 세션 Stop 안함(항상 활성)→위험 실재; 월경계에 큰 CUR 잡힐 수 있음 | **리셋 시 PREVFILE 삭제 금지 → 현재 CUR로 재베이스라인**(활성 SID) | **HIGH** |
| E6 | STOPPED/state 파일 폭증 → inode 고갈(#24) | 이 선박 SID 1개(churn↓); but #21 random-MAC 타선 | **state TTL-sweep(forever 포함)**, dash-anchored | MED |
| E7 | 필드별 octet 드롭(ipfw 재빌드 #19/#20) → 회복 과집계 | 증거 없음(first_point 작음) | 한방향 0-drop 시 high-water 유지(bank 0) | LOW-MED |
| E8 | 디스크풀(#24) → SESSFILE 쓰기 실패 | #24 실재 | **쓰기 성공시에만 PREV 전진**(self-heal) | MED |
| E9 | dash-glob 충돌(crust1↔crust10) | 기존 리셋크론 버그 잔존 | state clear에 **dash-anchored 글롭** | MED |
| E10 | **"PREV부재→0" 규칙이 신선 에포크(first_point) 과소** | **first_point=127M ×49 리셋 → 이 규칙 쓰면 매 에포크 소량씩 손실** | **DELTA_TOTAL 그대로 누적**(0 억제 금지). 대용량 재가산은 실측상 부재라 안전 | **MED(설계 정정)** |
| E11 | lockf 타임아웃 → interim 드롭 | 부하시(#24) | 타임아웃 시 PREV 미전진(다음 interim 회수) | LOW-MED |

**핵심 must-fix**: E1(락+STOPPED), E2(per-SID), E5(리셋시 재베이스라인), E6(TTL-sweep), E8(성공시 PREV전진), E10(DELTA_TOTAL 그대로).
**실측 de-risk**: E1/E2/E3 의 과집계는 프로덕션에서 미발화(sesschg=0, reset=0(CUR<PREV 미관측), first_point 작음, D≤WAN). 이론적 최악보다 안전 — but 가드는 defense-in-depth로 필수.

## 5b. 리셋 의미론 — "faithful 적산"이 진짜 요구사항 (운영 리셋 반영)
**used-octets ≡ InfluxDB 는 "리셋이 없는 구간"에서만 성립.** 운영상 리셋이 흔함:
- 수동 리셋(운영자가 used-octets-XXXX 를 0으로) — 종종 발생.
- 주기 리셋(daily=매일 / weekly / halfmonthly / monthly cron).
→ 리셋이 있으면 used-octets < InfluxDB 는 **정상**(설계). used-octets 는 "**마지막 리셋 이후**의 적산", InfluxDB 는 "리셋 없는 연속 적산".

**진짜 요구사항 = 둘 다 증분을 하나도 안 까먹고 적산:**
- InfluxDB: 매 interim 증분을 전부(연속). used-octets: 매 interim 증분을 전부(리셋 시점 이후).
- 우리가 찾은 버그(00002가 7/9에 14G로 동결, 이후 19.75G 유실)는 **리셋과 무관한 "증분 유실"** — 리셋을 감안해도 used-octets가 적산을 까먹고 있음.

### 리셋 시 delta 기준선(PREVFILE) 처리 = E5 의 본질
카운터(getVolume)는 파일 리셋으로 **안 줄어듦**(세션 teardown 때만). 그래서:
- 수동 리셋 = USEDFILE+SESSFILE 를 0으로, **PREVFILE(=마지막 CUR)은 보존**해야 함.
  - 보존 시: 다음 interim delta = CUR - PREV(직전) = **작은 증분** → used-octets = 0 + 증분. ✓ (리셋 이후만 계상)
  - **PREVFILE을 삭제하면(E5 버그): first_point → delta = 전체 CUR → 리셋한 10G가 즉시 되살아남(과집계/오탐 락아웃).**
- 권장: 리셋 시 **PREVFILE 을 현재 CUR로 재베이스라인**(카운터도 함께 리셋된 드문 경우까지 안전). 최소한 **삭제 금지.**
- STOPPED 는 quota 리셋과 무관(세션 미종료) → 유지.

### 검증 방법 정정 (리셋 감안)
- "used-octets 총계 == InfluxDB 총계" 를 무조건 비교하지 말 것(리셋이 깸).
- 올바른 테스트 = **리셋 없는 구간에서 Δused-octets == Δ(InfluxDB)**. daily 는 InfluxDB 를 그 날로 합산해 비교.
- 기준선 케이스(리셋 안 한 선박)는 반드시 일치해야 하고, **실제로 안 맞으므로(14 vs 56) 증분 유실 버그가 증명됨.**

### MySQL(radacct) 주의
운영자 기대("리셋 시 MySQL≈InfluxDB")는 **성립 안 함** — radacct 는 SET(덮어쓰기)라 적산기가 아님(high-water/역행방어 없음, 최신 카운터만, 리셋 시 함께 줄어듦). **radacct 를 연속 적산 기준으로 쓰려면 별도 수정(interim/stop SQL 에 accumulation/high-water)이 필요.** 현재 billing 은 used-octets 이므로 radacct 는 정보용.

## 6. 검증 스택 (거쳐온 길)
순환 오라클(폐기) → **비순환 물리 오라클** → **크로스모델(Opus/Sonnet/Fable) 적대검증** → **선상 실측(물리 WAN + 파일 + 로그)**. 4중 확인.
- 크로스모델이 잡은 것: 순환 오라클, 클램프/DELTA_TOTAL 오류(재검), per-user 키잉, 무락 interim, wall-clock belt, 리셋-활성삭제, inode 폭증.
- 실측이 정박한 것: 과소는 리셋 climb + never-Stop, D≈WAN 정확, 과집계 벡터 실제 미발화.

## 7. 배포/게이트
- `datacounter_acct.sh` + `freeradius.inc` 임베디드 사본 **글자단위 동시** 수정.
- 시뮬레이터 시나리오를 셸 단위테스트로.
- **최종 게이트**: 한 척 배포 → `usage_reconcile.py`로 used-octets가 InfluxDB≈WAN 에 수렴 + **오탐 락아웃 0** 확인 → 함대.
- 전환: 소급 락아웃 방지 위해 배포 시 used-octets 처리 정책 확정(현행부터 정확 시작 등).

## 8. 구현 완료 (2026-07-19, develop)
**핵심 = SESSFILE 을 `max(CUR)`(high-water) → `+= DELTA_TOTAL`(InfluxDB 와 동일한 검증된 delta) 로 전환.**
InfluxDB delta 가 물리 WAN 과 일치 검증됐으므로(§3), SESSFILE 이 같은 delta 를 누적하면 used-octets ≈ InfluxDB ≈ WAN.
- **수정 파일**: `usr/local/etc/raddb/scripts/datacounter_acct.sh` + `usr/local/pkg/freeradius.inc` 임베디드 nowdoc(바이트 동일 27659, 재추출 diff 0). php -l 통과.
- **변경 요약**:
  - **Interim**: high-water/REGRESS-KEEP 제거 → **락 안에서 `SESSFILE = OLD + DELTA_TOTAL`**. delta 는 기존 InfluxDB 계산 그대로 재사용.
  - **E1(락+센티넬)**: interim SESSFILE 누적과 Stop 은행이 **같은 per-user LOCK** 공유 + **STOPPED 센티넬**(Stop 후 늦은 interim → skip 42, SESSFILE 되살리기 차단) + **중복 Stop 멱등**(STOPMARK 재확인 → exit 0, 이중은행 차단).
  - **E1(동시성)**: **낙관적 락 토큰 `SIDPREV_TS`**(per-SID PREVFILE ts) — 동시/재전송 interim 이 이미 커밋했으면 skip(43). 절대-쓰기(OLD+delta)+overwrite 구조라 동일값 재기록도 자연 멱등.
  - **E2(per-SID)**: PREVFILE `prev-USER` → `prev-USER-SID`. 동시세션 PREV 핑퐁 제거. SESSION_CHANGED 는 사실상 dead(항상 0).
  - **E8(성공시 전진)**: SESSFILE 쓰기 성공 후에만 PREV 전진(디스크풀 등 실패 시 PREV 안 밀어 다음 interim 이 회수) + **InfluxDB export 도 커밋(rc=0) 시에만** → used-octets 와 lockstep.
  - **E10**: first_point delta=CUR 유지(0 억제 안 함).
  - **Stop 통합**: zero-stop 별도 블록 제거 → 통합 Stop 이 **SESSFILE(누적) + 마지막 구간 delta** 은행(짧은 세션/최종 gap 포착). 구버전 "packet CUR_TOTAL 은행"(리셋 후 high-water 손실) 버그 제거.
  - **배포 전환 마이그레이션**: 첫 interim 이 **구 per-USER prev 를 baseline 승계**(SESSION_CHANGED 억제) → 배포 직후 활성 세션이 first_point(DELTA=전체 CUR)로 **28G 스파이크 → 오탐 락아웃**나는 것 방지. 재부팅 시엔 tmpfs 소거로 옛 prev 도 없어 정상 first_point(카운터 0 → delta 작음).
  - **E5**: `captiveportal_reset_user_usage()` 는 disconnect(Stop)로 SESSFILE 은행+PREV 삭제 후 파일 삭제, `/var/run` prev 직접 미삭제 확인 → **무수정 안전**(활성 세션 중 삭제는 abort 가드로 차단).
  - **방어**: `if STATUS=Stop` 가드(Start/Accounting-On 이 STOPMARK 를 세워 그 세션 interim 전멸시키는 사고 차단) + TTL sweep 에 `stopped-*` 추가.
- **검증**: 셸 단위테스트 **16/16**(정상·재부팅 mid-session·never-stop 2회재부팅·STOPPED 센티넬·리셋후신규·짧은세션·zero interim·재부팅후stop·재전송멱등·프로덕션형14G+에포크·세션중리셋·중복Stop·배포전환무스파이크·전환후재부팅·Start no-op). 물리총량=Σdelta=InfluxDB 패리티 입증. 하네스 = 실제 스크립트를 경로리디렉트+lockf스텁으로 구동(`scratchpad/test_datacounter_v2.sh`).
- **남은 게이트(선상)**: 한 척 배포 → `usage_reconcile.py` 로 used-octets 가 InfluxDB≈WAN 수렴 + **오탐 락아웃 0** 며칠 관찰 → 함대. **소급 복구 없음**(배포 시점부터 정확). 근원(재부팅 빈도, §4b)은 별개로 안정화 필요.
