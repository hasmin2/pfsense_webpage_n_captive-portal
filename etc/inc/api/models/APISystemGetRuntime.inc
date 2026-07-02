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
    # 코어 서버 텔레메트리를 읽는 로컬 InfluxDB (선내 LAN, 무인증 — repo 관례와 동일)
    const CORE_INFLUX_BASE = "http://192.168.209.210:8086";
    const CORE_INFLUX_DB   = "core_status";
    const CORE_MEASUREMENT  = "coresystem";

    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-dashboard-all"];
    }
    public function action() {
        # fw_uptime = pfSense 부팅 이후 경과 초(정수, 로컬)
        # core_temp / core_uptime = 코어 서버가 InfluxDB(coresystem)에 적재한 값 (실패 시 null)
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

    # 코어 서버 텔레메트리를 SSH(sshpass, FreeBSD 미지원) 대신 로컬 InfluxDB 에서 읽는다.
    # 코어 박스(192.168.209.210)의 크론(coresystem_influx_write.sh)이 acustatus DB 의
    # coresystem measurement 로 sensors 평균온도(core_temp)와 /proc/uptime 초(core_uptime)를 적재한다.
    # 어떤 실패(influx 불통/타임아웃/데이터 없음/파싱 실패)에도 예외 없이 null 로 degrade.
    private function __get_core_state() {
        $result = array("core_temp" => null, "core_uptime" => null);

        # 최근 10분 내 최신값. last() 는 필드별 최신 포인트를 개별 반환(둘의 적재 시점이 달라도 안전).
        $row = $this->__influx_query_latest(
            "select last(core_temp) as core_temp, last(core_uptime) as core_uptime " .
            "from " . self::CORE_MEASUREMENT . " where time > now() - 10m",
            self::CORE_INFLUX_DB,
            2
        );
        if (!is_array($row)) {
            return $result;   # influx 불통/데이터 없음 → null 로 degrade
        }

        # -1(또는 음수) 은 코어 writer 가 값을 못 읽었을 때의 센티널 → null 로 취급
        if (isset($row["core_temp"]) && is_numeric($row["core_temp"]) && (float)$row["core_temp"] > -1) {
            $result["core_temp"] = round((float)$row["core_temp"], 2);
        }
        if (isset($row["core_uptime"]) && is_numeric($row["core_uptime"]) && (int)$row["core_uptime"] >= 0) {
            $result["core_uptime"] = intval(floatval($row["core_uptime"]));
        }

        return $result;
    }

    # InfluxDB 쿼리 최신 1행을 컬럼명=>값 으로. self-contained — server_module.inc(openvpn.inc 연쇄) 비의존.
    # cp_tz_offset_update.php 의 cp_tzcron_influx_latest 와 동일 패턴(로컬 LAN 2초 타임아웃).
    private function __influx_query_latest($influxql, $db, $timeout = 2) {
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, array(
            CURLOPT_URL => self::CORE_INFLUX_BASE . '/query?q=' .
                rawurlencode($influxql) .
                '&db=' . rawurlencode($db),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) {
            return false;
        }
        $decoded = json_decode($response, true);
        if (!isset($decoded['results'][0]['series'][0]['columns']) ||
            !isset($decoded['results'][0]['series'][0]['values'][0])) {
            return false;
        }
        $row = array();
        foreach ($decoded['results'][0]['series'][0]['columns'] as $i => $col) {
            $row[$col] = isset($decoded['results'][0]['series'][0]['values'][0][$i])
                ? $decoded['results'][0]['series'][0]['values'][0][$i] : null;
        }
        return $row;
    }
}

?>
