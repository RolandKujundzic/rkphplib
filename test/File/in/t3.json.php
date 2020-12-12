<?php

$test = [
	'rkphplib\File::realpath',
	[ '../settings.php', 1, "<? fix(\$out) == 'settings.php'" ],
	[ '../File/yalla', 2, "<? fix(\$out) == 'File/yalla'" ]
];

