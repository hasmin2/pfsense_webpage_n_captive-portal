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

class APIStatusSetTimeOffset extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-gateways"];
    }
    public function action() {
        if($this->initial_data['time_offset'] ){
            $last_timeoffset = $this->config['time_offset_enabled']['time_offset'];
            if($last_timeoffset!==$this->initial_data['time_offset']){
                if($this -> config['time_offset_enabled']['gmtcheck']=='1'){
                    return APIResponse\get(0, ("Manual timezone applied"));
                }
                $this->config['time_offset_enabled']['time_offset'] = $this->initial_data['time_offset'];
                $this->write_config();
            }
            else{
                return APIResponse\get(0, ("Same Timezone, no change made"));
            }
            return APIResponse\get(0, ("applied successful"));
        }
        else {
            return APIResponse\get(0,("no Data to be applied"));
        }
    }
}

?>