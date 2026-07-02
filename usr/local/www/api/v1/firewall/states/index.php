<?php
require_once('/etc/inc/api/endpoints/APIFirewallStates.inc');
(new APIFirewallStates())->listen();
