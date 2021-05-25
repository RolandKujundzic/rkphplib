<?php

namespace rkphplib;


/**
 * Hash manipulation
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Hash {


/**
 * @example …
 * $x = [ 'a' => [ 'b' => 1, [ 'c' => 2 ], 'b2.c' => 3 ] ];
 * Hash::get('a', $x) == [ 'b' => 1, [ 'c' => 2 ] ];
 * Hash::get('a.b', $x) == 1;
 * Hash::get('a.b.c', $x) == 2;
 * Hash::get('a.b2.c', $x) == 3;
 * Hash::get('a.g') == null;
 * @eol
 */
public static function get(string $key, array $p) {
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
		else if (!is_null($p)) {
			$res = null;
			$p = null;
		}
	}

	return $res;
}


/**
 * @example …
 * $x = [];
 * Hash::set('a', 1, $x) == [ 'a' => 1 ];
 * Hash::set('a.b', 2, $x) == [ 'a' => 1, 'a.b' => 2 ];
 * $y = [];
 * Hash::set('a.b', 1, $y) == [ 'a' => [ 'b' => 1 ] ];
 * Hash::set('a.c', 2, $y) == [ 'a' => [ 'b' => 1, 'c' => 2 ] ];
 * @eol
 * @param any $value
 */
public static function set(string $key, $value, array &$p) : void {
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

}

