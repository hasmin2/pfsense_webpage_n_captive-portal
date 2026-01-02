#!/bin/sh
#/usr/bin/timeout 5 sh -c '( ( sleep 1 ; echo admin ; sleep 1 ; echo admin ; sleep 1 ; echo "show interface | include line protocol | PVID" ; sleep 1; exit ) | telnet $1 ) > /etc/inc/$1.log' _ "$1"
/usr/bin/timeout 5 sh -c '
(
  sleep 1
  printf "\025admin\r"
  sleep 1
  printf "admin\r"
  sleep 2
  printf "show interface | include line protocol | PVID\r"
  sleep 1
) | telnet "$1"
' _ "$1" > "$1.log" 2>&1

