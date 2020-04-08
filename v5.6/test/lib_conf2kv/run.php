<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.class.php';
	$th = new rkphplib\TestHelper();
}

$th->load('src/lib/conf2kv.php');
$th->runFuncTest('conf2kv');
