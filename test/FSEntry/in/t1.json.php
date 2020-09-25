<?php

$test = [
	'rkphplib\FSEntry::fixSuffixList',
	[[ 'jpg' ], 'EXCEPTION' ],
	[[ '.jpg', '.png' ], [ '.jpg', '.png' ] ],
	[[ '.php', '!.inc.php' ], '{"ignore":[".inc.php"],"like":[],"unlike":[],"require":[".php"]}' ],
];

