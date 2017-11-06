<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/Exception.class.php');

use rkphplib\Exception;


/**
 * Replace function calls in string. Example:
 *
 * resolvPath("data/log/ajax/$date(Ym)/$date(dH)/$map(rechnung,email)_$date(is)") 
 *  == "data/log/ajax/201711/0316/roland@kujundzic.de_2917"
 * 
 * Allow: 
 *  - $map(n,m,k) = map[n][m][k]
 *  - $date(dH) = date('dH')
 *
 * @throws
 * @param string $path
 * @param array $map
 * @return string
 */
function resolvPath($path, $map) {

	while (preg_match('/\$([0-9A-Za-z_]+)\(([0-9A-Za-z,]*)\)/', $path, $match)) {
		$tag = '$'.$match[1].'('.$match[2].')';

		if ($match[1] == 'date') {
			$value = date($match[2]);
		}
		else if ($match[1] == 'map') {
			$rpath = explode(',', $match[2]);
			$rx = $map;

			while (count($rpath) > 0) {
				$name = array_shift($rpath);

				if (isset($rx[$name])) {
					if (is_string($rx[$name])) {
						$value = $rx[$name];
					}
					else {
						$rx = $rx[$name];
					}
				}
				else {
					throw new Exception("$name not in map");
				}
			}
		}
		else {
			throw new Exception('Unknown resolvPath action $'.$match[1], "path=$path");
		}

		$path = str_replace($tag, $value, $path);
	}

	return $path;
}


