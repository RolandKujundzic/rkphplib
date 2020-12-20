<?php

$php_server = new \rkphplib\BuildinHttpServer([
	'host' => 'localhost:15081',
	'docroot' => dirname(__DIR__),
	'log_dir' => TEST_TMP
]);

$php_server->stop();

if (!$php_server->checkHttp()) {
	print "done";
}
