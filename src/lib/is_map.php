<?php

namespace rkphplib\lib;

/**
 * Return true if $arr is map. Use !is_map to check for vector.
 *
 * @throws
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param array $arr
 * @return bool
 */
function is_map(array $arr) {
  $n = is_array($arr) ? count($arr) : 0;
  return $n > 0 && array_keys($arr) !== range(0, $n - 1);
}

