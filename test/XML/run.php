<?php

$src = dirname(dirname(__DIR__)).'/src/';
require_once $src.'XML.class.php';

global $th;
if (!isset($th)) {
	require_once $src.'TestHelper.class.php';
	$th = new \rkphplib\TestHelper();
}

$n = 3;

for ($i = 1; $i <= $n; $i++) {
	$th->execPHP($i);
}

