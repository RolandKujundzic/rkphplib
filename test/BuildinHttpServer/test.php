<?php

require_once '../../src/BuildinHttpServer.php';

$host = 'localhost:7777';

$php_server = new \rkphplib\BuildinHttpServer($host, [
	'docroot' => '.',
	'log_dir' => 'out'
	]);

if ($php_server->check()) {
	print "$host is running\n";
}

$php_server->start(false);

