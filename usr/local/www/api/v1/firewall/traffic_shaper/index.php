<?php
require_once('/etc/inc/api/endpoints/APIFirewallTrafficShaper.inc');
(new APIFirewallTrafficShaper())->listen();
