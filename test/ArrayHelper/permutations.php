<?php

function permutations($arr, $ok) {
	global $th;

	$in = $th->res2str($arr);
	$out = \rkphplib\ArrayHelper::permutations($arr);
	$out = $th->res2str($out);
	$ok = $th->res2str($ok);
	$th->compare("ArrayHelper::permutations($in)", [ $out ], [ $ok ]);
}

permutations([], []) ;
permutations([ 'a' ], [ [ 'a' ] ]);
permutations([ 'a', 'b' ], [ [ 'a', 'b' ], [ 'b', 'a' ] ]);
permutations([ 'a', 'b', 'c' ], [["a","b","c"],["a","c","b"],["b","a","c"],["b","c","a"],["c","a","b"],["c","b","a"]]);
