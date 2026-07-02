# 작업 세션 기록 — REST API 확장 / DNS 게이트웨이 바인딩 / 커버리지 맵 (2026-06-19)

이 세션에서 진행한 모든 작업·결정·디버깅 맥락을 정리한 핸드오프 문서. 브랜치는 전부 `develop`.

## 한눈에 보기 (커밋 순서)
| 커밋 | 내용 |
|---|---|
| `1e6e8e2` | feat(api): RoutingGateway 커스텀 게이트웨이 필드 (Create/Update/Delete) |
| `ab47c85` | feat(api): SystemDNS DNS 서버별 게이트웨이 바인딩 + 함대 자동배정 |
| `d46d641` | feat(api): SystemDNS dnsservergw_auto 를 topN/M 으로 일반화 |
| `cb50fcb` | docs: API 전체 사용 목록 + 페이로드 예시 (apiusage.md) |
| `0b7bd5a` | docs(api): pfSense-API 문서 트리(openapi.yml) + 커스텀 API 병합 |
| `41a7a9c` | feat(panel): 위성 커버리지 맵을 DB(coveragemap) 폴리곤 렌더로 전환 |

> 무관한 작업본 변경(`CLAUDE.md`, `usr/local/www/index.php` 중 비대상분 아님, `.claude/settings.local.json`)은
> 모든 커밋에서 의도적으로 제외. (index.php 는 마지막 커밋에서 대상으로 포함)

관련 문서: [api_gateway_and_dns.md](api_gateway_and_dns.md) · [apiusage.md](apiusage.md)

---

## 1. RoutingGateway API — 커스텀 게이트웨이 필드 (`1e6e8e2`)
**파일**: `etc/inc/api/models/APIRoutingGateway{Create,Update,Delete,Read,DetailRead}.inc`

- 스톡 pfSense-API 모델에 **이 배포본 전용 커스텀 필드**를 검증·저장하도록 추가
  (GUI `save_gateway()`/`system_gateways_edit.php`와 동일 의미):
  - `terminal_type`(GUI select 허용목록 검증) · `check_method`(nmap/none/ping) · `check_timeout`(초)
  - `allowance`(GB, -1/공란=무제한) · `rootinterface` · `destinationip`
  - `currentusage`(GB) → `/etc/inc/{rootinterface}_cumulative` 파일 기록(경로탐색 방어 추가)
  - `cutoff_enable`/`disablecrewinternet`/`blockall_bydefault` → 소비측 `=== 'yes'` 라서 `'yes'`/`''` 정규화
  - `sourceaddresses`/`destaddresses`/`portsfrom`/`portsto`/`protos` (`||` 결합 verbatim)
- **계산/읽기전용 필드**(`dynamic`/`friendlyiface`/`friendlyifdescr`/`attribute`/`tiername`)는 저장 안 함(명시 키만 기록)
- **식별자**: Update/Delete 가 `id` 없으면 `attribute`(상세 read 반환값)로 게이트웨이 식별 → 상세 read 객체를 그대로 되돌려 수정/삭제 가능
- Create/Update 검증 로직 동기 유지(주석 "Kept in sync"). 스텁 하네스로 요청 페이로드 + 음성/엣지 22케이스 검증.

## 2. SystemDNS API — DNS 서버별 게이트웨이 바인딩 (`ab47c85`, `d46d641`)
**파일**: `etc/inc/api/models/APISystemDNS{Read,Update,ServerCreate,ServerDelete}.inc`

### 핵심 스키마 사실 (가장 중요)
pfSense 는 "DNS 서버별 게이트웨이"를 **병렬 배열이 아니라 위치 키** `$config['system']['dns1gw']..['dns4gw']`
(1-인덱스, **최대 4개**)로 저장한다. `dns{N}gw` = `dnsserver` 배열 N번째 서버의 게이트웨이 이름(또는 `"none"`).
`system.php`·`system_resolvconf_generate()`가 이 키를 읽음. → **병렬 `dnsservergw` 배열에만 저장하면 무효**
(이전 분석이 이 부분을 오해했음; `array_unique`가 정렬한다는 진단도 틀림 — 첫 등장 순서 유지).

### 구현
- API 에는 직관적 병렬 배열로 노출, 내부에서 `dns{N}gw` 위치 키로 변환. 공유 헬퍼(`api_dns_*`)는 모든
  모델이 require 하는 `APISystemDNSRead.inc` 에 정의.
- 게이트웨이 지정 3가지(우선순위 `auto > type > name`):
  - `dnsservergw` — 게이트웨이 **이름** 배열
  - `dnsservergw_type` — **terminal_type** 배열 → 각 박스가 로컬 이름으로 변환(없는 타입=none 강등)
  - `dnsservergw_auto` — **`topN/M`**(`top2x2`/`top4x1`/`top1x4`…): 상위 N개 WAN 에 DNS M개씩 자동 배정
- 규칙: gw 필드 전부 생략 = **기존 바인딩 IP 기준 보존**(목록만 바꿀 때 wipe 방지) / `[]`·`["none",..]` = 전부 해제 /
  삭제 시 위치 키 재정렬 / GET 응답에 `dnsservergw` 포함(none→"")
- **함대 통합**: 이름·개수가 선박마다 달라도 `terminal_type`(표준 enum)으로 묶음 → 전 함대 동일 페이로드 1벌
- **auto top N/M 로직**: `gateway_item`(이름·terminal_type·disabled) + `return_gateways_status()`(up/down) 읽어
  **온라인 우선 → terminal_type 우선순위**로 랭킹. 온라인 1개라도 있으면 온라인 집합에서만 상위 선택
  (항해 중 down된 LANDLINE 자동 배제), 0 온라인이면 타입우선순위 폴백, `vpn`/`metered_other`/disabled 제외.
  우선순위는 `api_dns_terminal_type_priority()` 한 곳에서 조정.
- 검증: PyYAML 없이 스텁 하네스로 함대 7케이스 + 파서 11 + 분포 7케이스 통과.

> ⚠ up/down 은 **스냅샷** → 링크 상태가 바뀌면 다시 호출해야 갱신(중앙에서 주기적 PUT 권장).

## 3. apiusage.md — API 전체 목록 + 페이로드 예시 (`cb50fcb`)
**파일**: `docs/apiusage.md`

- 코드(`api/endpoints` URL·메서드 매핑 + `api/models` 입력 파라미터)에서 추출.
- 요약표(22개 URL) + 카테고리별 메서드·모델 클래스·파라미터 + **모든 쓰기 엔드포인트의 요청 본문 예시**(curl/PowerShell).
- 발견: `topup` 모델 클래스명 `TopUp`(파일명과 다름), `daytimecheck` 파일 `APIServicesWriteDayTimeCheck.inc`↔클래스
  `APIServicesUpdateDayTimeCheck`, `lanstate` GET 클래스 `APISystemLanstateRead`(소문자 s).
- `routing/gateway`·`system/dns(/server)` 는 엔드포인트(.inc)가 리포에 없고 pfSense-API 패키지 제공(모델만 오버라이드).

## 4. openapi.yml 병합 — Swagger 문서 (`0b7bd5a`)
**파일**: `usr/local/www/api/documentation/openapi.yml` (+ swagger-ui 트리 전체, 사용자가 B안으로 staged)

- Swagger UI 가 로드하는 스톡 OpenAPI 스펙에 이 리포 커스텀 API 를 병합:
  - 신규 커스텀 엔드포인트 17종 + 태그(firewall/enablerule, routing/defaultgw, freeradiususer(+topup),
    service/cron, service/interface, services/portal, services/daytimecheck,
    status/gpsposition·manageconfig·terminalinfo·timeoffset, system/lanstate·ping·toggleprepaid·vesselinfo·vlandevices)
  - `routing/gateway` POST/PUT 에 커스텀 필드 + `attribute`, `system/dns(/server)` 에 dnsservergw/_type/_auto,
    `status/openvpn` 에 POST(restart)
- PyYAML 설치 후 파싱 검증(142 paths / 109 tags, 전부 고유). 커스텀 항목은 설명에 `[Vessel custom]` 표시.
- ⚠ **패키지 재설치/업데이트 시 덮어쓰여짐**(패키지 동봉 파일). 갱신 후 재병합 필요.

## 5. Main Panel 위성 커버리지 맵 — DB 폴리곤 (`41a7a9c`)
**파일**: `usr/local/www/index.php`

- 기존 운영사 이미지/근사 밴드 → **로컬 DB `10.8.128.1:3306 SynerSAT.coveragemap`** 의 `(satellite, positionlist)`
  (폴리곤 JSON `[{"label":..,"points":[[lat,lon]..]}]`)를 PHP(mysql CLI, `sbox_reader`)로 읽어
  `CP_COVERAGE_DB` 주입 → Leaflet 폴리곤 렌더(`covDbLayer`), 키 기반 동적 토글.
- DB 조회 실패(빈 결과)면 기존 근사 위도대 밴드로 우아하게 폴백.
- CLI 쿼리·데이터·형식 모두 정상 확인됨(백엔드 정상).

---

## 배포 교훈 / 디버깅 체인 (이 세션에서 반복 관측)
이 세션 후반은 **전부 배포 문제**였음. 코드는 정상인데 선상에 반영이 안 됨.

1. **`APISystemDNS*` 변경이 안 먹힘** → 박스에 **구버전 파일 배포**(또는 미배포). pfSense-API 모델은
   런타임 require_once 라 파일 교체 후 **`/etc/rc.restart_webgui`(opcache 갱신)** 필요.
2. **PHP fatal: Cannot declare class APIRoutingGatewayDetailRead** → **모델 파일이 endpoints/ 로 잘못 복사됨**
   (models/ 와 endpoints/ 양쪽에서 같은 클래스 선언). pfSense-API 는 두 디렉터리의 모든 .inc 를 로드하므로
   중복 선언 fatal. 복구: `grep -l "extends APIModel" /etc/inc/api/endpoints/*.inc | xargs rm -f` (endpoints 에는
   `extends APIEndpoint` 만 있어야 함) → restart_webgui.
3. **커버리지 맵이 DB 미조회** → 박스 `index.php` 가 **구버전**(DB 코드 자체가 없음). 서버 렌더 HTML 의
   모달 제목이 `Satellite coverage (approximate)`(구) vs `Satellite coverage`(신)로 구분됨.
4. **재배포해도 동일** → 디스크 파일이 실제로 안 덮였거나(경로/권한) opcache 미갱신. 결정 진단:
   `grep -c CP_COVERAGE_DB /usr/local/www/index.php` (0=구버전 파일), `ls -la` mtime, 그리고 restart_webgui.

**배포 정합성 원칙**: 모델→`/etc/inc/api/models/`, 엔드포인트→`/etc/inc/api/endpoints/`(섞지 말 것).
같은 기능 파일 묶음은 같은 리비전으로 일괄 배포. 파일 교체 후 `/etc/rc.restart_webgui` + 브라우저 Ctrl+F5.

---

## 검증 대기 (선상)
- **#API**: `PUT /api/v1/system/dns` 의 dnsservergw_auto/type/name 동작, routing/gateway 커스텀 필드 round-trip,
  Swagger UI(`/api/documentation/`)에 커스텀 엔드포인트 표시.
- **#커버리지**: 신버전 index.php 배포 + restart_webgui 후 `var CP_COVERAGE_DB = {..}` 데이터 주입 →
  밴드 대신 폴리곤 렌더(모달 제목 "Satellite coverage", 토글 동적).
- **배포 위생**: endpoints/ 에 `extends APIModel` 파일 없는지(`grep -l` 결과 공백) 재확인.

## 미커밋/주의
- `.claude/settings.local.json`, `CLAUDE.md` 는 이 세션 작업과 무관하게 `M` 상태로 남아 있음(미커밋).
- 배포 규칙상 **develop 까지만** 반영. main/prod 는 별도 명시 지시 + 재확인 후.
