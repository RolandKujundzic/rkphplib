<?php

$test = [
	'rkphplib\lib\replace_tags',
	['x{:=b}xx{:=a}x{:=b}{:=a}', ['a' => 'A', 'b' => 'B'], 'xBxxAxBA'],
	['x{:=b.t}xx{:=a.k}x{:=b.f.g}{:=a.l}', ['a' => [ 'k' => 'K', 'l' => 'L'], 'b' => ['t' => 'T', 'f' => ['g' => 'G']]], 'xTxxKxGL'],
	['$u.firstname $u.lastname', ['firstname' => 'John', 'lastname' => 'Smith'], ['$', '', 'u'], 'John Smith']
];

