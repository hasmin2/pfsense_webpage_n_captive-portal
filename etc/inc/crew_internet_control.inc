<?php
require_once ("captiveportal.inc");

function toggle_crew_wifi($toggle){
    global $config;
    if($toggle=="1"){
        $config['captiveportal']['crew']['enable']='';
        enable_crew_freewifi();
    }
    else{
        unset($config['captiveportal']['crew']['enable']);
        disable_crew_freewifi();
    }

    write_config("Portal Change");
    captiveportal_configure();
}
function terminate_biz_internet($toggle, $ipaddr){
    $serverip = $_SERVER['SERVER_ADDR'];
    global $config;
    if($toggle=="1"){
        $config['ban_all']='';
        $config['ban_all_ip']=$ipaddr;
        add_linked_rule($serverip, $ipaddr);
    }
    else{
        unset($config['ban_all']);
        del_linked_rule($serverip, $ipaddr);
    }
    write_config("Portal Change");
    filter_configure();
}
function terminate_crew_internet($toggle, $duration){
    global $config;
    $date = new DateTime();
    if($toggle==="1"){
        $config['captiveportal']['crew']['terminate_duration']=$duration;
        $config['captiveportal']['crew']['terminate_timestamp']=round($date->getTimestamp()/60, 0);
        //captiveportal_disconnect_all($term_cause = 6, $logoutReason = "DISCONNECT", $carp_loop = false);
    }
    //$terminate_timeleft = $config['captiveportal']['crew']['terminate_duration']-(round($date->getTimestamp()/60,0) -$config['captiveportal']['crew']['terminate_timestamp']);
    else{
        unset($config['captiveportal']['crew']['terminate_duration']);
        unset($config['captiveportal']['crew']['terminate_timestamp']);
    }
    write_config("Portal Change");
    captiveportal_configure();
    filter_configure();

}
function get_interfacename(){
    global $config;
    foreach($config['interfaces'] as $ifname => $ifitem){
        if($ifitem['descr'] == 'BUSINESS'){
            $interface = $ifname;
            break;
        }
    }
    return $interface;
}
function get_crewinterface(){
    global $config;
    foreach($config['interfaces'] as $ifname => $ifitem){
        if($ifitem['descr'] == 'CREW'){
            $interface = $ifname;
            break;
        }
    }
    return $interface;
}

function del_linked_rule($serverip, $clientip){
    global $config;
    $interface = get_interfacename();
    if(isset($interface)) {
        foreach ($config['filter']['rule'] as $key => $rule) {
            if ($rule['type']=='pass'
                && $rule['interface']==$interface
                && $rule['source']['address']==$clientip
                && $rule['descr']=="[User Rule] {$clientip} allow only 'this' PC"){
                unset($config['filter']['rule'][$key]);
            }
            if ($rule['type']=='block'
                && $rule['interface']==$interface
                && $rule['source']['network']==$interface
                && $rule['destination']['network']=='(self)'
                && $rule['destination']['not']==''
                && $rule['descr']=="[User Rule] {$clientip} ban-all-rule"){
                unset($config['filter']['rule'][$key]);
            }
        }
    }
}
function add_linked_rule($serverip, $clientip){
    global $config;
    $interface = get_interfacename();
    if(isset($interface)){
        $newrule = array();
        $newrule['id'] = '';
        $newrule['tracker']=time();
        $newrule['type']='block';
        $newrule['interface']=$interface;
        $newrule['ipprotocol']='inet';
        $newrule['tag'] = '';
        $newrule['tagged'] = '';
        $newrule['max'] = '';
        $newrule['max-src-nodes'] = '';
        $newrule['max-src-conn'] = '';
        $newrule['max-src-states'] = '';
        $newrule['statetimeout'] = '';
        $newrule['statetype'] = 'keep state';
        $newrule['os'] = '';
        $newrule['source']['network']=$interface;
        $newrule['destination']['network']='(self)';
        $newrule['destination']['not']='';
        $newrule['descr']="[User Rule] {$clientip} ban-all-rule";
        $newrule['gateway']="";
        $newrule['updated']['time']=time();
        $newrule['updated']['username']='admin@{$clientip}';
        $newrule['created']['time']=time();
        $newrule['created']['username']='admin@{$clientip}';
        array_unshift($config['filter']['rule'], $newrule);
        $newrule['type']='pass';
        unset($newrule['source']['network']);
        unset($newrule['destination']['network']);
        unset($newrule['destination']['not']);
        $newrule['source']['address']=$clientip;
        $newrule['destination']['any']='';
        $newrule['descr']="[User Rule] {$clientip} allow only 'this' PC";
        array_unshift($config['filter']['rule'], $newrule);
    }
    return $interface;
}

function disable_crew_freewifi(){
    global $config;
    $interface = get_crewinterface();
    if(isset($interface)) {
        foreach ($config['filter']['rule'] as $key => $rule) {
            if ($rule['type']=='pass'
                && $rule['interface']==$interface
                && $rule['descr']==="[User Rule] enable_crew_wifi"){
                unset($config['filter']['rule'][$key]);
            }
        }
    }
}
function enable_crew_freewifi(){
    global $config;
    $interface = get_crewinterface();
    if(isset($interface)){
        $newrule = array();
        $newrule['id'] = '';
        $newrule['tracker']=time();
        $newrule['type']='pass';
        $newrule['interface']=$interface;
        $newrule['ipprotocol']='inet';
        $newrule['tag'] = '';
        $newrule['tagged'] = '';
        $newrule['max'] = '';
        $newrule['max-src-nodes'] = '';
        $newrule['max-src-conn'] = '';
        $newrule['max-src-states'] = '';
        $newrule['statetimeout'] = '';
        $newrule['statetype'] = 'keep state';
        $newrule['os'] = '';
        $newrule['source']['network']=$interface;
        $newrule['destination']['network']='(self)';
        $newrule['destination']['not']='';
        $newrule['descr']="[User Rule] enable_crew_wifi";
        $newrule['gateway']="";
        $newrule['updated']['time']=time();
        $newrule['updated']['username']='admin@{$clientip}';
        $newrule['created']['time']=time();
        $newrule['created']['username']='admin@{$clientip}';
        array_unshift($config['filter']['rule'], $newrule);
    }
    return $interface;
}
?>