<?php

require_once '../../src/db/Dummy.php';

function build_query(string $type, array $kv) : void {
	$db = new \rkphplib\db\Dummy();
	$kv['@is_null'] = [ 'comment' ];
	print $db->buildQuery('test', $type, $kv).";\n";
}

$r = array_flip([ 'id', 'name' ]);
$r['@tag'] = 1;

build_query('select', $r);

