<?php
require_once('/etc/inc/api/endpoints/APISystemHalt.inc');
(new APISystemHalt())->listen();
