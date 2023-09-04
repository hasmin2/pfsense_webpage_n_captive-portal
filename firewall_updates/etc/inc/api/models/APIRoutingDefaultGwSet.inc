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
use DateTime;

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

class APIRoutingDefaultGwSet extends APIModel
{
    # Create our method constructor
    public function __construct()
    {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-gateways"];
    }

    public function action()
    {
        $this->config["gateways"]["defaultgw4"] = $this->initial_data["defaultgw4"];
        if (isset($this->initial_data['manualrouteduration'])) {
            $this->config['gateways'] ['manualrouteduration'] = $this->initial_data['manualrouteduration'];
            $date = new DateTime();
            $this->config['gateways']['manualroutetimestamp'] = round($date->getTimestamp() / 60, 0);
        }

        $this->write_config();
        $this->apply();
        $output = [];
        foreach ($this->config['gateways']['gateway_item'] as $gateway) {
            if ($gateway['name'] === $this->initial_data['defaultgw4']) {
                $output = $gateway;
            }

        }
        return APIResponse\get(0, $output);
    }

    public function apply()
    {
        # Mark the routing subsystem as changed, clear if applied
        mark_subsystem_dirty("staticroutes");

        # Optionally allow clients to apply this gateway immediately if they passed in a true apply value
        if ($this->initial_data["apply"] === true) {
            system_routing_configure();
            system_resolvconf_generate();
            filter_configure();
            setup_gateways_monitor();
            send_event("service reload dyndnsall");
            clear_subsystem_dirty("staticroutes");
        }
    }
}
