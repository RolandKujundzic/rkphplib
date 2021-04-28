<?php

require_once '../settings.php';

global $th;

function msearch(string $cols, string $val, string $query = '_WHERE_SEARCH') {
	$search = new \rkphplib\SQLSearch([ 'search' => $cols, 'search.value' => $val ]);
	print $search->query($query)."\n";
}

$th->run(1, 2);
