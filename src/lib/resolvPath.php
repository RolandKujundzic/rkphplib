<?php

namespace rkphplib\lib;

require_once dirname(__DIR__).'/Exception.php';

use rkphplib\Exception;


/**
 * Replace function calls in string. Example:
 *
 * resolvPath("data/ajax/$map(_SCRIPT)/$date(Ym)/$date(dH)/$map(_FILE)") 
 *  == "data/ajax/SCRIPT_NAME/201711/0316/UNIQUE_HEX"
 * 
 * Allow: 
 *  - $map(n,m,k) = map[n][m][k]
 *  - $date(dH) = date('dH')
 *
 * Append _SCRIPT and _FILE to map (if not set):
 * $map(_SCRIPT) = basename($_SERVER['PHP_SELF'], '.php').
 * $map(_FILE) = date('is').sprintf("%08x", abs(crc32($_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_TIME_FLOAT']))); 
 */
function resolvPath(string $path, array $map = []) : string {

	if (!isset($map['_SCRIPT'])) {
		$map['_SCRIPT'] = basename($_SERVER['PHP_SELF'], '.php');
	}

	if (!isset($map['_FILE'])) {
		$remote_addr = empty($_SERVER['REMOTE_ADDR']) ? uniqid() : $_SERVER['REMOTE_ADDR'];
		$map['_FILE'] = date('is').sprintf("%08x", abs(crc32($remote_addr.$_SERVER['REQUEST_TIME_FLOAT'])));
	}

	while (preg_match('/\$([0-9A-Za-z_]+)\(([0-9A-Za-z_,]*)\)/', $path, $match)) {
		$tag = '$'.$match[1].'('.$match[2].')';

		if ($match[1] == 'date') {
			$value = date($match[2]);
		}
		else if ($match[1] == 'map') {
			$rpath = explode(',', $match[2]);
			$rx = $map;

			while (count($rpath) > 0) {
				$name = array_shift($rpath);

				if (array_key_exists($name, $rx)) {
					if (is_array($rx[$name])) {
						$rx = $rx[$name];
					}
					else {
						$value = $rx[$name];
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


