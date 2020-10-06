<?php

$nlt = "\n    ";

$test = [
	'rkphplib\JSON::encode',
	[ 'x', '"x"' ],
  [ [ 1, 2, 3 ], "[{$nlt}1,{$nlt}2,{$nlt}3\n]" ],
	[ [ 1, "a\"b's", 'x\\y' ], 'ok/t1_3.txt' ]
];

