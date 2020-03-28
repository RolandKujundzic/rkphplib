<?php

global $th;

if (!isset($th)) {
  require_once dirname(dirname(__DIR__)).'/src/TestHelper.class.php';
  $th = new rkphplib\TestHelper();
}

$th->load('src/lib/cli_input.php');

$_REQUEST = [];
$_SERVER = [ 'argv' => $_SERVER['argv'] ];

\rkphplib\lib\cli_http_input();
unset($_SERVER['argv']);

print 'REQUEST='.json_encode($_REQUEST)."\nSERVER=".json_encode($_SERVER);
