#!/bin/sh
#
# cp_gateway_alarm.sh — CP 라우팅 즉시화 래퍼
#
# dpinger 가 게이트웨이 up/down 알람 시 실행한다 (gwlb.inc start_dpinger 의 -C 명령).
#
# 동작:
#   1) pfSense 표준 핸들러(/etc/rc.gateway_alarm)를 전달받은 인자 그대로 먼저 실행.
#      → 게이트웨이 상태 처리에 일절 영향 없음(behavior parity). 결과 exit code 보존.
#   2) 그 직후(약간의 지연 후, 백그라운드) cp_gw_* pfctl 라우팅 테이블을 재적재.
#      → 표준 핸들러가 filter_configure 로 cp_gw_* 테이블을 flush 하더라도
#        고정(pinned) 사용자가 다른 uplink 로 새어 오과금되는 누수창을 ~수초로 단축.
#
# dpinger 를 블로킹하지 않도록 재적재는 sleep 후 백그라운드로 실행한다.
# (재적재 자체는 config/룰 미수정·pfctl 전용이라 가볍고, 실패해도 무해.)

# 1) 표준 게이트웨이 알람 핸들러 (인자 그대로 전달)
/etc/rc.gateway_alarm "$@"
_rc=$?

# 2) filter 재로드(동기/비동기)가 정착할 시간을 약간 준 뒤 테이블 재적재.
#    매분 크론(cp_routing_table_resync)이 백스톱이므로, 여기선 즉시성 보강만 담당.
( sleep 3; /usr/local/bin/php /usr/local/cron/cp_routing_table_resync.php >/dev/null 2>&1 ) &

exit $_rc
