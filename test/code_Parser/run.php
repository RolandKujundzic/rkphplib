<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/code/Parser.class.php';
	$th = new rkphplib\TestHelper();
}

use rkphplib\code\Parser;

$load = 0;
try {
	$bash = new Parser('bash');
	$bash->load('test1.sh');
	$load++;
	$bash->load('test2.sh');
	$load++;
}
catch (\Exception $e) {
	// ignore
}

$th->compare("new Parser('bash'): load test1.sh, test2.sh", [ $load ], [ 2 ]);

$load = 0;
try {
	$php = new Parser('php');
	$php->load('test1.php');
	$load++;
	$php->load('test2.php');
	$load++;
}
catch (\Exception $e) {
	// ignore
}

$th->compare("new Parser('php'): load test1.php, test2.php", [ $load ], [ 2 ]);

