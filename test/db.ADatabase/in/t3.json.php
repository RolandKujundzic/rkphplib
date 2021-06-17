<?php

$test = [
	'rkphplib\db\ADatabase::escape',
	[ 'a"b;\\c', 'a"b;\\c' ],
	[ '\\', '\\\\' ],
	[ '\\\\', '\\\\' ],
	[ "a`b'\xb1", "a`b''\xb1" ],
	[ "'ab'c'", "''ab''c''" ]
];
