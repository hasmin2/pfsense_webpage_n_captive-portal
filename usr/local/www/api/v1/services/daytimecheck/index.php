<?php
require_once('/etc/inc/api/endpoints/APIServicesDayTimeCheck.inc');
(new APIServicesDayTimeCheck())->listen();
