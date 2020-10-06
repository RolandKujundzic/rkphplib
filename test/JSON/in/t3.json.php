<?php

$ok = '[["1","John","32","male"],["2","Frank",18,"male"]]';

$test = [
	'rkphplib\JSON::toTable',
	[
		[
			[ "1", "John", "32", "male" ],
			[ "id" => "2", "age" => 18, "name" => "Frank" ]
		],
		[ 'id', 'name', 'age', 'gender:male' ],
		$ok
	],
	[		
		'[ [ "1", "John", "32", "male" ], [ "2", "Frank", 18, "male" ] ]',
		$ok
	]
];

