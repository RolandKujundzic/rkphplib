<?php

$r = [
	'a' => 'A',
	'a.b' => 'AB',
	'item' => [ 'name' => 'NAME', 'price' => 14.80 ]
];

$tpl = '{:=a} / {:=item.name}, {:=item.price} / {:=a.b}';

$tok = new \rkphplib\tok\Tokenizer();
print $tok->replaceTags($tpl, $r);

