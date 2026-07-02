<?php
require_once('/etc/inc/api/endpoints/APIServiceCron.inc');
(new APIServiceCron())->listen();
