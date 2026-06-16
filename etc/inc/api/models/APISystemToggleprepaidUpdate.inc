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
require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

class APISystemToggleprepaidUpdate extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-advanced-admin"];
        $this->change_note = "Update prepaid status";
    }

    public function action() {
        if (!isset($this->initial_data['prepaid_enabled'])) {
            return APIResponse\get(0, "Input invalid, no changed made");
        }

        if ($this->initial_data['prepaid_enabled']) {
            $this->config['system']['prepaid_enabled'] = "";
        } else {
            unset($this->config['system']['prepaid_enabled']);
        }
        // 과거 오염 정리: captiveportal zone 배열에 잘못 저장돼 '내부가 빈 가짜 zone'
        // 으로 보이던 키를 제거한다. (이 키가 CP zone 목록을 오염시키던 근본 원인)
        unset($this->config['captiveportal']['prepaid_enabled']);
        $this->write_config();

        $enabled = isset($this->config['system']['prepaid_enabled']);
        return APIResponse\get(0, ["prepaid_enabled" => $enabled]);
    }
}
