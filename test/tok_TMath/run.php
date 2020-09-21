<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.class.php';
	$th = new rkphplib\TestHelper();
}

require_once PATH_RKPHPLIB.'/tok/TMath.class.php';

use \rkphplib\tok\TMath;

$th->runTokenizer(1, array('TMath'));

