<?php
require_once('/etc/inc/api/endpoints/APISystemNotificationsEmail.inc');
(new APISystemNotificationsEmail())->listen();
