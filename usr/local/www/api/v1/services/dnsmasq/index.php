<?php
require_once('/etc/inc/api/endpoints/APIServicesDnsmasq.inc');
(new APIServicesDnsmasq())->listen();
