#!/bin/sh
#
# datacounter_acct.sh
#
# USAGE:
#   datacounter_acct.sh USER TIMERANGE IN_OCTETS OUT_OCTETS IN_GW OUT_GW STATUS SESSIONID
#
# NOTES:
# - Uses 64-bit value = (GW << 32) + OCTETS
# - Adds per-interim session file (SESSFILE) and accumulates on Stop.
# - Includes strong sanity guards to prevent overflow/garbage attributes from corrupting totals.
# - Logs are unified with logger -t datacounter (and include range/status/sid).
#

# ---------- helpers ----------
numonly() { echo -n "$1" | tr -cd '0-9'; }

log() {
  # Usage: log "message..."
  logger -t datacounter "$*"
}

# Influx line protocol tag escape (space, comma, equal, backslash)
esc_tag() {
  # tag escape: \ , space =
  printf '%s' "$1" | sed \
    -e 's/\\/\\\\/g' \
    -e 's/,/\\,/g' \
    -e 's/ /\\ /g' \
    -e 's/=/\\=/g'
}

# ---------- InfluxDB helpers (1.8.x) ----------
INFLUX_HOST="192.168.209.210"
INFLUX_PORT="8086"
INFLUX_DB="wifiusage"
INFLUX_PRECISION="m"
INFLUX_TIMEOUT="1"       # seconds
INFLUX_CACHE_SEC="600"   # 10분 캐시 (DB 존재/헬스체크 통과 상태)

# 인증이 켜져 있으면 채우세요 (비우면 인증 없이 동작)
INFLUX_USER=""
INFLUX_PASS=""

INFLUX_BASE="http://${INFLUX_HOST}:${INFLUX_PORT}"
INFLUX_WRITE_URL="${INFLUX_BASE}/write?db=${INFLUX_DB}&precision=${INFLUX_PRECISION}"
INFLUX_QUERY_URL="${INFLUX_BASE}/query"
INFLUX_READY_MARK="/tmp/influx_ready_${INFLUX_DB}.stamp"

curl_influx() {
  # Usage: curl_influx [curl args...]
  # 공통 타임아웃/조용한 옵션 + (필요시) 인증 옵션을 통일
  if [ -n "$INFLUX_USER" ] && [ -n "$INFLUX_PASS" ]; then
    curl -sS -m "$INFLUX_TIMEOUT" --connect-timeout "$INFLUX_TIMEOUT" -u "${INFLUX_USER}:${INFLUX_PASS}" "$@"
  else
    curl -sS -m "$INFLUX_TIMEOUT" --connect-timeout "$INFLUX_TIMEOUT" "$@"
  fi
}

influx_healthcheck() {
  # /ping 우선, 실패하면 /health 시도
  curl_influx -I "${INFLUX_BASE}/ping" >/dev/null 2>&1 && return 0
  curl_influx "${INFLUX_BASE}/health" >/dev/null 2>&1 && return 0
  return 1
}

influx_db_exists() {
  # SHOW DATABASES 응답에 "name":"<db>"가 있는지 확인
  resp="$(curl_influx -G "${INFLUX_QUERY_URL}" --data-urlencode "q=SHOW DATABASES" 2>/dev/null)"
  echo "$resp" | grep -q "\"name\":\"${INFLUX_DB}\""
}

influx_create_db() {
  curl_influx -G "${INFLUX_QUERY_URL}" --data-urlencode "q=CREATE DATABASE ${INFLUX_DB}" >/dev/null 2>&1
}

influx_prepare() {
  # 캐시: 최근에 준비 완료면 바로 OK
  now="$(date +%s)"
  if [ -f "$INFLUX_READY_MARK" ]; then
    last="$(cat "$INFLUX_READY_MARK" 2>/dev/null)"
    [ -z "$last" ] && last=0
    age=$(( now - last ))
    if [ "$age" -ge 0 ] && [ "$age" -lt "$INFLUX_CACHE_SEC" ]; then
      return 0
    fi
  fi

  # 헬스체크 실패면 포기(스크립트 흐름은 유지)
  if ! influx_healthcheck; then
    return 1
  fi

  # DB 없으면 생성
  if ! influx_db_exists; then
    influx_create_db
    influx_db_exists || return 1
  fi

  echo "$now" > "$INFLUX_READY_MARK" 2>/dev/null || true
  return 0
}

#influx_write_line() {
  # Usage: influx_write_line "line protocol..."
  #line="$1"
  # -f: HTTP 4xx/5xx면 실패로 처리. 실패해도 상위에서 || true로 무시 가능
  #curl_influx -f -H 'Content-Type: text/plain' --data-binary "$line" "${INFLUX_WRITE_URL}" >/dev/null 2>&1

#}
influx_write_line() {
  line="$1"

  # 응답/에러를 잠깐이라도 잡아서 원인을 남김
  resp="$(curl_influx -i -H 'Content-Type: text/plain' --data-binary "$line" "${INFLUX_WRITE_URL}" 2>&1)"
  rc=$?

  if [ $rc -ne 0 ]; then
    log "INFLUX WRITE FAIL rc=$rc url=${INFLUX_WRITE_URL} resp=$(echo "$resp" | tr '\n' ' ' | cut -c1-300)"
    return 1
  fi

  # 성공이면 보통 HTTP/1.1 204 No Content
  echo "$resp" | grep -q " 204 " || {
    log "INFLUX WRITE NON-204 url=${INFLUX_WRITE_URL} resp=$(echo "$resp" | tr '\n' ' ' | cut -c1-300)"
    return 1
  }

  return 0
}

# ---------- args (raw) ----------
RAW_USER="$1"
RAW_RANGE="$2"
RAW_IN_O="$3"
RAW_OUT_O="$4"
RAW_IN_GW="$5"
RAW_OUT_GW="$6"
RAW_STATUS="$7"
RAW_SID="$8"

# ---------- sanitize ----------
USERNAME=$(echo -n "$RAW_USER" | sed 's/[^0-9a-zA-Z.:_-]/X/g')

TIMERANGE=$(echo -n "$RAW_RANGE" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9_-]//g')
IN_OCTETS=$(numonly "$RAW_IN_O");     [ -z "$IN_OCTETS" ] && IN_OCTETS=0
OUT_OCTETS=$(numonly "$RAW_OUT_O");   [ -z "$OUT_OCTETS" ] && OUT_OCTETS=0

IN_GW=$(numonly "$RAW_IN_GW");        [ -z "$IN_GW" ] && IN_GW=0
OUT_GW=$(numonly "$RAW_OUT_GW");      [ -z "$OUT_GW" ] && OUT_GW=0

STATUS="$RAW_STATUS"
SESSIONID="$RAW_SID"
[ -z "$SESSIONID" ] && SESSIONID="nosession"

# ---------- paths ----------
BASE="/var/log/radacct/datacounter/$TIMERANGE"
MAXFILE="$BASE/max-octets-$USERNAME"
USEDFILE="$BASE/used-octets-$USERNAME"
SESSFILE="$BASE/used-octets-$USERNAME-$SESSIONID"

# Ensure base dir exists
[ -d "$BASE" ] || mkdir -p "$BASE" 2>/dev/null

# ---------- debug: RAW (always) ----------
#log "RAW user=[$USERNAME] range=[$TIMERANGE] in_oct=[$IN_OCTETS] out_oct=[$OUT_OCTETS] in_gw=[$IN_GW] out_gw=[$OUT_GW] status=[$STATUS] sid=[$SESSIONID]"

# ---------- quota not enabled ----------
[ ! -f "$MAXFILE" ] && exit 0

# ---------- init total file ----------
if [ ! -f "$USEDFILE" ]; then
  echo 0 > "$USEDFILE"
fi

# ---------- sanity guards ----------
MAX_OCTETS=4294967295
[ "$IN_OCTETS" -gt "$MAX_OCTETS" ] && IN_OCTETS=0
[ "$OUT_OCTETS" -gt "$MAX_OCTETS" ] && OUT_OCTETS=0

MAX_GW=100000
if [ "$IN_GW" -gt "$MAX_GW" ] || [ "$OUT_GW" -gt "$MAX_GW" ]; then
  log "DATACOUNTER IGNORE $STATUS user=$USERNAME range=$TIMERANGE sid=$SESSIONID (invalid gigawords in=$IN_GW out=$OUT_GW)"
  exit 0
fi

# ---------- 64-bit current value ----------
CUR_IN=$(( (IN_GW << 32) + IN_OCTETS ))
CUR_OUT=$(( (OUT_GW << 32) + OUT_OCTETS ))
CUR_TOTAL=$(( CUR_IN + CUR_OUT ))

if [ "$CUR_IN" -lt 0 ] || [ "$CUR_OUT" -lt 0 ] || [ "$CUR_TOTAL" -lt 0 ]; then
  log "DATACOUNTER IGNORE $STATUS user=$USERNAME range=$TIMERANGE sid=$SESSIONID (negative calc CUR_IN=$CUR_IN CUR_OUT=$CUR_OUT CUR_TOTAL=$CUR_TOTAL; in_gw=$IN_GW out_gw=$OUT_GW in_o=$IN_OCTETS out_o=$OUT_OCTETS)"
  exit 0
fi

# ---------- debug: CALC ----------
#log "CALC user=$USERNAME range=$TIMERANGE status=$STATUS sid=$SESSIONID IN_GW=$IN_GW OUT_GW=$OUT_GW IN_O=$IN_OCTETS OUT_O=$OUT_OCTETS CUR_IN=$CUR_IN CUR_OUT=$CUR_OUT CUR_TOTAL=$CUR_TOTAL"

# ---------- STOP zero-guard ----------
if [ "$STATUS" = "Stop" ] && [ "$CUR_TOTAL" -eq 0 ]; then
  log "DATACOUNTER IGNORE STOP user=$USERNAME range=$TIMERANGE sid=$SESSIONID (zero octets)"
  exit 0
fi

# ---------- INTERIM ----------
if [ "$STATUS" = "Interim-Update" ]; then
  # 0 interim 무시 (선택)
  if [ "$CUR_TOTAL" -eq 0 ]; then
    exit 0
  fi

  echo "$CUR_TOTAL" > "$SESSFILE"

  log "DATACOUNTER INTERIM user=$USERNAME range=$TIMERANGE sid=$SESSIONID in=$((CUR_IN/1048576))MB out=$((CUR_OUT/1048576))MB total=$((CUR_TOTAL/1048576))MB"

  # ===== InfluxDB 1.8 export (healthcheck + ensure DB + write) =====
  MEASUREMENT="datacounter_interim"

  now_s=$(date +%s)
  bucket_s=$(( (now_s / 600) * 600 ))   # 10분 내림
  ts_m=$(( bucket_s / 60 ))             # precision=m 이므로 "분" 단위 epoch

  # 필드값은 bytes 그대로 (정수 i)
  in_bytes=$(( CUR_IN ))
  out_bytes=$(( CUR_OUT ))
  total_bytes=$(( CUR_TOTAL ))

  line="${MEASUREMENT},user=$(esc_tag "$USERNAME"),range=$(esc_tag "$TIMERANGE") in_bytes=${in_bytes}i,out_bytes=${out_bytes}i,total_bytes=${total_bytes}i ${ts_m}"

  # 준비(헬스/DB 생성) 후 write. 실패해도 accounting 흐름은 계속.
  influx_prepare >/dev/null 2>&1 || true
  influx_write_line "$line" || true
  # ===== /Influx export =====

  exit 0
fi

# ---------- STOP / others ----------
LOCK="/tmp/datacounter_${TIMERANGE}_${USERNAME}.lock"

lockf -t 10 "$LOCK" sh -c '
  USERNAME="$1"
  TIMERANGE="$2"
  SESSIONID="$3"
  CUR_IN="$4"
  CUR_OUT="$5"
  CUR_TOTAL="$6"
  IN_GW="$7"
  OUT_GW="$8"

  BASE="/var/log/radacct/datacounter/$TIMERANGE"
  USEDFILE="$BASE/used-octets-$USERNAME"
  SESSFILE="$BASE/used-octets-$USERNAME-$SESSIONID"

  OLD_TOTAL=$(cat "$USEDFILE" 2>/dev/null)
  [ -z "$OLD_TOTAL" ] && OLD_TOTAL=0

  NEW_TOTAL=$(( OLD_TOTAL + CUR_TOTAL ))

  [ -f "$SESSFILE" ] && rm -f "$SESSFILE"

  echo "$NEW_TOTAL" > "$USEDFILE"

  logger -t datacounter \
"DATACOUNTER STOP user=$USERNAME range=$TIMERANGE sid=$SESSIONID \
in=$((CUR_IN/1048576))MB out=$((CUR_OUT/1048576))MB \
total=$((CUR_TOTAL/1048576))MB accum=$((NEW_TOTAL/1048576))MB"
' sh \
"$USERNAME" "$TIMERANGE" "$SESSIONID" \
"$CUR_IN" "$CUR_OUT" "$CUR_TOTAL" \
"$IN_GW" "$OUT_GW"

exit $?
