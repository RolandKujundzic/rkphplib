<?php

global $th;

$src_dir = dirname(dirname(__DIR__)).'/src/';

if (!isset($th)) {
	require_once $src_dir.'TestHelper.class.php';
	$th = new rkphplib\TestHelper();
}

require_once $src_dir.'code/Parser.class.php';
require_once $src_dir.'Dir.class.php';

use rkphplib\code\Parser;
use rkphplib\FSEntry;
use rkphplib\Dir;


$load = 0;
try {
	$bash = new Parser([ 'name' => 'bash' ]);
	$bash->load('test1.sh');
	$load++;
	$bash->load('test2.sh');
	$load++;
}
catch (\Exception $e) {
	print "Exception ".($load + 1).': '.$e->getMessage()."\t".$e->internal_message."\n";
}

$th->compare("new Parser('bash'): load test1.sh, test2.sh", [ $load ], [ 2 ]);

$load = 0;
try {
	$php = new Parser([ 'name' => 'php' ]);
	$php->load('test1.php');
	$load++;
	$php->load('test2.php');
	$load++;
}
catch (\Exception $e) {
	print "Exception ".($load + 1).': '.$e->getMessage()."\t".$e->internal_message."\n";
}

$th->compare("new Parser('php'): load test1.php, test2.php", [ $load ], [ 2 ]);

if (!empty($_SERVER['argv'][1]) && FSEntry::isFile($_SERVER['argv'][1])) {
	$php_files = [ $_SERVER['argv'][1] ];
}
else {
	$php_files = Dir::scanTree($src_dir, [ '.php' ]);
}

$php = new Parser([ 'name' => 'php' ]);

foreach ($php_files as $php_file) {
	$php->scan($php_file);
}

