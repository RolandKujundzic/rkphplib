<?php

$x = [ 'a' => [ 'b' => [ 'c' => 2, 'c.d' => 3 ], 'b2.c' => 4 ] ];

call2('array_get', 'a', $x);
call2('array_get', 'a.b', $x);
call2('array_get', 'a.b.c', $x);
call2('array_get', 'a.b.c.d', $x);
call2('array_get', 'a.b2.c', $x);

