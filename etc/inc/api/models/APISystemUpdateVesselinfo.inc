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
require_once("auth.inc");
require_once("certs.inc");

class APISystemUpdateVesselInfo extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-advanced-admin"];
        $this->change_note = "Modified system console settings";
        $this->validated_data = $this->config["system"];
    }
    public function action() {
        if (is_array($this->initial_data)) {
		    $this->config['system']['vesselinfo'] = $this->initial_data['vesselinfo'];
            if(isset($this->initial_data['accountinfo']['new_id']) && isset($this->initial_data['accountinfo']['new_pw'])){
                foreach($this->config['system']['user'] as $index => $user){
                    if($user['name'] == $this->initial_data['accountinfo']['new_id']){
                        $userent = $index;
                        break;
                    }
                }
		        local_user_set_password($this->config['system']['user'][$userent], $this->initial_data['accountinfo']['new_pw']);
            }
            write_config("Updated vessel info");

            return APIResponse\get(0,$this->config['system']['vesselinfo']);
        }
        else{
            $this->errors[] = APIResponse\get(5001);
	        return APIResponse\get(0,"Input failed you may input proper data or tagname.");
        }
    }
}
