<?php
require_once('/etc/inc/api/endpoints/APIServicesNTPd.inc');
(new APIServicesNTPd())->listen();
