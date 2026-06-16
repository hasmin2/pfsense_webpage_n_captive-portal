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

class APIFreeRADIUSUserRead extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
          $this->privileges = ["page-all", "page-services-freeradius"];
    }
    public function action()
    {
        $allUsers = $this->config["installedpackages"]["freeradius"]["config"] ?? [];
        $input = $this->initial_data["freeradius_username"] ?? null;
        if (!isset($input)) {
            return APIResponse\get(0, $allUsers);
        }
        if (is_string($input)) {
            foreach ($allUsers as $eachuser) {
                if (($eachuser['varusersusername'] ?? '') === $input) {
                    $timerange = strtolower((string)($eachuser['varusersmaxtotaloctetstimerange'] ?? ''));
                    $curquota = $this->getuservolume(
                        (string)($eachuser['varusersusername'] ?? ''),
                        $timerange
                    );

                    $eachuser['varuserscurquota'] = number_format($curquota / 1048576, 2, '.', '');
                    return APIResponse\get(0, (object)[$eachuser]);
                }
            }
            return APIResponse\get(0, (object)[]);
        }

        if (is_array($input)) {
            $wanted = array_flip(array_map('strval', $input));
            $result = [];

            foreach ($allUsers as $eachuser) {
                $username = (string)($eachuser['varusersusername'] ?? '');
                if (isset($wanted[$username])) {
                    $timerange = strtolower((string)($eachuser['varusersmaxtotaloctetstimerange'] ?? ''));
                    $curquota = $this->getuservolume($username, $timerange);

                    $eachuser['varuserscurquota'] = number_format($curquota / 1048576, 2, '.', '');
                    $result[] = $eachuser;
                }
            }

            return APIResponse\get(0, (object)$result);
        }
    }

    private function getuservolume($safeUser, $safeRange)
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
