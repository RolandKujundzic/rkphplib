<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/lib/log_debug.php';
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.class.php';
	$th = new rkphplib\TestHelper();
}

$th->runTokenizer(2, array('TArray'));