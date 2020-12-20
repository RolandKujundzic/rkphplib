<?php

require_once '../../src/BuildinHttpServer.php';

$do = empty($_SERVER['argv'][1]) ? '' : $_SERVER['argv'][1];
$port = empty($_SERVER['argv'][2]) ? 7777 : intval($_SERVER['argv'][2]);
$script = ($do == 'alive_php') ? 'alive.php' : '';

$php_server = new \rkphplib\BuildinHttpServer([
	'host' => 'localhost',
	'docroot' => '.',
	'script' => $script, 
	'port' => $port,
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
else if (substr($do, 0, 4) == 'get=') {
	$key = substr($do, 4);
	print $php_server->get($key)."\n";
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
	$php_server->start();
}
else if ($do == 'stop') {
	$php_server->stop();
}
else {
	die("\nSYNTAX: {$_SERVER['argv'][0]} alive|alive_php|check|get=NAME|pid|restart|start|stop [PORT=7777]\n\n");
}

