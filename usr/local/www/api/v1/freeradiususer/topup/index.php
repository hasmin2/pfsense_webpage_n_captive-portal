<?php
require_once('/etc/inc/api/endpoints/APIFreeRadiusUserTopup.inc');
(new APIFreeRadiusUserTopup())->listen();
