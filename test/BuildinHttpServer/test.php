<?php

require_once '../../src/BuildinHttpServer.php';

$do = empty($_SERVER['argv'][1]) ? '' : $_SERVER['argv'][1];
$host = 'localhost';
$port = 7777;

$conf = [ 'port' => $port, 'docroot' => '.', 'log_dir' => 'out' ];

$php_server = new \rkphplib\BuildinHttpServer($host, $conf);

if (!empty($_SERVER['argv'][2]) && in_array($do, [ 'start', 'alive_php', 'restart'])) {
	$php_server = new \rkphplib\BuildinHttpServer($host, [ 
		'docroot' => $php_server->get('docroot'),
		'log_dir' => $php_server->get('log_dir'),
		'script' => $php_server->get('script'),
		'port' => $_SERVER['argv'][2]
		]);
}

if ($do == 'check') {
	if ($php_server->checkHttp()) {
		print "$host is up\n";
	}
	else {
		print "$host is down\n";
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
		print "$host ($script) is up\n";
	}
	else {
		print "$host ($script) is down\n";
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

