<?php
require_once("status_traffic_totals.inc");
global $config;
$filepath = "/etc/inc/";
////////////////////
/// VLAN state write
////////////////////
$vlandevices=$config['vlan_device']['item'];
if ($config['vlan_device']['item'] && $vlandevices[0]!==""){
    $newstate = [];
    foreach($vlandevices as $vlandevice){
        // 느린 telnet 조회(장치당 최대 12초)는 락 밖에서 수행.
        mwexec("sh ".$filepath."vlanstate.sh ".$vlandevice);
        $handle = fopen($filepath.$vlandevice.".log", "r");
        if ($handle) {
            $vlan_state='';
            $vlan_id='';
            while (($line = fgets($handle)) !== false) {
                if(strpos($line,"Ethernet")!==false){
                    if(strpos($line, 'up')!==false){
                        $vlan_state.="UP||";
                    }
                    else{
                        $vlan_state.="DN||";
                    }
                    $pvidarray = explode( " ", fgets($handle));//next line
                    if(trim(preg_replace('/\s\s+/', ' ', $pvidarray[4])) === ''){
                        $vlan_id.='1||';
                    }
                    else{
                        $vlan_id.=trim(preg_replace('/\s\s+/', ' ', $pvidarray[4])).'||';
                    }
                }
            }
            fclose($handle);
        }
        array_push($newstate, ['id'=>$vlan_id, 'state'=>$vlan_state,'ipaddr'=>$vlandevice]);
    }

    // 변경 판정(스냅샷 기준). 어느 경우든 저장값은 $newstate 이다(기존 로직의 net 결과와 동일:
    // device 제거 시에도 [""] 로 초기화 후 비교루프가 devicechanged 를 만들어 결국 $newstate 를 씀).
    $deviceRemoved = (count($config['vlan_device']['item']) < count($config['vlan_device']['config']));
    $ischanged=false;
    $devicechanged=false;
    foreach($newstate as $eachstate){
        $devicechanged=true;
        foreach($config['vlan_device']['config'] as $vlan_device){
            if($vlan_device['ipaddr']===$eachstate['ipaddr']){
                $devicechanged=false;
                if($eachstate['state']!==$vlan_device['state'] || $eachstate['id']!==$vlan_device['id']) {
                    $ischanged = true;
                    break;
                }
            }
        }
        if($ischanged||$devicechanged){
            break;
        }
    }

    if($deviceRemoved){
        echo "vlan device removed from shoreside\n";
    }
    if($deviceRemoved || $ischanged || $devicechanged){
        echo "vlan state changed\n";
        // lost-update 방지: 느린 telnet 은 위에서 끝났고, 락 안에서 최신본(parse_config(true))
        // 재로딩 후 vlan_device.config 만 갱신한다. 스냅샷 전체를 저장하지 않으므로 동시
        // PW 변경 등을 덮지 않는다. (PW writer 와 같은 lock('freeradius_user_config') 공유.)
        $cnf_lock = lock('freeradius_user_config', LOCK_EX);
        try {
            $config = parse_config(true);
            if (!isset($config['vlan_device']) || !is_array($config['vlan_device'])) {
                $config['vlan_device'] = array();
            }
            $config['vlan_device']['config']=$newstate;
            write_config('vlan_config');
        } finally {
            unlock($cnf_lock);
        }
    }
    else {
        echo "vlan state Not changed\n";
    }
}
