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
// freeradius_atomic_write_file() 사용을 위해 필요 — APIFreeRadiusUserUpdate.inc 와 동일 사유로
// (주석: "update 컨텍스트엔 미로드") 이 API 컨텍스트에도 자동 로드되지 않으므로 명시적으로 require.
require_once("freeradius.inc");
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
            // 0 하한 clamp: 큰 음수 delta 가 누적값을 음수로 만들면 datacounter_auth.sh 의
            // `sed 's/[^0-9]//g'` 가 '-' 부호만 제거해 훨씬 큰 양수로 오염되므로(부호 유실 손상) 방지.
            $new_maxtotal = max(0,
                (int)$this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctets']
                + (int)$this->initial_data['freeradius_maxtotaloctets']
            );
            $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctets'] = $new_maxtotal;

            // max-octets 파일(=RADIUS 인증 시점 datacounter_auth.sh 가 직접 읽는 쿼터 상한 파일)을
            // 이 자리에서 즉시 동기화한다. config.xml 만 바뀌고 이 파일이 그대로면 실제 로그인
            // 게이트는 옛 값을 계속 쓰게 되어 UI 표시값과 실제 쿼터가 어긋난다(#Topup 버그).
            // freeradius_users_resync()/freeradius_update_user() 는 의도적으로 호출하지 않는다 —
            // 둘 다 radiusd 전체 재시작을 유발해(#23-A) accounting(1813) 리스너가 순간 닫히므로,
            // quota top-up 처럼 로그인보다 빈번하지 않은 이벤트라도 굳이 그 비용을 치를 이유가 없다.
            // datacounter_auth.sh 는 이 파일을 요청마다 그냥 cat 으로 읽으므로 radiusd 재로드 없이도
            // 파일만 바꾸면 즉시 반영된다(resync 의 max-octets 파일 쓰기와 동일한 방식, freeradius.inc
            // freeradius_atomic_write_file() 재사용 — 원자적 rename 이라 읽기 도중 파일이 잘려 보이는
            // torn-read 없음).
            if (function_exists('freeradius_atomic_write_file')) {
                $tr = strtolower(trim((string)$this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctetstimerange']));
                $un = (string)$this->config["installedpackages"]["freeradius"]["config"][$index]['varusersusername'];
                // 새로 파일시스템을 건드리는 코드라 방어적으로 문자셋을 제한한다(경로 이탈 방지).
                // 정상 계정명/timerange 는 create_wifi_user/#32 정규화를 거치므로 항상 이 안에 든다.
                if ($tr !== '' && $un !== '' && preg_match('/^[a-z]+$/', $tr) && preg_match('/^[A-Za-z0-9_.:-]+$/', $un)) {
                    // resync 의 바이트 환산 공식과 동일(freeradius.inc:648-650/1006-1010) — 레거시
                    // varuserstopupoctets 필드까지 포함해야 이후 전체 resync 가 돌 때와 값이 어긋나지 않음.
                    $topup_legacy_mb = (int)($this->config["installedpackages"]["freeradius"]["config"][$index]['varuserstopupoctets'] ?? 0);
                    $bytes = ($new_maxtotal + $topup_legacy_mb) * 1024 * 1024;

                    $dir = "/var/log/radacct/datacounter/{$tr}";
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $max_file = "{$dir}/max-octets-{$un}";

                    if ($bytes > 0) {
                        freeradius_atomic_write_file($max_file, (string)$bytes);
                    } else {
                        // resync 와 동일 취급: 쿼터 0 = 무제한(파일 없음 = datacounter_auth.sh 가 통과).
                        @unlink($max_file);
                    }
                } else {
                    log_error("APIFreeRadiusUserTopup: unsafe username/timerange, skipped max-octets file sync (username={$un} timerange={$tr})");
                }
            }
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
