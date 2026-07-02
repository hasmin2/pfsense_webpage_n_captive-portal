<?php
require_once('/etc/inc/api/endpoints/APIServicesDnsmasqRestart.inc');
(new APIServicesDnsmasqRestart())->listen();
