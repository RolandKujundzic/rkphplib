<?php

require_once '../../src/db/Dummy.php';

function build_query(string $type, array $kv) : void {
	$db = new \rkphplib\db\Dummy();
	$kv['@is_null'] = [ 'comment' ];
	print $db->buildQuery('test', $type, $kv).";\n";
}

build_query('insert_update', [ 'id' => 7, 'name' => 'Joe', 'x' => "'a'", '@tag' => [ 'insert_update' ] ]);

