<?php
require_once('/etc/inc/api/endpoints/APISystemReboot.inc');
(new APISystemReboot())->listen();
