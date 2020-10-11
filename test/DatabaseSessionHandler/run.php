<?php

require_once '../settings.php';

global $th;

$th->load('src/DatabaseSessionHandler.class.php');
$sh = new \rkphplib\DatabaseSessionHandler(TEST_MYSQL);

/**
 * ToDo: fix in/t1.php cookie check (set options.http = 1 and options.ob_wrap = 0)
 */

$th->run(1, 1);

