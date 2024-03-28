<?php
//hasmin build
require_once ("captiveportal.inc");
function destroy_firewall_preset(){
    global $config;
    foreach ($config['filter']['rule'] as $index => $ruleitem){
        if($ruleitem['descr']==='[System Rule] Auto generated rule for firewall-preset'){
            unset($config['filter']['rule'][$index]);
        }
    }
    unset($config['captiveportal']['crew']['terminate_duration']);
    unset($config['captiveportal']['crew']['terminate_timestamp']);
}
function build_firewall_preset($defaultgw){
    global $config;
    openlog("logportalauth", LOG_PID, LOG_LOCAL4);
    $disablecrewinternet="";
    $blockall="";
    foreach ($config['gateways']['gateway_item'] as $gatewayitem){
        if( $defaultgw===$gatewayitem['name'] && isset($gatewayitem['sourceaddresses'])){
            if($gatewayitem['sourceaddresses']!==''){
                $source_addresses = explode("||", $gatewayitem['sourceaddresses']);
                $dest_addresses = explode("||", $gatewayitem['destaddresses']);
                $port_from = explode("||", $gatewayitem['portsfrom']);
                $port_to = explode("||", $gatewayitem['portsto']);
                $proto = explode("||", $gatewayitem['protos']);
            }
            if($gatewayitem['blockall_bydefault']==='yes'){
                $blockall=$gatewayitem['blockall_bydefault'];
            }
            if($gatewayitem['disablecrewinternet']==='yes'){
                $disablecrewinternet=$gatewayitem['disablecrewinternet'];
            }
        }
        //syslog(LOG_INFO, "firewallpresetsubloop:$blockall");
    }

    // Log it
    syslog(LOG_INFO, "firewallpreset:$blockall");
      //GET LAN INTERFACE from iflist
    $laniflist=array();
    foreach($config['interfaces']as $ifname => $ifitem){
        if(!isset($ifitem['alias-address']) && strpos($ifitem['if'],"ovpnc")===false){
            array_push($laniflist, $ifname);
        }
    }
    $crewif=explode(",", $config['captiveportal']['crew']['interface']);
    if($blockall==='yes') {
        foreach($laniflist as $lanitem) {
            $iscrewitem=false;
            foreach($crewif as $crewitem){
                if($lanitem===$crewitem){
                    $iscrewitem=true;
                    break;
                }
            }
            if(!$iscrewitem) {
                //1. BLOCK ALL RULE 만듬. (floating rule
                //initial firewall rule for block all
                $newrule = array();
                $newrule['id'] = '';
                $newrule['tracker'] = time();
                $newrule['type'] = 'block';
                $newrule['interface'] = $lanitem;
                $newrule['ipprotocol'] = 'inet';
                $newrule['tag'] = '';
                $newrule['tagged'] = '';
                $newrule['direction'] = 'any';
                $newrule['max'] = '';
                $newrule['max-src-nodes'] = '';
                $newrule['max-src-conn'] = '';
                $newrule['max-src-states'] = '';
                $newrule['statetimeout'] = '';
                $newrule['statetype'] = 'keep state';
                $newrule['os'] = '';
                $newrule['source']['any'] = '';
                $newrule['destination']['network'] = '(self)';
                $newrule['destination']['not'] = '';
                $newrule['descr'] = '[System Rule] Auto generated rule for firewall-preset';
                $newrule['created']['time'] = time();
                $newrule['created']['username'] = 'sysadmin';
                $newrule['updated']['time'] = time();
                $newrule['updated']['username'] = 'sysadmin';
                array_unshift($config['filter']['rule'], $newrule);
                //2. FLOATING RULE 에 해당 룰 만듬.
                $counter = 0;
                while ($counter < count($source_addresses) - 1) {
                    //if there is URI structure is here, do alias first.
                    $newrule = array();
                    $newrule['id'] = '';
                    $newrule['tracker'] = time();
                    $newrule['type'] = 'pass';
                    $newrule['interface'] = $lanitem;
                    $newrule['ipprotocol'] = 'inet';
                    $newrule['tag'] = '';
                    $newrule['tagged'] = '';
                    $newrule['direction'] = 'any';
                    $newrule['max'] = '';
                    $newrule['max-src-nodes'] = '';
                    $newrule['max-src-conn'] = '';
                    $newrule['max-src-states'] = '';
                    $newrule['statetimeout'] = '';
                    $newrule['statetype'] = 'keep state';
                    $newrule['os'] = '';
                    if ($source_addresses[$counter] !== '') {
                        $newrule['source']['address'] = $source_addresses[$counter];
                    } else {
                        $newrule['source']['any'] = '';
                    }
                    if ($dest_addresses[$counter] !== '') {
                        //if destination is url, then do alias first.
                        //TODO make alias
                        $newrule['destination']['address'] = $dest_addresses[$counter];
                    } else {
                        $newrule['destination']['any'] = '';
                    }
                    if ($port_from[$counter] !== '') {
                        $newrule['destination']['port'] = $port_from[$counter];
                        if ($port_to[$counter] !== '') {
                            $newrule['destination']['port'] .= '-' . $port_to[$counter];
                        }
                    }
                    if ($proto[$counter] !== 'any') {
                        $newrule['protocol'] = $proto[$counter];
                    }
                    $newrule['descr'] = '[System Rule] Auto generated rule for firewall-preset';
                    $newrule['created']['time'] = time();
                    $newrule['created']['username'] = 'sysadmin';
                    $newrule['updated']['time'] = time();
                    $newrule['updated']['username'] = 'sysadmin';
                    array_unshift($config['filter']['rule'], $newrule);
                    $counter++;
                }
            }
        }
    }
    if($disablecrewinternet==="yes"){
        $config['captiveportal']['crew']['terminate_duration']='100000000';
        $date = new DateTime();
        $config['captiveportal']['crew']['terminate_timestamp']=round($date->getTimestamp()/60, 0);
        captiveportal_disconnect_all($term_cause = 6, $logoutReason = "DISCONNECT", $carp_loop = false);
    }

    closelog();
}
?>