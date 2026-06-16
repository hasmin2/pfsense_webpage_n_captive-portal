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
require_once("freeradius.inc");   // freeradius_users_resync (radiusd 전파) — update 컨텍스트엔 미로드


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
        # crew_account.php modify_wifi_user 와 동일하게 실행 중 radiusd 에 즉시 전파(users 파일
        # 재생성). 누락 시 config.xml 만 바뀌고 다음 resync 전까지 radiusd 미반영 → 정합 위해 추가.
        if (function_exists('freeradius_users_resync')) {
            freeradius_users_resync();
        }
        # #23 step3-A: random PW 변경 시 radcheck 동기화(락 밖).
        if (!empty($this->_radcheck_entries) && function_exists('freeradius_radcheck_sync_users')) {
            freeradius_radcheck_sync_users($this->_radcheck_entries);
        }
        return APIResponse\get(0, $this->validated_data);
    }

    private function __validate_username() {
        # 대상 사용자 수집: userlist(배열/구분문자열) + freeradius_username(단일/배열) 모두 수용.
        #   → crew_account.php modify_wifi_user($userlist, ...) 처럼 "여러 사용자"를 루프 처리한다.
        #   (하위호환: 기존 단일 freeradius_username 문자열도 그대로 1건 처리.)
        $usernames = $this->__collect_usernames();

        # 대상 0건이면 기존과 동일하게 5000(필수 인자 누락).
        if (empty($usernames)) {
            $this->errors[] = APIResponse\get(5000);
            return;
        }

        # usersreset 특수 키워드: 터미널타입별 전체 사용량 즉시 리셋(기존 동작 보존).
        if (in_array('usersreset', $usernames, true)) {
            $this->__handle_usersreset();
            return;
        }

        # 사용자별 루프: config index 찾아 update_userinfo 적용(crew modify_wifi_user 와 동형).
        $updated   = array();
        $not_found = array();
        foreach ($usernames as $uname) {
            $idx = $this->__find_user_index($uname);
            if ($idx === null) {
                $not_found[] = $uname;
                continue;
            }
            $this->update_userinfo($idx, $uname);
            $updated[] = $uname;
        }

        # 응답: 처리된 사용자(1건이면 문자열, 다건이면 배열) + 못 찾은 목록. 전부 못 찾으면 5001.
        $this->validated_data['freeradius_username'] = (count($updated) === 1) ? $updated[0] : $updated;
        if (!empty($not_found)) {
            $this->validated_data['not_found'] = $not_found;
        }
        if (empty($updated)) {
            $this->errors[] = APIResponse\get(5001);
        }
    }

    # userlist(배열 또는 콤마/공백 구분 문자열) + freeradius_username(단일/배열) → 정규화 목록.
    #   trim + 빈값/중복 제거. 비문자열 원소는 무시.
    private function __collect_usernames() {
        $names = array();

        if (isset($this->initial_data['userlist'])) {
            $ul = $this->initial_data['userlist'];
            if (is_array($ul)) {
                $names = $ul;
            } elseif (is_string($ul) && trim($ul) !== '') {
                $names = preg_split('/[\s,]+/', trim($ul));
            }
        }
        if (isset($this->initial_data['freeradius_username'])) {
            $fu = $this->initial_data['freeradius_username'];
            if (is_array($fu)) {
                $names = array_merge($names, $fu);
            } elseif (is_string($fu) && trim($fu) !== '') {
                $names[] = trim($fu);
            }
        }

        $clean = array();
        foreach ($names as $n) {
            if (!is_string($n)) {
                continue;
            }
            $n = trim($n);
            if ($n !== '' && !in_array($n, $clean, true)) {
                $clean[] = $n;
            }
        }
        return $clean;
    }

    # username → config index (없으면 null)
    private function __find_user_index($username) {
        $cfg = $this->config["installedpackages"]["freeradius"]["config"] ?? null;
        if (!is_array($cfg)) {
            return null;
        }
        foreach ($cfg as $item => $userentry) {
            if (is_array($userentry) && ($userentry['varusersusername'] ?? null) === $username) {
                return $item;
            }
        }
        return null;
    }

    # usersreset: 터미널타입별 전체 사용량 즉시 리셋(기존 단일 처리 로직 보존).
    private function __handle_usersreset() {
        $usercount = count($this->config["installedpackages"]["freeradius"]["config"]);
        $reset_users = array();
        for ($i = 0; $i < $usercount; $i++) {
            if ($this->initial_data['freeradius_terminaltype'] === "Auto") { $terminaltype = ""; }
            else { $terminaltype = $this->initial_data['freeradius_terminaltype']; }
            if (!isset($this->config["installedpackages"]["freeradius"]["config"][$i]["varusersterminaltype"])) {
                $this->config["installedpackages"]["freeradius"]["config"][$i]["varusersterminaltype"] = "";
            }
            if ($terminaltype === $this->config["installedpackages"]["freeradius"]["config"][$i]["varusersterminaltype"] &&
                strtolower($this->config["installedpackages"]["freeradius"]["config"][$i]["varusersmaxtotaloctetstimerange"]) !== 'forever') {
                // 태그는 유지(차후 로그인 fallback), 실제 리셋은 아래에서 즉시 수행
                $this->config["installedpackages"]["freeradius"]["config"][$i]["varusersresetquota"] = "true";
                $this->config["installedpackages"]["freeradius"]["config"][$i]["varusersmodified"] = "update";
                $reset_users[] = $this->config["installedpackages"]["freeradius"]["config"][$i]["varusersusername"];
            }
        }
        $this->write_config("Reset Crew wifi usage");

        // 차후 로그인이 아니라 "이때 바로": 활성 세션 로그아웃 + 사용량 0
        require_once("captiveportal.inc");
        if (function_exists('captiveportal_reset_user_usage')) {
            foreach (array_unique($reset_users) as $ru) {
                if (is_string($ru) && $ru !== '') {
                    if (function_exists('cp_wireless_log')) {
                        $actor = function_exists('cp_admin_actor') ? cp_admin_actor() : 'REMOTE API';
                        cp_wireless_log("Reset Datausage: " . $ru . " by " . $actor);
                    }
                    captiveportal_reset_user_usage($ru);
                }
            }
        }
        $this->validated_data['freeradius_username'] = 'usersreset';
        $this->validated_data['reset_users'] = array_values(array_unique($reset_users));
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

    # 단일 사용자(index) 의 필드 갱신. $username = 루프에서 전달된 "현재 사용자"
    #   (다건 루프 정합 — used-octets 파일경로가 initial_data 의 단일 freeradius_username 에 의존하지 않게).
    public function update_userinfo($index, $username) {
        # israndompw = true/"true" 시 사용자마다 독립 생성 비밀번호를 freeradius_password 에 주입(reset_random_wifi_user_pw 동일 로직).
        $_rp_u = $this->initial_data['israndompw'] ?? null;
        $_do_randpw_u = ($_rp_u === true) || (is_string($_rp_u) && !in_array(strtolower(trim($_rp_u)), array('', '0', 'false', 'no', 'off'), true));
        if ($_do_randpw_u) {
            $alphabet = '1234567890';
            $pass = array();
            $alphaLength = strlen($alphabet) - 1;
            for ($passwordchar = 0; $passwordchar < 6; $passwordchar++) {
                $n = rand(0, $alphaLength);
                $pass[] = $alphabet[$n];
            }
            $this->initial_data['freeradius_password'] = implode($pass);
        } elseif ($_rp_u !== null) {
            # israndompw 명시적 false/"false" → 비밀번호를 "1111" 로 초기화.
            $this->initial_data['freeradius_password'] = "1111";
        }
        # #23 step3-A: israndompw 가 명시적으로 설정됐을 때(true→난수/false→1111) radcheck 동기화.
        if ($_rp_u !== null) {
            if (!isset($this->_radcheck_entries)) { $this->_radcheck_entries = array(); }
            $enc = $this->config["installedpackages"]["freeradius"]["config"][$index]['varuserspasswordencryption'] ?? 'Cleartext-Password';
            $this->_radcheck_entries[] = array('user' => $username, 'pass' => $this->initial_data['freeradius_password'], 'enc' => $enc);
        }
        foreach ($this->config["installedpackages"]["freeradius"]["config"][$index] as $item => $value) {
            # varusersusername(키)은 update 로 변경하지 않는다(이름 변경은 update 대상 아님).
            #   다건 루프에서 freeradius_username 이 페이로드에 있어도 각 사용자 키를 덮어쓰지 않게.
            if ($item === 'varusersusername') {
                continue;
            }
            if ($this->startsWith($item, "varusers") == 1) {
                $postfix = substr($item, 8);
            } else {
                $postfix = $item;
            }
            if (isset($this->initial_data["freeradius_" . $postfix])) {
                $this->config["installedpackages"]["freeradius"]["config"][$index][$item] = $this->initial_data["freeradius_" . $postfix];
                $this->validated_data["freeradius_" . $postfix] = $this->initial_data["freeradius_" . $postfix];
            }

        }

        # lastbasedata: freeradius_maxtotaloctetstimerange 미전송 시 config 기존값 폴백.
        #   (루프 밖으로 이동 — 기존 코드는 foreach 안에서 필드 수만큼 반복 기록하는 버그 있었음.)
        if (isset($this->initial_data["freeradius_lastbasedata"])) {
            $timerange = $this->initial_data["freeradius_maxtotaloctetstimerange"]
                ?? ($this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctetstimerange'] ?? null);
            if ($timerange !== null && $timerange !== '') {
                $filepathall = "/var/log/radacct/datacounter/$timerange/used-octets-$username";
                if (($use_file = fopen($filepathall, "w")) !== false) {
                    fwrite($use_file, (string) ((int) ($this->initial_data["freeradius_lastbasedata"] * 1048576)));
                    log_error($this->initial_data["freeradius_lastbasedata"]);
                    fclose($use_file);
                }
            }
        }

        # crew_account.php modify_wifi_user 와 동일한 timeperiod 분해(정합).
        #   crew "Modify Voucher" 폼은 단일 timeperiod("half-Monthly"/"Monthly"/...)만 보내고,
        #   modify_wifi_user 가 이를 halftimeperiod + pointoftime + maxtotaloctetstimerange 3개로
        #   분해한다. API 의 일반 복사 루프는 이 분해를 못 하므로(half-Monthly 가 timerange 에
        #   그대로 들어가 datacounter 디렉터리 불일치) 여기서 동일 로직으로 처리.
        #   - timeperiod / freeradius_timeperiod 양쪽 수용(미전송 시 위 직접 필드 그대로 유지).
        $period_raw = $this->initial_data['timeperiod'] ?? ($this->initial_data['freeradius_timeperiod'] ?? null);
        if (is_string($period_raw) && trim($period_raw) !== '') {
            $halftimeperiod = explode('-', $period_raw);
            if (count($halftimeperiod) == 2) {
                $varusershalftimeperiod = $halftimeperiod[0];
                $varuserstimeperiod     = $halftimeperiod[1];
            } else {
                $varusershalftimeperiod = "";
                $varuserstimeperiod     = $halftimeperiod[0];
            }
            # 방어 정규화(#32): 입력 케이스 무관하게 웹 UI 표준 표기로 캐논화.
            #   half 세그먼트=소문자("half"), pointoftime=첫글자 대문자("Monthly"), timerange=소문자("monthly").
            #   "Half-Monthly"/"half-monthly"/"HALF-MONTHLY" 가 와도 웹과 동일한 half/Monthly/monthly 로 수렴.
            $varusershalftimeperiod = ($varusershalftimeperiod === "") ? "" : strtolower(trim($varusershalftimeperiod));
            $varuserstimeperiod     = ucfirst(strtolower(trim($varuserstimeperiod)));
            $this->config["installedpackages"]["freeradius"]["config"][$index]['varusershalftimeperiod']         = $varusershalftimeperiod;
            $this->config["installedpackages"]["freeradius"]["config"][$index]['varuserspointoftime']            = $varuserstimeperiod;
            $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctetstimerange'] = strtolower($varuserstimeperiod);
            $this->validated_data['freeradius_halftimeperiod']         = $varusershalftimeperiod;
            $this->validated_data['freeradius_pointoftime']            = $varuserstimeperiod;
            $this->validated_data['freeradius_maxtotaloctetstimerange'] = strtolower($varuserstimeperiod);
        }

        $this->config["installedpackages"]["freeradius"]["config"][$index]['varuserscreatedate'] = date('Y/m/d H:i:s');
        $this->config["installedpackages"]["freeradius"]["config"][$index]["varusersmodified"] = "update";
    }

    private function startsWith($haystack, $needle) {
        // search backwards starting from haystack length characters from the end
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }
}
