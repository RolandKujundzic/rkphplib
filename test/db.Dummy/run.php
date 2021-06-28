<?php

require_once '../settings.php';
require_once PATH_SRC.'db/Dummy.php';

function query(string $query, ?array $replace = null) {
	$db = new \rkphplib\db\Dummy();
	try {
		print $db->getQuery($query, $replace).";\n";
	}
	catch (\Exception $e) {
		print "EXCEPTION\n";
	}
}

global $th;

$th->run(1, 2);

