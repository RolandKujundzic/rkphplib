<?php

$x = [ 'a' => [ 'b' => [ 'c' => 2, 'c.d' => 3 ], 'b2.c' => 4 ] ];

global $th;
$th->call2('array_get', 'a', $x);
$th->call2('array_get', 'a.b', $x);
$th->call2('array_get', 'a.b.c', $x);
$th->call2('array_get', 'a.b.c.d', $x);
$th->call2('array_get', 'a.b2.c', $x);

