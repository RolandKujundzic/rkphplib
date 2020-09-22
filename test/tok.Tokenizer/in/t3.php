<?php

require_once 'src/tok/Tokenizer.class.php';

$tok = new \rkphplib\tok\Tokenizer();
// $tok->setText('[{rand:password}]');
// print $tok->toString();
print $tok->callPlugin('rand', 'password');

