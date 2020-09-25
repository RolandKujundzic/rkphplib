<?php

$test = [
	'shell_exec',
	[ 'php script.php a b c', 'ok/t1_1.txt' ],
	[ 'php script.php --a --b=1 --c="u v"', 'ok/t1_2.txt' ]
];
