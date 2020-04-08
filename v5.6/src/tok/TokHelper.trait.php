<?php

namespace rkphplib\tok;

require_once dirname(__DIR__).'/Exception.class.php';

use rkphplib\Exception;


/**
 * Trait collection for Tokenizer plugins.
 * 
 * @code:
 * require_once(PATH_RKPHPLIB.'tok/TokHelper.trait.php');
 *
 * class SomePlugin {
 * use TokHelper;
 * @:
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
trait TokHelper {


/**
 * If map key from required_keys list is missing throw exception.
 * If key in required_keys has suffix "!" use strlen > 0 check.
 *
 * @code $this->checkMap($this->tok->getPluginTxt('plugin:param', $p, [ 'key', 'key_not_0!', ... ]);
 *
 * @throws if required key is missing
 * @param string $plugin
 * @param map $map
 * @param vector $required_keys
 */
private function checkMap($plugin_param, $map, $required_keys) {
	foreach ($required_keys as $key) {
		$error = false;

		if (mb_substr($key, -1) == '!') {
			$key = mb_substr($key, 0, -1);
			if (!isset($map[$key]) || strlen($map[$key]) == 0) {
				$error = true;
			}
		}
		else if (!isset($map[$key])) {
			$error = true;
		}

		if ($error) {
			$example = $this->tok->getPluginTxt($plugin_param, "$key=...");
			throw new Exception("missing parameter $key (use $example)");
		}
	}
}


/**
 * Return submap or value. It does not matter if map is multimap or multimap keys
 * are used. 
 *
 * @code
 * $x = [ 'a' => 7, 'a.b.0' => 18, 'a.b.1' => 19, 'c' => [ 0 => 5, 1 => 6 ], 'd' => [ 'x' => 3, 'y' => 4 ] ];
 * getMapKeys('a', $x) == 7; getMapKeys('a.b') == [ 18, 19 ]; getMapKeys('c') == [ 5, 6 ];
 * getMapKeys('c.0', $x) == 5; getMapKeys('c.x', $x) == 3; getMapKeys('d') == [ 'x' => 3, 'y' => 4 ]
 * @:
 *
 * @throws
 * @param string $path_str e.g. name1.name2
 * @param map $map
 * @return map|string|false
 */
private function getMapKeys($path_str, $map) {

	if (empty($path_str)) {
		throw new Exception('empty path', 'map: '.print_r($map, true));
	}

	if (!is_array($map)) {
		throw new Exception('invalid map', "path_str=[$path_str] map: ".print_r($map, true));
	}

	$path = explode('.', $path_str);
	$is_array = true;
	$found = true; 
	$fkey = '';
	$pkey = '';

	// \rkphplib\lib\log_debug("TokHelper.getMapKeys:89> path_str=[$path_str] path=[".join('|', $path)."] map: ".print_r($map, true));
	while (count($path) > 0) {
		$pkey = array_shift($path);

		if (isset($map[$pkey]) || array_key_exists($pkey, $map)) {
			if (is_array($map[$pkey])) {
				$map = $map[$pkey];
				$fkey = join('.', $path);
			}
			else {
				$is_array = false;
			}
		}
		else {
			$found = false;
			break;
		}
	}

	// \rkphplib\lib\log_debug("TokHelper.getMapKeys:108> found=[$found] fkey=[$fkey] pkey=[$pkey] is_array=[$is_array] map: ".print_r($map, true));
	if (isset($map[$fkey])) {
		$path_str = $fkey;
		$found = false;
	}

	if (!$found) {
		// check if we are using multi-map-keys
		$len = mb_strlen($path_str); 
		$last_value = false;
		$res = [];

		foreach ($map as $mkey => $value) {
			if (mb_strpos($mkey, $path_str) === 0) {
				if ($mkey == $path_str) {
					$last_value = $value;
				}
				
				$key = mb_substr($mkey, $len + 1);
				$res[$key] = $value;
			}
			// \rkphplib\lib\log_debug("TokHelper.getMapKeys:129> path_str=[$path_str] mkey=[$mkey] value=[$value] res: ".print_r($res, true));
		}

		if (count($res) == 1) {
			return ($last_value === false && count($res) > 0) ? $res : $last_value;
		}

		return (count($res) > 0) ? $res : false;
	}
	else {
		return $is_array ? $map : $map[$pkey]; 
	}
}


}
