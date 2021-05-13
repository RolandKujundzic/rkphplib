<?php

$x = [];
\rkphplib\Hash::set('a', 1, $x);
\rkphplib\Hash::set('a.b', 2, $x);
print json_encode($x)."\n";

$y = [];
\rkphplib\Hash::set('a.b', 1, $y);
\rkphplib\Hash::set('a.c', 2, $y);
\rkphplib\Hash::set('a.x.y', 3, $y);
print json_encode($y)."\n";
