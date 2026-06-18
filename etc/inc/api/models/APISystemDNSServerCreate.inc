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


class APISystemDNSServerCreate extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system"];
        $this->change_note = "Added system DNS servers via API";
    }

    public function action() {
        # Write changes to config and apply backend changes
        $this->config["system"]["dnsserver"] = $this->validated_data["dnsserver"];
        # Persist the per-server gateway bindings as positional dns{N}gw keys
        api_dns_write_servergw($this->config["system"], $this->validated_data["dnsserver"], $this->validated_data["dnsservergw"]);
        $this->write_config();
        $this->apply_backend_changes();

        return APIResponse\get(0, $this->validated_data);
    }

    public function validate_payload() {
        $this->validated_data["dnsserver"] = $this->config["system"]["dnsserver"];
        $this->__validate_dnsserver();
    }

    public function __validate_dnsserver() {
        # Validate the optional `dnsserver` payload value plus its optional gateway bindings, which may
        # be given three ways (precedence: dnsservergw_auto > dnsservergw_type > dnsservergw):
        #   - dnsservergw       : parallel array of gateway *names*
        #   - dnsservergw_type  : parallel array of fleet-standard terminal_types (box resolves -> name)
        #   - dnsservergw_auto  : "top2x2" -> box auto-assigns 2 DNS per top-2 online gateways
        # When none are given, existing per-server bindings are preserved (by IP).
        if (isset($this->initial_data['dnsserver'])) {
            # Normalize the requested DNS server list to an array
            $req_servers = $this->initial_data["dnsserver"];
            if (!is_array($req_servers)) {
                $req_servers = [$req_servers];
            }

            # Per-request explicit bindings keyed by server IP (so they stay aligned even when the
            # additive-create path prepends existing servers). Source: terminal_type > name.
            $type_sent = isset($this->initial_data["dnsservergw_type"]);
            $name_sent = isset($this->initial_data["dnsservergw"]);
            $auto = isset($this->initial_data["dnsservergw_auto"]) ? trim((string)$this->initial_data["dnsservergw_auto"]) : "";
            $req_types = $type_sent ? (is_array($this->initial_data["dnsservergw_type"]) ? $this->initial_data["dnsservergw_type"] : [$this->initial_data["dnsservergw_type"]]) : [];
            $req_names = $name_sent ? (is_array($this->initial_data["dnsservergw"]) ? $this->initial_data["dnsservergw"] : [$this->initial_data["dnsservergw"]]) : [];

            $valid_gws = api_dns_valid_gateway_names($this->config);
            $online = api_dns_online_gateway_names($this->config);
            $existing_map = api_dns_existing_servergw_map($this->config["system"]);

            # Seed from already-validated servers (additive create path). In the update/replace path
            # validated_data starts unset, so this is empty and the request fully replaces the list.
            $servers = [];
            if (isset($this->validated_data["dnsserver"])) {
                $servers = is_array($this->validated_data["dnsserver"])
                    ? $this->validated_data["dnsserver"]
                    : [$this->validated_data["dnsserver"]];
            }

            # Validate each requested DNS server; capture any explicitly-requested gateway by server IP
            $explicit_gw = [];
            foreach ($req_servers as $idx => $ds) {
                if (!is_ipaddrv4($ds) and !is_ipaddrv6($ds)) {
                    $this->errors[] = APIResponse\get(1007);
                    break;
                }
                $servers[] = $ds;
                if ($type_sent) {
                    $t = isset($req_types[$idx]) ? trim((string)$req_types[$idx]) : "";
                    $explicit_gw[$ds] = ($t === "" or strtolower($t) === "none") ? "" : api_dns_name_for_terminal_type($this->config, $t, $online);
                } elseif ($name_sent) {
                    $gw = isset($req_names[$idx]) ? $req_names[$idx] : "";
                    $explicit_gw[$ds] = api_dns_normalize_gateway($gw, $valid_gws, $this->errors);
                }
            }

            # Dedup servers preserving order
            $seen = [];
            $out_servers = [];
            foreach ($servers as $ds) {
                if ($ds === null or $ds === "") {
                    continue;
                }
                if (in_array($ds, $seen, true)) {
                    continue;
                }
                $seen[] = $ds;
                $out_servers[] = $ds;
            }

            # Resolve the aligned gateway list for the final server order
            if ($auto !== "") {
                # Auto policy depends on the final positions, not per-IP
                $out_gws = api_dns_auto_servergw($this->config, $out_servers, $auto);
            } else {
                # explicit request (by IP) > preserved existing binding > none
                $out_gws = [];
                foreach ($out_servers as $ds) {
                    if (array_key_exists($ds, $explicit_gw)) {
                        $out_gws[] = $explicit_gw[$ds];
                    } elseif (array_key_exists($ds, $existing_map)) {
                        $out_gws[] = $existing_map[$ds];
                    } else {
                        $out_gws[] = "";
                    }
                }
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
