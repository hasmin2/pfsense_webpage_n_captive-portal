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
require_once("/usr/local/pkg/cron.inc");

class APIServiceCronWrite extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-advanced-admin"];
        $this->change_note = "Resetting crontab scheduler";
    }

    public function action() {
        if(isset($this->initial_data['cronlist'])){
            if(isset($this->config['cron']['item'])){
                unset($this->config['cron']['item']);
            }
            $cronlist["item"] = $this->initial_data['cronlist'];
            $this->config['cron']=$cronlist;
            $this->write_config();
            cron_sync_package();
            return APIResponse\get(0,($this->config['cron']['item']));
        }
        else {
            return APIResponse\get(0,("no cronlist defined, put 'cronlist' and input proper format as JSON array"));
        }
    }
}
