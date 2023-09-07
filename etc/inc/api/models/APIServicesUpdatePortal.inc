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
require_once("captiveportal.inc");
class APIServicesUpdatePortal extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-gateways"];
    }
    public function action() {
        # Local variables
        if(!isset($this->initial_data['zone']) ||!isset($this->initial_data['portalactive'])){
            return APIResponse\get(5000, "Missing 'zone', or'portalactive' parameters");
        }

        else{
            if(!$this->config["captiveportal"][$this->initial_data['zone']]){
		        return APIResponse\get(5000, "Zone was not found");
	        }
	        else{
	            if($this->initial_data['portalactive']){
			$this->config["captiveportal"][$this->initial_data['zone']]['enable']="";
	    	    }
	    	    else{
			unset($this->config["captiveportal"][$this->initial_data['zone']]['enable']);
	    	    }
            }
            write_config("CaptivePortal Toggle by landline Active");
		captiveportal_configure();
	      filter_configure();
        }
        return APIResponse\get(0, $this->config["captiveportal"][$this->initial_data['zone']]);
    }
}

?>