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
require_once("captiveportal.inc");

class TopUp extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-services-freeradius"];
    }
    public function action()
    {
        $commandIdPath = "/etc/inc/commandId.log";
        $lockPath = "/etc/inc/commandId.lock";

        $commandId = "";
        if (isset($this->initial_data['commandId'])) {
            $commandId = trim((string)$this->initial_data['commandId']);
        }

        /*
         * 중복 요청 동시 실행 방지용 lock
         * 첫 번째 요청이 write_config() 전까지 처리 중일 때,
         * 두 번째 요청은 여기서 대기했다가 commandId.log 확인 후 중복이면 종료됨
         */
        $lockFp = @fopen($lockPath, "c");
        if ($lockFp === false) {
            $this->errors[] = "Failed to open commandId lock file.";
            return;
        }

        if (!flock($lockFp, LOCK_EX)) {
            fclose($lockFp);
            $this->errors[] = "Failed to lock commandId file.";
            return;
        }

        try {
            /*
             * commandId가 있는 경우에만 중복 체크
             * commandId가 빈 값이면 기존 동작 유지
             */
            if ($commandId !== "") {
                $lastCommandId = "";

                if (is_file($commandIdPath) && is_readable($commandIdPath)) {
                    $lastCommandId = trim((string)@file_get_contents($commandIdPath));
                }

                if ($lastCommandId !== "" && hash_equals($lastCommandId, $commandId)) {
                    cp_wireless_log("Topup skipped: duplicate commandId={$commandId}");
                    return;
                }
            }

            if (isset($this->initial_data['freeradius_username'])) {
                foreach ($this->config["installedpackages"]["freeradius"]["config"] as $item => $userentry) {
                    if ($this->initial_data["freeradius_username"] === $userentry['varusersusername']) {
                        $this->validated_data["freeradius_username"] = $userentry['varusersusername'];

                        $this->performTopup($item);
                        $this->config["installedpackages"]["freeradius"]["config"][$item]["varusersmodified"] = "update";

                        cp_wireless_log(
                            "Topup successful: current " .
                            $userentry['varusersmaxtotaloctets'] .
                            "MBytes, added " .
                            $this->initial_data['freeradius_maxtotaloctets'] .
                            "MBytes"
                        );

                        /*
                         * 요청사항:
                         * commandId 기록은 write_config() 바로 앞에서 수행
                         */
                        if ($commandId !== "") {
                            if (@file_put_contents($commandIdPath, $commandId . PHP_EOL, LOCK_EX) === false) {
                                $this->errors[] = "Failed to write commandId log.";
                                return;
                            }
                        }

                        $this->write_config("User topped up");

                        return;
                    }
                }
            }
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    public function performTopup($index)
    {
        $timerange = $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctetstimerange'];
        $username = $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersusername'];

        if (isset($this->initial_data['freeradius_maxtotaloctets'])) {
            $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctets'] =
                (int)$this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctets']
                + (int)$this->initial_data['freeradius_maxtotaloctets'];
        }
    }

    private function getuservolume($safeUser, $safeRange): float
    {
        $dir = "/var/log/radacct/datacounter/{$safeRange}";
        $baseFile = "{$dir}/used-octets-{$safeUser}";
        $sessionPattern = "{$dir}/used-octets-{$safeUser}-*";
        $usagenum = 0;
        // 1) BASE exact file
        if (is_file($baseFile) && is_readable($baseFile)) {
            $line = trim((string)@file_get_contents($baseFile));

            if (preg_match('/^\d+$/', $line)) {
                $usagenum += (int)$line;
            }
        }
        // 2) SESSION files exact-prefix only
        $files = glob($sessionPattern, GLOB_NOSORT);
        if (is_array($files)) {
            foreach ($files as $filepath) {
                if (!is_file($filepath) || !is_readable($filepath)) {
                    continue;
                }
                $line = trim((string)@file_get_contents($filepath));
                if (preg_match('/^(\d+)/', $line, $m)) {
                    $usagenum += (int)$m[1];
                }
            }
        }
        return number_format($usagenum, 0, '.', '');
    }
}
