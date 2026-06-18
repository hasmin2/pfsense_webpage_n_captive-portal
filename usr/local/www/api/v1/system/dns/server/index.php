<?php
require_once('/etc/inc/api/endpoints/APISystemDNSServer.inc');
(new APISystemDNSServer())->listen();
