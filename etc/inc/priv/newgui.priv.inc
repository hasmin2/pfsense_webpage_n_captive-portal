<?php
/*
 * bandwidthd.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2022 Rubicon Communications, LLC (Netgate)
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

global $priv_list;

$priv_list['page-newGUIAccess'] = array();
$priv_list['page-newGUIAccess']['name'] = "WebCfg - newGUI : Accesses";
$priv_list['page-newGUIAccess']['descr'] = "Allow access to new GUI";
$priv_list['page-newGUIAccess']['match'] = array();
$priv_list['page-newGUIAccess']['match'][] = "index.php*";
$priv_list['page-newGUIAccess']['match'][] = "network_control.php*";
$priv_list['page-newGUIAccess']['match'][] = "terminal.php*";
$priv_list['page-newGUIAccess']['match'][] = "lan_svrstatus.php*";
$priv_list['page-newGUIAccess']['match'][] = "crew_account.php*";
$priv_list['page-newGUIAccess']['match'][] = "download_center.php*";
?>
