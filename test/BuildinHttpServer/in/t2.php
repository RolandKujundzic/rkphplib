<?php

$php_server = new \rkphplib\BuildinHttpServer([
	'host' => 'localhost:15081',
	'docroot' => dirname(__DIR__),
	'log_dir' => TEST_TMP
]);

if (!$php_server->alive()) {
	print "server is down\n";
}

$php_server->start(false);

if ($php_server->checkHttp()) {
	print "server is up\n";
}
else {
	print "server is down\n";
}
