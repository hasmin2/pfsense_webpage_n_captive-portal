<?php
require_once('/etc/inc/api/endpoints/APIStatusLogFirewall.inc');
(new APIStatusLogFirewall())->listen();
