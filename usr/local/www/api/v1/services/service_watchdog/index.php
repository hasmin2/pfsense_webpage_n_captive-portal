<?php
require_once('/etc/inc/api/endpoints/APIServicesServiceWatchdog.inc');
(new APIServicesServiceWatchdog())->listen();
