<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}

$th->load('src/lib/array_join.php');
$th->runFuncTest('array_join');
