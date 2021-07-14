<?php

function build_query(string $type, array $kv) : void {
	$db = new \rkphplib\db\Dummy();
	$kv['@is_null'] = [ 'comment' ];

	try {
		print $db->buildQuery('test', $type, $kv).";\n";
	}
	catch (\Exception $e) {
		print "EXCEPTION\n";
	}
}

build_query('insert', [ 'id' => 7, 'name' => 'Joe', 'comment' => 'bla' ]);
build_query('update', [ 'id' => 7, 'name' => 'Joe', 'comment' => '' ]);
build_query('replace', [ 'id' => 7, 'name' => 'Joe' ]);
build_query('insert_update', [ 'id' => 7, 'name' => 'Joe' ]);
build_query('insert_update', [ 'id' => 7, 'name' => 'Joe', 'c' => "'CONST'", '@tag' => [ 'insert_update' ] ]);

