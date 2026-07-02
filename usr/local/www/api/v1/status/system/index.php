<?php
require_once('/etc/inc/api/endpoints/APIStatusSystem.inc');
(new APIStatusSystem())->listen();
