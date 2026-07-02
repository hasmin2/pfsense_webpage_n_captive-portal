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
require_once("api/models/APISystemDNSRead.inc");


class APISystemDNSServerDelete extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system"];
        $this->change_note = "Deleted system DNS servers via API";
    }

    public function action() {
        $this->config["system"]["dnsserver"] = $this->validated_data["dnsserver"];
        # Re-emit the positional dns{N}gw keys so the surviving servers keep their gateway bindings
        # after the list is reindexed (dns{N}gw is positional, so a naive delete would shift them).
        api_dns_write_servergw($this->config["system"], $this->validated_data["dnsserver"], $this->validated_data["dnsservergw"]);
        $this->write_config();
        $this->apply_backend_changes();
        return APIResponse\get(0, $this->validated_data);
    }

    public function validate_payload() {
        $this->validated_data["dnsserver"] = $this->config["system"]["dnsserver"];
        $this->__validate_dnsserver();
    }

    private function __validate_dnsserver() {
        # Validate the optional `dnsserver` payload value
        if (isset($this->initial_data['dnsserver'])) {
            $del_server = $this->initial_data['dnsserver'];
            $del_server = (!is_array($del_server)) ? array($del_server) : $del_server;

            # Snapshot the current DNS server list and its gateway bindings
            $servers = [];
            if (isset($this->validated_data["dnsserver"])) {
                $servers = is_array($this->validated_data["dnsserver"])
                    ? $this->validated_data["dnsserver"]
                    : [$this->validated_data["dnsserver"]];
            }
            $existing_map = api_dns_existing_servergw_map($this->config["system"]);

            # Keep every server not requested for deletion (dedup, order preserved), carrying its
            # gateway binding along so it stays aligned after reindexing.
            $seen = [];
            $out_servers = [];
            $out_gws = [];
            foreach ($servers as $cs) {
                if ($cs === null or $cs === "") {
                    continue;
                }
                if (in_array($cs, $del_server, true)) {
                    continue;
                }
                if (in_array($cs, $seen, true)) {
                    continue;
                }
                $seen[] = $cs;
                $out_servers[] = $cs;
                $out_gws[] = array_key_exists($cs, $existing_map) ? $existing_map[$cs] : "";
            }

            $this->validated_data["dnsserver"] = $out_servers;
            $this->validated_data["dnsservergw"] = $out_gws;
        }
    }

    public function apply_backend_changes() {
        # Update a slew of backend services
        system_resolvconf_generate();
        if (isset($this->config['dnsmasq']['enable'])) {
            services_dnsmasq_configure();
        } elseif (isset($this->config['unbound']['enable'])) {
            services_unbound_configure();
        }

        # Reload DNS services and firewall filter
        send_event("service reload dns");
        filter_configure();
    }
}
