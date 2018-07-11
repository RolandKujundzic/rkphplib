<?php

require_once('../../src/lib/config.php');
require_once('../../src/tok/Tokenizer.class.php');
require_once('../../src/tok/TBase.class.php');


$tok = new \rkphplib\tok\Tokenizer();

$t_base = new \rkphplib\tok\TBase();
$tok->register($t_base);

$tok->load($t_base->tok_find('layout.inc.html'));
print $tok->toString();

