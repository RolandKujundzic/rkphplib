<?php

$tx = new \rkphplib\tok\Tokenizer(\rkphplib\tok\Tokenizer::TOK_DEBUG);
$tx->setText('1{x:a}2{y:b}3{:x}4{z:}5');

$test = [
	'compare,TOK_DEBUG',
	[ $tx->toString(), '1{debug:x:a}2{y:b}3{:debug}4{debug:z:}5' ]
];

