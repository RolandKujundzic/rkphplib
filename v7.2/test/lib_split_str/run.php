<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}

$th->load('src/lib/split_str.php');
$th->runFuncTest('split_str');
