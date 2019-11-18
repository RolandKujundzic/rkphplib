<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.class.php';
	$th = new rkphplib\TestHelper();
}

$th->load('src/lib/csv2kv.php');
$th->runFuncTest('csv2kv');
