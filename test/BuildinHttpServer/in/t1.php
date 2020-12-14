<?php

global $php_server;

$php_server->stop();

if (!$php_server->checkHttp()) {
	print "done";
}
