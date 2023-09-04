#/bin/sh
## $1 => gateway_timeout
# $2 => source_ip (nic ip)
# $3 => destination (8.8.8.8, 168,126 or etc)
#$ 4 => port

if [ $# -ne 4 ];then
	 exit 1;
fi

nc -z -w$1 $3 $4 -s$2

if [ $? == 0 ];then
	echo "online" > /etc/inc/$2.log

else
	echo "offline" > /etc/inc/$2.log
fi
