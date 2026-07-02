<?php
require_once('/etc/inc/api/endpoints/APISystemVersionUpgrade.inc');
(new APISystemVersionUpgrade())->listen();
