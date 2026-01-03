## USAGE: datacounter_acct.sh USERNAME TIMERANGE ACCTINPUTOCTETS ACCTOUTPUTOCTETS
### We need this from an Accounting-Request packet to count the octets
USERNAME=`echo -n "\$1" | sed 's/[^0-9a-zA-Z.:_-]/X/g' `
TIMERANGE=`echo -n "\$2" | sed 's/[^a-z]//g' `
ACCTINPUTOCTETS=`echo -n "\$3" | sed 's/[^0-9]/0/g' `
ACCTOUTPUTOCTETS=`echo -n "\$4" | sed 's/[^0-9]/0/g' `
UPDATETYPE=$5
SESSIONID=$6

### If we do not get Octets we set some default values
if [ ! $ACCTINPUTOCTETS ]; then
        ACCTINPUTOCTETS=0
fi
if [ ! $ACCTOUTPUTOCTETS ]; then
        ACCTOUTPUTOCTETS=0
fi

### We only write this to the file if username exists
### If all counters are activated (daily, weekly, monthly, forever) we need to check which is active for the user
if [ ! -e "/var/log/radacct/datacounter/$TIMERANGE/max-octets-$USERNAME" ]; then
        exit 0
else
        ### If no used-octets file exist then we assume that it was deleted by cron job and we need to create a new file starting from zero
    if [ ! -e "/var/log/radacct/datacounter/$TIMERANGE/used-octets-$USERNAME" ]; then
                echo 0 > "/var/log/radacct/datacounter/$TIMERANGE/used-octets-$USERNAME"
        fi
    USEDOCTETS=$(($ACCTINPUTOCTETS+$ACCTOUTPUTOCTETS))
    # If this is an interim update, track it in a separate session file
    # since the incoming data is a gauge not a counter.
    if [ $UPDATETYPE = "Interim-Update" ]; then
        echo $USEDOCTETS > "/var/log/radacct/datacounter/$TIMERANGE/used-octets-$USERNAME-$SESSIONID"
        logger -f /var/log/system.log "FreeRADIUS: User $USERNAME has used $USEDOCTETS MB IN  $TIMERANGE allotted traffic with interim traffic."
    else
        USEDOCTETS=$(($USEDOCTETS+`cat "/var/log/radacct/datacounter/$TIMERANGE/used-octets-$USERNAME"`))
        # If there was a session file for this session (from interim updates) clear it since the equivalent
        # value was just added to the total.
        if [ -e "/var/log/radacct/datacounter/$TIMERANGE/used-octets-$USERNAME-$SESSIONID" ]; then
            rm "/var/log/radacct/datacounter/$TIMERANGE/used-octets-$USERNAME-$SESSIONID"
        fi
        echo "$USEDOCTETS" > "/var/log/radacct/datacounter/$TIMERANGE/used-octets-$USERNAME"
        logger -f /var/log/system.log "FreeRADIUS: User $USERNAME has used $USEDOCTETS MB IN  $TIMERANGE allotted traffic with outsess session ."
    fi
    exit 0
fi
~