<?php

require_once '../settings.php';

/*
 * ../../run.sh php server --port=8080
 * http://localhost:8080/run.php
 */

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}


$th->load('src/DatabaseSessionHandler.class.php');

$sh = new \rkphplib\DatabaseSessionHandler(TEST_MYSQL);

$sh->set('abc', 3);
$th->compare('has|get()', [ $sh->has('abc'), $sh->get('abc') ], [ true, 3 ]);

