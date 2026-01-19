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
TIMERANGE=$(echo -n "$RAW_RANGE" | sed 's/[^a-z]//g')

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
# 32-bit octets upper bound (defensive)
MAX_OCTETS=4294967295
[ "$IN_OCTETS" -gt "$MAX_OCTETS" ] && IN_OCTETS=0
[ "$OUT_OCTETS" -gt "$MAX_OCTETS" ] && OUT_OCTETS=0

# Gigawords should be small in real life; keep a generous cap to avoid corruption.
# 100000 GW ~= 100000 * 4GiB ~= ~400 TiB
MAX_GW=100000
if [ "$IN_GW" -gt "$MAX_GW" ] || [ "$OUT_GW" -gt "$MAX_GW" ]; then
  log "DATACOUNTER IGNORE $STATUS user=$USERNAME range=$TIMERANGE sid=$SESSIONID (invalid gigawords in=$IN_GW out=$OUT_GW)"
  exit 0
fi

# ---------- 64-bit current value ----------
# FreeBSD /bin/sh arithmetic is signed 64-bit; keep within sane range above.
CUR_IN=$(( (IN_GW << 32) + IN_OCTETS ))
CUR_OUT=$(( (OUT_GW << 32) + OUT_OCTETS ))
CUR_TOTAL=$(( CUR_IN + CUR_OUT ))

# If arithmetic wrapped negative for any reason, drop it to avoid corrupting totals.
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

    exit 0
fi


# ---------- STOP / others ----------
# To prevent races when multiple exec calls update the same USEDFILE, lock per user+range.
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

  # read old total
  OLD_TOTAL=$(cat "$USEDFILE" 2>/dev/null)
  [ -z "$OLD_TOTAL" ] && OLD_TOTAL=0

  NEW_TOTAL=$(( OLD_TOTAL + CUR_TOTAL ))

  # clear session file if exists
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
