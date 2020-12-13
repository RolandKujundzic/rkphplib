<?php

require_once '../settings.php';
require_once '../../src/lib/php_server.php';

global $th;

$conf = [
	'host' => 'localhost:15081',
	'pid' => TEST_TMP.'/php_server.pid',
	'log' => TEST_TMP.'/php_server.log',
	'script' => __DIR__.'/alive.php'
];

$th->run(0, 0);

// restart default php_server
$th->prepare();

