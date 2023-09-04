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

class APISystemUpdateVesselInfo extends APIModel
{
    # Create our method constructor
    public function __construct()
    {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-advanced-admin"];
        $this->change_note = "Modified system console settings";
        $this->validated_data = $this->config["system"];
    }

    public function action()
    {
        $isSet = false;
        if (isset($this->initial_data['vesselname'])) {
            $this->config['system']['vesselinfo']['vesselname'] = $this->initial_data['vesselname'];
            $isSet = true;
        }
        if (isset($this->initial_data['vesselcallsign'])) {
            $this->config['system']['vesselinfo']['vesselcallsign'] = $this->initial_data['vesselcallsign'];
            $isSet = true;
        }
        if (isset($this->initial_data['vesselimo'])) {
            $this->config['system']['vesselinfo']['vesselimo'] = $this->initial_data['vesselimo'];
            $isSet = true;
        }
        if (isset($this->initial_data['vesselmmsi'])) {
            $this->config['system']['vesselinfo']['vesselmmsi'] = $this->initial_data['vesselmmsi'];
            $isSet = true;
        }
        if (isset($this->initial_data['vesselhullno'])) {
            $this->config['system']['vesselinfo']['vesselhullno'] = $this->initial_data['vesselhullno'];
            $isSet = true;
        }
        if (isset($this->initial_data['vesselvpnip'])) {
            $this->config['system']['vesselinfo']['vesselvpnip'] = $this->initial_data['vesselvpnip'];
            $isSet = true;
        }
        if (isset($this->initial_data['vesselpublicip'])) {
            $this->config['system']['vesselinfo']['vesselpublicip'] = $this->initial_data['vesselpublicip'];
            $isSet = true;
        }
        if ($isSet) {
            write_config("Updated vessel info");
            return APIResponse\get(0, "Success???");
        } else {
            $this->errors[] = APIResponse\get(5001);
            return APIResponse\get(0, "Input failed you may input proper data or tagname.");
        }

    }
}
