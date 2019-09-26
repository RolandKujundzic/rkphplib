<?php

function normalize($arr, $ok) {
	global $th;

	$in = $th->res2str($arr);
	\rkphplib\ArrayHelper::normalize($arr);
	$out = $th->res2str($arr);
	$ok = $th->res2str($ok);
	$th->compare("ArrayHelper::normalize($in)", [ $out ], [ $ok ]);
}

normalize([ 'a', [ 'b' ], 'c' ], [ 'a', 'b', 'c' ]);
normalize([ [ [ 'a', 'b' ], 'c' ], 'd' ], [ 'a', 'b', 'c', 'd' ]);
normalize([ ['a', [ 'b' ] ], 'c' ], [ 'a', 'b', 'c' ]);
normalize([ 'a', [ [ 'b' ], [ [ 'c' ] ] ] ], [ 'a', 'b', 'c' ]);
