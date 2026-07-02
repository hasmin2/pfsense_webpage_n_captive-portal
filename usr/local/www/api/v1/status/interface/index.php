<?php
require_once('/etc/inc/api/endpoints/APIStatusInterface.inc');
(new APIStatusInterface())->listen();
