<?php

require_once '../../src/CLI.php';

$test = [
	'rkphplib\CLI::parse',
	["run.php --name value --k2 v2", '{"":["run.php","value","v2"],"name":1,"k2":1}'],
	["run.php -k -n abc", '{"":["run.php","abc"],"k":1,"n":1}'],
	["run.php -uvw xyz test", '{"":["run.php","xyz","test"],"u":1,"v":1,"w":1}'],
	["run.php --k1=K1 --k2=K2 --k2=K3 -a -b x -b y", '{"":["run.php","x","y"],"k1":"K1","k2":["K2","K3"],"a":1,"b":1}'],
	["run.php k=v -f --g=arg", '{"":["run.php","k=v"],"f":1,"g":"arg"}'],
	["run.php @file=test.json", '{"":["run.php"],"hash":{"a":"aa","b":"bbb"},"list":["a","b"]}'],
	['run.php @json={"k1":"v1","k2":["a","b"]}', '{"":["run.php"],"k1":"v1","k2":["a","b"]}']
];
