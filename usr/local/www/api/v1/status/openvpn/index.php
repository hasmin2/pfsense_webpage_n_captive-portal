<?php
require_once('/etc/inc/api/endpoints/APIStatusOpenVPN.inc');
(new APIStatusOpenVPN())->listen();
