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
    if(count($config['vlan_device']['item'])<count($config['vlan_device']['config'])){
        echo "vlan device removed from shoreside\n";
        $config['vlan_device']['config']=[""];
        write_config('vlan_config');
    }
    $ischanged=false;
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
    if($ischanged||$devicechanged){
        $config['vlan_device']['config']=$newstate;
        echo "vlan state changed\n";
        write_config('vlan_config');
    }
    else {
        echo "vlan state Not changed\n";
    }
}
