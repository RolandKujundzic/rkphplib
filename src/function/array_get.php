<?php

/**
 * @example …
 * $x = [ 'a' => [ 'b' => 1, [ 'c' => 2 ], 'b2.c' => 3 ] ];
 * array_get('a', $x) == [ 'b' => 1, [ 'c' => 2 ] ];
 * array_get('a.b', $x) == 1;
 * array_get('a.b.c', $x) == 2;
 * array_get('a.b2.c', $x) == 3;
 * @eol
 */
function array_get(string $key, array $p) {
  $path = explode('.', $key);
	$res = null;

  while (!is_null($p) && count($path) > 0) {
		$pkey = join('.', $path);
    $key = array_shift($path);
		if (isset($p[$pkey])) {
			$res = $p[$pkey];
			$p = null;
		}
    if (isset($p[$key])) {
			$p = $p[$key];
			$res = $p;
    }
    else {
     	$p = null;
    }
  }

  return $res;
}

