<?php

/**
 * @function array_set
 * @example …
 * $x = [];
 * array_set('a', 1, $x) == [ 'a' => 1 ];
 * array_set('a.b', 2, $x) == [ 'a' => 1, 'a.b' => 2 ];
 * $y = [];
 * array_set('a.b', 1, $y) == [ 'a' => [ 'b' => 1 ] ];
 * array_set('a.c', 2, $y) == [ 'a' => [ 'b' => 1, 'c' => 2 ] ];
 * @eol
 * @param any $value
 */
function array_set(string $key, $value, array &$p) : void {
	$path = explode('.', $key);
	$tmp = &$p;

	while (count($path)) {
		$key = array_shift($path);

		if (!count($path)) {
			$tmp[$key] = $value;
		}
		else {
			if (!array_key_exists($key, $tmp)) {
				$tmp[$key] = [];
			}
			else if (!is_array($tmp[$key])) {
				$key .= '.'.array_shift($path);

				if (!count($path)) {
					$tmp[$key] = $value;
				}
				else {
					$tmp[$key] = [];
				}
			}

			$tmp = &$tmp[$key];
		}
	}
}

