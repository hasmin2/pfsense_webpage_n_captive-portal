<?php
require_once('/etc/inc/api/endpoints/APIFirewallTrafficShaperLimiter.inc');
(new APIFirewallTrafficShaperLimiter())->listen();
