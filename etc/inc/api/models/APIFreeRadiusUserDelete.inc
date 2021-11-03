<?php
//   Copyright 2021 Jared Hendrickson
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


class APIFreeRADIUSUserDelete extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-usermanager"];
        $this->change_note = "Modified user via API";
    }
	public function action() {
        # Update our new user in the config and set the user on the backend
        $this->write_config();
        return APIResponse\get(0, $this->validated_data);
    }

    private function __validate_username() {
        # Check for our required `username` payload value
        if (isset($this->initial_data['freeradius_username'])) {
            # Loop through each configured user and check if this user exists
            foreach ($this->config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
				if ($this->initial_data["freeradius_username"] === $userentry['varusersusername']) {
					$this->validated_data["freeradius_username"] = $userentry['varusersusername'];
					unset($this->config["installedpackages"]["freeradius"]["config"][$item]);
				}
            }
            # Set an error if no user was found
            if (!isset($this->validated_data["freeradius_username"])) {
                $this->errors[] = APIResponse\get(5001);
            }
        } else {
            $this->errors[] = APIResponse\get(5000);
        }
    }
    private function __validate_priv() {
        global $priv_list;

        # Check for our optional `priv` payload value
        if ($this->initial_data["priv"]) {
            # Revert priv array to default
            $this->validated_data["priv"] = [];

            # Ensure value is an array
            if (!is_array($this->initial_data["priv"])) {
                $this->initial_data["priv"] = array($this->initial_data["priv"]);
            }

            # Loop through each requested privilege and ensure it exists
            foreach ($this->initial_data["priv"] as $priv) {
                if (array_key_exists($priv, $priv_list)) {
                    $this->validated_data["priv"][] = $priv;
                    $this->validated_data["priv"] = array_unique($this->validated_data["priv"]);
                } else {
                    $this->errors[] = APIResponse\get(5006);
                    break;
                }
            }
        }
    }


    public function validate_payload() {
        # Run each validation method
        $this->__validate_username();
        $this->__validate_priv();
    }

}
