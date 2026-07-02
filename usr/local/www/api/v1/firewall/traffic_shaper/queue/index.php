<?php
require_once('/etc/inc/api/endpoints/APIFirewallTrafficShaperQueue.inc');
(new APIFirewallTrafficShaperQueue())->listen();
