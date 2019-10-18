<?php

global $th;

if (!isset($th)) {
  require_once dirname(dirname(__DIR__)).'/src/TestHelper.class.php';
  $th = new rkphplib\TestHelper();
}

$th->output = __FILE__.'.out';
$GLOBALS['OVERWRITE_SETTINGS']['LOG_DEBUG'] = $th->output;

$th->runFuncTest('test_01');
