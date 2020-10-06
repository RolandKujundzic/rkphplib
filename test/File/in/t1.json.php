<?php

$ok = '[["a1","b1","c1"],["a2","b2","c2"]]';

$test = [
	'rkphplib\File::loadTable',
	[ 'csv:file://in/t1.in1.csv', [ ';' ], $ok ],
	[ 'unserialize:file://in/t1.in2.ser', $ok ],
	[ 'json:file://in/t1.in3.json', $ok ],
	[ 'csv:file://in/t2.csv', [ ';' ],
		'[["ax\nay","b","c","dx ; dy"],["a2","b2","c2","d2"]]' ],
	[ 'csv:file://in/t3.in.txt', [ '|&|', '|@|' ],
		'[["c11","c12"],["c21","c22"]]' ],
	[ 'csv:file://in/t4.in.txt', [ '|&|', '|@|', '=' ],
		'[{"c11":"a","c12":"b"},{"c21":"c","c22":"d"}]' ]
];

