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
class APIFreeRADIUSUserCreate extends APIModel {
    # Create our method constructor

    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-services-freeradius"];
        $this->change_note = "Added FreeRADIUS user via API";
    }

    public function action() {
        # Write the user to configuration and set the user on the backend. Return response with created user object.
        $this->write_config();
        return APIResponse\get(0, $this->validated_data);
    }

    private function __validate_username() {

        # Check for our required `username` payload value

        if (isset($this->initial_data['freeradius_username'])) {
			foreach($this->config["installedpackages"]["freeradius"]["config"] as $item => $value){
				if($this->initial_data['freeradius_username'] === $value["varusersusername"]){
		            $this->errors[] = APIResponse\get(5002);
					break;
				}
				else { $this->id = $item+1; }
			}
		}
		else { $this->errors[] = APIResponse\get(5000); }
		if (!preg_match("/[^a-zA-Z0-9\.\-_]/", $this->initial_data['freeradius_username'])) {
		# Ensure username is not reserved by the system
			if (!$this->is_username_reserved($this->initial_data["freeradius_username"])) {
			# Ensure username is not longer that 32 characters
				if (strlen($this->initial_data["freeradius_username"]) <= 32) {
					if (isset($this->initial_data['freeradius_password'])) {
					    if(!isset($this->id)){
					        $this->id = 0;
                            $this->config["installedpackages"]["freeradius"]=["config"=>[$this->id]];
                        }
                        $this->config["installedpackages"]["freeradius"]["config"][$this->id]=$this->create_userinfo($this->id);
                        $this->config["installedpackages"]["freeradius"]["config"][$this->id]["varusersmodified"]="create";
                        $this->write_config();

					} else {
			            $this->errors[] = APIResponse\get(5003);
			        }
				}
				else { $this->errors[] = APIResponse\get(5038); }
			}
			else { $this->errors[] = APIResponse\get(5037);	}
		}
		else { $this->errors[] = APIResponse\get(5036); }


    }

    private function __validate_priv() {
        global $priv_list;
        $this->validated_data["priv"] = [];

        # Check for our optional `priv` payload value
        if ($this->initial_data["priv"]) {
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

    public function is_username_reserved($user) {
        # Open the /etc/passwd file to read all system users
        $sys_users = explode(PHP_EOL, file_get_contents("/etc/passwd"));

        # Loop through each system user and check if the username is reserved
        foreach ($sys_users as $sys_user_ent) {
            $sys_username = explode(":", $sys_user_ent)[0];
            if ($sys_username == $user) {
                return true;
            }
        }
        return false;
    }
	private function create_userinfo($id){
        # Check for our required `password` payload value
        $userinfoentry = array(
            "sortable"=>"",
            "varusersusername"=>"",
            "varuserspassword"=>"",
            "varuserspasswordencryption"=>"Cleartext-Password",
            "varusersmotpenable"=>"",
            "varusersauthmethod"=>"motp",
            "varusersmotpinitsecret"=>"",
            "varusersmotppin"=>"",
            "varusersmotpoffset"=>"",
            "qrcodetext"=>"",
            "varuserswisprredirectionurl"=>"",
            "varuserssimultaneousconnect"=>"",
            "description"=>"",
            "varusersframedipaddress"=>"",
            "varusersframedipnetmask"=>"",
            "varusersframedroute"=>"",
            "varusersframedip6address"=>"",
            "varusersframedip6route"=>"",
            "varusersvlanid"=>"",
            "varusersexpiration"=>"",
            "varuserssessiontimeout"=>"",
            "varuserslogintime"=>"",
            "varusersamountoftime"=>"",
            "varuserspointoftime"=>"Monthly",
            "varusersmaxtotaloctets"=>"1",
            "varusersmaxtotaloctetstimerange"=>"monthly",
            "varusersmaxbandwidthdown"=>"",
            "varusersmaxbandwidthup"=>"",
            "varusersacctinteriminterval"=>"600",
            "varuserstopadditionaloptions"=>"",
            "varuserscheckitemsadditionaloptions"=>"",
            "varusersreplyitemsadditionaloptions"=>"",
            "varuserslastreceivedata"=>0,
            "varuserslastsentdata"=>0,
            "varuserslastbasedata"=>0,
            "varusersresetquota"=>"true",
        );
        $result=array();
		foreach($userinfoentry as $item => $value){
			if($this->startsWith($item, "varusers") == 1){ $postfix = substr($item, 8); }
			else { $postfix = $item; }
			if(isset($this->initial_data["freeradius_".$postfix])){
                $result[$item] = $this->initial_data["freeradius_".$postfix];
				$this->validated_data["freeradius_".$postfix] = $this->initial_data["freeradius_".$postfix];
			} else{
				$result[$item] = $value;
			}
		}
		return $result;
	}
	private function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
	}
}

