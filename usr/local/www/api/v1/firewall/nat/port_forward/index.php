<?php
require_once('/etc/inc/api/endpoints/APIFirewallNATPortForward.inc');
(new APIFirewallNATPortForward())->listen();
