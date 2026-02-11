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
# - ✅ state 파일은 /var/run 아래로 이동: 리부트 시 자동 청소됨.
# - ✅ Stop이 안 오는 세션 대비로 /var/run state TTL 청소(가볍게, 1시간에 1번) 포함.
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
  if [ -n "$INFLUX_USER" ] && [ -n "$INFLUX_PASS" ]; then
    curl -sS -m "$INFLUX_TIMEOUT" --connect-timeout "$INFLUX_TIMEOUT" -u "${INFLUX_USER}:${INFLUX_PASS}" "$@"
  else
    curl -sS -m "$INFLUX_TIMEOUT" --connect-timeout "$INFLUX_TIMEOUT" "$@"
  fi
}

influx_healthcheck() {
  curl_influx -I "${INFLUX_BASE}/ping" >/dev/null 2>&1 && return 0
  curl_influx "${INFLUX_BASE}/health" >/dev/null 2>&1 && return 0
  return 1
}

influx_db_exists() {
  resp="$(curl_influx -G "${INFLUX_QUERY_URL}" --data-urlencode "q=SHOW DATABASES" 2>/dev/null)"
  echo "$resp" | grep -q "\"name\":\"${INFLUX_DB}\""
}

influx_create_db() {
  curl_influx -G "${INFLUX_QUERY_URL}" --data-urlencode "q=CREATE DATABASE ${INFLUX_DB}" >/dev/null 2>&1
}

influx_prepare() {
  now="$(date +%s)"
  if [ -f "$INFLUX_READY_MARK" ]; then
    last="$(cat "$INFLUX_READY_MARK" 2>/dev/null)"
    [ -z "$last" ] && last=0
    age=$(( now - last ))
    if [ "$age" -ge 0 ] && [ "$age" -lt "$INFLUX_CACHE_SEC" ]; then
      return 0
    fi
  fi

  if ! influx_healthcheck; then
    return 1
  fi

  if ! influx_db_exists; then
    influx_create_db
    influx_db_exists || return 1
  fi

  echo "$now" > "$INFLUX_READY_MARK" 2>/dev/null || true
  return 0
}

influx_write_line() {
  line="$1"
  resp="$(curl_influx -i -H 'Content-Type: text/plain' --data-binary "$line" "${INFLUX_WRITE_URL}" 2>&1)"
  rc=$?

  if [ $rc -ne 0 ]; then
    log "INFLUX WRITE FAIL rc=$rc url=${INFLUX_WRITE_URL} resp=$(echo "$resp" | tr '\n' ' ' | cut -c1-300)"
    return 1
  fi

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

# ---------- paths (log/base) ----------
BASE="/var/log/radacct/datacounter/$TIMERANGE"
MAXFILE="$BASE/max-octets-$USERNAME"
USEDFILE="$BASE/used-octets-$USERNAME"
SESSFILE="$BASE/used-octets-$USERNAME-$SESSIONID"

# ---------- kick spool (in /var/run => reboot clears) ----------
KICKDIR="/var/run/datacounter-kick"
mkdir -p "$KICKDIR" 2>/dev/null || true

# ---------- state (✅ move to /var/run => reboot clears) ----------
STATE_ROOT="/var/run/datacounter-state"
STATE_DIR="${STATE_ROOT}/${TIMERANGE}"
mkdir -p "$STATE_DIR" 2>/dev/null || true
STATEFILE="${STATE_DIR}/state-${USERNAME}-${SESSIONID}"

# ✅ 핵심: kick 파일이 소비/삭제돼도 재생성하지 않도록 마커 유지 (also in /var/run)
KICKMARK="${KICKDIR}/${USERNAME}.${SESSIONID}.${TIMERANGE}.kick.sent"
KICKDONE="${KICKDIR}/${USERNAME}.${SESSIONID}.${TIMERANGE}.kick.done"

# Ensure base dir exists
[ -d "$BASE" ] || mkdir -p "$BASE" 2>/dev/null

# ---------- quota not enabled ----------
[ ! -f "$MAXFILE" ] && exit 0

# ---------- init total file ----------
if [ ! -f "$USEDFILE" ]; then
  echo 0 > "$USEDFILE"
fi

# ---------- state TTL cleanup (lightweight) ----------
# Stop이 안 오는 세션이 계속 state를 남길 수 있으니,
# 1시간에 1번만, 12시간 이상 된 state를 /var/run에서 청소.
CLEAN_STAMP="${STATE_ROOT}/.cleanup_stamp"
NOW_TS="$(date +%s)"
last_clean=0
if [ -f "$CLEAN_STAMP" ]; then
  last_clean="$(cat "$CLEAN_STAMP" 2>/dev/null)"
  [ -z "$last_clean" ] && last_clean=0
fi
if [ $((NOW_TS - last_clean)) -ge 3600 ] 2>/dev/null; then
  echo "$NOW_TS" > "$CLEAN_STAMP" 2>/dev/null || true
  find "$STATE_ROOT" -type f -name "state-*" -mmin +720 -delete >/dev/null 2>&1 || true
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

# ---------- STOP zero-guard ----------
if [ "$STATUS" = "Stop" ] && [ "$CUR_TOTAL" -eq 0 ]; then
  tmp="${STATEFILE}.$$"
  {
    echo "last_seen_ts=$NOW_TS"
    echo "last_stop_zero_ts=$NOW_TS"
    echo "username=$USERNAME"
    echo "timerange=$TIMERANGE"
    echo "sessionid=$SESSIONID"
  } > "$tmp" 2>/dev/null && mv -f "$tmp" "$STATEFILE" 2>/dev/null || true

  # ✅ 이미 kick 요청 만든 적 있으면 재생성 금지
  if [ -f "$KICKMARK" ] || [ -f "$KICKDONE" ]; then
    log "DATACOUNTER IGNORE STOP user=$USERNAME range=$TIMERANGE sid=$SESSIONID (zero octets; kick already marked)"
    exit 0
  fi

  # (선택) stop_zero도 kick 요청 남기기
  KICKFILE="${KICKDIR}/${USERNAME}.${SESSIONID}.${TIMERANGE}.kick"
  if [ ! -f "$KICKFILE" ]; then
    {
      echo "ts=$NOW_TS"
      echo "reason=stop_zero"
      echo "username=$USERNAME"
      echo "timerange=$TIMERANGE"
      echo "acctsessionid=$SESSIONID"
    } > "${KICKFILE}.$$" 2>/dev/null && mv -f "${KICKFILE}.$$" "$KICKFILE" 2>/dev/null || true

    # ✅ 마커 생성(핵심)
    : > "$KICKMARK" 2>/dev/null || true
  fi

  log "DATACOUNTER IGNORE STOP user=$USERNAME range=$TIMERANGE sid=$SESSIONID (zero octets)"
  exit 0
fi

# ---------- INTERIM ----------
if [ "$STATUS" = "Interim-Update" ]; then
  # ---- load previous state (best-effort) ----
  last_nonzero_ts=0
  zero_streak=0
  if [ -f "$STATEFILE" ]; then
    last_nonzero_ts="$(grep -E '^last_nonzero_ts=' "$STATEFILE" 2>/dev/null | tail -n1 | cut -d= -f2)"
    zero_streak="$(grep -E '^zero_streak=' "$STATEFILE" 2>/dev/null | tail -n1 | cut -d= -f2)"
    [ -z "$last_nonzero_ts" ] && last_nonzero_ts=0
    [ -z "$zero_streak" ] && zero_streak=0
  fi

  # ---- update streak ----
  if [ "$CUR_TOTAL" -eq 0 ]; then
    zero_streak=$((zero_streak + 1))
  else
    zero_streak=0
    last_nonzero_ts="$NOW_TS"

    # ✅ traffic이 다시 나오면 과거 kick 마커는 해제(일시적 오류 복구)
    [ -f "$KICKMARK" ] && rm -f "$KICKMARK" 2>/dev/null || true
    [ -f "$KICKDONE" ] && rm -f "$KICKDONE" 2>/dev/null || true
  fi

  # ---- persist state (atomic) ----
  tmp="${STATEFILE}.$$"
  {
    echo "last_seen_ts=$NOW_TS"
    echo "last_nonzero_ts=$last_nonzero_ts"
    echo "zero_streak=$zero_streak"
    echo "username=$USERNAME"
    echo "timerange=$TIMERANGE"
    echo "sessionid=$SESSIONID"
  } > "$tmp" 2>/dev/null && mv -f "$tmp" "$STATEFILE" 2>/dev/null || true

  # ---- if zero, do NOT overwrite SESSFILE, do NOT export influx ----
  ZERO_STREAK_KICK=2
  ZERO_MIN_AGE_KICK=1200  # 20min

  if [ "$CUR_TOTAL" -eq 0 ]; then
    # ✅ 이미 kick 요청 만든 적 있으면 로그/재요청 자체를 멈춤(로그 폭주 방지)
    if [ -f "$KICKMARK" ] || [ -f "$KICKDONE" ]; then
      exit 0
    fi

    log "DATACOUNTER INTERIM ZERO user=$USERNAME range=$TIMERANGE sid=$SESSIONID streak=$zero_streak"

    age=$(( NOW_TS - last_nonzero_ts ))
    if [ "$zero_streak" -ge "$ZERO_STREAK_KICK" ] || { [ "$last_nonzero_ts" -gt 0 ] && [ "$age" -ge "$ZERO_MIN_AGE_KICK" ]; }; then
      KICKFILE="${KICKDIR}/${USERNAME}.${SESSIONID}.${TIMERANGE}.kick"
      if [ ! -f "$KICKFILE" ]; then
        {
          echo "ts=$NOW_TS"
          echo "reason=interim_zero"
          echo "zero_streak=$zero_streak"
          echo "last_nonzero_age_sec=$age"
          echo "username=$USERNAME"
          echo "timerange=$TIMERANGE"
          echo "acctsessionid=$SESSIONID"
        } > "${KICKFILE}.$$" 2>/dev/null && mv -f "${KICKFILE}.$$" "$KICKFILE" 2>/dev/null || true

        # ✅ 마커 생성(핵심)
        : > "$KICKMARK" 2>/dev/null || true

        log "DATACOUNTER KICK-REQUEST user=$USERNAME range=$TIMERANGE sid=$SESSIONID reason=interim_zero streak=$zero_streak age=${age}s"
      fi
    fi
    exit 0
  fi

  # ---- 정상(non-zero) 처리: SESSFILE 기록 + 로그 + Influx export ----
  echo "$CUR_TOTAL" > "$SESSFILE"

  log "DATACOUNTER INTERIM user=$USERNAME range=$TIMERANGE sid=$SESSIONID in=$((CUR_IN/1048576))MB out=$((CUR_OUT/1048576))MB total=$((CUR_TOTAL/1048576))MB"

  # ===== InfluxDB 1.8 export =====
  MEASUREMENT="datacounter_interim"

  now_s="$NOW_TS"
  bucket_s=$(( (now_s / 600) * 600 ))
  ts_m=$(( bucket_s / 60 ))

  in_bytes=$(( CUR_IN ))
  out_bytes=$(( CUR_OUT ))
  total_bytes=$(( CUR_TOTAL ))

  line="${MEASUREMENT},user=$(esc_tag "$USERNAME"),range=$(esc_tag "$TIMERANGE") in_bytes=${in_bytes}i,out_bytes=${out_bytes}i,total_bytes=${total_bytes}i ${ts_m}"

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

  # ✅ state/kick 마커 정리 (Stop 들어오면 세션 종료로 보고 청소)
  STATEFILE="/var/run/datacounter-state/$TIMERANGE/state-${USERNAME}-${SESSIONID}"
  rm -f "$STATEFILE" 2>/dev/null || true

  KICKDIR="/var/run/datacounter-kick"
  rm -f "$KICKDIR/${USERNAME}.${SESSIONID}.${TIMERANGE}.kick.sent" 2>/dev/null || true
  rm -f "$KICKDIR/${USERNAME}.${SESSIONID}.${TIMERANGE}.kick.done" 2>/dev/null || true

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
