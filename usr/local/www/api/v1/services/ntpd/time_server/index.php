<?php
require_once('/etc/inc/api/endpoints/APIServicesNTPdTimeServer.inc');
(new APIServicesNTPdTimeServer())->listen();
