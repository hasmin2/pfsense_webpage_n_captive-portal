<?php
require_once('/etc/inc/api/endpoints/APIFirewallTrafficShaperLimiterQueue.inc');
(new APIFirewallTrafficShaperLimiterQueue())->listen();
