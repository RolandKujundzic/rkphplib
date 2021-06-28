<?php

require_once '../../src/db/Dummy.php';

function query(string $query, ?array $replace = null) {
	$db = new \rkphplib\db\Dummy();
	try {
		print $db->getQuery($query, $replace).";\n";
	}
	catch (\Exception $e) {
		print "EXCEPTION\n";
	}
}

query("SELECT * FROM test WHERE pid={:=pid}", [ 'pid' => null ]);
query("UPDATE SET id=NULL WHERE pid={:=pid}", [ 'pid' => null ]);

