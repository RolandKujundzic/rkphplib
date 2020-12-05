<?php

global $th;

if (!isset($th)) {
        require_once dirname(dirname(__DIR__)).'/src/TestHelper.php';
	$th = new rkphplib\TestHelper();
}

$th->runTokenizer(1, array('TBase', 'TFileSystem'));

