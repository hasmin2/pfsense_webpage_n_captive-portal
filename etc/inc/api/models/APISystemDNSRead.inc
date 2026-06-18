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

# ----------------------------------------------------------------------------------------------------
# Shared helpers for translating between the API's intuitive parallel `dnsservergw` array and the way
# pfSense actually stores per-DNS-server gateway bindings: the positional scalar keys
# $config['system']['dns1gw'] .. ['dns4gw'] (1-indexed, max 4), where dns{N}gw binds the Nth DNS
# server (in `dnsserver` order) to a gateway name (or the "none" sentinel = no binding).
# system.php (General Setup) and system_resolvconf_generate() both read these positional keys, so a
# parallel `dnsservergw` array alone would never take effect. These helpers are defined here because
# every APISystemDNS* model require_once's this file.
# ----------------------------------------------------------------------------------------------------
if (!function_exists("api_dns_gw_max")) {
    # pfSense only supports gateway bindings for the first 4 DNS servers (dns1gw..dns4gw)
    function api_dns_gw_max() {
        return 4;
    }

    # Read the positional dns{N}gw keys into a 0-based array aligned to the DNS server order. The
    # pfSense "none" sentinel is normalized to "" for the API representation.
    function api_dns_read_servergw($system, $count) {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $key = "dns" . ($i + 1) . "gw";
            $val = (is_array($system) and isset($system[$key])) ? $system[$key] : "";
            $out[] = ($val === "none") ? "" : $val;
        }
        return $out;
    }

    # Build a map of {dns_server_ip => bound_gateway_name} from the current config so existing
    # bindings can be preserved across updates/deletes when the client does not restate them.
    function api_dns_existing_servergw_map($system) {
        $map = [];
        $servers = [];
        if (is_array($system) and isset($system["dnsserver"])) {
            $servers = is_array($system["dnsserver"]) ? $system["dnsserver"] : [$system["dnsserver"]];
        }
        $gws = api_dns_read_servergw($system, count($servers));
        foreach ($servers as $i => $ds) {
            $map[$ds] = isset($gws[$i]) ? $gws[$i] : "";
        }
        return $map;
    }

    # Collect the set of selectable gateway / gateway-group names (mirrors the GUI dropdown) used to
    # validate a requested binding.
    function api_dns_valid_gateway_names($config) {
        $names = [];
        if (function_exists("return_gateways_array")) {
            # Include disabled and inactive gateways so a binding to a temporarily-down uplink validates
            $gws = return_gateways_array(true, false, true);
            if (is_array($gws)) {
                foreach ($gws as $gw_name => $gw) {
                    if (!empty($gw["name"])) {
                        $names[] = $gw["name"];
                    } elseif (is_string($gw_name) and $gw_name !== "") {
                        $names[] = $gw_name;
                    }
                }
            }
        }
        # Manually-configured gateways (in case return_gateways_array is unavailable)
        if (isset($config["gateways"]["gateway_item"]) and is_array($config["gateways"]["gateway_item"])) {
            foreach ($config["gateways"]["gateway_item"] as $gw) {
                if (!empty($gw["name"])) {
                    $names[] = $gw["name"];
                }
            }
        }
        # Gateway groups are valid DNS gateway targets too
        if (isset($config["gateways"]["gateway_group"]) and is_array($config["gateways"]["gateway_group"])) {
            foreach ($config["gateways"]["gateway_group"] as $gg) {
                if (!empty($gg["name"])) {
                    $names[] = $gg["name"];
                }
            }
        }
        return array_values(array_unique($names));
    }

    # Normalize/validate a single requested gateway binding. Blank or "none" => "" (no binding).
    # An unknown name appends an error and returns "".
    function api_dns_normalize_gateway($gw, $valid_gws, &$errors) {
        $gw = is_string($gw) ? trim($gw) : "";
        if ($gw === "" or strtolower($gw) === "none") {
            return "";
        }
        if (in_array($gw, $valid_gws, true)) {
            return $gw;
        }
        $errors[] = APIResponse\get(
            5000,
            "Invalid 'dnsservergw' value '" . $gw . "'. Must be an existing gateway or gateway-group name, " .
            "'none', or blank. Valid gateways: " . (empty($valid_gws) ? "(none configured)" : implode(", ", $valid_gws))
        );
        return "";
    }

    # Persist a 0-based, DNS-order-aligned gateway list back into $system as positional dns{N}gw keys.
    # All dns{1..max}gw keys are cleared first, then re-emitted, so deletes/reorders stay consistent.
    # "" (no binding) is written as the pfSense "none" sentinel, matching the GUI.
    function api_dns_write_servergw(&$system, $servers, $gws) {
        $max = api_dns_gw_max();
        for ($i = 1; $i <= $max; $i++) {
            unset($system["dns" . $i . "gw"]);
        }
        $count = is_array($servers) ? count($servers) : 0;
        for ($i = 0; $i < $count and $i < $max; $i++) {
            $gw = (is_array($gws) and isset($gws[$i])) ? $gws[$i] : "";
            $system["dns" . ($i + 1) . "gw"] = ($gw === "") ? "none" : $gw;
        }
    }

    # ------------------------------------------------------------------------------------------------
    # Fleet-wide (vessel-agnostic) gateway resolution. Gateway *names* differ per vessel but the
    # `terminal_type` field is a fleet-standard enum, so binding logic keys off terminal_type, not name.
    # ------------------------------------------------------------------------------------------------

    # DNS-reliability priority of terminal types, best first. Used as the tiebreak among candidates.
    # vpn / metered_other are excluded from auto binding entirely (see api_dns_rank_gateways).
    # Edit this single list to retune fleet behaviour.
    function api_dns_terminal_type_priority() {
        return [
            "vsat_pri", "nexuswave_pri", "tcp_starlink", "tcp_oneweb", "tcp_kuiper",
            "vsat_sec", "vsat_thi", "nexuswave_sec", "nexuswave_thi", "nexuswave_fth",
            "tcp_other", "fbb_satlink", "fbb_jrc", "fbb_furuno", "fbb_sailor", "iridium_other",
        ];
    }

    # Names of gateways currently considered up: dpinger status "online", or monitoring disabled
    # (pfSense treats monitor_disable gateways as always up). Disabled/force_down are never online.
    function api_dns_online_gateway_names($config) {
        $online = [];
        if (isset($config["gateways"]["gateway_item"]) and is_array($config["gateways"]["gateway_item"])) {
            foreach ($config["gateways"]["gateway_item"] as $gw) {
                if (empty($gw["name"]) or isset($gw["disabled"]) or isset($gw["force_down"])) {
                    continue;
                }
                if (isset($gw["monitor_disable"])) {
                    $online[] = $gw["name"];
                }
            }
        }
        # return_gateways_status() can be a little slow (dpinger sockets) but is the standard source
        # and is already used by other admin APIs (e.g. APISystemSendPing). Called once here.
        if (function_exists("return_gateways_status")) {
            $st = return_gateways_status(true);
            if (is_array($st)) {
                foreach ($st as $name => $s) {
                    if ((isset($s["status"]) ? $s["status"] : "") === "online") {
                        $online[] = is_string($name) ? $name : (isset($s["name"]) ? $s["name"] : "");
                    }
                }
            }
        }
        return array_values(array_unique(array_filter($online)));
    }

    # Resolve a terminal_type to the local gateway name on this box. When several gateways share the
    # type, prefers an online one. Returns "" when no eligible (non-disabled) gateway has that type.
    function api_dns_name_for_terminal_type($config, $type, $online = null) {
        $type = is_string($type) ? trim($type) : "";
        if ($type === "" or strtolower($type) === "none") {
            return "";
        }
        $cands = [];
        if (isset($config["gateways"]["gateway_item"]) and is_array($config["gateways"]["gateway_item"])) {
            foreach ($config["gateways"]["gateway_item"] as $gw) {
                if (empty($gw["name"]) or isset($gw["disabled"])) {
                    continue;
                }
                if ((isset($gw["terminal_type"]) ? $gw["terminal_type"] : "") === $type) {
                    $cands[] = $gw["name"];
                }
            }
        }
        if (empty($cands)) {
            return "";
        }
        if (is_array($online)) {
            foreach ($cands as $n) {
                if (in_array($n, $online, true)) {
                    return $n;
                }
            }
        }
        return $cands[0];
    }

    # Ordered list of candidate gateway names for auto DNS binding (best first): online-first, then by
    # terminal_type priority. Excludes disabled gateways and the vpn / metered_other terminal types.
    function api_dns_rank_gateways($config) {
        $excluded_types = ["vpn", "metered_other"];
        $prio = array_flip(api_dns_terminal_type_priority());
        $online = api_dns_online_gateway_names($config);

        $cands = [];
        if (isset($config["gateways"]["gateway_item"]) and is_array($config["gateways"]["gateway_item"])) {
            foreach ($config["gateways"]["gateway_item"] as $gw) {
                if (empty($gw["name"]) or isset($gw["disabled"])) {
                    continue;
                }
                $tt = isset($gw["terminal_type"]) ? $gw["terminal_type"] : "";
                if (in_array($tt, $excluded_types, true)) {
                    continue;
                }
                $cands[] = [
                    "name"   => $gw["name"],
                    "online" => in_array($gw["name"], $online, true) ? 0 : 1,
                    "prio"   => isset($prio[$tt]) ? $prio[$tt] : 999,
                ];
            }
        }
        usort($cands, function ($a, $b) {
            if ($a["online"] !== $b["online"]) { return $a["online"] - $b["online"]; }
            if ($a["prio"] !== $b["prio"]) { return $a["prio"] - $b["prio"]; }
            return strcmp($a["name"], $b["name"]);
        });
        $names = [];
        foreach ($cands as $c) { $names[] = $c["name"]; }
        return $names;
    }

    # Auto-assign gateways to a DNS server list. Policy "top2x2" (default): 2 DNS servers per gateway
    # across the top gateways, filling the 4 dns{N}gw slots -> dns[0,1]=rank#1, dns[2,3]=rank#2.
    # Only online gateways are used while any are online (so a down in-port link like LANDLINE is never
    # bound when other uplinks are up); if none are online, falls back to type-priority order.
    # Fewer eligible gateways than ranks => remaining DNS fall back to the last available gateway.
    function api_dns_auto_servergw($config, $servers, $policy) {
        $n = is_array($servers) ? count($servers) : 0;
        if ($n === 0) {
            return [];
        }
        $gws = array_fill(0, $n, "");

        $ranked = api_dns_rank_gateways($config);
        if (empty($ranked)) {
            return $gws;
        }
        $online = api_dns_online_gateway_names($config);
        $online_ranked = [];
        foreach ($ranked as $nm) {
            if (in_array($nm, $online, true)) { $online_ranked[] = $nm; }
        }
        $pick = !empty($online_ranked) ? $online_ranked : $ranked;

        # Only "top2x2" is implemented; any non-empty policy maps to it.
        $max = api_dns_gw_max();
        for ($i = 0; $i < $n and $i < $max; $i++) {
            $rank = intdiv($i, 2);
            if (!isset($pick[$rank])) {
                $rank = count($pick) - 1;
            }
            $gws[$i] = $pick[$rank];
        }
        return $gws;
    }

    # Resolve a gateway-name array aligned by INDEX to $servers from the request fields.
    # Precedence: dnsservergw_auto > dnsservergw_type (terminal types) > dnsservergw (names) >
    # preserve existing bindings (by server IP). Used when the server list is already fixed.
    function api_dns_servergw_from_request($config, $servers, $req, &$errors) {
        $n = is_array($servers) ? count($servers) : 0;

        $auto = isset($req["dnsservergw_auto"]) ? trim((string)$req["dnsservergw_auto"]) : "";
        if ($auto !== "") {
            return api_dns_auto_servergw($config, $servers, $auto);
        }

        if (isset($req["dnsservergw_type"])) {
            $types = is_array($req["dnsservergw_type"]) ? $req["dnsservergw_type"] : [$req["dnsservergw_type"]];
            $online = api_dns_online_gateway_names($config);
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $t = isset($types[$i]) ? trim((string)$types[$i]) : "";
                $out[] = ($t === "" or strtolower($t) === "none") ? "" : api_dns_name_for_terminal_type($config, $t, $online);
            }
            return $out;
        }

        if (isset($req["dnsservergw"])) {
            $names = is_array($req["dnsservergw"]) ? $req["dnsservergw"] : [$req["dnsservergw"]];
            $valid = api_dns_valid_gateway_names($config);
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $g = isset($names[$i]) ? $names[$i] : "";
                $out[] = api_dns_normalize_gateway($g, $valid, $errors);
            }
            return $out;
        }

        # Nothing specified -> preserve existing bindings by server IP
        $map = api_dns_existing_servergw_map($config["system"]);
        $out = [];
        foreach ($servers as $ds) {
            $out[] = array_key_exists($ds, $map) ? $map[$ds] : "";
        }
        return $out;
    }
}


class APISystemDNSRead extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system"];
    }

    public function action() {
        $this->validate_payload();
        return APIResponse\get(0, $this->validated_data);
    }

    public function validate_payload() {
        # Initialize DNS items
        $this->validated_data["dnsserver"] = [];
        $this->validated_data["dnsservergw"] = [];
        $this->validated_data["dnsallowoverride"] = false;
        $this->validated_data["dnslocalhost"] = false;

        # Populate our system DNS servers (and their per-server gateway bindings) if they exist
        if (!empty($this->config["system"]["dnsserver"])) {
            $servers = is_array($this->config["system"]["dnsserver"])
                ? $this->config["system"]["dnsserver"]
                : [$this->config["system"]["dnsserver"]];
            $this->validated_data["dnsserver"] = $servers;
            # Translate the positional dns{N}gw keys into a parallel array aligned to dnsserver
            $this->validated_data["dnsservergw"] = api_dns_read_servergw($this->config["system"], count($servers));
        }

        # Format our DNS allow override on WAN
        if (array_key_exists("dnsallowoverride", $this->config["system"])) {
            $this->validated_data["dnsallowoverride"] = true;
        }

        # Format our DNS local host value
        if ($this->config["system"]["dnslocalhost"] === "remote") {
            $this->validated_data["dnslocalhost"] = false;
        } else {
            $this->validated_data["dnslocalhost"] = true;
        }


    }
}
