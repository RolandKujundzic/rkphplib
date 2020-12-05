<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.php';
	$th = new rkphplib\TestHelper();
}

$th->runTokenizer([ 'test1.inc.html', 'test2.inc.html', 'test3.inc.html' ], array('TOutput'));
