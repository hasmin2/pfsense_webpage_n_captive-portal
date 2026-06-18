<?php
require_once('/etc/inc/api/endpoints/APIStatusLogSystem.inc');
(new APIStatusLogSystem())->listen();
