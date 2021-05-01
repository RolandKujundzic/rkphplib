<?php

require_once '../settings.php';

global $th;

function msearch(string $cols, string $val = '', string $query = '_WHERE_SEARCH') {
	$search = new \rkphplib\SQLSearch([ 'search' => $cols, 'search.value' => $val ]);
	$req = json_encode($_REQUEST);
	print "search='$cols', search.value='$val', query='$query'\nrequest: $req\n[".$search->query($query)."]\n\n";
}

$th->run(1, 3);
