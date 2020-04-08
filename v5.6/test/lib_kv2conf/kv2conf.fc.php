<?php

$func = 'rkphplib\lib\kv2conf';

$e01_conf = trim(file_get_contents(__DIR__.'/example_01.conf'));
$e01_ok = trim(file_get_contents(__DIR__.'/example_01.ok'));

$e02_conf = trim(file_get_contents(__DIR__.'/example_02.conf'));
$e02_ok = trim(file_get_contents(__DIR__.'/example_02.ok'));

$test = [
	[['x' => 'a', 'y' => 'b', 'z' => 'c'], "x=a|#|\ny=b|#|\nz=c"],
	[['a' => ' 12 '], 'a=" 12 "'],
	[['abc' => 'A"BC', 'b' => 'B B B'], 'abc=A"BC|#|'."\nb=B B B"],
];

/* ToDo ...
	[['" x y z "'], ' x y z ']
	[['a', ''], 'a|#|']
*/

/*
	['" x y z "', ' x y z '],
	['a|#|', '["a",""]'],
	['a=A', '{"a":"A"}'],
	['abc="A""BC"'."\n|#|\n\t b =  B B B", '{"abc":"A\"BC","b":"B B B"}'],
	['a=1|#|b=2|#|c=3|#|', '{"a":"1","b":"2","c":"3"}'],
	[$e01_conf, $e01_ok],
	[$e02_conf, $e02_ok]
*/
