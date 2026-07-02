<?php
require_once('/etc/inc/api/endpoints/APIFirewallRuleFlush.inc');
(new APIFirewallRuleFlush())->listen();
