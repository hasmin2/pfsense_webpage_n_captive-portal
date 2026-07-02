<?php
require_once('/etc/inc/api/endpoints/APISystemAPISync.inc');
(new APISystemAPISync())->listen();
