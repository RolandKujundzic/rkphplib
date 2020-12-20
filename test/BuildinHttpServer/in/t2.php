<?php

global $php_server;

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
