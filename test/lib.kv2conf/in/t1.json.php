<?php

$test = [
	'rkphplib\lib\kv2conf',
	[['x' => 'a', 'y' => 'b', 'z' => 'c'], "x=a|#|\ny=b|#|\nz=c"],
	[['a' => ' 12 '], 'a=" 12 "'],
	[['abc' => 'A"BC', 'b' => 'B B B'], 'abc=A"BC|#|'."\nb=B B B"],
];

