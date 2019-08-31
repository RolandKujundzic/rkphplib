<?php

namespace rkphplib\lib;

/**
 * Return true if $arr is map. Use !is_map to check for vector.
 * Set $true_if_empty if empty array should be true. Flags:
 *
 *  - 1 = return true if empty
 *  - 2 = instead of range 0-1 check ... check if n,n+1, ... n+k (k > 0)
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function is_map(array $arr, int $flags = 0) : bool {
  $n = is_array($arr) ? count($arr) : 0;

	if ($n == 0 && ($flags & 1)) {
		return true;
	}

	$arr_keys = array_keys($arr);

	if ($flags & 2) {
		$min = $arr_keys[0];
		$max = $arr_keys[$n - 1];

		$res = $n == 1 || $arr_keys !== range($min, $max);
	}
	else {
		$res = $n > 0 && $arr_keys !== range(0, $n - 1);
	}

  return $res;
}

