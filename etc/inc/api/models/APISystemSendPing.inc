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

class APISystemSendPing extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-advanced-admin"];
        $this->change_note = "Modified system console settings";
        $this->validated_data = $this->config["system"];
    }
    public function action() {
        global $config;
        $result=array();
        $gw_metrics_raw = return_gateways_status(true);
        $gw_metrics = [];
        unset($config['gatewaystatus']);
        # (#opt-B) dpinger(monitor_disable) 동작 여부와 무관하게, 게이트웨이 status 가
        # online 이면 EXT-NET 실검사(nmap/ping)를 수행한다.
        #  - 기존엔 monitor_disable 게이트웨이를 통째로 건너뛰어(pingresult=online 만 세팅)
        #    내가 만든 외부 인터넷 판정 로직이 안 돌았다.
        #  - monitor_disable 로 dpinger 를 꺼도 return_gateways_status() 가 status=online 으로
        #    강제하므로, 여기서 예전처럼 건너뛰지 않고 아래 동일 경로로 실검사를 돌려
        #    /etc/inc/{srcip}.log 를 갱신한다(표시부 get_extnet_status 는 이 로그를 읽음).
        foreach ($gw_metrics_raw as $gw) {
            $gw["delay"] = floatval(str_replace(["%", "ms"], "", $gw["delay"]));
            $gw["stddev"] = floatval(str_replace(["%", "ms"], "", $gw["stddev"]));
            $gw["loss"] = floatval(str_replace(["%", "ms"], "", $gw["loss"]));
            $gw["status"] = ($gw["status"] === "none") ? "up" : $gw["status"];
            # check_method/check_timeout 방어: monitor_disable 게이트웨이는 상태배열에 값이
            # 비어 있을 수 있어 기본값을 채운다(비면 실검사 대신 online 으로 강등되는 것 방지).
            $check_method = isset($gw["check_method"]) ? $gw["check_method"] : 'none';
            $check_timeout = (isset($gw["check_timeout"]) && $gw["check_timeout"] !== "") ? $gw["check_timeout"] : "10";
            if($gw["status"] === "online"){
                if($check_method === "ping"){
                    $gwList = explode(";", isset($gw["destinationip"]) ? $gw["destinationip"] : "");
                    if($gwList[0] === ""){
                        $gwList = array("168.126.63.2", "8.8.4.4","149.112.112.112","9.9.9.9","1.0.0.1","208.67.222.222");
                    }
                    $randgw = $gwList[rand(0, count($gwList) - 1)];
                    mwexec('/bin/sh /etc/inc/ping_check.sh '.$check_timeout.' '.$gw['srcip'].' '.$gw['monitorip'].' '.$randgw.' > /dev/null &');
                    if(file_exists("/etc/inc/".$gw['srcip'].".log")){
                        $fp=fopen ("/etc/inc/".$gw['srcip'].".log", "r");
                        $online_status= preg_replace('/\r\n|\r|\n/','',fgets($fp));
                        fclose($fp);
                        $gw["pingresult"] = $online_status;
                    }
                    else{
                        $gw["pingresult"] = "unknown";
                    }
                }
                else if ($check_method === "nmap"){
                    $gwList = explode(";", isset($gw["destinationip"]) ? $gw["destinationip"] : "");
                    if($gwList[0] === ""){
                        $gwList = array("google.com:443", "naver.com:443","daum.net:443", "kakao.com:443", "youtube.com:443","facebook.com:443","tiktok.com:443");
                    }
                    $randgw = $gwList[rand(0, count($gwList) - 1)];
                    $randgw_ip = explode(":", $randgw)[0];
                    $randgw_port = explode(":", $randgw)[1];
                    mwexec('/bin/sh /etc/inc/nmap_check.sh '.$check_timeout.' '.$gw['srcip'].' '.$randgw_ip.' '.$randgw_port.' > /dev/null &');
                    if(file_exists("/etc/inc/".$gw['srcip'].".log")){
                        $fp=fopen ("/etc/inc/".$gw['srcip'].".log", "r");
                        $online_status= preg_replace('/\r\n|\r|\n/','',fgets($fp));
                        fclose($fp);
                        $gw["pingresult"] = $online_status;
                    }
                    else{
                        $gw["pingresult"] = "unknown";
                    }
                }
                else if ($check_method === 'none'){
                    $gw["pingresult"] = "online";
                }
                else {
                    $gw["pingresult"] = "unknown";
                }
            }
            else{
                $gw["pingresult"]="offline";
            }
            $gw_metrics[] = $gw;
        }
        return APIResponse\get(0,$gw_metrics);

    }
}
