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

class APIRoutingGatewayCreate extends APIModel {
    private $currentusage_changed;

    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-system-gateways-editgateway"];
        $this->change_note = "Added gateway via API";
        $this->currentusage_changed = false;
    }

    public function action() {
        $this->__init_config();
        $this->config["gateways"]["gateway_item"][] = $this->validated_data;
        $this->write_config();
        # Mirror the GUI's save_gateway() manual-usage side effect (must run after config write)
        $this->__sync_cumulative_file();
        $this->apply();
        return APIResponse\get(0, $this->validated_data);
    }

    private function __validate_interface() {
        # Require clients to pass in an interface
        if (isset($this->initial_data['interface'])) {
            $interface = APITools\get_pfsense_if_id($this->initial_data['interface']);
            # Require interface to exist
            if ($interface) {
                $this->validated_data["interface"] = $interface;
            } else {
                $this->errors[] = APIResponse\get(6008);
            }
        } else {
            $this->errors[] = APIResponse\get(6007);
        }
    }

    private function __validate_ipprotocol() {
        # Require client to specify an IP protocol
        if (isset($this->initial_data["ipprotocol"])) {
            # Require the IP protocol to be a supported protocol
            if (in_array($this->initial_data["ipprotocol"], ["inet", "inet6"])) {
                $this->validated_data["ipprotocol"] = $this->initial_data["ipprotocol"];
            } else {
                $this->errors[] = APIResponse\get(6010);
            }
        } else {
            $this->errors[] = APIResponse\get(6009);
        }
    }

    private function __validate_name() {
        # Require client to pass in a name for this gateway
        if (isset($this->initial_data["name"])) {
            # Require the gateway name to be valid and not in use already
            if (!is_validaliasname($this->initial_data["name"])) {
                $this->errors[] = APIResponse\get(6012);
            } elseif (APITools\is_gateway($this->initial_data["name"])) {
                $this->errors[] = APIResponse\get(6026);
            } else {
                $this->validated_data["name"] = $this->initial_data["name"];
            }
        } else {
            $this->errors[] = APIResponse\get(6011);
        }
    }

    private function __validate_gateway() {
        # Require client to pass in a gateway address
        if (isset($this->initial_data["gateway"])) {
            # Always set non-local gateway to reduce conditions
            $this->validated_data["nonlocalgateway"] = "";

            # Gateway can be valid IPv4 address
            if ($this->validated_data["ipprotocol"] === "inet" and is_ipaddrv4($this->initial_data["gateway"])) {
                $this->validated_data["gateway"] = $this->initial_data["gateway"];
            }
            # Gateway can be valid IPv6 address
            elseif ($this->validated_data["ipprotocol"] === "inet6" and is_ipaddrv6($this->initial_data["gateway"])) {
                $this->validated_data["gateway"] = $this->initial_data["gateway"];
            }
            # Gateway can be dynamic if one does not already exist and a dynamic gateway is supported
            elseif ($this->initial_data["gateway"] === "dynamic") {
                # Check if this interface and IP protocol combo is eligible for a dynamic interface
                if ($this->allow_dynamic_gw($this->validated_data["interface"], $this->validated_data["ipprotocol"])) {
                    $this->validated_data["gateway"] = $this->initial_data["gateway"];
                } else {
                    $this->errors[] = APIResponse\get(6029);
                }
            }
            # Otherwise this gateway is not valid
            else {
                $this->errors[] = APIResponse\get(6014);
            }
        } else {
            $this->errors[] = APIResponse\get(6013);
        }
    }

    private function __validate_monitor() {
        # Optionally allow client to pass in a alternate gateway monitor address. Default to gateway address.
        if (isset($this->initial_data["monitor"])) {
            # Require gateway address to be valid IPv4 or IPv6 address depending on the protocol
            if ($this->validated_data["ipprotocol"] === "inet" and is_ipaddrv4($this->initial_data["monitor"])) {
                $this->validated_data["monitor"] = $this->initial_data["monitor"];
            } elseif ($this->validated_data["ipprotocol"] === "inet6" and is_ipaddrv6($this->initial_data["monitor"])) {
                $this->validated_data["monitor"] = $this->initial_data["monitor"];
            } else {
                $this->errors[] = APIResponse\get(6025);
            }
        }
        # Automatically assume the gateway address unless it is a dynamic gateway
        elseif($this->validated_data["gateway"] !== "dynamic") {
            $this->validated_data["monitor"] = $this->validated_data["gateway"];
        }
    }

    private function __validate_monitor_disable() {
        # Optionally allow client to disable gateway monitoring
        if ($this->initial_data['monitor_disable'] === true) {
            $this->validated_data["monitor_disable"] = "";
        }
    }

    private function __validate_action_disable() {
        # Optionally allow client to disable gateway actions
        if ($this->initial_data['action_disable'] === true) {
            $this->validated_data["action_disable"] = "";
        }
    }

    private function __validate_force_down() {
        # Optionally allow client to force this gateway to be considered down
        if ($this->initial_data['force_down'] === true) {
            $this->validated_data["force_down"] = "";
        }
    }

    private function __validate_disabled() {
        # Optionally allow client to disable gateway upon entry
        if ($this->initial_data['disabled'] === true) {
            $this->validated_data["disabled"] = "";
        }
    }

    private function __validate_descr() {
        # Optionally allow client to specify a description for this entry
        if (isset($this->initial_data["descr"])) {
            $this->validated_data["descr"] = $this->initial_data["descr"];
        }
    }

    private function __validate_weight() {
        # Optionally allow client to specify this gateway's weight when load balancing. Otherwise assume default
        if (isset($this->initial_data["weight"])) {
            # Require weight to be between 1 and 30
            if ($this->initial_data["weight"] >= 1 and 30 >= $this->initial_data["weight"]) {
                $this->validated_data["weight"] = intval($this->initial_data["weight"]);
            } else {
                $this->errors[] = APIResponse\get(6015);
            }
        } else {
            $this->validated_data["weight"] = 1;
        }
    }

    private function __validate_data_payload() {
        # Optionally allow client to specify a custom data payload to send ICMP monitoring packets
        if (isset($this->initial_data["data_payload"])) {
            # Require data payload to be greater than 1
            if ($this->initial_data["data_payload"] >= 1) {
                $this->validated_data["data_payload"] = intval($this->initial_data["data_payload"]);
            } else {
                $this->errors[] = APIResponse\get(6016);
            }
        } else {
            $this->validated_data["data_payload"] = 1;
        }
    }

    private function __validate_latencylow() {
        # Optionally allow client to set the low threshold for latency monitoring
        if (isset($this->initial_data["latencylow"])) {
            # Require latencylow to be greater or equal than 1
            if ($this->initial_data["latencylow"] >= 1) {
                $this->validated_data["latencylow"] = intval($this->initial_data["latencylow"]);
            } else {
                $this->errors[] = APIResponse\get(6017);
            }
        } else {
            $this->validated_data["latencylow"] = 200;
        }
    }

    private function __validate_latencyhigh() {
        # Optionally allow client to set the high threshold for latency monitoring
        if (isset($this->initial_data["latencyhigh"])) {
            # Require latencyhigh to be greater than low latency threshold
            if ($this->initial_data["latencyhigh"] > $this->validated_data["latencylow"]) {
                $this->validated_data["latencyhigh"] = intval($this->initial_data["latencyhigh"]);
            } else {
                $this->errors[] = APIResponse\get(6018);
            }
        } else {
            $this->validated_data["latencyhigh"] = 500;
        }
    }

    private function __validate_losslow() {
        # Optionally allow client to set the low threshold for packet loss monitoring
        if (isset($this->initial_data["losslow"])) {
            # Require losslow to be greater or equal than 1 and less than or equal to 100
            if ($this->initial_data["losslow"] >= 1 and 100 >= $this->initial_data["losslow"]) {
                $this->validated_data["losslow"] = intval($this->initial_data["losslow"]);
            } else {
                $this->errors[] = APIResponse\get(6019);
            }
        } else {
            $this->validated_data["losslow"] = 10;
        }
    }

    private function __validate_losshigh() {
        # Optionally allow client to set the high threshold for packet loss monitoring
        if (isset($this->initial_data["losshigh"])) {
            # Require losshigh to be greater or equal than 1 and less than the low loss threshold
            if ($this->initial_data["losshigh"] >= $this->validated_data["losslow"] and 100 >= $this->initial_data["losshigh"]) {
                $this->validated_data["losshigh"] = intval($this->initial_data["losshigh"]);
            } else {
                $this->errors[] = APIResponse\get(6020);
            }
        } else {
            $this->validated_data["losshigh"] = 20;
        }
    }

    private function __validate_interval() {
        # Optionally allow client to set the milliseconds between ICMP probes
        if (isset($this->initial_data["interval"])) {
            # Require interval to be between 1 and 3600000
            if ($this->initial_data["interval"] >= 1 and 3600000 >= $this->initial_data["interval"]) {
                $this->validated_data["interval"] = intval($this->initial_data["interval"]);
            } else {
                $this->errors[] = APIResponse\get(6021);
            }
        } else {
            $this->validated_data["interval"] = 500;
        }
    }

    private function __validate_loss_interval() {
        # Optionally allow client to set time in milliseconds when packets will be considered lost
        if (isset($this->initial_data["loss_interval"])) {
            # Require loss_interval to be greater than high latency threshold
            if ($this->initial_data["loss_interval"] >= $this->validated_data["latencyhigh"]) {
                $this->validated_data["loss_interval"] = intval($this->initial_data["loss_interval"]);
            } else {
                $this->errors[] = APIResponse\get(6022);
            }
        } else {
            $this->validated_data["loss_interval"] = 2000;
        }
    }

    private function __validate_time_period() {
        # Optionally allow client to set time in milliseconds which results will be averaged
        if (isset($this->initial_data["time_period"])) {
            # Require time_period to be greater than twice the probe interval plus the loss interval
            $min = 2 * intval($this->validated_data["interval"]) + intval($this->validated_data["loss_interval"]);
            if ($this->initial_data["time_period"] > $min) {
                $this->validated_data["time_period"] = intval($this->initial_data["time_period"]);
            } else {
                $this->errors[] = APIResponse\get(6023);
            }
        } else {
            $this->validated_data["time_period"] = 60000;
        }
    }

    private function __validate_alert_interval() {
        # Optionally allow client to set time in milliseconds which alert conditions are checked
        if (isset($this->initial_data["alert_interval"])) {
            # Require alert_interval to be greater than twice the probe interval plus the loss interval
            if ($this->initial_data["alert_interval"] >= $this->validated_data["interval"]) {
                $this->validated_data["alert_interval"] = intval($this->initial_data["alert_interval"]);
            } else {
                $this->errors[] = APIResponse\get(6024);
            }
        } else {
            $this->validated_data["alert_interval"] = 1000;
        }
    }

    # ----------------------------------------------------------------------------------------------
    # Custom (vessel-specific) gateway fields. These are not part of stock pfSense gateways but are
    # persisted/consumed by save_gateway() (gwlb.inc), system_gateways_edit.php, firewallpreset.inc,
    # terminal_status.inc and the usage crons. The validators below let the API set them exactly the
    # way the GUI does. (Kept in sync with APIRoutingGatewayUpdate.)
    # ----------------------------------------------------------------------------------------------

    # Normalize a checkbox-style flag to the stored representation: 'yes' (enabled) or '' (disabled).
    # Consuming code checks `=== 'yes'` (firewallpreset.inc) / `!empty()` (terminal_status.inc), so a
    # truthy value must persist as the literal string 'yes'.
    private function __normalize_yesno($val) {
        if ($val === true) {
            return "yes";
        }
        if (is_string($val)) {
            # Explicit falsy list guards against PHP's "false"/"0" string truthiness footgun
            if (in_array(strtolower(trim($val)), ["", "0", "false", "no", "off"], true)) {
                return "";
            }
            return "yes";
        }
        return !empty($val) ? "yes" : "";
    }

    private function __validate_terminal_type() {
        # Optionally allow clients to set the terminal/uplink type. Mirrors the GUI select options.
        if (isset($this->initial_data["terminal_type"])) {
            $terminal_type = $this->initial_data["terminal_type"];
            $allowed = [
                "vsat_pri", "vsat_sec", "vsat_thi", "tcp_other",
                "nexuswave_pri", "nexuswave_sec", "nexuswave_thi", "nexuswave_fth",
                "tcp_starlink", "tcp_oneweb", "tcp_kuiper",
                "fbb_satlink", "fbb_jrc", "fbb_furuno", "fbb_sailor",
                "iridium_other", "metered_other", "vpn"
            ];
            # Allow blank to clear, otherwise require a known terminal type
            if ($terminal_type === "" or in_array($terminal_type, $allowed, true)) {
                $this->validated_data["terminal_type"] = $terminal_type;
            } else {
                $this->errors[] = APIResponse\get(5000, "Invalid 'terminal_type'. Allowed values: " . implode(", ", $allowed));
            }
        }
    }

    private function __validate_check_method() {
        # Optionally allow clients to set the online-check method
        if (isset($this->initial_data["check_method"])) {
            $check_method = $this->initial_data["check_method"];
            if ($check_method === "" or in_array($check_method, ["nmap", "none", "ping"], true)) {
                $this->validated_data["check_method"] = $check_method;
            } else {
                $this->errors[] = APIResponse\get(5000, "Invalid 'check_method'. Allowed values: nmap, none, ping");
            }
        }
    }

    private function __validate_check_timeout() {
        # Optionally allow clients to set the online-check timeout in seconds (GUI offers 3/5/10)
        if (isset($this->initial_data["check_timeout"])) {
            $check_timeout = $this->initial_data["check_timeout"];
            if ($check_timeout === "") {
                $this->validated_data["check_timeout"] = "";
            } elseif (is_numeric($check_timeout) and intval($check_timeout) >= 1) {
                $this->validated_data["check_timeout"] = strval(intval($check_timeout));
            } else {
                $this->errors[] = APIResponse\get(5000, "Invalid 'check_timeout'. Must be a positive integer (seconds), e.g. 3, 5, or 10");
            }
        }
    }

    private function __validate_destinationip() {
        # Optionally allow clients to set the check destination. Free-form (IP, IP:port, URL, or
        # ';'-separated list); stored verbatim exactly like the GUI.
        if (isset($this->initial_data["destinationip"])) {
            $this->validated_data["destinationip"] = strval($this->initial_data["destinationip"]);
        }
    }

    private function __validate_allowance() {
        # Optionally allow clients to set the monthly data allowance in GB. Blank or -1 = unlimited.
        if (isset($this->initial_data["allowance"])) {
            $allowance = $this->initial_data["allowance"];
            if ($allowance === "" or is_numeric($allowance)) {
                $this->validated_data["allowance"] = strval($allowance);
            } else {
                $this->errors[] = APIResponse\get(5000, "Invalid 'allowance'. Must be numeric GB, -1, or blank for unlimited");
            }
        }
    }

    private function __validate_rootinterface() {
        # Optionally allow clients to set the root interface name (e.g. vtnet0, vtnet4.10)
        if (isset($this->initial_data["rootinterface"])) {
            $this->validated_data["rootinterface"] = strval($this->initial_data["rootinterface"]);
        }
    }

    private function __validate_currentusage() {
        # Optionally allow clients to set the initial usage in GB. Flag the change so the backing
        # _cumulative file is written in action() (matches save_gateway()).
        if (isset($this->initial_data["currentusage"])) {
            $currentusage = $this->initial_data["currentusage"];
            if ($currentusage === "" or is_numeric($currentusage)) {
                $this->validated_data["currentusage"] = strval($currentusage);
                $this->currentusage_changed = true;
            } else {
                $this->errors[] = APIResponse\get(5000, "Invalid 'currentusage'. Must be numeric (GB)");
            }
        }
    }

    private function __validate_cutoff_enable() {
        # Optionally allow clients to enable/disable cutoff when the monthly allowance is exceeded
        if (isset($this->initial_data["cutoff_enable"])) {
            $this->validated_data["cutoff_enable"] = $this->__normalize_yesno($this->initial_data["cutoff_enable"]);
        }
    }

    private function __validate_disablecrewinternet() {
        # Optionally allow clients to disable crew internet while this gateway is selected
        if (isset($this->initial_data["disablecrewinternet"])) {
            $this->validated_data["disablecrewinternet"] = $this->__normalize_yesno($this->initial_data["disablecrewinternet"]);
        }
    }

    private function __validate_blockall_bydefault() {
        # Optionally allow clients to block all traffic by default while this gateway is selected
        if (isset($this->initial_data["blockall_bydefault"])) {
            $this->validated_data["blockall_bydefault"] = $this->__normalize_yesno($this->initial_data["blockall_bydefault"]);
        }
    }

    private function __validate_firewall_presets() {
        # Optionally allow clients to set the firewall preset lists. The GUI builds these as parallel
        # '||'-joined strings; the API accepts those strings verbatim.
        foreach (["sourceaddresses", "destaddresses", "portsfrom", "portsto", "protos"] as $field) {
            if (isset($this->initial_data[$field])) {
                $this->validated_data[$field] = strval($this->initial_data[$field]);
            }
        }
    }

    # Mirror save_gateway()'s side effect: persist the manual usage adjustment to
    # /etc/inc/{rootinterface}_cumulative, which the usage cron and status builders read back.
    private function __sync_cumulative_file() {
        if (!$this->currentusage_changed) {
            return;
        }
        $rootif = isset($this->validated_data["rootinterface"]) ? $this->validated_data["rootinterface"] : "";
        # Require a root interface and guard the filename against path traversal (it is only ever an
        # interface name such as vtnet4.10).
        if ($rootif === "" or !preg_match('/^[A-Za-z0-9._-]+$/', $rootif)) {
            return;
        }
        @file_put_contents("/etc/inc/" . $rootif . "_cumulative", strval($this->validated_data["currentusage"]));
    }

    public function validate_payload() {
        $this->__validate_disabled();
        $this->__validate_interface();
        $this->__validate_ipprotocol();
        $this->__validate_name();
        $this->__validate_gateway();
        $this->__validate_monitor_disable();
        $this->__validate_action_disable();
        $this->__validate_monitor();
        $this->__validate_force_down();
        $this->__validate_descr();
        $this->__validate_weight();
        $this->__validate_data_payload();
        $this->__validate_latencylow();
        $this->__validate_latencyhigh();
        $this->__validate_losslow();
        $this->__validate_losshigh();
        $this->__validate_interval();
        $this->__validate_loss_interval();
        $this->__validate_time_period();
        $this->__validate_alert_interval();
        # Custom (vessel-specific) fields
        $this->__validate_terminal_type();
        $this->__validate_check_method();
        $this->__validate_check_timeout();
        $this->__validate_destinationip();
        $this->__validate_allowance();
        $this->__validate_rootinterface();
        $this->__validate_currentusage();
        $this->__validate_cutoff_enable();
        $this->__validate_disablecrewinternet();
        $this->__validate_blockall_bydefault();
        $this->__validate_firewall_presets();
    }

    # Checks if this interface already has a saved configuration for a dynamic gateway using this IP protocol
    public function allow_dynamic_gw($interface, $ipprotocol) {
        # Local variables
        $gateway_details = return_gateways_array(true, true);
        $interface = APITools\get_pfsense_if_id($interface);
        $dyn_gw = false;
        $dyn_gw_exists = false;

        # Loop through each system gateway and see if this interface is dynamic
        foreach ($gateway_details as $gateway) {
            if ($gateway["friendlyiface"] === $interface and $gateway["ipprotocol"] === $ipprotocol and isset($gateway["dynamic"])) {
                $dyn_gw = true;
            }
        }

        # Loop through each configured gateway and check if this interface already has a dynamic gateway set
        foreach ($this->config["gateways"]["gateway_item"] as $gateway) {
            if ($gateway["interface"] === $interface and $gateway["ipprotocol"] === $ipprotocol and $gateway["gateway"] === "dynamic") {
                $dyn_gw_exists = true;
            }
        }

        # Check if dynamic gateways should be supported and if one already exists
        if ($dyn_gw and !$dyn_gw_exists) {
            return true;
        } else {
            return false;
        }
    }

    private function __init_config() {
        # Ensure our gateway configuration is in array format
        if (!is_array($this->config["gateways"]["gateway_item"])) {
            $this->config["gateways"] = [];
            $this->config["gateways"]["gateway_item"] = [];
        }
    }

    public function apply() {
        # Mark the routing subsystem as changed, clear if applied
        mark_subsystem_dirty("staticroutes");

        # Optionally allow clients to apply this gateway immediately if they passed in a true apply value
        if ($this->initial_data["apply"] === true) {
            system_routing_configure();
            system_resolvconf_generate();
            filter_configure();
            setup_gateways_monitor();
            send_event("service reload dyndnsall");
            clear_subsystem_dirty("staticroutes");
        }
    }
}
