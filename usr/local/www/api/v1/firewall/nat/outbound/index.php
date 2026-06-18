<?php
require_once('/etc/inc/api/endpoints/APIFirewallNATOutbound.inc');
(new APIFirewallNATOutbound())->listen();
