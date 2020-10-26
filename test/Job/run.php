<?php

require_once '../settings.php';

function show(array $x) : void {
	foreach ([ 'lastModified', 'since' ] as $key) {
		if (!empty($x[$key])) {
			$x[$key] = 'unset';
		}
	}

	print_r($x);
}


global $th;

$th->run(1, 2);

