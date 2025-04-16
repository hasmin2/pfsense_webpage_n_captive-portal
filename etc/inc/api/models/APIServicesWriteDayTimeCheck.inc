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
class APIServicesUpdateDayTimeCheck extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-gateways"];
    }
    public function action() {
        global $config;
        if($this->initial_data['dayNight'] && $this->initial_data['sunriseTime'] && $this->initial_data['sunsetTime']){
            $datetimestring = array("daynight" => $this->initial_data['dayNight'], "sunrisetime" => $this->initial_data['sunriseTime'], "sunsettime" => $this->initial_data['sunsetTime']);
            $this->config['daytimecheck']= $datetimestring;
            write_config("Date and Time Check updated");
            return APIResponse\get(0, ("applied successful"));
        }
        else {
            return APIResponse\get(0,("No data defined, put 'dayNight', 'sunriseTime', and 'sunsetTime' and input proper format as JSON MAP format"));
        }
    }
}
?>