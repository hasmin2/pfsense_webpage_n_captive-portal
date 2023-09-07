<?php
/*
 * head.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("globals.inc");
require_once("functions.inc");
require_once("shortcuts.inc");
require_once("service-utils.inc");
require_once('notices.inc');
header('Content-Type: text/html; charset=utf-8');
$pagetitle = gentitle($pgtitle);
$vesselinfo=$config['system']['vesselinfo'];
if ($user_settings['webgui']['pagenamefirst']) {
	$tabtitle = $pagetitle . " - " . htmlspecialchars($system_url);
} else {
	$tabtitle = htmlspecialchars($vesselinfo['vesselname']) . " - " . $pagetitle;
}

$cssfile = "/css/pfSense.css";

if (isset($user_settings['webgui']['webguicss'])) {
	if (file_exists("/usr/local/www/css/" . $user_settings['webgui']['webguicss'])) {
		$cssfile = "/css/" . $user_settings['webgui']['webguicss'];
	}
}
// set default columns to two if unset
if (!isset($config['system']['webgui']['dashboardcolumns'])) {
	$config['system']['webgui']['dashboardcolumns'] = 2;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="apple-touch-icon-precomposed" href="/apple-touch/apple-touch-icon-iphone-60x60-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="60x60" href="/apple-touch/apple-touch-icon-ipad-76x76-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="114x114" href="/apple-touch/apple-touch-icon-iphone-retina-120x120-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="144x144" href="/apple-touch/apple-touch-icon-ipad-retina-152x152-precomposed.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/manifest.json">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="theme-color" content="#ffffff">

	<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css?v=<?=filemtime('/usr/local/www/vendor/font-awesome/css/all.min.css')?>">
	<link rel="stylesheet" href="/vendor/font-awesome/css/v4-shims.css?v=<?=filemtime('/usr/local/www/vendor/font-awesome/css/v4-shims.css')?>">
	<link rel="stylesheet" href="/vendor/sortable/sortable-theme-bootstrap.css?v=<?=filemtime('/usr/local/www/vendor/sortable/sortable-theme-bootstrap.css')?>">
	<link rel="stylesheet" href="<?=$cssfile?>?v=<?=filemtime('/usr/local/www/' . $cssfile)?>" />

	<title><?=$tabtitle?></title>
	<script type="text/javascript">
	//<![CDATA[
	var events = events || [];
	var newSeperator = false;
	//]]>
	</script>
</head>

<?php

/* Determine automated help URL. Should output the page name and parameters
   separately */
$uri_split = "";
preg_match("/\/(.*)\?(.*)/", $_SERVER["REQUEST_URI"], $uri_split);
/* If there was no match, there were no parameters, just grab the filename
   Otherwise, use the matched filename from above. */
if (empty($uri_split[0])) {
	$pagename = ltrim($_SERVER["REQUEST_URI"], '/');
} else {
	$pagename = $uri_split[1];
}

/* If the page name is still empty, the user must have requested / (index.php) */
if (empty($pagename)) {
	$pagename = "index.php";
}

/* If the filename is pkg_edit.php or wizard.php, reparse looking
	for the .xml filename */
if (($pagename == "pkg.php") || ($pagename == "pkg_edit.php") || ($pagename == "wizard.php")) {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 4) == "xml=") {
			$xmlfile = explode('=', $param);
			$pagename = $xmlfile[1];
		}
	}
} else if ($pagename == "status_logs.php") {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 8) == "logfile=") {
			$logtype = explode('=', $param);
			$pagename .= '-' . $logtype[1];
		}
	}
}

// Build the full help URL.
$helpurl .= "{$g['help_base_url']}?page={$pagename}";

/*
 * Read files from $g['ext_menu_path']/*.xml and fill an array with menu info
 */
function read_ext_menu_path_data() {
	global $g;

	$result = array();

	if (!is_dir($g['ext_menu_path'])) {
		return $result;
	}

	foreach (glob("{$g['ext_menu_path']}/*.xml") as $menu_xml) {
		$xml_data = parse_xml_config_pkg($menu_xml, "packagegui");
		if (empty($xml_data['menu'])) {
			continue;
		}
		foreach ($xml_data['menu'] as $menu) {
			$result[] = $menu;
		}
	}

	return $result;
}

// Create a menu entry of any installed packages in the specified category
// (Now reads the menu information from $config['installedpackages']['menu'] only)
function return_ext_menu($section) {
	global $config, $ext_menu_path_data;

	$htmltext = "";
	$extarray = array();
	$ext_menu_entries = array();

	if ((!empty($config['installedpackages']['package'])) && (!empty($config['installedpackages']['menu']))) {
		foreach ($config['installedpackages']['menu'] as $menu) {
			if (isset($menu['name']) && ($menu['name'] != "AutoConfigBackup")) { // AutoConfigBackup was moved to a built-in function
	//			print('Name: ' . $menu['name'] . ', Pkg category: ' . $menu['category'] . ', Section: ' . $section . '<br />');
				if (isset($menu['section']) && ($menu['section'] == $section)) {
					$ext_menu_entries[] = $menu;
				}
			}
		}
	}

	foreach ($ext_menu_path_data as $menu) {
		if ($menu['section'] == $section) {
			$ext_menu_entries[] = $menu;
		}
	}

	foreach ($ext_menu_entries as $menu) {
		if ($menu['url'] != "") {
			$test_url = $menu['url'];
			$addresswithport = getenv("HTTP_HOST");
			$colonpos = strpos($addresswithport, ":");

			if ($colonpos !== false) {
				//my url is actually just the IP address of the pfsense box
				$myurl = substr($addresswithport, 0, $colonpos);
			} else {
				$myurl = $addresswithport;
			}
			$description = str_replace('$myurl', $myurl, $menu['url']);
		} else {
			$description = '/pkg.php?xml=' . $menu['configfile'];
			$test_url=$description;
		}

		if (isAllowedPage($test_url)) {
			$extarray[] = array($menu['name'], $description);
		}
	}

	return $extarray;
}

function output_menu($arrayitem, $target = null, $section = "") {
	$output = "";

	foreach ($arrayitem as $item) {

		/* If the user has access to help pages, also show the full help menu. See #5909 */
		if (isAllowedPage($item[1]) || $item[1] == "/index.php?logout" ||
		    (($section == "Help") && isAllowedPage("help.php")) ||
		    (substr($item[1], 0, 4) == "http") || (substr($item[1], 0, 7) == "mailto:")) {
			$attr = sprintf("href=\"%s\"", htmlentities($item[1]));

			if ($target) {
				$attr .= sprintf(" target=\"%s\"", htmlentities($target));
			}

			$class = "navlnk";

			if ($item['class']) {
				$class .= " {$item['class']}";
			}

			$attr .= sprintf(" class=\"%s\"", htmlentities($class));

			if ($item['style']) {
				$attr .= sprintf(" style=\"%s\"", htmlentities($item['style']));
			}


			if ($item[0] == '-DIVIDER-') {
				$output .= ' <li class="divider"></li>';
			} else {
				$output .= "<li>". sprintf("<a %s %s>%s</a>", $attr, ($item[1] == "/index.php?logout") ? "usepost":"",$item[0]) . "</li>\n";
			}
		}
	}

	return $output;
}

$ext_menu_path_data = read_ext_menu_path_data();

// System
$system_menu = array();
$system_menu[] = array(gettext("Logout") . " (" . $_SESSION['Username'] . ")", "/index.php?logout");
$system_menu[] = array(gettext("Advanced"), "/system_advanced_admin.php");
$system_menu[] = array(gettext("Update"), "/pkg_mgr_install.php?id=firmware");
$system_menu[] = array(gettext("General Setup"), "/system.php");
$system_menu[] = array(gettext("High Avail. Sync"), "/system_hasync.php");
$system_menu[] = array(gettext("Package Manager"), "/pkg_mgr_installed.php");
$system_menu[] = array(gettext("Setup Wizard"), "/wizard.php?xml=setup_wizard.xml");
$system_menu[] = array(gettext("Routing"), "/system_gateways.php");
$system_menu[] = array(gettext("Cert. Manager"), "/system_camanager.php");
if (!isAllowedPage("system_usermanager.php")) {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager_passwordmg.php");
} else {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager.php");
}

if ($user_settings['customsettings'] && isAllowedPage("system_user_settings.php")) {
	$system_menu[] = array(gettext("User Settings"), "/system_user_settings.php");
}

$system_menu = msort(array_merge($system_menu, return_ext_menu("System")), 0);

// Interfaces
// NOTE:
// Now that menus are sorted, adding a DIVIDER must be done after the sorting so an array is formed of the
// items above the divider and another for below it. These are then sorted and combined with the divider
$interfaces_menu = array();
$interfaces_top = array();
$interfaces_bottom = array();

if (!isset($config['system']['webgui']['noassigninterfaces'])) {
	$interfaces_top[] = array(gettext("Assignments"), "/interfaces_assign.php");
	$div = true;
}

$platform = system_identify_specific_platform();

if ($platform['name'] == "uFW") {
	$interfaces_top[] = array(gettext("Switches"), "/switch_system.php");
}

$opts = get_configured_interface_with_descr(true);

foreach ($opts as $oif => $odescr) {
	if (!isset($config['interfaces'][$oif]['ovpn'])) {
		$interfaces_bottom[] = array(htmlspecialchars($odescr), "/interfaces.php?if={$oif}");
	}
}

$interfaces_bottom = array_merge($interfaces_bottom, return_ext_menu("Interfaces"));

if ($user_settings['webgui']['interfacessort']) {
	$interfaces_bottom = msort($interfaces_bottom, 0);
}

// Combine the top section, the divider and the bottom section of this menu
$interfaces_menu = array_merge($interfaces_top, [array(0 => "-DIVIDER-")], $interfaces_bottom);

// Firewall
$firewall_menu = array();
$firewall_menu[] = array(gettext("Aliases"), "/firewall_aliases.php");
$firewall_menu[] = array(gettext("NAT"), "/firewall_nat.php");
$firewall_menu[] = array(gettext("Rules"), "/firewall_rules.php");
$firewall_menu[] = array(gettext("Schedules"), "/firewall_schedule.php");
$firewall_menu[] = array(gettext("Traffic Shaper"), "/firewall_shaper.php");
$firewall_menu[] = array(gettext("Virtual IPs"), "/firewall_virtual_ip.php");
$firewall_menu = msort(array_merge($firewall_menu, return_ext_menu("Firewall")), 0);

// Services
$services_menu = array();
$services_menu[] = array(gettext("Auto Config Backup"), "/services_acb.php");
$services_menu[] = array(gettext("Captive Portal"), "/services_captiveportal.php");
$services_menu[] = array(gettext("DNS Forwarder"), "/services_dnsmasq.php");
$services_menu[] = array(gettext("DNS Resolver"), "/services_unbound.php");
$services_menu[] = array(gettext("DHCP Relay"), "/services_dhcp_relay.php");
$services_menu[] = array(gettext("DHCPv6 Relay"), "/services_dhcpv6_relay.php");

if ($g['services_dhcp_server_enable']) {
	$services_menu[] = array(gettext("DHCP Server"), "/services_dhcp.php");
	$services_menu[] = array(htmlspecialchars(gettext("DHCPv6 Server & RA")), "/services_dhcpv6.php");
}

$services_menu[] = array(gettext("Dynamic DNS"), "/services_dyndns.php");
$services_menu[] = array(gettext("IGMP Proxy"), "/services_igmpproxy.php");
$services_menu[] = array(gettext("NTP"), "/services_ntpd.php");
$services_menu[] = array(gettext("PPPoE Server"), "/services_pppoe.php");
$services_menu[] = array(gettext("SNMP"), "/services_snmp.php");

if (count($config['interfaces']) > 1) {
	/* no use for UPnP in single-interface deployments
	remove to reduce user confusion
	*/
	$services_menu[] = array(gettext("UPnP &amp; NAT-PMP"), "/pkg_edit.php?xml=miniupnpd.xml");
}

$services_menu[] = array(gettext("Wake-on-LAN"), "/services_wol.php");
$services_menu = msort(array_merge($services_menu, return_ext_menu("Services")), 0);

// VPN
$vpn_menu = array();
$vpn_menu[] = array(gettext("IPsec"), "/vpn_ipsec.php");
$vpn_menu[] = array(gettext("OpenVPN"), "/vpn_openvpn_server.php");
//$vpn_menu[] = array(gettext("PPTP"), "/vpn_pptp.php");
$vpn_menu[] = array(gettext("L2TP"), "/vpn_l2tp.php");
$vpn_menu = msort(array_merge($vpn_menu, return_ext_menu("VPN")), 0);

// Status
$status_menu = array();
$status_menu[] = array(gettext("Captive Portal"), "/status_captiveportal.php");
$status_menu[] = array(gettext("CARP (failover)"), "/status_carp.php");
$status_menu[] = array(gettext("Dashboard"), "/index.php");
$status_menu[] = array(gettext("Gateways"), "/status_gateways.php");
$status_menu[] = array(gettext("DHCP Leases"), "/status_dhcp_leases.php");
$status_menu[] = array(gettext("DHCPv6 Leases"), "/status_dhcpv6_leases.php");
$status_menu[] = array(gettext("DNS Resolver"), "/status_unbound.php");
$status_menu[] = array(gettext("Filter Reload"), "/status_filter_reload.php?user=true");
$status_menu[] = array(gettext("Interfaces"), "/status_interfaces.php");
$status_menu[] = array(gettext("IPsec"), "/status_ipsec.php");
$status_menu[] = array(gettext("NTP"), "/status_ntpd.php");
$status_menu[] = array(gettext("OpenVPN"), "/status_openvpn.php");
$status_menu[] = array(gettext("Queues"), "/status_queues.php");
$status_menu[] = array(gettext("Services"), "/status_services.php");
$status_menu[] = array(gettext("System Logs"), "/status_logs.php");
$status_menu[] = array(gettext("Traffic Graph"), "/status_graph.php");

if (count($config['interfaces']) > 1) {
	$status_menu[] = array(gettext("UPnP &amp; NAT-PMP"), "/status_upnp.php");
}

$wifdescrs = array();
$ifentries = get_configured_interface_with_descr();
foreach ($ifentries as $ent => $entdesc) {
	if (is_array($config['interfaces'][$ent]['wireless']) &&
	    preg_match($g['wireless_regex'], $config['interfaces'][$ent]['if'])) {
		$wifdescrs[$ent] = $entdesc;
	}
}

if (count($wifdescrs) > 0) {
	$status_menu[] = array(gettext("Wireless"), "/status_wireless.php");
}

$status_menu = msort(array_merge($status_menu, return_ext_menu("Status")), 0);

// Diagnostics
$diagnostics_menu = array();
$diagnostics_menu[] = array(gettext("ARP Table"), "/diag_arp.php");
$diagnostics_menu[] = array(gettext("Authentication"), "/diag_authentication.php");
$diagnostics_menu[] = array(htmlspecialchars(gettext("Backup & Restore")), "/diag_backup.php");
$diagnostics_menu[] = array(gettext("Command Prompt"), "/diag_command.php");
$diagnostics_menu[] = array(gettext("DNS Lookup"), "/diag_dns.php");
$diagnostics_menu[] = array(gettext("Edit File"), "/diag_edit.php");
$diagnostics_menu[] = array(gettext("Factory Defaults"), "/diag_defaults.php");

if (file_exists("/var/run/gmirror_active")) {
	$diagnostics_menu[] = array(gettext("GEOM Mirrors"), "/diag_gmirror.php");
}

$diagnostics_menu[] = array(gettext("Halt System"), "/diag_halt.php");
$diagnostics_menu[] = array(gettext("Limiter Info"), "/diag_limiter_info.php");
$diagnostics_menu[] = array(gettext("NDP Table"), "/diag_ndp.php");
$diagnostics_menu[] = array(gettext("Tables"), "/diag_tables.php");
$diagnostics_menu[] = array(gettext("Ping"), "/diag_ping.php");
$diagnostics_menu[] = array(gettext("Test Port"), "/diag_testport.php");
$diagnostics_menu[] = array(gettext("pfInfo"), "/diag_pf_info.php");
$diagnostics_menu[] = array(gettext("pfTop"), "/diag_pftop.php");
$diagnostics_menu[] = array(gettext("Reboot"), "/diag_reboot.php");
$diagnostics_menu[] = array(gettext("Routes"), "/diag_routes.php");
$diagnostics_menu[] = array(gettext("S.M.A.R.T. Status"), "/diag_smart.php");
$diagnostics_menu[] = array(gettext("Sockets"), "/diag_sockets.php");
$diagnostics_menu[] = array(gettext("States"), "/diag_dump_states.php");
$diagnostics_menu[] = array(gettext("States Summary"), "/diag_states_summary.php");
$diagnostics_menu[] = array(gettext("System Activity"), "/diag_system_activity.php");
$diagnostics_menu[] = array(gettext("Traceroute"), "/diag_traceroute.php");
$diagnostics_menu[] = array(gettext("Packet Capture"), "/diag_packet_capture.php");

$diagnostics_menu = msort(array_merge($diagnostics_menu, return_ext_menu("Diagnostics")), 0);
if (!$g['disablehelpmenu']) {
///////////////////////////////////////////////////////////////////////////////////////////////////
//여기에서 헬프 링크 추가한다, 만약 https 가 아닌 다른 링크를 넣고싶으면 204번째줄의 아웃풋 펑션에서 추가하면 된다.
//
///////////////////////////////////////////////////////////////////////////////////////////////////
	$help_menu = array();
	$help_menu[] = array(gettext("Mail to Support"), "mailto:korea@synersat.com?subject=Support Request");
	$help_menu[] = array(gettext("Download TeamViewer Quick Support"), "http://{$_SERVER['SERVER_ADDR']}/manuals/TeamViewerQS.exe");
	$help_menu[] = array(gettext("SynerSAT Home(Korean)"), "https://synersat.co.kr");
	$help_menu[] = array(gettext("FX Guide Book - English"), "http://{$_SERVER['SERVER_ADDR']}/manuals/Fleet Xpress User guide latest-Eng.pdf");
	$help_menu[] = array(gettext("FX Guide Book - Korean"), "http://{$_SERVER['SERVER_ADDR']}/manuals/Fleet Xpress User guide latest-Kor.pdf");
	$help_menu[] = array(gettext("Bluewave Mail Guide Book - English"), "http://{$_SERVER['SERVER_ADDR']}/manuals/Bluewave Mail User guide latest-Eng.pdf");
	$help_menu[] = array(gettext("Bluewave Mail Guide Book - Korean"), "http://{$_SERVER['SERVER_ADDR']}/manuals/Bluewave Mail User guide latest-Kor.pdf");
	$help_menu[] = array(gettext("FX Quick Guide - English"), "http://{$_SERVER['SERVER_ADDR']}/manuals/Fleet Xpress Quick guide latest-Eng.pdf");
	$help_menu[] = array(gettext("Fleet Hotspot Guide - English"), "http://{$_SERVER['SERVER_ADDR']}/manuals/Fleet Hotspot User guide latest-Eng.pdf");
	$help_menu[] = array(gettext("Crew Internet Guide - English"), "http://{$_SERVER['SERVER_ADDR']}/manuals/Crew Internet User guide latest-Eng.pdf");
	$help_menu[] = array(gettext("Crew Internet Guide - Korean"), "http://{$_SERVER['SERVER_ADDR']}/manuals/Crew Internet User guide latest-Kor.pdf");
	$help_menu = msort(array_merge($help_menu, return_ext_menu("Help")), 0);
}

$menuclass = "static";

if ($user_settings['webgui']['webguifixedmenu'] == "fixed") {
	$menuclass = "fixed";
}

$numColumns = (int) $user_settings['webgui']['dashboardcolumns'];

if (($pagename === "index.php") && ($numColumns > 2)) {
	$columnsContainer = 'style="max-width: ' . 585*$numColumns . 'px;width: 100%"';
}

$display_notices = false;
$allow_clear_notices = false;

if (are_notices_pending()) {
	// Evaluate user privs to determine if notices should be displayed, and if the user can clear them.
	$user_entry = getUserEntry($_SESSION['Username']);
	if (isAdminUID($_SESSION['Username']) || userHasPrivilege($user_entry, "user-view-clear-notices") || userHasPrivilege($user_entry, "page-all")) {
		$display_notices = true;
		$allow_clear_notices = true;
	} elseif (userHasPrivilege($user_entry, "user-view-notices")) {
		$display_notices = true;
	}
}
?>
<body id="<?=$numColumns?>">
<nav id="topmenu" class="navbar navbar-<?=$menuclass?>-top navbar-inverse">
	<div class="container">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#pf-navbar">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="/">
				<?//php include("/usr/local/www/logo.svg"); ?>
<svg width="171" height="39" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" overflow="hidden"><defs><clipPath id="clip0"><rect x="614" y="286" width="171" height="39"/></clipPath><radialGradient cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" spreadMethod="pad" id="fill1" gradientTransform="matrix(17.0809 0 0 -2.3083 354.39 397.227)"><stop offset="0" stop-color="#ACA6A5"/><stop offset="0.612978" stop-color="#FFFFFF"/><stop offset="1" stop-color="#FFFFFF"/></radialGradient><linearGradient x1="346.961" y1="423.652" x2="366.543" y2="398.475" gradientUnits="userSpaceOnUse" spreadMethod="pad" id="fill2"><stop offset="0" stop-color="#93D9F8"/><stop offset="0.527466" stop-color="#6B86C2"/><stop offset="1" stop-color="#69489A"/></linearGradient><radialGradient cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" spreadMethod="pad" id="fill3" gradientTransform="matrix(20.8769 0 0 -20.8769 350.484 419.786)"><stop offset="0" stop-color="#FFFFFF"/><stop offset="0.325095" stop-color="#FFFFFF"/><stop offset="1" stop-color="#BDB8B7"/></radialGradient><linearGradient x1="341.726" y1="425.426" x2="366.346" y2="400.806" gradientUnits="userSpaceOnUse" spreadMethod="pad" id="fill4"><stop offset="0" stop-color="#93D9F8"/><stop offset="0.527466" stop-color="#6B86C2"/><stop offset="1" stop-color="#69489A"/></linearGradient><linearGradient x1="336.99" y1="412.933" x2="371.808" y2="412.933" gradientUnits="userSpaceOnUse" spreadMethod="pad" id="fill5"><stop offset="0" stop-color="#93D9F8"/><stop offset="0.527466" stop-color="#6B86C2"/><stop offset="1" stop-color="#69489A"/></linearGradient></defs><g clip-path="url(#clip0)" transform="translate(-614 -286)"><path d="M0 0C-1.41383 0-2.67956 0.129105-3.78708 0.38605-4.89587 0.64426-5.85403 0.964491-6.66284 1.34674L-5.80467 5.52621-5.33761 5.52621C-4.57437 4.79334-3.6757 4.22503-2.64792 3.82252-1.61508 3.42129-0.50756 3.22003 0.679701 3.22003 1.89354 3.22003 2.78842 3.39344 3.35927 3.74152 3.93011 4.08833 4.2149 4.58703 4.2149 5.23382 4.2149 5.47558 4.16934 5.68569 4.07694 5.8591 3.98201 6.0363 3.8048 6.2097 3.53773 6.38184 3.27066 6.55272 2.90487 6.72233 2.44287 6.8894 1.97961 7.05775 1.37965 7.25141 0.645526 7.47038-0.162014 7.70327-0.887281 7.95769-1.52901 8.23109-2.17074 8.50449-2.71627 8.82851-3.1694 9.20317-3.62127 9.58542-3.96429 10.0259-4.19845 10.5259-4.43008 11.0246-4.54906 11.6233-4.54906 12.3169-4.54906 14.0649-3.81746 15.4597-2.35174 16.5039-0.887281 17.5444 1.11765 18.0659 3.66178 18.0659 4.88321 18.0659 6.03883 17.9608 7.12483 17.7494 8.20957 17.5393 9.13229 17.2545 9.8892 16.8951L9.0766 12.8789 8.6222 12.8789C8.04249 13.4383 7.28052 13.9054 6.33628 14.2813 5.38951 14.6585 4.3554 14.8458 3.23902 14.8458 2.1391 14.8458 1.29105 14.6787 0.689827 14.3459 0.0898673 14.0117-0.210112 13.551-0.210112 12.9611-0.210112 12.6877-0.165812 12.4624-0.0772099 12.2827 0.0113916 12.103 0.192392 11.927 0.468323 11.7549 0.705015 11.5992 1.07967 11.4296 1.59103 11.2461 2.10239 11.0625 2.70488 10.8727 3.39471 10.679 5.24648 10.155 6.57044 9.54239 7.36279 8.83991 8.15767 8.13742 8.55385 7.18432 8.55385 5.98314 8.55385 4.97688 8.33867 4.09086 7.90832 3.32509 7.47797 2.56059 6.88434 1.94037 6.12996 1.46446 5.33508 0.955631 4.43514 0.58477 3.42635 0.351875 2.41756 0.117714 1.27586 0 0 0" fill="#647ABB" transform="matrix(1 0 0 -1.00833 664.943 315.663)"/><path d="M0 0-4.4035 0-1.22144 5.08193-3.27826 17.9722 0.8164 17.9722 1.89734 9.36265 6.65524 17.9722 10.8879 17.9722Z" fill="#647ABB" transform="matrix(1 0 0 -1.00833 679.445 320.208)"/><path d="M0 0C0-0.234161-0.0139231-0.487309-0.043035-0.760708-0.0734127-1.03411-0.122776-1.3189-0.186063-1.61508L-2.05303-10.1753-6.10718-10.1753-4.68829-3.65292C-4.5845-3.16814-4.50349-2.75424-4.444-2.41249-4.38451-2.06948-4.35413-1.74798-4.35413-1.4518-4.35413-0.94424-4.47691-0.573379-4.7212-0.339217-4.96675-0.105056-5.41482 0.0113916-6.07047 0.0113916-6.38817 0.0113916-6.7565-0.0607554-7.1704-0.205049-7.58556-0.349343-8.05388-0.565784-8.5741-0.854372L-10.6094-10.1753-14.6357-10.1753-11.7511 2.97448-7.72226 2.97448-8.05262 1.52268C-7.22862 2.09226-6.47551 2.53654-5.79707 2.85677-5.11611 3.177-4.36806 3.33775-3.55292 3.33775-2.43148 3.33775-1.55812 3.05296-0.935379 2.48211-0.310106 1.91253 0 1.08474 0 0" fill="#647ABB" transform="matrix(1 0 0 -1.00833 704.296 305.085)"/><path d="M0 0C0.0227833 0.141763 0.0392378 0.270868 0.0493637 0.388581 0.0620211 0.506295 0.0658183 0.627805 0.0658183 0.753113 0.0658183 1.37206-0.116448 1.84544-0.479714 2.1758-0.845512 2.50489-1.36953 2.66944-2.05556 2.66944-2.87955 2.66944-3.57444 2.43528-4.13896 1.96442-4.69968 1.49357-5.12497 0.839183-5.41356 0ZM-3.06308-8.61714C-5.19458-8.61714-6.83751-8.12223-7.99566-7.12863-9.15381-6.13629-9.73225-4.7655-9.73225-3.01625-9.73225-1.82013-9.54365-0.722736-9.1652 0.277196-8.78548 1.27839-8.23995 2.15682-7.52734 2.91373-6.8451 3.64785-5.992 4.23009-4.96928 4.66044-3.9453 5.08953-2.82006 5.30344-1.59483 5.30344 0.268336 5.30344 1.67077 4.90726 2.61754 4.11364 3.56431 3.32003 4.0377 2.14922 4.0377 0.602491 4.0377 0.0708813 3.98707-0.449337 3.88581-0.956897 3.78582-1.46572 3.65418-1.97075 3.49343-2.47072L-5.8072-2.47072-5.8072-2.60489-5.8072-2.73905C-5.8072-3.65292-5.50342-4.36679-4.89207-4.88195-4.28199-5.3971-3.34914-5.65531-2.09353-5.65531-1.21511-5.65531-0.384784-5.49836 0.397441-5.18572 1.18473-4.87435 1.86696-4.52627 2.4454-4.14402L2.89221-4.14402 2.14542-7.52734C1.367-7.88681 0.546798-8.15894-0.313903-8.3412-1.17587-8.52474-2.091-8.61714-3.06308-8.61714" fill="#647ABB" transform="matrix(1 0 0 -1.00833 715.998 307.021)"/><path d="M0 0-0.355672 0C-0.534141 0.0620211-0.786022 0.108853-1.11005 0.139231-1.43661 0.17214-1.75811 0.186063-2.07707 0.186063-2.60362 0.186063-3.10992 0.146825-3.59849 0.0632868-4.08706-0.0177203-4.59083-0.148091-5.10725-0.327826L-7.04762-9.13356-11.0803-9.13356-8.18299 4.01492-4.15035 4.01492-4.5769 2.0834C-3.82252 2.66817-3.06815 3.14409-2.31756 3.51242-1.56318 3.87822-0.80754 4.06175-0.0443008 4.06175 0.096196 4.06175 0.251882 4.05922 0.427819 4.05036 0.601225 4.04276 0.755645 4.03137 0.888547 4.01492Z" fill="#647ABB" transform="matrix(1 0 0 -1.00833 732.094 306.135)"/><path d="M0 0C-1.41636 0-2.67703 0.129105-3.78708 0.38605-4.89587 0.64426-5.8553 0.964491-6.66284 1.34674L-5.8072 5.52621-5.34014 5.52621C-4.57437 4.79334-3.67823 4.22503-2.64792 3.82252-1.61635 3.42129-0.508826 3.22003 0.677169 3.22003 1.89354 3.22003 2.78715 3.39344 3.358 3.74152 3.92885 4.08833 4.21617 4.58703 4.21617 5.23382 4.21617 5.47558 4.16934 5.68569 4.07567 5.8591 3.98201 6.0363 3.80227 6.2097 3.5352 6.38184 3.26813 6.55272 2.90487 6.72233 2.44161 6.8894 1.97835 7.05775 1.37965 7.25141 0.645526 7.47038-0.16328 7.70327-0.888547 7.95769-1.53028 8.23109-2.17074 8.50449-2.7188 8.82851-3.17194 9.20317-3.62254 9.58542-3.96555 10.0259-4.19971 10.5259-4.43261 11.0246-4.55032 11.6233-4.55032 12.3169-4.55032 14.0649-3.81746 15.4597-2.35174 16.5039-0.888547 17.5444 1.11511 18.0659 3.65924 18.0659 4.88195 18.0659 6.03883 17.9608 7.12357 17.7494 8.20957 17.5393 9.12976 17.2545 9.8892 16.8951L9.07533 12.8789 8.62093 12.8789C8.04249 13.4383 7.27925 13.9054 6.33375 14.2813 5.38824 14.6585 4.3554 14.8458 3.23649 14.8458 2.13783 14.8458 1.29105 14.6787 0.689827 14.3459 0.0886016 14.0117-0.210112 13.551-0.210112 12.9611-0.210112 12.6877-0.167077 12.4624-0.0772099 12.2827 0.0126574 12.103 0.192392 11.927 0.467057 11.7549 0.705015 11.5992 1.08094 11.4296 1.59103 11.2461 2.10112 11.0625 2.70235 10.8727 3.39217 10.679 5.24648 10.155 6.56791 9.54239 7.36152 8.83991 8.15514 8.13742 8.55385 7.18432 8.55385 5.98314 8.55385 4.97688 8.33867 4.09086 7.90706 3.32509 7.47797 2.56059 6.88434 1.94037 6.1287 1.46446 5.33508 0.955631 4.43261 0.58477 3.42635 0.351875 2.41756 0.117714 1.27586 0 0 0" fill="#647ABB" transform="matrix(1 0 0 -1.00833 738.633 315.663)"/><path d="M0 0-0.675903 6.22363-4.06808 0ZM5.00346-6.68562 0.692358-6.68562 0.311371-3.12637-5.7591-3.12637-7.69568-6.68562-12.1688-6.68562-2.23529 10.7486 2.70235 10.7486Z" fill="#647ABB" transform="matrix(1 0 0 -1.00833 759.211 308.603)"/><path d="M0 0-5.15914 0-8.24501-14.0623-12.5359-14.0623-9.45379 0-14.6167 0-13.8813 3.37192 0.734127 3.37192Z" fill="#647ABB" transform="matrix(1 0 0 -1.00833 782.491 301.165)"/><path d="M340.941 397.119C340.941 394.897 351.417 394.042 359.463 394.769L359.463 394.769C364.735 395.244 367.837 396.338 367.837 397.343L367.837 397.343C367.837 398.346 364.735 399.387 359.463 399.774L359.463 399.774C357.957 399.885 356.367 399.939 354.776 399.939L354.776 399.939C347.864 399.939 340.941 398.922 340.941 397.119" fill="url(#fill1)" transform="matrix(1 0 0 -1.00833 281.336 722.192)"/><path d="M340.584 413.748C340.584 405.969 346.889 399.665 354.666 399.665L354.666 399.665C362.441 399.665 368.745 405.969 368.745 413.748L368.745 413.748C368.745 421.522 362.441 427.828 354.666 427.828L354.666 427.828C346.889 427.828 340.584 421.522 340.584 413.748" fill="url(#fill2)" transform="matrix(1 0 0 -1.00833 281.336 722.192)"/><path d="M342.264 413.748C342.264 406.896 347.816 401.344 354.666 401.344L354.666 401.344C361.515 401.344 367.067 406.896 367.067 413.748L367.067 413.748C367.067 420.595 361.515 426.148 354.666 426.148L354.666 426.148C347.816 426.148 342.264 420.595 342.264 413.748" fill="url(#fill3)" transform="matrix(1 0 0 -1.00833 281.336 722.192)"/><path d="M0 0-1.74292 1.63913 0.043035 3.32129C0.121511 3.4023 0.121511 3.53014 0.043035 3.60988L-0.102525 3.75544-2.03784 1.93278-3.85164 3.63773C-3.93264 3.71873-4.06175 3.71873-4.14022 3.63773L-4.28452 3.49217-2.32896 1.63913-4.26427-0.197455-4.11871-0.343015C-4.04023-0.42149-3.91113-0.42149-3.83138-0.343015L-2.03404 1.34674-0.144294-0.432882 0-0.288588C0.0810072-0.207581 0.0810072-0.0797414 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 637.803 307.489)"/><path d="M0 0C-0.48984-0.578442-0.931582-1.15435-1.31637-1.68343-0.918925-1.72014-0.48984-1.82899-0.0164546-2.01885 0.102525-2.06821 0.091133-2.15935 0.0291119-2.23023L-0.122776-2.36819C-0.601225-2.16188-1.06955-2.01379-1.52268-1.97202-2.02898-2.6821-2.42642-3.28712-2.69602-3.6719-2.75171-3.74911-2.80994-3.79848-2.8631-3.79848L-2.97448-3.79594C-2.54287-3.1289-2.11251-2.4935-1.73533-1.95936L-1.76823-1.95936C-2.13783-1.95936-2.49983-2.00366-2.98334-2.18466L-2.89474-2.06062C-2.87449-1.99986-2.76943-1.86823-2.66058-1.83279-2.34668-1.72267-2.01252-1.66698-1.65432-1.66698L-1.52901-1.66824C-1.05562-1.01512-0.563253-0.365798-0.107588 0.174672L0.00632868 0.173406C0.0658183 0.16328 0.0696155 0.0797414 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 628.334 297.565)"/><path d="M0 0C-0.616414 0.218972-1.17081 0.373392-1.77456 0.410099-2.34161-0.0316434-2.8631-0.531609-3.36559-1.03284-3.46559-1.1341-3.54406-1.14676-3.59849-1.14296L-3.73772-1.13283C-3.24155-0.637931-2.59982 0-2.07075 0.415162-2.5454 0.42149-3.10992 0.343015-3.70481 0.192392L-3.60229 0.26707C-3.5352 0.321497-3.36939 0.398707-3.28459 0.424022-2.78842 0.568316-2.30238 0.629071-1.81886 0.629071L-1.79481 0.629071C-1.25814 1.02651-0.578442 1.45433 0.0405036 1.70368L0.169609 1.70621C0.255679 1.70621 0.241756 1.6733 0.117714 1.61255-0.448071 1.34042-0.991072 0.999932-1.50623 0.616414-1.00246 0.582239-0.448071 0.459462 0.153154 0.25821 0.243021 0.227833 0.24935 0.15442 0.164546 0.096196Z" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 632.662 295.446)"/><path d="M0 0C0.0113916-0.00506295-1.25308 1.08727-1.79735 1.51382-2.35174 0.936645-2.87449 0.374658-3.36053-0.140497-3.45546-0.243021-3.57064-0.236693-3.63266-0.178469L-3.73519-0.0784757C-3.32256 0.374658-2.68336 1.09486-2.08087 1.73533-2.57324 2.10619-3.21624 2.55173-3.71114 2.83272L-3.58963 2.99473C-3.52381 3.05929-3.41749 3.09219-3.32636 3.0403-2.8631 2.77449-2.3606 2.43907-1.80747 2.02518-1.30118 2.55805-0.560721 3.27066 0.024049 3.85164L0.165812 3.7238C0.225301 3.66431 0.24049 3.56052 0.155686 3.47698-0.407567 2.91373-0.973352 2.3644-1.51888 1.80241-1.01259 1.41256-0.469588 0.958163 0.122776 0.435413 0.22277 0.345546 0.216441 0.227833 0.153154 0.156951Z" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 632.653 303.167)"/><path d="M0 0C0.543001 0.470854 1.19992 1.02018 1.93025 1.52521 1.32269 1.95177 0.534141 2.41629 0.00506295 2.66817L0.137965 2.78335C0.22277 2.84538 0.378455 2.84917 0.48984 2.79348 1.05309 2.51375 1.63407 2.16694 2.23149 1.74925 2.83272 2.16694 3.56431 2.61754 4.16048 2.88208L4.32502 2.78842C4.40856 2.73526 4.42628 2.64539 4.27566 2.57198 3.72886 2.29605 3.14536 1.95177 2.53654 1.52521 3.15422 1.0936 3.75038 0.61768 4.33009 0.120245 4.43008 0.0329092 4.42628-0.0594896 4.34781-0.108853L4.18073-0.207581C3.53394 0.356938 2.87449 0.865764 2.23529 1.30118 1.59103 0.865764 0.998666 0.399973 0.434148-0.0822729 0.331623-0.17214 0.175937-0.159483 0.102525-0.096196Z" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 633.533 298.512)"/><path d="M0 0C0.492372 0.701218 1.01132 1.25308 1.48091 1.66318 1.92772 2.06695 2.27579 2.35174 2.82766 2.67324 3.09473 2.82766 2.93651 2.60362 2.69982 2.47958 2.31756 2.27579 1.93278 1.98847 1.50749 1.63533 1.06448 1.24042 0.593631 0.736659 0.137965 0.0974617-0.024049-0.129105-0.0860701-0.121511 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 625.454 298.121)"/><path d="M0 0C0.764505 0.230364 2.06568 0.218972 2.06568 0.218972 2.06568 0.218972 3.36306 0.230364 4.13136 0 4.31743-0.0556924 4.2668-0.184798 3.86556-0.0708813 3.0441 0.162014 2.56691 0.116448 2.06568 0.158217 1.56192 0.116448 1.08474 0.162014 0.265805-0.0708813-0.1367-0.184798-0.186063-0.0556924 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 633.699 293.282)"/><path d="M0 0C0.488574-0.579707 1.07081-1.32902 1.45433-1.8581 1.05562-1.89607 0.553127-2.02012-0.00759442-2.25934L0.0936645-2.38338C0.151888-2.46439 0.282259-2.53021 0.378455-2.4897 0.822729-2.30744 1.25561-2.18213 1.66318-2.14669 2.17074-2.85677 2.66058-3.60102 2.92765-3.97315L3.00612-3.97315C3.04536-3.97315 3.0808-3.91872 2.97701-3.7605 2.61754-3.21497 2.24921-2.66817 1.87329-2.1353L1.9062-2.13403C2.27959-2.13403 2.62261-2.19099 2.9441-2.29984 3.02131-2.32389 3.05549-2.30364 3.03397-2.24289L2.9479-2.06315C2.44287-1.86316 2.04163-1.84291 1.79481-1.84291L1.66698-1.84418C1.23536-1.23916 0.754379-0.597428 0.348078-0.130371 0.227833 0.00759442 0.14556 0 0.14556 0Z" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 643.06 297.389)"/><path d="M0 0C0.61768 0.218972 1.16068 0.322763 1.59103 0.348078 2.15682-0.0936645 2.79981-0.71261 3.29598-1.20878L3.43774-1.20372C3.48457-1.186 3.4871-1.12904 3.39597-1.04044 2.90107-0.544267 2.41376-0.0620211 1.88721 0.353141 2.33022 0.348078 2.75045 0.311371 3.17573 0.208847 3.44534 0.143028 3.46685 0.169609 3.4023 0.217707L3.28459 0.289854C2.87449 0.477183 2.07454 0.579707 1.63407 0.56705L1.61002 0.56705C1.07208 0.964491 0.511358 1.30751-0.0784757 1.58217-0.160749 1.62141-0.315168 1.65052-0.379721 1.64293L-0.474651 1.63533C0.112651 1.37079 0.806274 0.937911 1.32269 0.554393 0.820197 0.520218 0.220238 0.392378-0.502497 0.141763L-0.359469 0.0417693C-0.289854-0.0164546-0.148091-0.0531609 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 639.056 295.384)"/><path d="M0 0C0.501232 0.440476 1.03284 0.897407 1.57837 1.32396 2.13277 0.746785 2.79475 0.0443008 3.28079-0.473386L3.41243-0.373392C3.47065-0.322763 3.48457-0.225301 3.39724-0.127839 2.98334 0.325294 2.46439 0.903736 1.86443 1.5442 2.35427 1.91506 2.84917 2.26314 3.34534 2.54413 3.45926 2.60995 3.4466 2.72007 3.37699 2.80108L3.23902 2.92765C2.75171 2.65425 2.14416 2.24795 1.58977 1.83405 1.0822 2.36693 0.50756 2.91879-0.0734127 3.49976-0.186063 3.61241-0.306308 3.60229-0.373392 3.5428L-0.50756 3.42635C0.0556924 2.86436 0.682232 2.23909 1.29991 1.61255 0.746785 1.19106 0.091133 0.62654-0.501232 0.102525L-0.364532-0.0417693C-0.289854-0.115182-0.148091-0.131637 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 639.099 302.975)"/><path d="M0 0C0.48984 0.577176 1.06575 1.32016 1.45053 1.84924 0.951834 1.89228 0.482246 2.05049 0.00632868 2.24162L0.0772099 2.353C0.125308 2.44667 0.269602 2.52514 0.373392 2.48084 0.817666 2.29731 1.25055 2.172 1.65938 2.1353 2.16568 2.84791 2.66438 3.5871 2.93524 3.97188L3.01878 3.95922C3.05296 3.9415 3.05043 3.87189 2.97195 3.75164 2.61248 3.20485 2.24415 2.65931 1.86949 2.12517L1.90114 2.12517C2.27453 2.12517 2.61754 2.18213 2.93904 2.29098 3.02258 2.31883 3.06941 2.29605 3.00739 2.18972L2.88461 2.02012C2.49224 1.88215 2.14796 1.83152 1.78975 1.83152L1.66191 1.83405C1.25561 1.26574 0.837918 0.71261 0.402504 0.184798 0.263273 0.0164546 0.193658-0.0113916 0.124042-0.00886016Z" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 643.065 314.273)"/><path d="M0 0C0.501232-0.440476 1.03284-0.898673 1.57837-1.32396 2.13277-0.746785 2.79475-0.0405036 3.28079 0.477183L3.41369 0.369595C3.47065 0.318966 3.48457 0.22277 3.39724 0.126574 2.98334-0.32656 2.46439-0.905002 1.86443-1.54167 2.35427-1.91379 2.84917-2.26187 3.34534-2.5454 3.46685-2.61375 3.4466-2.72766 3.37445-2.80108L3.22889-2.92891C2.76817-2.66438 2.14416-2.24921 1.58977-1.83405 1.0822-2.36819 0.50756-2.91879-0.0734127-3.49976-0.182266-3.60988-0.298714-3.60355-0.368329-3.54786L-0.50756-3.42255C0.0556924-2.8593 0.755645-2.17327 1.29991-1.61255 0.794883-1.2227 0.091133-0.627805-0.502497-0.105056L-0.378455 0.0265805C-0.30884 0.112651-0.158217 0.139231 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 639.099 308.695)"/><path d="M0 0C-0.493637 0.701218-1.01386 1.25308-1.48218 1.66318-1.92772 2.06695-2.27706 2.35174-2.82766 2.67324-3.09473 2.82766-2.93398 2.60362-2.70108 2.47958-2.31883 2.27579-1.93278 1.98847-1.50749 1.63533-1.06575 1.24042-0.593631 0.736659-0.137965 0.0974617 0.0227833-0.129105 0.0835386-0.121511 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 646.076 298.121)"/><path d="M0 0C-0.493637-0.699952-1.01386-1.25308-1.48218-1.66318-1.92772-2.06695-2.27706-2.35174-2.82766-2.67324-3.09473-2.82766-2.93398-2.60362-2.70108-2.48084-2.31883-2.27706-1.93278-1.98974-1.50749-1.63533-1.06575-1.24169-0.593631-0.737925-0.137965-0.0987275 0.0227833 0.127839 0.0835386 0.120245 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 646.076 313.553)"/><path d="M0 0C0.273399-0.477183 0.544267-0.997401 0.799946-1.56572 0.565784-1.23789 0.246819-0.874624-0.0873358-0.679701L-0.0556924-0.761974C-0.0392378-0.808806 0.0848044-0.980946 0.207581-1.07841 0.469588-1.28472 0.71261-1.54673 0.929051-1.8657 1.1822-2.45426 1.41636-3.09093 1.63407-3.77569 1.66824-3.87695 1.6809-3.86936 1.65685-3.77949 1.49104-3.19725 1.29991-2.64412 1.09107-2.12264 1.29738-2.47452 1.48091-2.88588 1.64166-3.36053 1.66444-3.42888 1.67584-3.42888 1.65179-3.36053 1.45939-2.76184 1.23283-2.25048 0.968289-1.82266 0.689827-1.16068 0.378455-0.553127 0.0556924-0.00253147 0.0291119 0.0417693-0.173406 0.306308 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 646.597 299.065)"/><path d="M0 0C0.273399 0.477183 0.544267 0.996135 0.799946 1.56572 0.565784 1.23916 0.256945 0.879687-0.0873358 0.686029L-0.0632868 0.744253C-0.0405036 0.820197 0.117714 1.00499 0.207581 1.07588 0.469588 1.28346 0.71261 1.54546 0.929051 1.8657 1.1822 2.45426 1.41636 3.09093 1.63407 3.77316 1.66824 3.87695 1.6809 3.86936 1.65685 3.77949 1.49104 3.19725 1.29991 2.64286 1.09107 2.12264 1.29738 2.47325 1.48091 2.88461 1.64166 3.35927 1.66444 3.42761 1.67584 3.43015 1.65179 3.358 1.45939 2.76057 1.23283 2.24921 0.968289 1.82266 0.689827 1.15941 0.378455 0.551861 0.0556924 0.00126574 0.0291119-0.0443008-0.173406-0.307574 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 646.597 312.605)"/><path d="M0 0C-0.767036-0.229098-2.06568-0.220238-2.06568-0.220238-2.06568-0.220238-3.36686-0.229098-4.13136 0-4.31743 0.0556924-4.26806 0.183532-3.86556 0.0696155-3.04536-0.16328-2.56818-0.117714-2.06568-0.159483-1.56318-0.117714-1.08727-0.16328-0.265805 0.0696155 0.135434 0.183532 0.186063 0.0556924 0 0" fill="#647ABB" fill-rule="evenodd" transform="matrix(1 0 0 -1.00833 637.83 318.391)"/><path d="M339.221 422.788C337.033 419.208 341.52 411.469 349.915 406.341L349.915 406.341C358.309 401.212 367.435 400.637 369.622 404.216L369.622 404.216C370.657 405.908 370.076 408.38 368.286 411.057L368.286 411.057C368.251 410.848 368.208 410.639 368.164 410.432L368.164 410.432C370.029 407.308 369.565 405.872 368.843 404.69L368.843 404.69C367.338 402.227 359.25 403.485 351.282 408.353L351.282 408.353C343.316 413.219 338.493 419.85 340 422.314L340 422.314C340.722 423.5 342.454 424.366 345.289 424.221L345.289 424.221C345.493 424.396 345.703 424.564 345.913 424.724L345.913 424.724C345.32 424.793 344.751 424.827 344.212 424.827L344.212 424.827C341.851 424.827 340.063 424.166 339.221 422.788" fill="url(#fill4)" transform="matrix(1 0 0 -1.00833 281.336 722.192)"/><path d="M366.288 421.025C369.818 420.136 370.505 418.794 370.827 417.448L370.827 417.448C371.505 414.64 364.895 409.811 355.82 407.619L355.82 407.619C346.747 405.428 338.648 406.704 337.971 409.511L337.971 409.511C337.642 410.864 338.253 412.698 340.363 414.603L340.363 414.603C340.383 414.871 340.411 415.136 340.449 415.401L340.449 415.401C337.928 413.398 336.616 411.225 337.083 409.296L337.083 409.296C338.068 405.221 346.714 402.921 356.276 405.229L356.276 405.229C365.84 407.541 372.699 413.585 371.714 417.663L371.714 417.663C371.247 419.589 369.089 420.927 365.931 421.555L365.931 421.555C366.056 421.381 366.173 421.203 366.288 421.025" fill="url(#fill5)" transform="matrix(1 0 0 -1.00833 281.336 722.192)"/></g></svg>
				<span style="color:white;font-size:.5em;text-transform:uppercase;letter-spacing:1px;"></span>
			</a>
		</div>
		<div class="collapse navbar-collapse" id="pf-navbar">
			<ul class="nav navbar-nav">
			<?php
                if ($user_settings['webgui']['webguihostnamemenu'] == 'hostonly') {
                    $help_menu_title = htmlspecialchars($config['system']['hostname']);
                }
                elseif ($user_settings['webgui']['webguihostnamemenu'] == 'fqdn') {
                    $help_menu_title = htmlspecialchars($system_url);
                }
                else {
                    $help_menu_title = 'Help';
                }
                foreach ([
					['name' => 'System',	     'menu' => $system_menu,	  'href' => null],
					['name' => 'Interfaces',     'menu' => $interfaces_menu,  'href' => null],
					['name' => 'Firewall',	     'menu' => $firewall_menu,	  'href' => null],
					['name' => 'Services',	     'menu' => $services_menu,	  'href' => null],
					['name' => 'VPN',		     'menu' => $vpn_menu,		  'href' => null],
					['name' => 'Status',	     'menu' => $status_menu,	  'href' => null],
					['name' => 'Diagnostics',    'menu' => $diagnostics_menu, 'href' => null],
                    ['name' => $help_menu_title, 'menu' => $help_menu,		  'href' => '_blank']
				] as $item):
					if ($item['name'] == 'Help' && $g['disablehelpmenu']) {
						continue;
					}

					$menu_output = output_menu($item['menu'], $item['href'], $item['name']);

					if (strlen($menu_output) > 0):
?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
						<?=gettext($item['name'])?>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu" role="menu"><?=$menu_output?></ul>
				</li>

<?php
					endif;
			 	endforeach?>
			</ul>
			<ul class="nav navbar-nav navbar-right">
				<?php if ($display_notices):?>
					<?php $notices = get_notices()?>
					<li class="dropdown">
						<a href="#" data-toggle="modal" data-target="#notices" role="button" aria-expanded="false">
							<i class="fa fa-bell text-danger" title="<?=gettext("Notices")?>"></i>
							<span class="badge bg-danger"><?=count($notices)?></span>
						</a>
					</li>
				<?php
					endif;
				?>
					<li class="dropdown">
						<a href="/index.php?logout" usepost>
							<i class="fa fa-sign-out" title="<?=gettext("Logout") . " (" . $_SESSION['Username'] . "@" . htmlspecialchars($system_url) . ")"?>"></i>
						</a>
					</li>
			</ul>
		</div>
	</div>
</nav>

<div class="container <?=$menuclass?>" <?=$columnsContainer?>>

<?php
	// Print a warning if current user = admin and the password hash is still set to the default value
	if ($_SESSION['Username'] == "admin") {
		$cu = getUserEntry("admin");

		$hash = (empty($cu['bcrypt-hash']) ? $cu['password'] : $cu['bcrypt-hash']);

		if (password_verify($g['factory_shipped_password'], $hash)) {
			print('<div class="alert alert-danger">' .
				sprintf(gettext('%sWARNING:%s The \'admin\' account password is set to the default value. ' .
				' %s Change the password in the User Manager.%s'),
				'<strong>', '</strong>', '<a href="/system_usermanager.php?act=edit&userid=' . $cu['uid'] . '">', '</a>') .
				'</div>');
		}
	}
?>

	<header class="header">

<?php
	// If you set $notitle = true BEFORE including head.inc, the page title will be supressed
	if (isset($notitle)) {
		print('<br />');
		unset($notitle);
	} else {
		if (isset($pglinks)) {
			print(genhtmltitle($pgtitle, $pglinks));
		} else {
			print(genhtmltitle($pgtitle));
		}
	}
?>
		<ul class="context-links">

	<?php if (isset($widgets)): ?>
		<li>
			<a href="#" title="<?=gettext("Save dashboard layout")?>" id="btnstore" class="invisible">
				<i class="fa fa-save icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($dashboard_available_widgets_hidden): ?>
		<li>
			<a onclick="$('#widget-available').toggle(360);" title="<?=gettext("Available widgets")?>">
				<i class="fa fa-plus icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($system_logs_filter_form_hidden): ?>
		<li>
			<a onclick="$('#filter-form').toggle(360)" title="<?=gettext("Log filter")?>">
				<i class="fa fa-filter icon-pointer"></i>
			</a>
		</li>
	<?php endif ?>

	<?php if ($system_logs_manage_log_form_hidden):
			/* If the user does not have access to status logs settings page, then exclude the manage log panel icon from the title bar. */
			if (isAllowedPage("status_logs_settings.php")) {
	?>
		<li>
			<a onclick="$('#manage-log-form').toggle(360)" title="<?=gettext("Manage log")?>">
				<i class="fa fa-wrench icon-pointer"></i>
			</a>
		</li>
	<?php	}
		endif
	?>

	<?php if ($monitoring_settings_form_hidden): ?>
		<li>
			<a onclick="$('#monitoring-settings-form').toggle(360);" title="<?=gettext("Settings")?>">
				<i class="fa fa-wrench icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($status_monitoring): ?>
		<li>
			<a class="update-graph" title="<?=gettext("Refresh Graph")?>">
				<i class="fa fa-repeat icon-pointer"></i>
			</a>
		</li>
		<li>
			<a class="export-graph" id="export-graph" title="<?=gettext("Export Graph")?>">
				<i class="fa fa-download icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

<?php
/* Determine shortcut section for XML-based packages */
if (empty($shortcut_section) && !empty($xmlfile)) {
	$shortcut_section = basename($pagename, '.xml');
}

if (!$hide_service_status && !empty($shortcuts[$shortcut_section]['service']) && isAllowedPage('status_services.php')) {
	$ssvc = array();
	switch ($shortcut_section) {
		case "openvpn":
			$ssvc = find_service_by_openvpn_vpnid($vpnid);
			break;
		case "captiveportal":
			$ssvc = find_service_by_cp_zone($cpzone);
			break;
		default:
			$ssvc = find_service_by_name($shortcuts[$shortcut_section]['service']);
	}
	if (!empty($ssvc)) {
		// echo '<li>'. get_service_status_icon($ssvc, false). '</li>'; TODO: Add missing function
		echo '<li>'. get_service_control_links($ssvc, false). '</li>';
	}
}

if (('' != ($link = get_shortcut_main_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['main']))) {
	echo '<li>' . $link . '</li>';
}

if (('' != ($link = get_shortcut_status_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['status']))) {
	echo '<li>' . $link . '</li>';
}

if (('' != ($link = get_shortcut_log_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['log']))) {
	echo '<li>' . $link . '</li>';
}

?>
	<?php if (!$g['disablehelpicon'] && isAllowedPage("help.php")): ?>
		<li>
			<a href="<?=$helpurl?>" target="_blank" title="<?=gettext("Help for items on this page")?>">
				<i class="fa fa-question-circle"></i>
			</a>
		</li>
	<?php endif?>
		</ul>
	</header>
<?php
/* if upgrade in progress, alert user */
$warning_text = "";
if (file_exists('/conf/needs_package_sync') && platform_booting()) {
	$warning_text = sprintf(gettext(
	    '%1$s%3$s is booting, then packages will be reinstalled in the ' .
	    'background.%2$s%1$sDo not make changes in the GUI until this is ' .
	    'complete.%2$s'), '<p>', '</p>', $g['product_label']);
} elseif (is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"), gettext("Package Manager"));
	$warning_text = sprintf(gettext('%1$sPackages are currently being ' .
	    'reinstalled in the background.%2$s%1$sDo not make changes in ' .
	    'the GUI until this is complete.%2$s'), '<p>', '</p>');
	$warning_text .= sprintf(gettext('%1$sIf the above message is still ' .
	    'displayed after a couple of hours, use the \'Clear Package ' .
	    'Lock\' button on the %3$s page and reinstall packages manually.' .
	    '%2$s'), '<p>', '</p>', sprintf('<a href="diag_backup.php" ' .
	    'title="%1$s &gt; %2$s">%1$s &gt; %2$s</a>', gettext('Diagnostics'),
	    htmlspecialchars(gettext('Backup & Restore'))));
}

if (!empty($warning_text)) {
	print_info_box($warning_text);
}

/*	If this page is being remotely managed then do not allow the loading of the contents. */
if ($config['remote_managed_pages']['item']) {
	foreach ($config['remote_managed_pages']['item'] as $rmp) {
		if ($rmp == $_SERVER['SCRIPT_NAME']) {
			print_info_box(gettext("This page is currently being managed by a remote machine."));
			include("foot.inc");
			exit;
		}
	}
}

// Modal notices window
// The notices modal needs to be outside of the page display div or things get messy
if ($display_notices):
?>

<div id="notices" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>

				<h3 class="modal-title" id="myModalLabel"><?=gettext("Notices")?></h3>
			</div>

			<div class="modal-body">
<?php
	$noticeCategories = array();

	if (is_array($notices)) {
		foreach ($notices as $time => $notice) {
			if (!isset($noticeCategories[ $notice['category'] ])) {
				$noticeCategories[ $notice['category'] ] = array();
			}

			$notice['time'] = $time;
			array_push($noticeCategories[ $notice['category'] ], $notice);
		}
	}

	foreach ($noticeCategories as $category => $catNotices):?>
				<h4><?=$category?></h4>
				<ul>
<?php
	foreach ($catNotices as $notice):
?>
					<li>
						<b>
<?php if (!empty($notice['url'])):?>
							<a href="<?=htmlspecialchars($notice['url'])?>"><?=htmlspecialchars($notice['id'])?></a> -
<?php endif;?>
						</b>
						<?=str_replace("\n", "<br/>", htmlspecialchars(htmlspecialchars_decode(htmlspecialchars_decode($notice['notice']))))?>
						<i>@ <?=date('Y-m-d H:i:s', $notice['time'])?></i>
					</li>
<?php	endforeach;?>
				</ul>
<?php endforeach;?>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-info" data-dismiss="modal"><i class="fa fa-times icon-embed-btn"></i><?=gettext("Close")?></button>
<?php if ($allow_clear_notices && isAllowedPage("/index.php")):?>
				<button type="button" id="clearallnotices" class="btn btn-primary"><i class="fa fa-trash-o icon-embed-btn"></i><?=gettext("Mark All as Read")?></button>
<?php endif;?>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
	events.push(function() {
	    $('#clearallnotices').click(function() {
			ajaxRequest = $.ajax({
				url: "/index.php",
				type: "post",
				data: { closenotice: "all"},
				success: function() {
					window.location = window.location.href;
				},
				failure: function() {
					alert("Error clearing notices!");
				}
			});
		});
	});
//]]>
</script>

<?php
endif; // ($display_notices)

// Get the flash Messages
get_flash_message();
