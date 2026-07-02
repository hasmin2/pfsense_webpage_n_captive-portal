<?php
require_once('/etc/inc/api/endpoints/APIStatusGpsPosition.inc');
(new APIStatusGpsPosition())->listen();
