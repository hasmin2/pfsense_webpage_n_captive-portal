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
        if (isset($this->initial_data['freeradius_username'])) {
            # Loop through each configured user and check if this user exists
            foreach ($this->config["installedpackages"]["freeradius"]["config"] as $item=>$userentry) {
				if ($this->initial_data["freeradius_username"] === $userentry['varusersusername']) {
					$this->validated_data["freeradius_username"] = $userentry['varusersusername'];
					$this->id = $item;
					$this->update_userinfo();
                    $this->config["installedpackages"]["freeradius"]["config"][$this->id]["varusersmodified"]="update";
				}
            }
            # Set an error if no user was found
            if (!isset($this->validated_data["freeradius_username"])) {
                if($this->initial_data['freeradius_username']==='usersreset'){
                    $usercount = count ($this->config["installedpackages"]["freeradius"]["config"]);
                    for ($i=0; $i < $usercount; $i++){
                        if($this->initial_data['terminaltype']==="Auto"){$terminaltype="";}
                        else{$terminaltype=$this->initial_data['terminaltype'];}
                        if(!isset($this->config["installedpackages"]["freeradius"]["config"][$i]["varusersterminaltype"])){
                            $this->config["installedpackages"]["freeradius"]["config"][$i]["varusersterminaltype"]="";
                        }
                        if(isset($this->initial_data['terminaltype']) &&
                            $terminaltype===$this->config["installedpackages"]["freeradius"]["config"][$i]["varusersterminaltype"] &&
                            strtolower($this->config["installedpackages"]["freeradius"]["config"][$i]["varusersmaxtotaloctetstimerange"]) !== 'forever'){
                            $this->config["installedpackages"]["freeradius"]["config"][$i]["varusersresetquota"] = "true";
                            $this->config["installedpackages"]["freeradius"]["config"][$i]["varusersmodified"] = "update";
                        }
                    }
                    $this->write_config("Reset Crew wifi usage");
                }
                else{
                    $this->errors[] = APIResponse\get(5001);
                }
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

	public function update_userinfo(){
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
			"varusersresetquota"=>false,
		);
		foreach($userinfoentry as $item => $value){
			if($this->startsWith($item, "varusers") == 1){ $postfix = substr($item, 8); } 
			else { $postfix = $item; }
			if(isset($this->initial_data["freeradius_".$postfix])){
				$this->config["installedpackages"]["freeradius"]["config"][$this->id][$item] = $this->initial_data["freeradius_".$postfix];
				$this->validated_data["freeradius_".$postfix] = $this->initial_data["freeradius_".$postfix];
			}
		}
	}
	private function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
	}
}
