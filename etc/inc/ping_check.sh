#/bin/sh
## $1 => gateway_timeout
# $2 => source_ip (nic ip)
# $3 => gateway_ip (GW IP)
# $4 => destination (8.8.8.8, 168,126 or etc)

if [ $# -ne 4 ];then
	 exit 1;
fi

route add -host $4 $3
ping -c1 -t $1 -S $2 $4
if [ $? == 0 ];then
	echo "online" > /etc/inc/$2.log
else
	echo "offline" > /etc/inc/$2.log
fi
route del -host $4 $3