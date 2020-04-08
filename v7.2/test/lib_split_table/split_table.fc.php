<?php

$func = 'rkphplib\lib\split_table';

$test = [
	["a|b|c\nx|y\nz\n\nu|v|w", '|', "\n", '[["a","b","c"],["x","y"],["z"],["u","v","w"]]'],
	["a\\|b|c\\\nd\r\ne|f", '|', "\n", '[["a|b","c\nd"],["e","f"]]'],
	[' a |&|b |@| c |&| d|@|', '|&|', '|@|', '[["a","b"],["c","d"]]']
];

