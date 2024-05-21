#!/bin/sh
( ( sleep 1 ; echo admin ; sleep 1 ; echo admin ; sleep 1 ; echo "show interface | include line protocol | PVID" ; sleep 1; exit ) | telnet $1 ) > /etc/inc/$1.log