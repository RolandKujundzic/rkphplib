<?php

require_once PATH_SRC.'/lib/log_warn.php';

$GLOBALS['SETTINGS']['LOG_WARN'] = dirname(__DIR__).'/out/t2.txt';

\rkphplib\lib\log_warn('log to '.basename($GLOBALS['SETTINGS']['LOG_WARN']));

