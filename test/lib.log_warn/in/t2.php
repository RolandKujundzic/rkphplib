<?php

require_once PATH_RKPHPLIB.'/lib/log_warn.php';

$GLOBALS['SETTINGS']['LOG_WARN'] = dirname(__DIR__).'/out/t1.txt';

\rkphplib\lib\log_warn('log to: ', $GLOBALS['SETTINGS']['LOG_DEBUG']);

