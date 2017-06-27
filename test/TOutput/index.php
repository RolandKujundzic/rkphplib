<?php

require_once('../../src/tok/Tokenizer.class.php');
require_once('../../src/tok/TBase.class.php');
require_once('../../src/tok/TOutput.class.php');


$tok = new \rkphplib\tok\Tokenizer();

$tbase = new \rkphplib\tok\TBase();
$tok->register($tbase);

$toutput = new \rkphplib\tok\TOutput();
$tok->register($toutput);

if (!isset($_REQUEST['t'])) {
	$_REQUEST['t'] = 1;
}

$test_html = 'test'.intval($_REQUEST['t']).'.inc.html';
$tok->load($test_html);
print $tok->toString();
