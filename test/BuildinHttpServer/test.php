<?php

require_once '../../src/BuildinHttpServer.php';

$host = 'localhost:7777';

$php_server = new \rkphplib\BuildinHttpServer($host, [
	'docroot' => '.',
	'log_dir' => 'out'
	]);

$do = empty($_SERVER['argv'][1]) ? '' : $_SERVER['argv'][1];

if ($do == 'check') {
	if ($php_server->check()) {
		print "$host is up\n";
	}
	else {
		print "$host is down\n";
	}
}
else if ($do == 'pid') {
	print $php_server->getPid()."\n";
}
else if ($do == 'start') {
	$php_server->start(false);
}
else if ($do == 'restart') {
	$php_server->start();
}
else if ($do == 'alive') {
	$php_server->set('script', 'alive.php'); 
	$php_server->start();
}
else if ($do == 'stop') {
	$php_server->stop();
}
else {
	die("\nSYNTAX: {$_SERVER['argv'][0]} alive|check|pid|restart|start|stop\n\n");
}

