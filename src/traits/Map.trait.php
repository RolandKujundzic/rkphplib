<?php

namespace rkphplib\traits;

require_once(dirname(__DIR__).'/Exception.class.php');

use \rkphplib\Exception;


/**
 * Trait with Map methods.
 * 
 * @code:
 * require_once(PATH_RKPHPLIB.'trait/Map.trait.php');
 *
 * class SomeClass {
 * use Map;
 * @:
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2018
 */
trait Map {


/**
 * Return value of path_str. Example: If $path_str = a.b.c 
 * return $map['a']['b']['c'] (throw Exception if not set and $abort
 * - if not abort return false).
 *
 * @throws
 * @param map $map
 * @param string $path_str
 * @param boolean $abort (= true)
 * @return any|false
 */
private static function getMapPathValue($map, $path_str, $abort = true) {

	if (!is_array($map)) {
		throw new Exception('invalid map', "path_str=$path_str map: ".print_r($map, true));
	}

	if (empty($path_str)) {
		throw new Exception('empty map key', $path_str);
	}

	$path = explode('.', $path_str);

	while (count($path) > 0) {
		$key = array_shift($path);

		if (!isset($map[$key]) && !array_key_exists($key, $map)) {
			if ($abort) {
				throw new Exception('invalid key '.$key, $path_str);
			}
			else {
				return false;
			}
		}

		$map = $map[$key];
	}

	return $map;
}
	

/**
 * Set value of path_str. Example: If $path_str = a.b.c 
 * set $map['a']['b']['c'] = $value.
 *
 * @throws
 * @param map-reference &$map
 * @param string $path_str
 * @param any $value
 */
private static function setMapPathValue(&$map, $path_str, $value) {

	if (!is_array($map)) {
		throw new Exception('invalid map', "path_str=$path_str map: ".print_r($map, true));
	}

	if (empty($path_str)) {
		throw new Exception('empty map key', $path_str);
	}

	$path = explode('.', $path_str);

	if (count($path) > 1) {
		$key = array_shift($path);

		if (!isset($map[$key]) || !is_array($map[$key])) {
			$map[$key] = [];
		}

		self::setMapPathValue($map[$key], join('.', $path), $value);
	}
	else {
		$map[$path_str] = $value;
	}
}


}

