<?php

$ok = '[["ax\nay","b","c","dx ; dy"],["a2","b2","c2","d2"]]';

$test = [
	'rkphplib\loadTable',
	[ 'csv:file://in/t2.csv', [ ';' ], $ok ]
];

