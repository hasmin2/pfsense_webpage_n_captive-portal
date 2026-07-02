<?php
require_once('/etc/inc/api/endpoints/APIServicesDnsmasqHostOverride.inc');
(new APIServicesDnsmasqHostOverride())->listen();
