#!/bin/bash
# coresystem_influx_write.sh
# -----------------------------------------------------------------------------
# 코어 박스(CentOS, 192.168.209.210)에서 실행. CPU 코어 평균온도(sensors)와
# 시스템 uptime(/proc/uptime 초)을 로컬 InfluxDB 의 acustatus.coresystem measurement 로 기록한다.
# pfSense runtime API(APISystemGetRuntime.inc)가 이 measurement 를 읽어
# core_temp / core_uptime 을 반환한다. (SSH/sshpass 회피 — FreeBSD 미지원 대응)
#
# 설치:
#   cp coresystem_influx_write.sh /usr/local/bin/
#   chmod 755 /usr/local/bin/coresystem_influx_write.sh
#   crontab -e  →  * * * * * /usr/local/bin/coresystem_influx_write.sh >/dev/null 2>&1
#
# 의존: lm_sensors(sensors), curl. InfluxDB 1.x 가 로컬 8086 에서 동작.
# -----------------------------------------------------------------------------

# 코어 박스에서 로컬 실행이므로 loopback. (192.168.209.210 도 동일 호스트라 가능)
INFLUX_URL="http://127.0.0.1:8086/write?db=acustatus&precision=s"

# 1) CPU 코어 온도 평균(℃). "Core N: +39.0°C (high=+105 ...)" 에서 콜론 뒤 첫 숫자(실측 온도)만 추출해 평균.
#    (grep -Eo 로 뽑으면 high/crit 값까지 섞이므로 sed 로 라인당 첫 값만.)
TEMP="$(sensors 2>/dev/null \
  | grep -E '^Core[[:space:]]+[0-9]+:' \
  | sed -E 's/^[^:]*:[[:space:]]*\+?([-0-9.]+).*/\1/' \
  | awk '{s+=$1; n++} END{ if(n>0) printf "%.2f", s/n }')"

# 코어별 라인이 없으면(칩셋 라벨 차이) Package id 로 폴백
if [ -z "$TEMP" ]; then
  TEMP="$(sensors 2>/dev/null \
    | grep -E '^Package id[[:space:]]+[0-9]+:' \
    | sed -E 's/^[^:]*:[[:space:]]*\+?([-0-9.]+).*/\1/' \
    | head -n 1 \
    | awk '{printf "%.2f", $1}')"
fi

# 2) 시스템 uptime 정수 초 (/proc/uptime 첫 필드)
UP="$(awk '{printf "%d", $1}' /proc/uptime 2>/dev/null)"

# 3) 읽힌 필드만 line protocol 로 구성 (실패한 필드는 생략 → last() 가 직전 정상값 유지)
FIELDS=""
if [ -n "$TEMP" ]; then
  FIELDS="core_temp=${TEMP}"
fi
if [ -n "$UP" ]; then
  # core_uptime 은 정수 필드 → 'i' 접미사
  if [ -n "$FIELDS" ]; then FIELDS="${FIELDS},"; fi
  FIELDS="${FIELDS}core_uptime=${UP}i"
fi

# 둘 다 못 읽으면 아무것도 쓰지 않음(빈 line protocol 방지)
if [ -z "$FIELDS" ]; then
  exit 0
fi

TS="$(date +%s)"
LINE="coresystem ${FIELDS} ${TS}"

# 로컬 LAN 타임아웃 가드 (repo 관례: curl -sS -m 2 --connect-timeout 2)
curl -sS -m 2 --connect-timeout 2 \
  -H 'Content-Type: text/plain' \
  --data-binary "${LINE}" \
  "${INFLUX_URL}" >/dev/null 2>&1

exit 0
