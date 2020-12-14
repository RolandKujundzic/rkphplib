<?php

global $php_server;

$php_server->start();

if ($php_server->checkHttp()) {
	print 'done';
}
