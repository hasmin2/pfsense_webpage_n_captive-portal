#!/bin/sh
### USAGE: datacounter_auth.sh USERNAME TIMERANGE
### Example: datacounter_auth.sh crewpay-crust01 monthly

LOGFILE="/var/log/wireless.log"

log() {
	printf '%s datacounter_auth: %s\n' "$(date '+%b %e %H:%M:%S')" "$*" >> "$LOGFILE" 2>/dev/null
}

# 로그 파일 없으면 생성 시도
: >> "$LOGFILE" 2>/dev/null || true

USERNAME=`echo -n "$1" | sed 's/[^0-9a-zA-Z._:-]/X/g'`
TIMERANGE=`echo -n "$2" | sed 's/[^a-z]//g'`

BASE_DIR="/var/log/radacct/datacounter/$TIMERANGE"
MAX_FILE="$BASE_DIR/max-octets-$USERNAME"
USED_FILE="$BASE_DIR/used-octets-$USERNAME"

### Invalid argument guard
if [ -z "$USERNAME" ] || [ -z "$TIMERANGE" ]; then
	log "FreeRADIUS: datacounter_auth.sh invalid arguments. USERNAME='$USERNAME', TIMERANGE='$TIMERANGE'"
	exit 1
fi

### If user has no max-octets file, do not block login
if [ ! -e "$MAX_FILE" ]; then
	exit 0
fi

### Make sure base used-octets file exists after cron reset
if [ ! -e "$USED_FILE" ]; then
	echo 0 > "$USED_FILE"
	rm -f "$USED_FILE-"*
fi

### Read max bytes
MAXOCTETSUSERNAME=`/bin/cat "$MAX_FILE" 2>/dev/null | sed 's/[^0-9]//g'`

if [ -z "$MAXOCTETSUSERNAME" ]; then
	log "FreeRADIUS: User $USERNAME has invalid max-octets value. Login request was denied."
	exit 1
fi

### Sum used bytes
### Important:
### Do NOT use: cat used-octets-* | awk ...
### Because files without newline can be concatenated like 3374670277 + 371347344 => 3374670277371347344
### Glob 은 base 파일 + "-*" 세션 파일만 합산한다. "$USED_FILE"* (대시 없음) 는
### 같은 prefix 의 다른 사용자(예: crust1 ↔ crust10)까지 합산해 과다계상되므로 쓰지 않는다.
USEDOCTETSUSERNAME=`/usr/bin/awk '
	/^[0-9]+$/ {
		SUM += $1
	}
	END {
		printf "%.0f\n", SUM
	}
' "$USED_FILE" "$USED_FILE"-* 2>/dev/null`

if [ -z "$USEDOCTETSUSERNAME" ]; then
	USEDOCTETSUSERNAME=0
fi

### Convert bytes to MB only for log output
MAXOCTETSUSERNAMEMB=`/usr/bin/awk -v v="$MAXOCTETSUSERNAME" 'BEGIN { printf "%.2f\n", v / 1048576 }'`
USEDOCTETSUSERNAMEMB=`/usr/bin/awk -v v="$USEDOCTETSUSERNAME" 'BEGIN { printf "%.2f\n", v / 1048576 }'`

### Compare bytes, not MB
if [ "$MAXOCTETSUSERNAME" -gt "$USEDOCTETSUSERNAME" ]; then
	log "FreeRADIUS: User $USERNAME has used $USEDOCTETSUSERNAMEMB MB of $MAXOCTETSUSERNAMEMB MB $TIMERANGE allotted traffic. The login request was accepted."
	exit 0
else
	log "FreeRADIUS: User $USERNAME has reached the $TIMERANGE amount of upload and download traffic ($USEDOCTETSUSERNAMEMB MB of $MAXOCTETSUSERNAMEMB MB). The login request was denied."
	exit 1
fi