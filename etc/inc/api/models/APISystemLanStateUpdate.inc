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

class APISystemLanStateUpdate extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-advanced-admin"];
        $this->change_note = "Reset LANstate by operator lan connect/disconnect";
    }

    public function action() {
        if(isset($this->initial_data['lanstate'])){
            $lanstate["lanstate"] = $this->initial_data['lanstate'];
            $this->config['interface']=$lanstate;
            $this->write_config();
            return APIResponse\get(0,($this->config['interface']['lanstate']));
        }
        else {
            return APIResponse\get(0,("no lanstate defined, put 'lanstate' and input proper format as JSON MAP format"));
        }
    }
}
