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
    # 코어 서버(선내 CentOS) SSH 접속 정보 (내부망 신뢰 구간, 하드코딩)
    const CORE_HOST = "192.168.209.210";
    const CORE_USER = "synersatroot";
    const CORE_PASS = "P@ssw0rd";

    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-dashboard-all"];
    }
    public function action() {
        # fw_uptime = pfSense 부팅 이후 경과 초(정수)
        # core_temp / core_uptime = 코어 서버(192.168.209.210) sensors / /proc/uptime (실패 시 null)
        $core = $this->__get_core_state();
        $data = array(
            "fw_uptime"   => $this->__get_uptime_seconds(),
            "core_temp"   => $core["core_temp"],
            "core_uptime" => $core["core_uptime"],
        );
        return APIResponse\get(0, $data);
    }

    # kern.boottime sysctl 로 부팅 시각을 읽어 현재까지 경과 초(정수) 계산
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

    # 코어 서버에 SSH(sshpass) 로 접속해 sensors(코어 온도 평균℃) + /proc/uptime(초) 조회.
    # 어떤 실패(도구 부재/연결 실패/파싱 실패)에도 예외 없이 null 로 degrade.
    private function __get_core_state() {
        $result = array("core_temp" => null, "core_uptime" => null);

        $sshpass = "/usr/local/bin/sshpass";
        $ssh     = "/usr/bin/ssh";
        # sshpass/ssh 미설치 시 조용히 degrade (core_* = null)
        if (!file_exists($sshpass) || !file_exists($ssh)) {
            return $result;
        }

        $remote = "sensors; echo ===UPTIME===; cat /proc/uptime";
        $cmd = $sshpass . " -p " . escapeshellarg(self::CORE_PASS) . " " . $ssh .
               " -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null" .
               " -o ConnectTimeout=10 -o BatchMode=no" .
               " " . escapeshellarg(self::CORE_USER . "@" . self::CORE_HOST) .
               " " . escapeshellarg($remote) . " 2>/dev/null";

        $out = array();
        exec($cmd, $out);
        if (empty($out)) {
            return $result;
        }
        $text  = implode("\n", $out);
        $parts = explode("===UPTIME===", $text, 2);
        $sensorsText = isset($parts[0]) ? $parts[0] : "";
        $uptimeText  = isset($parts[1]) ? $parts[1] : "";

        # core_temp = "Core N: +39.0°C" 들의 평균(℃)
        if (preg_match_all('/^\s*Core\s+\d+:\s*\+?(-?\d+(?:\.\d+)?)/mi', $sensorsText, $m) && count($m[1]) > 0) {
            $sum = 0.0;
            foreach ($m[1] as $t) {
                $sum += floatval($t);
            }
            $result["core_temp"] = round($sum / count($m[1]), 2);
        }

        # core_uptime = /proc/uptime 첫 필드 → 정수 초 (DB core_uptime = INT)
        $uptimeTrim = trim($uptimeText);
        if ($uptimeTrim !== "") {
            $tok = preg_split('/\s+/', $uptimeTrim);
            if (isset($tok[0]) && is_numeric($tok[0])) {
                $result["core_uptime"] = intval(floatval($tok[0]));
            }
        }

        return $result;
    }
}

?>
