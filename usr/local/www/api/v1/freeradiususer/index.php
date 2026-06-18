<?php
require_once('/etc/inc/api/endpoints/APIFreeRadiusUser.inc');
(new APIFreeRadiusUser())->listen();
