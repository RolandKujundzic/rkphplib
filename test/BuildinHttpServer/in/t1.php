<?php

global $php_server;

$php_server->stop();

if (!$php_server->check()) {
	print "done";
}
