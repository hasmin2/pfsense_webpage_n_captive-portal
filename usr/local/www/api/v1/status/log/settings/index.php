<?php
require_once('/etc/inc/api/endpoints/APIStatusLogSettings.inc');
(new APIStatusLogSettings())->listen();
