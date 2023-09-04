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

namespace etc\inc\api\models;
use APIModel;

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");
require_once("interfaces.inc");

class APIServiceInterfaceRenew extends APIModel
{
    # Create our method constructor
    public function __construct()
    {
        parent::__construct();
        $this->privileges = ["page-all", "page-status-interfaces"];
    }

    public function action()
    {
        # Update our new user in the config and set the user on the backend
        return APIResponse\get(0, $this->__get_metrics());
    }

    public function validate_payload()
    {
        # Run each validation method
        $this->__run_renew();
    }

    private function __run_renew()
    {
        interface_bring_down($this->initial_data["interface"]);
        sleep(3);
        interface_configure($this->initial_data["interface"]);
    }

    private function __get_metrics()
    {
        $if_descrs = get_configured_interface_with_descr(true);
        $if_info = [];
        foreach ($if_descrs as $if_descr => $if_name) {
            if ($this->initial_data["interface"] == $if_descr) {
                $if = ["name" => $if_descr, "descr" => $if_name];
                $if = $if + get_interface_info($if_descr);
                $if_info[] = $if;
            }
        }
        return $if_info;
    }
}
