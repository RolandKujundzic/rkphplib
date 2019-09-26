<?php

global $th;

if (!isset($th)) {
	require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
	$th = new rkphplib\TestHelper();
}

$th->load('src/ArrayHelper.class.php');

include_once 'normalize.php';
include_once 'permutations.php';
