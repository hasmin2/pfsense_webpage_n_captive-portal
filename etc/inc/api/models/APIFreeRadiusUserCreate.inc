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
require_once("manage_crew_wifi_account.inc");
// #49: 계정 변경 이력 헬퍼 (radius.radacct_changehistory) — 미배포(버전섞임) 시 기록만 skip
if (file_exists('/etc/inc/cp_account_history.inc')) {
    require_once('/etc/inc/cp_account_history.inc');
}


class APIFreeRADIUSUserCreate extends APIModel {
    # Create our method constructor
    private $bulk_create_mode = false;
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-services-freeradius"];
        $this->change_note = "Added FreeRADIUS user via API";
    }

    public function action() {
        if (!$this->bulk_create_mode) {
            $this->write_config();
            # 단건 생성도 웹(create_wifi_user)처럼 실행 중 radiusd 에 즉시 전파.
            # (bulk 는 create_wifi_user 가 내부에서 이미 resync 하므로 단건만 — 중복 방지.)
            if (function_exists('freeradius_users_resync')) {
                freeradius_users_resync();
            }
            # #23 step3-A: 단건 생성 radcheck 동기화(비밀번호가 있을 때).
            if (function_exists('freeradius_radcheck_sync_users') &&
                !empty($this->validated_data['freeradius_username']) &&
                !empty($this->validated_data['freeradius_password'])) {
                $enc = 'Cleartext-Password';
                if (isset($this->id) && isset($this->config["installedpackages"]["freeradius"]["config"][$this->id]['varuserspasswordencryption'])) {
                    $enc = $this->config["installedpackages"]["freeradius"]["config"][$this->id]['varuserspasswordencryption'];
                }
                freeradius_radcheck_sync_users(array(array('user' => $this->validated_data['freeradius_username'], 'pass' => $this->validated_data['freeradius_password'], 'enc' => $enc)));
            }
            # #49: 변경 이력 기록 (단건 생성 — bulk 는 create_wifi_user 내부에서 기록. 실제 PW 미기재)
            if (function_exists('cp_account_history_record') && !empty($this->validated_data['freeradius_username'])) {
                cp_account_history_record('user_create',
                    'user=' . $this->validated_data['freeradius_username']
                    . ' created via API (quota=' . ($this->validated_data['freeradius_maxtotaloctets'] ?? '') . 'MB'
                    . ', timerange=' . ($this->validated_data['freeradius_maxtotaloctetstimerange'] ?? '')
                    . ', terminaltype=' . ($this->validated_data['freeradius_terminaltype'] ?? '')
                    . ', password=(set)) by ' . cp_account_history_actor()
                    . cp_account_history_tag($this->validated_data['freeradius_username']));
            }
        }
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
        else if (isset($this->initial_data['userquantity'])) {
            if (
                isset($this->initial_data['freeradius_maxtotaloctetstimerange']) &&
                isset($this->initial_data['freeradius_maxtotaloctets']) &&
                isset($this->initial_data['freeradius_terminaltype']) &&
                isset($this->initial_data['israndompw'])
            ) {
                # issimplefied: crew_account.php "Create Voucher" 폼의 6번째 인자(체크박스)와 정합.
                #   create_wifi_user 의 6번째 인자는 기본값 없는 "필수" 파라미터라 누락 시
                #   ArgumentCountError fatal → 반드시 전달. 방어적으로 "기본 false(비단순화)"로 두고,
                #   명시적 true 신호일 때만 true. (PHP 함정: 문자열 "false"/"0" 은 truthy 이므로
                #   문자열 falsy 표현을 명시 처리 → 의도치 않게 단순 ID 가 생성되는 것 차단.)
                $issimplefied = false;
                if (isset($this->initial_data['issimplefied'])) {
                    $raw = $this->initial_data['issimplefied'];
                    if (is_bool($raw)) {
                        $issimplefied = $raw;
                    } elseif (is_string($raw)) {
                        $issimplefied = !in_array(strtolower(trim($raw)), ['', '0', 'false', 'no', 'off'], true);
                    } else {
                        $issimplefied = (bool)$raw;   // 숫자 등은 표준 truthy 판정
                    }
                }
                # 방어 정규화(#32): bulk timeperiod("half-Monthly"/"Monthly"/...) 를 웹 표준 케이스로
                #   캐논화한 뒤 전달. create_wifi_user 가 내부에서 '-' 분해하므로 분해 전에 표기 통일
                #   (입력 케이스 무관 → varusershalftimeperiod 가 "Half" 처럼 저장되는 것 방지).
                $tp_seg  = explode('-', (string)$this->initial_data['freeradius_maxtotaloctetstimerange']);
                $tp_norm = (count($tp_seg) == 2)
                    ? strtolower(trim($tp_seg[0])) . '-' . ucfirst(strtolower(trim($tp_seg[1])))
                    : ucfirst(strtolower(trim($tp_seg[0])));
                $returnarray = create_wifi_user(
                    $this->initial_data['freeradius_maxtotaloctets'],
                    $this->initial_data['userquantity'],
                    $this->initial_data['israndompw'],
                    $this->initial_data['freeradius_terminaltype'],
                    $tp_norm,
                    $issimplefied
                );
                $this->validated_data['freeradius_maxtotaloctetstimerange'] = $tp_norm;
                $this->validated_data['issimplefied'] = $issimplefied;
                $this->bulk_create_mode = true;
                $this->validated_data['created_count'] = count($returnarray);
                $this->validated_data['users'] = $returnarray;

                // priv가 응답 schema에 항상 필요하면 초기화
                if (!isset($this->validated_data['priv'])) {
                    $this->validated_data['priv'] = [];
                }

                return;
            } else {
                $this->errors[] = APIResponse\get(5000, "More args needed, freeradius_maxtotaloctetstimerange, freeradius_maxtotaloctets, userquantity, israndompw, freeradius_terminaltype");
                return;
            }
        }
		else { $this->errors[] = APIResponse\get(5000); }
        if (
            empty($this->initial_data['freeradius_username']) ||
            !preg_match("/[^a-zA-Z0-9\.\-_]/", $this->initial_data['freeradius_username'])
        ) {		# Ensure username is not reserved by the system
			if (!$this->is_username_reserved($this->initial_data["freeradius_username"])) {
			# Ensure username is not longer that 32 characters
				if (strlen($this->initial_data["freeradius_username"]) <= 32) {
					# israndompw = true/"true" 시 자동 생성 비밀번호를 freeradius_password 에 주입(create_wifi_user 동일 로직).
					$_rp_c = $this->initial_data['israndompw'] ?? null;
					$_do_randpw_c = ($_rp_c === true) || (is_string($_rp_c) && !in_array(strtolower(trim($_rp_c)), array('', '0', 'false', 'no', 'off'), true));
					if (!isset($this->initial_data['freeradius_password']) && $_do_randpw_c) {
					    $alphabet = '1234567890';
					    $pass = array();
					    $alphaLength = strlen($alphabet) - 1;
					    for ($passwordchar = 0; $passwordchar < 6; $passwordchar++) {
					        $n = rand(0, $alphaLength);
					        $pass[] = $alphabet[$n];
					    }
					    $this->initial_data['freeradius_password'] = implode($pass);
					}
					if (isset($this->initial_data['freeradius_password'])) {
					    if(!isset($this->id)){
					        $this->id = 0;
                            $this->config["installedpackages"]["freeradius"]=["config"=>[$this->id]];
                        }
                        $this->config["installedpackages"]["freeradius"]["config"][$this->id]=$this->create_userinfo();
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
        if (isset($this->initial_data["priv"]) && $this->initial_data["priv"]) {            # Ensure value is an array
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
	private function create_userinfo(){
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
            "varusershalftimeperiod"=>"",
            "varuserspointoftime"=>"Monthly",
            "varusersmaxtotaloctets"=>"1",
            "varusersmaxtotaloctetstimerange"=>"monthly",
            "varusersmaxbandwidthdown"=>"",
            "varusersmaxbandwidthup"=>"",
            "varusersacctinteriminterval"=>"600",
            "varuserstopadditionaloptions"=>"",
            "varuserscheckitemsadditionaloptions"=>"",
            "varusersreplyitemsadditionaloptions"=>"",
            "varusersterminaltype"=>"",
            "varusersprepaidaccount"=>"",
            "varusersresetquota"=>"true",
            "varuserscreatedate"=>"",

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
        if (isset($this->initial_data["freeradius_maxtotaloctetstimerange"])) {
            $timerange = strtolower((string)$this->initial_data["freeradius_maxtotaloctetstimerange"]);
            $username  = (string)($this->initial_data["freeradius_username"] ?? '');

            if ($username !== '') {
                $base = "/var/log/radacct/datacounter/{$timerange}";
                $prefix = $base . "/used-octets-" . $username;

                // 기본 파일 삭제
                unlink_if_exists($prefix);
                $files = glob($prefix . '-*');
                if ($files !== false) {
                    foreach ($files as $f) {
                        if (is_file($f)) {
                            unlink_if_exists($f);
                        }
                    }
                }
            }
        }

        # 방어 정규화(#32): 단건 생성의 timeperiod 관련 3필드를 웹 UI 표준 케이스로 캐논화.
        #   generic 복사 루프는 raw 저장 → timerange 가 대문자면 datacounter 디렉터리(.../monthly/) 불일치.
        $result['varusershalftimeperiod'] = ($result['varusershalftimeperiod'] === '')
            ? '' : strtolower(trim((string)$result['varusershalftimeperiod']));
        $result['varuserspointoftime'] = ucfirst(strtolower(trim((string)$result['varuserspointoftime'])));
        $result['varusersmaxtotaloctetstimerange'] = strtolower(trim((string)$result['varusersmaxtotaloctetstimerange']));
        # 응답(validated_data) 도 캐논값으로 동기화(해당 필드가 전송된 경우).
        if (isset($this->validated_data['freeradius_halftimeperiod'])) {
            $this->validated_data['freeradius_halftimeperiod'] = $result['varusershalftimeperiod'];
        }
        if (isset($this->validated_data['freeradius_pointoftime'])) {
            $this->validated_data['freeradius_pointoftime'] = $result['varuserspointoftime'];
        }
        if (isset($this->validated_data['freeradius_maxtotaloctetstimerange'])) {
            $this->validated_data['freeradius_maxtotaloctetstimerange'] = $result['varusersmaxtotaloctetstimerange'];
        }

        $result['varuserscreatedate'] = date('Y/m/d H:i:s');
        $result["varusersmodified"] = "create";
		return $result;
	}
	private function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
	}
}

