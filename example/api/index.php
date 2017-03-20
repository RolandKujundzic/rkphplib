<?php

define('PATH_RKPHPLIB', __DIR__.'/../../src/');

require_once('ApiExample.class.php');


$api = new ApiExample([ 'log_dir' => '/tmp/api' ]);

if (!empty($_SERVER['argv'][0])) {
	require_once(PATH_RKPHPLIB.'File.class.php');

	print __DIR__."\n";

	$index_php = $_SERVER['argv'][0];

	if (!File::exists(__DIR__.'.htaccess')) {
		File::save(__DIR__.'/.htaccess', ApiExample::apacheHtaccess());
	}

	if (!File::exists(__DIR__.'/routing.php')) {
		File::save(__DIR__.'/routing.php', ApiExample::phpAPIServer());
	}

	print "\nApache2: Use ".dirname(__DIR__)." as document root\n";
	print 'PHP buildin Webserver: php -S localhost:10080 '.dirname($index_php)."/routing.php\n";
	print "Authorization: basic auth (e.g. http://test:test@localhost:10080/some/action)\n\n";
}
else {
	$api->run();
}

