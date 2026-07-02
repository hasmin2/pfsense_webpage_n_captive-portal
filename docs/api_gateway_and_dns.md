# Routing Gateway / System DNS REST API 확장

pfSense REST API(`/etc/inc/api/models/`)에 추가한 두 기능 묶음 문서.

- **A. Routing Gateway** — 게이트웨이 CRUD에서 이 배포본 전용(선상) 커스텀 필드 지원
- **B. System DNS** — DNS 서버별 게이트웨이 바인딩(+ 함대 단위 자동 배정)

> 인증은 본문 JSON에 `client-id` / `client-token`. 동작은 HTTP 메서드로 결정. 응답 `return:0` = 성공,
> 그 외는 `message`에 사유. 예시 호스트는 `http://192.168.209.1`.

---

## A. Routing Gateway API

### 엔드포인트
| 메서드 + URL | 동작 | 모델 |
|---|---|---|
| `GET /api/v1/routing/gateway` | 게이트웨이 목록(config 원본) | `APIRoutingGatewayRead` |
| `GET /api/v1/routing/gateway/detail` | 계산 필드 포함 상세 | `APIRoutingGatewayDetailRead` |
| `POST /api/v1/routing/gateway` | 수정 | `APIRoutingGatewayUpdate` |
| `PUT /api/v1/routing/gateway` | 생성 | `APIRoutingGatewayCreate` |
| `DELETE /api/v1/routing/gateway` | 삭제 | `APIRoutingGatewayDelete` |

> 엔드포인트 파일(`APIRoutingGateway.inc`)은 pfSense-API 패키지가 제공. 본 리포는 모델만 오버라이드.

### 지원 필드 분류
**표준 pfSense**: `interface` `gateway` `name` `weight` `ipprotocol` `descr` `monitor` `monitor_disable`
`action_disable` `force_down` `disabled` `data_payload` `latencylow/high` `losslow/high` `interval`
`loss_interval` `time_period` `alert_interval`

**커스텀(선상 전용, 이번에 추가)** — `save_gateway()`(gwlb.inc)·`system_gateways_edit.php`·
`firewallpreset.inc`·`terminal_status.inc`·사용량 크론이 소비:

| 필드 | 검증/저장 |
|---|---|
| `terminal_type` | GUI select 허용목록 검증(`vsat_pri`/`vsat_sec`/`vsat_thi`/`tcp_other`/`nexuswave_*`/`tcp_starlink`/`tcp_oneweb`/`tcp_kuiper`/`fbb_*`/`iridium_other`/`metered_other`/`vpn`), 공란 허용 |
| `check_method` | `nmap`/`none`/`ping` |
| `check_timeout` | 양의 정수(초) |
| `allowance` | 숫자(GB)/`-1`/공란(무제한) |
| `currentusage` | 숫자(GB) → `/etc/inc/{rootinterface}_cumulative` 파일 기록(GUI와 동일 부수효과, 경로탐색 방어) |
| `rootinterface` | 인터페이스명 |
| `destinationip` | 자유 텍스트(IP/IP:port/URL/`;`구분) |
| `cutoff_enable` `disablecrewinternet` `blockall_bydefault` | 체크박스 → `'yes'`/`''` 정규화(소비측 `=== 'yes'`) |
| `sourceaddresses` `destaddresses` `portsfrom` `portsto` `protos` | `\|\|` 결합 문자열 verbatim |

**계산/읽기전용(저장 안 함)**: `dynamic` `friendlyiface` `friendlyifdescr` `attribute` `tiername`
→ 페이로드에 있어도 무시(config 오염 방지).

### 식별자
Update/Delete는 `id` 또는 `attribute`(상세 read가 반환하는 값)로 게이트웨이를 찾음 → 상세 read 객체를
그대로 되돌려 보내 수정/삭제 가능.

### 예시 (수정, 상세 read 객체 그대로 POST)
```bash
curl -sk -X POST http://192.168.209.1/api/v1/routing/gateway \
  -H 'Content-Type: application/json' \
  -d '{
    "client-id":"admin","client-token":"globe1@3",
    "attribute":1,
    "interface":"vtnet4.10","gateway":"10.157.123.193","name":"FX_CORP",
    "ipprotocol":"inet","monitor":"10.157.123.193","weight":"1",
    "terminal_type":"vsat_pri","check_method":"nmap","check_timeout":"10",
    "allowance":"","rootinterface":"vtnet4.10","currentusage":"0",
    "disablecrewinternet":"","blockall_bydefault":"",
    "descr":"VSAT or FX Gateway(DHCP client)"
  }'
```

---

## B. System DNS API — DNS 서버별 게이트웨이 바인딩

### 핵심 스키마 사실 (중요)
pfSense는 "DNS 서버별 게이트웨이"를 **병렬 배열이 아니라 위치 기반 개별 키**로 저장한다:
`$config['system']['dns1gw'] .. ['dns4gw']` (1-인덱스, **최대 4개**). `dns{N}gw` = `dnsserver` 배열의
N번째 서버에 묶인 게이트웨이 이름(또는 `"none"` = 바인딩 없음). `system.php`·`system_resolvconf_generate()`가
이 키를 읽는다. → 병렬 `dnsservergw` 배열에만 저장하면 pfSense가 안 읽어 무효.

**구현 방식**: API에는 직관적인 병렬 `dnsservergw` 배열로 노출하되, 내부적으로 `dns{N}gw` 위치 키로 변환.
공유 헬퍼(`api_dns_*`)는 모든 모델이 require하는 `APISystemDNSRead.inc`에 정의.

### 엔드포인트
| 메서드 + URL | 동작 | 모델 |
|---|---|---|
| `GET /api/v1/system/dns` | 조회(응답에 `dnsservergw` 포함) | `APISystemDNSRead` |
| `PUT /api/v1/system/dns` | DNS 목록·바인딩 교체/수정 | `APISystemDNSUpdate` |
| `POST /api/v1/system/dns/server` | DNS 서버 추가(기존 유지) | `APISystemDNSServerCreate` |
| `DELETE /api/v1/system/dns/server` | DNS 서버 삭제(바인딩 위치 재정렬) | `APISystemDNSServerDelete` |

### 게이트웨이 지정 3가지 (우선순위: `auto` > `type` > `name`)
| 입력 필드 | 의미 |
|---|---|
| `dnsservergw` | 게이트웨이 **이름** 배열(`dnsserver`와 1:1). 없는 이름 → 에러 |
| `dnsservergw_type` | **terminal_type** 배열 → 각 박스가 로컬 이름으로 변환. 없는 타입 → `none`으로 강등 |
| `dnsservergw_auto` | `"topN/M"`(또는 `topNxM`) → 상위 N개 WAN에 DNS M개씩 자동 배정. 예: `top2x2`(2개씩 2 WAN), `top4x1`(WAN당 1개=최대 분산), `top1x4`(최선 1개에 전부), 바로 `topN`은 M=1. 미인식 값(auto/true/1)은 `top2x2` |

규칙:
- **세 필드 모두 생략** → 기존 바인딩을 **서버 IP 기준으로 보존**(DNS 목록만 바꿀 때 실수로 wipe 방지).
- `dnsservergw: []` 또는 `["none",...]` → **전부 해제**.
- `dnsserver` 생략 + gw 필드만 → 기존 서버 목록에 게이트웨이만 재바인딩.
- 응답/저장 시 `none`은 빈 문자열로 표현(GET이 `["","",""]` 반환).

### 함대 통합 — `terminal_type`이 통합 키
게이트웨이 **이름·개수는 선박마다 다르지만** `terminal_type`은 함대 표준 enum. 따라서 이름이 아니라
terminal_type으로 묶으면 **전 함대에 동일 페이로드 1벌**이면 충분.

### auto `"topN/M"` 로직
정책 문자열 `topN/M`(또는 `topNxM`) = **상위 N개 WAN에 DNS M개씩** (`top2x2`, `top4x1`, `top1x4`, …).
바로 `topN`은 M=1. 미인식 값(`auto`/`true`/`1`)은 `top2x2`로 처리.
1. 박스의 `gateway_item`(이름·terminal_type·disabled) + `return_gateways_status()`(up/down) 읽기
2. **온라인 우선 → terminal_type 우선순위**로 랭킹 (우선순위는 `api_dns_terminal_type_priority()` 한 곳에서 조정)
3. 온라인이 1개라도 있으면 **온라인 집합 안에서만** 상위 N개 선택 → 항해 중 down된 LANDLINE 등 자동 배제
4. `dns[i]` → `i / M`번째(정수나눗셈) WAN. 예 top2x2: dns1,2→1순위 / dns3,4→2순위. 최대 4슬롯
5. 폴백: 가용 WAN < N이면 남는 DNS는 마지막 선택 WAN으로 / 0 온라인 → 타입우선순위(offline) / `vpn`·`metered_other`·disabled 제외
6. 비고: up/down **스냅샷**이라 링크 상태가 바뀌면 다시 호출해야 갱신(중앙에서 주기적 PUT 권장)

### 적용 흐름 (`action()`)
`write_config()`(config.xml 저장) → `system_resolvconf_generate()`(resolv.conf + **DNS별 정적경로 생성**)
→ unbound/dnsmasq 재설정 → `service reload dns` → `filter_configure()`.
효과: `dns{N}gw`가 `none`이 아니면 그 DNS IP 트래픽을 지정 WAN으로만 보내는 정적경로 → 한 WAN이 죽어도
다른 DNS가 다른 WAN으로 살아있어 방화벽 자신의 DNS가 멀티WAN에서 끊기지 않음.

### 예시
**조회**
```bash
curl -sk -X GET http://192.168.209.1/api/v1/system/dns \
  -H 'Content-Type: application/json' \
  -d '{"client-id":"admin","client-token":"globe1@3"}'
```
**함대 자동 배정(권장, 전 선박 동일)**
```bash
curl -sk -X PUT http://192.168.209.1/api/v1/system/dns \
  -H 'Content-Type: application/json' \
  -d '{"client-id":"admin","client-token":"globe1@3",
       "dnsserver":["208.67.220.220","168.126.63.1","8.8.8.8","1.1.1.1"],
       "dnsservergw_auto":"top2x2"}'
```
**terminal_type 지정**
```bash
curl -sk -X PUT http://192.168.209.1/api/v1/system/dns \
  -H 'Content-Type: application/json' \
  -d '{"client-id":"admin","client-token":"globe1@3",
       "dnsserver":["208.67.220.220","168.126.63.1","8.8.8.8"],
       "dnsservergw_type":["vsat_pri","tcp_starlink","none"]}'
```
**이름 지정 / 전부 해제 / 재바인딩만**
```bash
# 이름
-d '{...,"dnsserver":["208.67.220.220","168.126.63.1","168.126.63.2"],"dnsservergw":["FX_CORP","FX_CREW","FX_CORP"]}'
# 전부 none
-d '{...,"dnsserver":["208.67.220.220","168.126.63.1","168.126.63.2"],"dnsservergw":[]}'
# 서버 그대로, 게이트웨이만
-d '{...,"dnsservergw_auto":"top2x2"}'
```
**PowerShell**
```powershell
$body = @{ "client-id"="admin"; "client-token"="globe1@3"
  dnsserver=@("208.67.220.220","168.126.63.1","8.8.8.8","1.1.1.1"); dnsservergw_auto="top2x2" } | ConvertTo-Json
Invoke-RestMethod -Method Put -Uri "http://192.168.209.1/api/v1/system/dns" -ContentType "application/json" -Body $body
```

---

## 배포 정합성 (중요)
- **각 묶음을 같은 리비전으로 일괄 배포**할 것. SystemDNS는 공유 헬퍼가 `APISystemDNSRead.inc`에 있어
  일부만 배포하면 `api_dns_*` undefined fatal. RoutingGateway도 5개 함께.
- 배포 후 **`/etc/rc.restart_webgui`**로 php-fpm 재시작(opcache 갱신)해야 반영됨.
- 확인: `GET`으로 `dnsservergw` 응답 확인 / `grep -c api_dns_write_servergw /etc/inc/api/models/APISystemDNSRead.inc`.

## 검증
- 전 파일 `php -l` 통과.
- RoutingGateway: 요청 페이로드 정상 저장 + 음성/엣지 22케이스(잘못된 terminal_type/timeout/allowance 거부,
  체크박스 정규화, id/attribute 식별, _cumulative 플래그) 통과.
- SystemDNS: 순서보존·바인딩 보존·삭제 재정렬·none 해제·Read 출력 + 함대 7케이스(온라인우선·LANDLINE회피·
  타입우선순위·이름다른선박·단일WAN·0온라인폴백·type변환·홀수개수) 전부 통과.
