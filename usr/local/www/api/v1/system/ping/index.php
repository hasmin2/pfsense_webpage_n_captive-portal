<?php
require_once('/etc/inc/api/endpoints/APISystemPing.inc');
(new APISystemPing())->listen();
