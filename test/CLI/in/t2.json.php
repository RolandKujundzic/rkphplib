<?php

$test = [
	'shell_exec',
	[ 'php script.php a b c', 'ok/t2_1.txt' ],
	[ 'php script.php --a --b=1 --c="u v"', 'ok/t2_2.txt' ]
];
