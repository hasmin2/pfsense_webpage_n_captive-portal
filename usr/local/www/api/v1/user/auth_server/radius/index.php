<?php
require_once('/etc/inc/api/endpoints/APIUserAuthServerRADIUS.inc');
(new APIUserAuthServerRADIUS())->listen();
