<?php
require_once('/etc/inc/api/endpoints/APIFirewallTrafficShaperLimiterBandwidth.inc');
(new APIFirewallTrafficShaperLimiterBandwidth())->listen();
