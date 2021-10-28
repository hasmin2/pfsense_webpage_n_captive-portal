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

class APIFreeRADIUSUserUpdate extends APIModel {
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
        if (isset($this->initial_data['freeradius_id'])) {
            # Loop through each configured user and check if this user exists
            foreach ($this->config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
		if ($this->initial_data["freeradius_id"] === $userentry['varusersusername']) {
		    $this->validated_data["freeradius_id"] = $userentry['varusersusername'];
		    $this->id = $item;
		}
            }
            # Set an error if no user was found
            if (!isset($this->validated_data["freeradius_id"])) {
                $this->errors[] = APIResponse\get(5001);
            }
        } else {
            $this->errors[] = APIResponse\get(5000);
        }
    }

    private function __validate_password() {
        # Check for our optional `password` payload value
        if (isset($this->initial_data['freeradius_pw'])) {
            # Generate the password hash and add it to our validated data
	    $this->config["installedpackages"]["freeradius"]["config"][$this->id]["varuserspassword"]=$this->initial_data['freeradius_pw'];
	    //$this->validated_data['freeradius_pw']= $this->config["installedpackages"]["freeradius"]["config"][$this->id]["varuserspassword"];
        }
	if (isset($this->initial_data['freeradius_pw_encryption'])){
	    $this->config["installedpackages"]["freeradius"]["config"][$this->id]["varuserpasswordencryption"]=$this->initial_data['freeradius_pw_encryption'];
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

    private function __update_allocated_octets() {
        # Check for our optional `disabled` payload value
        if (isset($this->initial_data['freeradius_allocated_data'])) {
	    if (is_numeric($this->initial_data['freeradius_allocated_data'])){
		$this->config["installedpackages"]["freeradius"]["config"][$this->id]["varusersmaxtotaloctets"] = $this->initial_data['freeradius_allocated_data'];
	    } else {
		$this->errors[] = APIResponse\get(3061, "The data amount must put integer MBytes");
	    }
	}
    }

    private function __update_updown_bandwidth() {
        if (isset($this->initial_data['freeradius_up_bandwidth'])) {
	    if (is_numeric($this->initial_data['freeradius_up_bandwidth'])){
		$this->config["installedpackages"]["freeradius"]["config"][$this->id]["varusersmaxbandwidthup"] = $this->initial_data['freeradius_up_bandwidth'];
	    } else {
		$this->errors[] = APIResponse\get(3061, "The upload bandwidth must put integer Kbits");
	    }
	}
        if (isset($this->initial_data['freeradius_dn_bandwidth'])) {
	    if (is_numeric($this->initial_data['freeradius_dn_bandwidth'])){
		$this->config["installedpackages"]["freeradius"]["config"][$this->id]["varusersmaxbandwidthdown"] = $this->initial_data['freeradius_dn_bandwidth'];
	    } else {
		$this->errors[] = APIResponse\get(3061, "The download bandwidth must put integer Kbits");
	    }
	}
    }

    private function __update_period() {
        if (isset($this->initial_data['freeradius_update_period'])) {
	    $this->config["installedpackages"]["freeradius"]["config"][$this->id]["varuserspointoftime"]=ucwords(strtolower($this->initial_data['freeradius_update_period']));
	    $this->config["installedpackages"]["freeradius"]["config"][$this->id]["varusersmaxtotaloctetstimerange"]=strtolower($this->initial_data['freeradius_update_period']);
        }
    }

    private function __validate_authorizedkeys() {
        # Check for our optional `authorizedkeys` payload value
        if (isset($this->initial_data['authorizedkeys'])) {
            $this->validated_data["authorizedkeys"] = base64_encode($this->initial_data['authorizedkeys']);
        }
    }

    private function __validate_ipsecpsk() {
        # Check for our optional `ipsecpsk` payload value
        if (isset($this->initial_data['ipsecpsk'])) {
            # Ensure the PSK does not contain invalid characters
            if (preg_match('/^[[:ascii:]]*$/', $_POST['ipsecpsk'])) {
                $this->validated_data["ipsecpsk"] = $this->initial_data['ipsecpsk'];
            } else {
                $this->errors[] = APIResponse\get(5039);
            }
        }
    }

    public function validate_payload() {
        # Run each validation method
        $this->__validate_username();
        $this->__validate_password();
        $this->__update_updown_bandwidth();
        $this->__update_period();
        $this->__update_allocated_octets();
        //$this->__validate_expires();
        //$this->__validate_authorizedkeys();
        //$this->__validate_ipsecpsk();
    }
}
