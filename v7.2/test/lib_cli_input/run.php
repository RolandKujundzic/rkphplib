<?php

global $th;

if (!isset($th)) {
  require_once dirname(dirname(__DIR__)).'/src/TestHelper.class.php';
  $th = new rkphplib\TestHelper();
}

$th->load('src/lib/cli_input.php');

$data = \rkphplib\lib\cli_input();
print json_encode($data);
