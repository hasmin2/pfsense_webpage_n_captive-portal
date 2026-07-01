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

class APISystemGetRuntime extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-dashboard-all"];
    }
    public function action() {
        return APIResponse\get(0, $this->__get_runtime());
    }

    # 부팅 이후 경과 시간(uptime)을 "N days HH:MM:SS" 형식으로 반환
    private function __get_runtime() {
        $uptime = $this->__get_uptime_seconds();

        $days  = intval($uptime / 86400);
        $hours = intval(($uptime % 86400) / 3600);
        $mins  = intval(($uptime % 3600) / 60);
        $secs  = intval($uptime % 60);

        return sprintf("%d days %02d:%02d:%02d", $days, $hours, $mins, $secs);
    }

    # kern.boottime sysctl 로 부팅 시각을 읽어 현재까지 경과 초 계산
    private function __get_uptime_seconds() {
        $boottime = "";
        exec("/sbin/sysctl -n kern.boottime 2>/dev/null", $out);
        if (is_array($out)) {
            $boottime = implode(" ", $out);
        }

        # 형식: "{ sec = 1719800000, usec = 123456 } Mon Jul  1 08:22:23 2026"
        if (preg_match('/sec\s*=\s*(\d+)/', $boottime, $m)) {
            $elapsed = time() - intval($m[1]);
            return ($elapsed > 0) ? $elapsed : 0;
        }

        return 0;
    }
}

?>
