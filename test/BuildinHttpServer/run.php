<?php

require_once '../settings.php';
require_once '../../src/BuildinHttpServer.php';

global $th;

$php_server = new \rkphplib\BuildinHttpServer('localhost:15081', [
	'script' => __DIR__.'/alive.php',
	'log_dir' => TEST_TMP
]);

$th->run(1, 2);

// restart default php_server
$th->prepare();

