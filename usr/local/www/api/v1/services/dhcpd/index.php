<?php
require_once('/etc/inc/api/endpoints/APIServicesDHCPd.inc');
(new APIServicesDHCPd())->listen();
