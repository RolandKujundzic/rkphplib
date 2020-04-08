<?php

$func = 'rkphplib\lib\array_join';

$test = [
	[',', [ " a ", "b" ], ' a ,b'],
	['µ', [ "aµ ", " µb " ], 'a\µ µ \µb ']
];

