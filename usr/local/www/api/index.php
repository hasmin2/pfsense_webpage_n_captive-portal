<?php
require_once('/etc/inc/api/endpoints/APIFreeRADIUSUser.inc');
(new APIFreeRADIUSUser())->listen();
