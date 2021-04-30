<?php

require_once '../../src/tok/Tokenizer.class.php';
require_once '../../src/tok/TBase.class.php';

$tok = new \rkphplib\tok\Tokenizer();
$tok->register(new \rkphplib\tok\TBase());

// $tok->setText('{tf:}10{:tf}{false:}F{:false}{true:}T{:true}');
$tok->setText('{tf:cmp:}{:tf}{t:}T{:t}{f:}F{:f}');

print $tok->toString();

