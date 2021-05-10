<?php

require_once '../../function/array_set.php';

$x = [ 'm' => 4 ];
array_set('a.b.c', 1, $x);
print json_encode($x)."\n";


$x = [ ];
array_set('a', 1, $x);
array_set('a.b', 2, $x);
array_set('a', 3, $x);
print json_encode($x)."\n";
