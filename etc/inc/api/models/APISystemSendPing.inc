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
	if(!$this->initial_data['timeoutinsec']){
	    return APIResponse\get(3017,"timeoutinsec missing");
	}
	if(!$this->initial_data['destination']){
	    return APIResponse\get(4005,"destination missing");
	}
	if(!$this->initial_data['interface']){
	    return APIResponse\get(3010,"begin address missing");
	}
	else{
	    foreach(return_gateways_status(true) as $key => $value){
		if($value['name']==$this->initial_data['interface']){
		    if(substr($value['loss'], 0, 3)){
			$output["network"]='1';
	    	        $output["internet"] = mwexec('ping -c1 -t '.$this->initial_data['timeoutinsec'].' -S '.$value['srcip'].' '.$this->initial_data['destination']);
		    }
		    else{
			$output["network"]='0';
			$output["internet"]='2';

		    }
		}
	    }
	}

        return APIResponse\get(0,$output);
//return APIResponse\get(0,return_gateways_status(true));


    }

}
