<?php

require_once '../settings.php';

global $th;

$GLOBALS['SETTINGS']['LOG_DEBUG'] = __DIR__.'/out/t1.txt';
$th->run(1, 1);
