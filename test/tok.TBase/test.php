<?php

require_once '../../src/tok/Tokenizer.php';
require_once '../../src/tok/TBase.php';

$tok = new \rkphplib\tok\Tokenizer();
$tok->register(new \rkphplib\tok\TBase());

$_REQUEST = [ 'dir' => 'test', 'sval' => 'x' ];
$tok->setText('{link:@}');

print $tok->toString();

