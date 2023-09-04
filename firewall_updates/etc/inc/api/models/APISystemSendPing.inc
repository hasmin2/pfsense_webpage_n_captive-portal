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

namespace etc\inc\api\models;
use APIModel;

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

class APISystemSendPing extends APIModel
{
    # Create our method constructor
    public function __construct()
    {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-advanced-admin"];
        $this->change_note = "Modified system console settings";
        $this->validated_data = $this->config["system"];
    }

    public function action()
    {
        global $config;
        $result = array();
        $gw_metrics_raw = return_gateways_status(true);
        $gw_metrics = [];
        unset($config['gatewaystatus']);
        # Loop through each gateway's metrics, format time data as floats, and update statuses
        foreach ($gw_metrics_raw as $gw) {
            if (!isset($gw["monitor_disable"])) {
                $gw["delay"] = floatval(str_replace(["%", "ms"], "", $gw["delay"]));
                $gw["stddev"] = floatval(str_replace(["%", "ms"], "", $gw["stddev"]));
                $gw["loss"] = floatval(str_replace(["%", "ms"], "", $gw["loss"]));
                $gw["status"] = ($gw["status"] === "none") ? "up" : $gw["status"];
                if ($gw["status"] === "online") {
                    if ($gw["check_method"] === "ping") {
                        $gwList = explode(";", $gw["destinationip"]);
                        if ($gwList[0] === "") {
                            $gwList = array("8.8.8.8", "8.8.4.4", "168.126.63.1", "1.1.1.1", "1.0.0.1", "208.67.222.222");
                        }
                        $randgw = $gwList[rand(0, count($gwList) - 1)];
                        /*mwexec('route add -host '.$randgw.' '.$gw['monitorip']);
                        sleep(1);
                        $gw['choosed_destination']=$randgw;
                        $gw["pingresult"] = mwexec('ping -c1 -t '.$gw['check_timeout'].' -S '.$gw['srcip'].' '.$randgw.' > /dev/null')==0 ? "online": "offline";
                        mwexec('route del -host '.$randgw.' '.$gw['monitorip']);*/
                        mwexec('/bin/sh /etc/inc/ping_check.sh ' . $gw['check_timeout'] . ' ' . $gw['srcip'] . ' ' . $gw['monitorip'] . ' ' . $randgw . ' > /dev/null &');
                        if (file_exists("/etc/inc/" . $gw['srcip'] . ".log")) {
                            $fp = fopen("/etc/inc/" . $gw['srcip'] . ".log", "r");
                            $online_status = preg_replace('/\r\n|\r|\n/', '', fgets($fp));
                            fclose($fp);
                            $gw["pingresult"] = $online_status;
                        } else {
                            $gw["pingresult"] = "unknown";
                        }
                    } else if ($gw["check_method"] === "nmap") {
                        $gwList = explode(";", $gw["destinationip"]);
                        if ($gwList[0] === "") {
                            $gwList = array("google.com:443", "naver.com:443", "daum.net:443", "kakao.com:443", "youtube.com:443", "facebook.com:443", "tiktok.com:443");
                        }
                        $randgw = $gwList[rand(0, count($gwList) - 1)];
                        $randgw_ip = explode(":", $randgw)[0];
                        $randgw_port = explode(":", $randgw)[1];
                        /*mwexec('route add -host '.$randgw_ip.' '.$gw['monitorip']);
                        $gw['choosed_destination']=$randgw;
                        $gw["pingresult"] = mwexec('nc -z -w'.$gw['check_timeout'].' '.$randgw_ip.' '.$randgw_port.' > /dev/null')==0 ? "online": "offline";
                        mwexec('route del -host '.$randgw_ip.' '.$gw['monitorip'].' > /dev/null');*/
                        mwexec('/bin/sh /etc/inc/nmap_check.sh ' . $gw['check_timeout'] . ' ' . $gw['srcip'] . ' ' . $randgw_ip . ' ' . $randgw_port . ' > /dev/null &');
                        if (file_exists("/etc/inc/" . $gw['srcip'] . ".log")) {
                            $fp = fopen("/etc/inc/" . $gw['srcip'] . ".log", "r");
                            $online_status = preg_replace('/\r\n|\r|\n/', '', fgets($fp));
                            fclose($fp);
                            $gw["pingresult"] = $online_status;
                        } else {
                            $gw["pingresult"] = "unknown";
                        }
                    } else if ($gw["check_method"] === 'none') {
                        $gw["pingresult"] = "online";
                    } else {
                        $gw["pingresult"] = "unknown";
                    }
                } else {
                    $gw["pingresult"] = "offline";
                }
                $gw_metrics[] = $gw;
            } else {
                $gw["pingresult"] = "online";
            }
            $config['gatewaystatus'][$gw['name']]['pingresult'] = $gw['pingresult'];
        }
        setup_gateways_monitor();
        write_config("Write ping result");
        return APIResponse\get(0, $gw_metrics);

    }
}