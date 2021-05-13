<?php

$x = [ 'a' => [ 'b' => [ 'c' => 2, 'c.d' => 3 ], 'b2.c' => 4 ] ];

global $th;
$th->pcall('\rkphplib\Hash::get', [ 'a', $x ]);
$th->pcall('\rkphplib\Hash::get', [ 'a.b', $x ]);
$th->pcall('\rkphplib\Hash::get', [ 'a.b.c', $x ]);
$th->pcall('\rkphplib\Hash::get', [ 'a.b.c.d', $x ]);
$th->pcall('\rkphplib\Hash::get', [ 'a.b2.c', $x ]);
$th->pcall('\rkphplib\Hash::get', [ 'a.g', $x ]);
$th->pcall('\rkphplib\Hash::get', [ 'x', $x ]);

