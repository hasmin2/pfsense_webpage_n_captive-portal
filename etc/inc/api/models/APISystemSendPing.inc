<?php
//   Copyright 2022 Jared Hendrickson
//
//   Licensed under the Apache License, Version 2.0 (the "License");
//   you may not use this file except in compliance with the License.
//   You may obtain a copy of the License at
//
//       http://www.apache.org/licenses/LICENSE-2.0
//
//   Unless required by applicable law or agreed to in writing, software
//   distributed under the License is distributed on an "AS IS" BASIS,
//   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//   See the License for the specific language governing permissions and
//   limitations under the License.

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

class APISystemSendPing extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-advanced-admin"];
        $this->change_note = "Modified system console settings";
        $this->validated_data = $this->config["system"];
    }
	
    public function action() {
	global $config;
	$result=array();

        $gw_metrics_raw = return_gateways_status(true);
        $gw_metrics = [];
		unset($config['gatewaystatus']);
        # Loop through each gateway's metrics, format time data as floats, and update statuses
        foreach ($gw_metrics_raw as $gw) {
	    if(!isset($gw["monitor_disable"]) && !isset($this->initial_data['interface']) || $this->initial_data['interface']==$gw['name']){
                $gw["delay"] = floatval(str_replace(["%", "ms"], "", $gw["delay"]));
                $gw["stddev"] = floatval(str_replace(["%", "ms"], "", $gw["stddev"]));
                $gw["loss"] = floatval(str_replace(["%", "ms"], "", $gw["loss"]));
                $gw["status"] = ($gw["status"] === "none") ? "up" : $gw["status"];
		if($gw["status"] === "online"){
		    if($gw["check_method"]=== "ping"){
		    	$gwList = explode(";", $gw["destinationip"]);
		        $randgw = $gwList[rand(0, count($gwList) - 1)];
				mwexec('route add -host '.$randgw.' '.$gw['monitorip']);
				sleep(1);
				$gw["pingresult"] = mwexec('ping -c1 -t '.$gw['check_timeout'].' -S '.$gw['srcip'].' '.randgw)==0 ? "online": "offline";
				mwexec('route del -host '.randgw.' '.$gw['monitorip']);
		    }
		    else if ($gw["check_method"]=== "nmap"){
		    //TODO
		    }
		    else if ($gw["check_method"]=== 'none'){
		    	$gw["pingresult"] = "online";
		    }
		    else {
		    //TODO
		    	$gw["pingresult"] = "unknown";
		    }
		}
		else{
			$gw["pingresult"]="offline";
		}
                $gw_metrics[] = $gw;
	    }
		$config['gatewaystatus'][$gw['name']]['pingresult']=$gw['pingresult'];
        }
		$config['gatewaystatus']['pingdestination']=$this->initial_data['destination'];
		setup_gateways_monitor();
		write_config("Write ping result");
        return APIResponse\get(0,$gw_metrics);

	}
}