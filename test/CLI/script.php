<?php

require_once '../settings.php';
require_once PATH_SRC.'/CLI.php';

print json_encode(\rkphplib\CLI::parse());
