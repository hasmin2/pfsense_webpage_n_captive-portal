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
// #49: 계정 변경 이력 헬퍼 (radius.radacct_changehistory) — 미배포(버전섞임) 시 기록만 skip
if (file_exists('/etc/inc/cp_account_history.inc')) {
    require_once('/etc/inc/cp_account_history.inc');
}

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

                        $logparts = array("Topup:" . $userentry['varusersusername']);
                        if (isset($this->initial_data['freeradius_maxtotaloctets']) && (int)$this->initial_data['freeradius_maxtotaloctets'] !== 0) {
                            $logparts[] = "quota+" . (int)$this->initial_data['freeradius_maxtotaloctets'] . "MB";
                        }
                        if (isset($this->initial_data['freeradius_lastbasedata']) && (int)$this->initial_data['freeradius_lastbasedata'] !== 0) {
                            $adj = (int)$this->initial_data['freeradius_lastbasedata'];
                            $logparts[] = "usage" . ($adj >= 0 ? '+' : '') . $adj . "MB";
                        }
                        cp_wireless_log(implode(' ', $logparts));

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

                        // #49: 변경 이력 기록 (quota/usage delta — PW 무관)
                        if (function_exists('cp_account_history_record')) {
                            $topup_detail = implode(', ', array_slice($logparts, 1));
                            if ($topup_detail === '') { $topup_detail = 'no delta'; }
                            cp_account_history_record('quota_topup',
                                "user={$userentry['varusersusername']} topup ({$topup_detail}) via API by "
                                . cp_account_history_actor()
                                . cp_account_history_tag($userentry['varusersusername']));
                        }

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

        if (isset($this->initial_data['freeradius_maxtotaloctets']) && (int)$this->initial_data['freeradius_maxtotaloctets'] !== 0) {
            $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctets'] =
                (int)$this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctets']
                + (int)$this->initial_data['freeradius_maxtotaloctets'];
        }

        // freeradius_lastbasedata: MB 단위 delta 가감 → used-octets 베이스 파일에 직접 반영.
        //   양수(+50) = 현재 누적값 +50MB, 음수(-50) = -50MB (0 하한).
        //   세션 파일(used-octets-{user}-{SID})은 건드리지 않음 — 베이스 파일만 수정.
        //   quota 검사(datacounter_auth.sh)는 베이스+세션 합산이므로 효과 = delta 그대로 반영.
        if (isset($this->initial_data['freeradius_lastbasedata']) && (int)$this->initial_data['freeradius_lastbasedata'] !== 0) {
            $delta_bytes = (int)$this->initial_data['freeradius_lastbasedata'] * 1048576;
            $basefile = "/var/log/radacct/datacounter/{$timerange}/used-octets-{$username}";
            $current_base = 0;
            if (is_file($basefile) && is_readable($basefile)) {
                $raw = trim((string)@file_get_contents($basefile));
                if (preg_match('/^\d+$/', $raw)) {
                    $current_base = (int)$raw;
                }
            }
            $new_base = max(0, $current_base + $delta_bytes);
            @file_put_contents($basefile, (string)$new_base);
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
