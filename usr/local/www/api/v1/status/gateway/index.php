<?php
require_once('/etc/inc/api/endpoints/APIStatusGateway.inc');
(new APIStatusGateway())->listen();
