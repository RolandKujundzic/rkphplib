<?php

require_once '../settings.php';
require_once PATH_RKPHPLIB.'/lib/cli_input.php';

print json_encode(\rkphplib\lib\cli_input());
