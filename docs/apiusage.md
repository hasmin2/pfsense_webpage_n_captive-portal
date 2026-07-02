# pfSense REST API 사용 목록 (apiusage)

이 리포(`etc/inc/api/`)에 정의/오버라이드된 REST API 전체 목록. 코드(`endpoints/` URL·메서드 매핑 +
`models/` 입력 파라미터)에서 추출.

## 공통 사항
- **베이스 URL**: `http(s)://<box>/api/v1/...` (예: `http://192.168.209.1`)
- **인증**: 모든 요청 본문 JSON에 `client-id` / `client-token` 포함(로컬 인증). 예시에선 생략.
- **동작 결정**: HTTP 메서드(GET/POST/PUT/DELETE)로 모델 선택.
- **응답**: `{"return":0, ...}` → 성공. `return`이 0이 아니면 `message`에 사유.
- **요청 헤더**: `Content-Type: application/json`.
- 표의 **출처**: `repo`=이 리포 엔드포인트 파일 있음 / `pkg`=엔드포인트는 pfSense-API 패키지 제공, 이 리포는 모델만 오버라이드.

## 전체 요약
| URL | 메서드 | 동작 | 출처 |
|---|---|---|---|
| `/api/v1/firewall/enablerule` | GET | 방화벽 룰 전체 활성화 새로고침 | repo |
| `/api/v1/routing/defaultgw` | POST / PUT | 기본 게이트웨이 조회 / 설정 | repo |
| `/api/v1/routing/gateway` | GET / PUT / POST / DELETE | 게이트웨이 조회 / 생성 / 수정 / 삭제 | pkg |
| `/api/v1/routing/gateway/detail` | GET | 게이트웨이 상세(계산필드 포함) | pkg |
| `/api/v1/freeradiususer` | GET / PUT / POST / DELETE | crew 계정 조회 / 생성 / 수정 / 삭제 | repo |
| `/api/v1/freeradiususer/topup` | PUT | 사용량/쿼터 증감(탑업) | repo |
| `/api/v1/service/cron` | GET / PUT | cron 목록 조회 / 기록 | repo |
| `/api/v1/service/interface` | GET / POST / PUT / DELETE | 인터페이스 메트릭 / 갱신(renew) / up / down | repo |
| `/api/v1/services/portal` | GET / POST / PUT | 캡티브포털 zone 조회 / 활성화토글 / 웹페이지(html) 수정 | repo |
| `/api/v1/services/daytimecheck` | POST | 주/야(일출·일몰) 상태 기록 | repo |
| `/api/v1/status/gpsposition` | GET | GPS 위치 조회 | repo |
| `/api/v1/status/manageconfig` | GET / POST | config.xml 조회 / 설정 | repo |
| `/api/v1/status/openvpn` | GET / POST | OpenVPN 상태 조회 / 재시작 | repo |
| `/api/v1/status/terminalinfo` | GET | 단말(터미널) 상태 정보 조회 | repo |
| `/api/v1/status/timeoffset` | GET / POST | GMT 오프셋 조회 / 설정 | repo |
| `/api/v1/system/dns` | GET / PUT | DNS 서버·게이트웨이 바인딩 조회 / 수정 | pkg |
| `/api/v1/system/dns/server` | POST / DELETE | DNS 서버 추가 / 삭제 | pkg |
| `/api/v1/system/lanstate` | GET / POST | LAN 상태 조회 / 설정 | repo |
| `/api/v1/system/ping` | POST | 게이트웨이 ping/상태 메트릭 | repo |
| `/api/v1/system/toggleprepaid` | GET / POST | prepaid 모드 조회 / 토글 | repo |
| `/api/v1/system/vesselinfo` | POST | 선박/계정 정보 갱신 | repo |
| `/api/v1/system/vlandevices` | POST | VLAN 장치 목록 갱신 | repo |

---

## 상세 (메서드 · 모델 · 파라미터)

> 파라미터는 모델이 읽는 입력 키. 표준 게이트웨이/사용자 필드는 GUI와 동일 의미.

### Firewall
**`/api/v1/firewall/enablerule`**
- `GET` → `APIFirewallEnableRuleRefresh` — 비활성화된 룰 전체 재활성화. 파라미터 없음.

### Routing
**`/api/v1/routing/defaultgw`**
- `POST` → `APIRoutingDefaultGwRead` — 현재 기본 GW 조회. 파라미터 없음.
- `PUT` → `APIRoutingDefaultGwSet` — `defaultgw4`, `manualrouteduration`.

**`/api/v1/routing/gateway`** (pkg 엔드포인트 + 이 리포 모델)
- `GET` → `APIRoutingGatewayRead` — 게이트웨이 목록(config 원본). 파라미터 없음.
- `PUT` → `APIRoutingGatewayCreate` — 생성.
- `POST` → `APIRoutingGatewayUpdate` — 수정(식별자 `id` 또는 `attribute`).
- `DELETE` → `APIRoutingGatewayDelete` — 삭제(`id`/`attribute`, `apply`).
- 생성/수정 파라미터(공통): `interface` `gateway` `name` `ipprotocol` `monitor` `monitor_disable`
  `action_disable` `force_down` `disabled` `descr` `weight` `data_payload` `latencylow` `latencyhigh`
  `losslow` `losshigh` `interval` `loss_interval` `time_period` `alert_interval` `apply`
  **+ 커스텀(선상)**: `terminal_type` `check_method` `check_timeout` `destinationip` `allowance`
  `rootinterface` `currentusage` `cutoff_enable` `disablecrewinternet` `blockall_bydefault`
  `sourceaddresses` `destaddresses` `portsfrom` `portsto` `protos`

**`/api/v1/routing/gateway/detail`**
- `GET` → `APIRoutingGatewayDetailRead` — `return_gateways_array`(계산필드 포함). 파라미터 없음.

### FreeRADIUS (crew 계정)
**`/api/v1/freeradiususer`**
- `GET` → `APIFreeRADIUSUserRead` — `freeradius_username`(선택, 미지정 시 전체).
- `PUT` → `APIFreeRADIUSUserCreate` — `freeradius_username` `freeradius_password` `freeradius_maxtotaloctets`
  `freeradius_maxtotaloctetstimerange` `freeradius_terminaltype` `userquantity` `israndompw` `issimplefied` `priv`.
- `POST` → `APIFreeRADIUSUserUpdate` — `userlist` 또는 `freeradius_username`(다건), `freeradius_terminaltype`
  `freeradius_password` `israndompw` `freeradius_lastbasedata` `freeradius_maxtotaloctetstimerange`
  `timeperiod`(=`freeradius_timeperiod`) `priv`.
- `DELETE` → `APIFreeRADIUSUserDelete` — `userlist` 또는 `freeradius_username`(다건), `priv`.

**`/api/v1/freeradiususer/topup`**
- `PUT` → `TopUp` — `commandId` `freeradius_username` `freeradius_maxtotaloctets`(쿼터 증감)
  `freeradius_lastbasedata`(사용량 증감, MB; 양수=+, 음수=−, 0 하한).

### Service
**`/api/v1/service/cron`**
- `GET` → `APIServiceCronRead` — cron 목록. 파라미터 없음.
- `PUT` → `APIServiceCronWrite` — `cronlist`(JSON 배열).

**`/api/v1/service/interface`**
- `GET` → `APIServiceInterfaceRead` — 메트릭. 파라미터 없음.
- `POST` → `APIServiceInterfaceRenew` — `interface`(DHCP 갱신).
- `PUT` → `APIServiceInterfaceUp` — `interface`(up).
- `DELETE` → `APIServiceInterfaceDown` — `interface`(down).

### Services
**`/api/v1/services/portal`**
- `GET` → `APIServicesReadPortal` — `zone`.
- `POST` → `APIServicesUpdatePortal` — `zone` `portalactive`.
- `PUT` → `APIServicesUpdatePortalWeb` — `zone` `htmltext` `errtext` `logouttext`.

**`/api/v1/services/daytimecheck`**
- `POST` → `APIServicesUpdateDayTimeCheck` (파일 `APIServicesWriteDayTimeCheck.inc`) — `dayNight` `sunriseTime` `sunsetTime`.

### Status
**`/api/v1/status/gpsposition`** — `GET` → `APIStatusGetGpsPosition`. 파라미터 없음.

**`/api/v1/status/manageconfig`**
- `GET` → `APIStatusGetConfig` — config 조회. 파라미터 없음.
- `POST` → `APIStatusSetConfig` — `config`.

**`/api/v1/status/openvpn`**
- `GET` → `APIStatusOpenVPNRead` — 상태 조회. 파라미터 없음.
- `POST` → `APIStatusOpenVPNRestart` — 재시작. 파라미터 없음.

**`/api/v1/status/terminalinfo`** — `GET` → `APIStatusTerminalInfoRead`. 파라미터 없음.

**`/api/v1/status/timeoffset`**
- `GET` → `APIStatusGetTimeOffset` — 조회. 파라미터 없음.
- `POST` → `APIStatusSetTimeOffset` — `time_offset`.

### System
**`/api/v1/system/dns`** (pkg 엔드포인트 + 이 리포 모델)
- `GET` → `APISystemDNSRead` — DNS + 게이트웨이 바인딩 조회(응답에 `dnsservergw`). 파라미터 없음.
- `PUT` → `APISystemDNSUpdate` — `dnsserver` `dnsservergw` `dnsservergw_type` `dnsservergw_auto`
  `dnsallowoverride` `dnslocalhost`. (게이트웨이 바인딩 상세는 [api_gateway_and_dns.md](api_gateway_and_dns.md) 참고)

**`/api/v1/system/dns/server`** (pkg 엔드포인트 + 이 리포 모델)
- `POST` → `APISystemDNSServerCreate` — `dnsserver`(추가) + `dnsservergw`/`dnsservergw_type`/`dnsservergw_auto`.
- `DELETE` → `APISystemDNSServerDelete` — `dnsserver`(삭제, 바인딩 위치 재정렬).

**`/api/v1/system/lanstate`**
- `GET` → `APISystemLanstateRead` — 조회. 파라미터 없음.
- `POST` → `APISystemLanStateUpdate` — `lanstate`.

**`/api/v1/system/ping`** — `POST` → `APISystemSendPing` — 게이트웨이 ping/상태 메트릭 반환. 파라미터 없음.

**`/api/v1/system/toggleprepaid`**
- `GET` → `APISystemToggleprepaidRead` — 조회. 파라미터 없음.
- `POST` → `APISystemToggleprepaidUpdate` — `prepaid_enabled`.

**`/api/v1/system/vesselinfo`** — `POST` → `APISystemUpdateVesselInfo` — `vesselinfo` `accountinfo`.

**`/api/v1/system/vlandevices`** — `POST` → `APISystemUpdateVLANDevices` — `vlandevices`.

---

## 페이로드 예시 (요청 본문)

> 아래 예시는 가독성을 위해 인증 키를 생략했습니다. **모든 요청 본문에 `"client-id"` / `"client-token"` 을 함께 넣어야 합니다.**
> 파라미터 없는 GET/조회는 본문에 인증 키만 넣으면 됩니다(예: `{"client-id":"admin","client-token":"..."}`).

### Routing
**`PUT /api/v1/routing/defaultgw`** — 기본 게이트웨이 설정
```json
{ "defaultgw4": "FX_CORP", "manualrouteduration": 60 }
```
`manualrouteduration` = 임시 라우팅 유지(분). 생략 시 영구. `defaultgw4` = 게이트웨이 이름.

**`PUT /api/v1/routing/gateway`** — 게이트웨이 생성 (`POST` = 수정, `id`/`attribute` 추가)
```json
{
  "interface": "vtnet4.10", "gateway": "10.157.123.193", "name": "FX_CORP",
  "ipprotocol": "inet", "monitor": "10.157.123.193", "weight": 1,
  "terminal_type": "vsat_pri", "check_method": "nmap", "check_timeout": "10",
  "allowance": "", "rootinterface": "vtnet4.10", "currentusage": "0",
  "disablecrewinternet": "", "blockall_bydefault": "",
  "descr": "VSAT or FX Gateway", "apply": true
}
```
**`DELETE /api/v1/routing/gateway`**
```json
{ "attribute": 1, "apply": true }
```

### FreeRADIUS
**`GET /api/v1/freeradiususer`** — `{ "freeradius_username": "crust1" }` (생략 시 전체)

**`PUT /api/v1/freeradiususer`** — 다건(bulk) 생성
```json
{
  "userquantity": 10,
  "freeradius_maxtotaloctets": 1024,
  "freeradius_maxtotaloctetstimerange": "Monthly",
  "freeradius_terminaltype": "vsat_pri",
  "israndompw": true,
  "issimplefied": false
}
```
**`PUT /api/v1/freeradiususer`** — 단건 생성
```json
{
  "freeradius_username": "crust1",
  "freeradius_password": "1111",
  "freeradius_maxtotaloctets": 1024,
  "freeradius_maxtotaloctetstimerange": "Monthly",
  "freeradius_terminaltype": "vsat_pri",
  "israndompw": false
}
```
`freeradius_maxtotaloctetstimerange` = `daily`/`weekly`/`Monthly`/`half-Monthly`/`forever`. `israndompw`:
`true`=6자리 난수 비번 / `false`+`freeradius_password` 미지정=기본 / `issimplefied`=단순 ID 여부.

**`POST /api/v1/freeradiususer`** — 다건 수정
```json
{
  "userlist": ["crust1", "crust2"],
  "freeradius_terminaltype": "vsat_sec",
  "israndompw": true,
  "timeperiod": "half-Monthly",
  "freeradius_maxtotaloctetstimerange": "monthly"
}
```
`israndompw`: `true`=난수 / `false`=비번 `1111` 초기화 / 생략=비번 무변경 (#34).

**`DELETE /api/v1/freeradiususer`** — `{ "userlist": ["crust1", "crust2"] }`

**`PUT /api/v1/freeradiususer/topup`** — 쿼터/사용량 증감
```json
{
  "commandId": "2026-06-18T01:00:00Z-abc123",
  "freeradius_username": "crust1",
  "freeradius_maxtotaloctets": 500,
  "freeradius_lastbasedata": -50
}
```
`freeradius_maxtotaloctets` = 쿼터 증감(MB), `freeradius_lastbasedata` = 사용량 증감(MB, 양수=+/음수=−, 0 하한),
`commandId` = 중복 요청 방지용 식별자(같은 값 재전송 시 skip).

### Service
**`PUT /api/v1/service/cron`** — cron 항목 기록
```json
{ "cronlist": [
  { "minute": "*/5", "hour": "*", "mday": "*", "month": "*", "wday": "*",
    "who": "root", "command": "/usr/local/bin/php -f /usr/local/cron/example.php" }
] }
```
**`POST|PUT|DELETE /api/v1/service/interface`** — 인터페이스 renew/up/down — `{ "interface": "opt8" }`

### Services
**`GET /api/v1/services/portal`** — `{ "zone": "crew" }`

**`POST /api/v1/services/portal`** — zone 활성화 토글 — `{ "zone": "crew", "portalactive": true }`

**`PUT /api/v1/services/portal`** — 포털 웹페이지 수정
```json
{ "zone": "crew", "htmltext": "<h1>Welcome</h1>", "errtext": "Auth failed", "logouttext": "Goodbye" }
```
**`POST /api/v1/services/daytimecheck`** — `{ "dayNight": "day", "sunriseTime": "06:12", "sunsetTime": "18:47" }`

### Status
**`POST /api/v1/status/manageconfig`** — `{ "config": "<...config.xml 페이로드...>" }`

**`POST /api/v1/status/timeoffset`** — `{ "time_offset": "9" }` (GMT 오프셋; 정수 또는 "5.5")

**`GET /api/v1/status/gpsposition` · `/status/terminalinfo` · `/status/openvpn`** — 인증 키만 (`{}` 형태)

**`POST /api/v1/status/openvpn`** — 재시작 — 인증 키만

### System
**`PUT /api/v1/system/dns`** — DNS + 게이트웨이 바인딩 (함대 자동, 권장)
```json
{
  "dnsserver": ["208.67.220.220", "168.126.63.1", "8.8.8.8", "1.1.1.1"],
  "dnsservergw_auto": "top4x1"
}
```
바인딩 방법(택1): `dnsservergw_auto`(`top2x2`/`top4x1`/`top1x4`/`topN/M`) · `dnsservergw_type`(터미널타입 배열) ·
`dnsservergw`(이름 배열). 생략=기존 보존, `[]`/`["none",..]`=전부 해제. 상세 → [api_gateway_and_dns.md](api_gateway_and_dns.md).
```json
{ "dnsserver": ["208.67.220.220","168.126.63.1"], "dnsservergw_type": ["vsat_pri","tcp_starlink"] }
```
**`POST /api/v1/system/dns/server`** — DNS 추가 — `{ "dnsserver": ["9.9.9.9"], "dnsservergw_type": ["vsat_pri"] }`
**`DELETE /api/v1/system/dns/server`** — DNS 삭제 — `{ "dnsserver": ["9.9.9.9"] }`

**`POST /api/v1/system/lanstate`** — `{ "lanstate": "connect" }`
값은 코드가 verbatim 저장(운영 정의 토큰; 응답 안내는 "JSON MAP format").

**`POST /api/v1/system/ping`** — 게이트웨이 ping/상태 메트릭 — 인증 키만

**`POST /api/v1/system/toggleprepaid`** — `{ "prepaid_enabled": true }` (true=활성 / false=해제)

**`POST /api/v1/system/vesselinfo`** — 선박/계정 정보
```json
{
  "vesselinfo": { "name": "MV EXAMPLE", "imo": "1234567" },
  "accountinfo": { "new_id": "admin", "new_pw": "newsecret" }
}
```
`accountinfo`(new_id/new_pw)는 관리자 비밀번호 변경 시에만 포함.

**`POST /api/v1/system/vlandevices`** — `{ "vlandevices": ["vtnet4.10", "vtnet4.20"] }`

---

## 참고
- `routing/gateway`, `system/dns(/server)`는 엔드포인트(.inc)가 이 리포에 없고 pfSense-API 패키지가 제공.
  이 리포는 해당 **모델만** 커스텀 오버라이드. 배포 시 패키지 설치 + 모델 일괄 배포 필요.
- 커스텀 게이트웨이 필드(RoutingGateway) / DNS 게이트웨이 바인딩(SystemDNS) 상세는
  [api_gateway_and_dns.md](api_gateway_and_dns.md) 참조.
- `priv`는 일부 FreeRADIUS 모델의 내부 권한 분기용 파라미터.
