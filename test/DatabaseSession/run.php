<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}


$th->load('src/DatabaseSession.class.php');

$sess = new \rkphplib\Session();

$sess->set('abc', 3);
$th->compare('has|get()', [ $sess->has('abc'), $sess->get('abc') ], [ true, 3 ]);

