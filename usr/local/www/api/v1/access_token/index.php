<?php
require_once('/etc/inc/api/endpoints/APIAccessToken.inc');
(new APIAccessToken())->listen();
