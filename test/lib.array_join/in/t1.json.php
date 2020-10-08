<?php

$r = [];
$r[4] = 'd,d';
$r[2] = 'b';
$r[1] = 'a';
$r[3] = 'c';

$test = [
	'rkphplib\lib\array_join',
	[',', [ " a ", "b" ], ' a ,b'],
	['µ', [ "aµ ", " µb " ], 'a\\µ µ \\µb '],
	[',', $r, 'a,b,c,d\\,d' ]
];

