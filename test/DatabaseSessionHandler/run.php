<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}


$th->load('src/DatabaseSessionHandler.class.php');

$sh = new \rkphplib\DatabaseSessionHandler();

$sh->set('abc', 3);
$th->compare('has|get()', [ $sh->has('abc'), $sh->get('abc') ], [ true, 3 ]);

