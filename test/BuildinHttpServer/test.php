<?php

require_once '../../src/BuildinHttpServer.php';

$do = empty($_SERVER['argv'][1]) ? '' : $_SERVER['argv'][1];
$port = empty($_SERVER['argv'][2]) ? 7777 : intval($_SERVER['argv'][2]);

$php_server = new \rkphplib\BuildinHttpServer([
	'host' => 'localhost',
	'port' => $port,
	'docroot' => '.',
	'log_dir' => 'out'
	]);

$server = $php_server->get('server');

if ($do == 'check') {
	if ($php_server->checkHttp()) {
		print "$server is up\n";
	}
	else {
		print "$server is down\n";
	}
}
else if ($do == 'pid') {
	print $php_server->getPid()."\n";
}
else if ($do == 'get') {
	print $php_server->get($_SERVER['argv'][2])."\n";
}
else if ($do == 'start') {
	$php_server->start(false);
}
else if ($do == 'restart') {
	$php_server->start();
}
else if ($do == 'alive') {
	$script = $php_server->get('script');
	if ($php_server->alive()) {
		print "$server ($script) is up\n";
	}
	else {
		print "$server ($script) is down\n";
	}
}
else if ($do == 'alive_php') {
	$php_server->set('script', 'alive.php'); 
	$php_server->start();
}
else if ($do == 'stop') {
	$php_server->stop();
}
else {
	die("\nSYNTAX: {$_SERVER['argv'][0]} alive|alive_php|check|get|pid|restart|start|stop [PORT|KEY]\n\n");
}

