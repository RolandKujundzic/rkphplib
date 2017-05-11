<?php

namespace rkphplib\lib;

/**
 * Return true if $arr is map. Use !is_map to check for vector.
 * Set $true_if_empty if empty array should be true.
 *
 * @throws
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param array $arr
 * @param bool $true_if_empty
 * @return bool
 */
function is_map(array $arr, $true_if_empty = false) {
  $n = is_array($arr) ? count($arr) : 0;

	if ($n == 0 && $true_if_empty) {
		return true;
	}

  return $n > 0 && array_keys($arr) !== range(0, $n - 1);
}

