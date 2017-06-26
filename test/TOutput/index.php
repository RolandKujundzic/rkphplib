<?php

require_once('../../src/tok/Tokenizer.class.php');
require_once('../../src/tok/TBase.class.php');
require_once('../../src/tok/TOutput.class.php');


$tok = new \rkphplib\tok\Tokenizer();

$tbase = new \rkphplib\tok\TBase();
$tok->register($tbase);

$toutput = new \rkphplib\tok\TOutput();
$tok->register($toutput);

$tok->load($t_base->tok_find('layout.inc.html'));
print $tok->toString();
