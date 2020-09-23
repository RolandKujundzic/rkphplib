<?php

$tx = new \rkphplib\tok\Tokenizer(\rkphplib\tok\Tokenizer::TOK_IGNORE);
$tx->setText('1{x:}2{y:}3{:x}4{z:}5');

$test = [
	'compare,TOK_IGNORE',
	[ $tx->toString(), '145' ]
]; 

