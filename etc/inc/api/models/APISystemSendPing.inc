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
	if(!$this->initial_data['timeoutinsec']){
	    return APIResponse\get(3017,"timeoutinsec missing");
	}
	if(!$this->initial_data['destination']){
	    return APIResponse\get(4005,"destination missing");
	}
	else{
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
		    $gw["pingresult"] = mwexec('ping -c1 -t '.$this->initial_data['timeoutinsec'].' -S '.$gw['srcip'].' '.$this->initial_data['destination'])==0 ? "online": "offline";
		}
		else{
			$gw["pingresult"]="offline";
		}
                $gw_metrics[] = $gw;
	    }
		$config['gatewaystatus'][$gw['name']]['pingresult']=$gw['pingresult'];
        }
	write_config("Write ping result");
        return APIResponse\get(0,$gw_metrics);
    }
}
}
