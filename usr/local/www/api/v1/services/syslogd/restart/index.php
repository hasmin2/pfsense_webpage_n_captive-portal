<?php
require_once('/etc/inc/api/endpoints/APIServicesSyslogdRestart.inc');
(new APIServicesSyslogdRestart())->listen();
