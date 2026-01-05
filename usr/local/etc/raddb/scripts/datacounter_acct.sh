#!/bin/sh
#
# USAGE:
# datacounter_acct.sh USER TIMERANGE IN_OCTETS OUT_OCTETS IN_GW OUT_GW STATUS SESSIONID
#

USERNAME=$(echo -n "$1" | sed 's/[^0-9a-zA-Z.:_-]/X/g')
TIMERANGE=$(echo -n "$2" | sed 's/[^a-z]//g')

IN_OCTETS=$(echo -n "$3" | sed 's/[^0-9]/0/g')
OUT_OCTETS=$(echo -n "$4" | sed 's/[^0-9]/0/g')

IN_GW=$(echo -n "$5" | sed 's/[^0-9]/0/g')
OUT_GW=$(echo -n "$6" | sed 's/[^0-9]/0/g')

STATUS="$7"
SESSIONID="$8"

BASE="/var/log/radacct/datacounter/$TIMERANGE"
MAXFILE="$BASE/max-octets-$USERNAME"
USEDFILE="$BASE/used-octets-$USERNAME"
SESSFILE="$BASE/used-octets-$USERNAME-$SESSIONID"

# ---------- sanity ----------
[ -z "$IN_OCTETS" ] && IN_OCTETS=0
[ -z "$OUT_OCTETS" ] && OUT_OCTETS=0
[ -z "$IN_GW" ] && IN_GW=0
[ -z "$OUT_GW" ] && OUT_GW=0
[ -z "$SESSIONID" ] && SESSIONID="nosession"

# quota not enabled
[ ! -f "$MAXFILE" ] && exit 0

# init total file
[ ! -f "$USEDFILE" ] && echo 0 > "$USEDFILE"

# ---------- 64bit current value ----------
CUR_IN=$(( (IN_GW << 32) + IN_OCTETS ))
CUR_OUT=$(( (OUT_GW << 32) + OUT_OCTETS ))
CUR_TOTAL=$(( CUR_IN + CUR_OUT ))

# ---------- STOP zero-guard (CRITICAL FIX) ----------
if [ "$STATUS" = "Stop" ] && [ "$CUR_TOTAL" -eq 0 ]; then
    logger -f /var/log/system.log \
      "DATACOUNTER IGNORE STOP user=$USERNAME range=$TIMERANGE sid=$SESSIONID (zero octets)"
    exit 0
fi

# ---------- INTERIM ----------
if [ "$STATUS" = "Interim-Update" ]; then
    echo "$CUR_TOTAL" > "$SESSFILE"

    logger -f /var/log/system.log \
      "DATACOUNTER INTERIM user=$USERNAME range=$TIMERANGE sid=$SESSIONID \
IN=$((CUR_IN/1048576))MB OUT=$((CUR_OUT/1048576))MB TOTAL=$((CUR_TOTAL/1048576))MB \
(GW in=$IN_GW out=$OUT_GW)"

    exit 0
fi

# ---------- STOP / others ----------
OLD_TOTAL=$(cat "$USEDFILE" 2>/dev/null)
[ -z "$OLD_TOTAL" ] && OLD_TOTAL=0

NEW_TOTAL=$(( OLD_TOTAL + CUR_TOTAL ))

# clear session file if exists
[ -f "$SESSFILE" ] && rm -f "$SESSFILE"

echo "$NEW_TOTAL" > "$USEDFILE"

logger -f /var/log/system.log \
  "DATACOUNTER STOP user=$USERNAME range=$TIMERANGE sid=$SESSIONID \
IN=$((CUR_IN/1048576))MB OUT=$((CUR_OUT/1048576))MB TOTAL=$((CUR_TOTAL/1048576))MB \
ACCUM=$((NEW_TOTAL/1048576))MB (GW in=$IN_GW out=$OUT_GW)"

exit 0
