<?php

$x = [];
array_set('a', 1, $x);
array_set('a.b', 2, $x);
print json_encode($x)."\n";

$y = [];
array_set('a.b', 1, $y);
array_set('a.c', 2, $y);
array_set('a.x.y', 3, $y);
print json_encode($y)."\n";
