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
require_once("manage_crew_wifi_account.inc");   // del_wifi_user (캐논 삭제: 다건+파일정리+resync+radcheck+로그)
require_once("freeradius.inc");                  // resync/radcheck (안전 — del_wifi_user 의존)

class APIFreeRADIUSUserDelete extends APIModel {
    # del_wifi_user 가 내부에서 write_config 까지 수행 → action() 중복 write 방지 플래그
    #   (create 의 bulk_create_mode 와 동일 패턴)
    private $delete_done = false;

    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-usermanager"];
        $this->change_note = "Modified user via API";
    }

    public function action() {
        if (!$this->delete_done) {
            $this->write_config();
        }
        return APIResponse\get(0, $this->validated_data);
    }

    private function __validate_username() {
        # 대상 수집: userlist(배열/구분문자열) + freeradius_username(단일/배열) 모두 수용.
        #   → crew_account.php del_wifi_user($_POST['userlist']) 처럼 "여러 사용자"를 일괄 삭제.
        #   (하위호환: 기존 단일 freeradius_username 문자열도 그대로 1건 처리.)
        $usernames = $this->__collect_usernames();
        if (empty($usernames)) {
            $this->errors[] = APIResponse\get(5000);
            return;
        }

        # 존재 여부 선판정: 없는 사용자는 not_found 로 반환, 전부 없으면 5001.
        $existing  = array();
        $not_found = array();
        foreach ($usernames as $u) {
            if ($this->__find_user_index($u) !== null) { $existing[] = $u; }
            else { $not_found[] = $u; }
        }
        if (!empty($not_found)) {
            $this->validated_data['not_found'] = $not_found;
        }
        if (empty($existing)) {
            $this->errors[] = APIResponse\get(5001);
            return;
        }

        # crew_account.php 와 "동일 함수"로 삭제 → 다건 + 파일정리 + resync + radcheck(#23 step3-A) +
        #   로그가 전부 정합. (인라인 중복 로직/드리프트 제거.)
        if (function_exists('del_wifi_user')) {
            del_wifi_user($existing);
            $this->delete_done = true;   // del_wifi_user 가 write_config + resync 수행
        } else {
            # 버전 섞임(구 manage_crew_wifi_account.inc) fallback: 최소 인라인 삭제(올바른 weekly 경로).
            #   resync/radcheck 는 빠지지만 계정·파일 제거는 보장. action() 의 write_config 로 저장.
            foreach ($existing as $user) {
                $idx = $this->__find_user_index($user);
                if ($idx === null) { continue; }
                unset($this->config["installedpackages"]["freeradius"]["config"][$idx]);
                foreach (array('daily', 'weekly', 'monthly', 'forever') as $tr) {
                    unlink_if_exists("/var/log/radacct/datacounter/$tr/used-octets-$user*");
                    unlink_if_exists("/var/log/radacct/datacounter/$tr/max-octets-$user");
                }
            }
        }

        $this->validated_data['freeradius_username'] = (count($existing) === 1) ? $existing[0] : $existing;
    }

    # userlist(배열 또는 콤마/공백 구분 문자열) + freeradius_username(단일/배열) → 정규화 목록.
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
