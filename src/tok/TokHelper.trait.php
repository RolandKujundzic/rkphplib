<?php

namespace rkphplib\tok;

require_once dirname(__DIR__).'/Exception.php';

use rkphplib\Exception;


/**
 * Trait collection for Tokenizer plugins.
 * 
 * @code 
 * require_once 'tok/TokHelper.trait.php';
 *
 * class SomePlugin {
 * use TokHelper;
 * @end:code
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
trait TokHelper {

// @var Tokenizer $tok
protected $tok = null;


/**
 * If map key from required_keys list is missing throw exception.
 * If key in required_keys has suffix "!" use strlen > 0 check.
 * If key=default is used set $map[$key]=default if not set (name=0 - allow keyless entry).
 * If key is required and not set and $_REQUEST[key] is not empty, set $map[key]=$_REQUEST[key].
 *
 * @code $this->checkMap('plugin:param', $p, [ 'key', 'key_not_0!', 'key=default', ... ]);
 */
private function checkMap(string $plugin_param, array &$map, array $key_def) : void {
	foreach ($key_def as $key) {
		$error = false;

		if (mb_substr($key, -1) == '!') {
			$key = mb_substr($key, 0, -1);
			if (!isset($map[$key]) || strlen($map[$key]) == 0) {
				if (!empty($_REQUEST[$key])) {
					$map[$key] = $_REQUEST[$key];
				}
				else {
					$error = true;
				}
			}
		}
		else if (!isset($map[$key])) {
			if (mb_strpos($key, '=') > 0) {
				list ($name, $default) = explode('=', $key, 2);
				if ($default === '0') {
					$map[$name] = $map[0];
					unset($map[0]);
				}
				else if (!isset($map[$name])) {
					$map[$name] = $default;
				}
			}
			else {
				$error = true;
			}
		}

		if ($error) {
			$this->tokError("missing parameter $key (use {:=ref})", [ $plugin_param, $key ]);
		}
	}
}


/**
 * Return html error message <div class="tok_error">$msg</div>
 */
private function tokErrorMsg(string $msg) : string {
	return '<div class="tok_error">'.$msg.'</div>';
}


/**
 * Throw Exception. Resolve ref as function call or plugin call and replace {:=ref} in $error.
 */
private function tokError(string $error, ?array $ref = null) : void {
	if ($ref) {
		$name = array_pop($ref);
		$ref_val = $name;

		if (method_exists($this, $name)) {
			$ref_val = $name.'('.join(', ', $ref).')';
		}
		else if ($this->tok) {
			$arg = (count($ref) > 0) ? join('=...'.HASH_DELIMITER, $ref).'=...'.HASH_DELIMITER.'...' : '';
			$ref_val = $this->tok->getPluginTxt($ref, $arg);
		}

		$error = str_replace('{:=ref}', $ref_val, $error);
	}

	throw new Exception($error);
}


/**
 * Return submap or value. It does not matter if map is multimap or multimap keys
 * are used. 
 *
 * @code
 * $x = [ 'a' => 7, 'a.b.0' => 18, 'a.b.1' => 19, 'c' => [ 0 => 5, 1 => 6 ], 'd' => [ 'x' => 3, 'y' => 4 ] ];
 * getMapKeys('a', $x) == 7; getMapKeys('a.b') == [ 18, 19 ]; getMapKeys('c') == [ 5, 6 ];
 * getMapKeys('c.0', $x) == 5; getMapKeys('c.x', $x) == 3; getMapKeys('d') == [ 'x' => 3, 'y' => 4 ]
 * @end:code
 *
 * @return map|string|false
 */
private function getMapKeys(string $path_str, array $map) {

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

	// \rkphplib\lib\log_debug("TokHelper.getMapKeys:133> path_str=[$path_str] path=[".join('|', $path)."] map: ".print_r($map, true));
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

	// \rkphplib\lib\log_debug("TokHelper.getMapKeys:152> found=[$found] fkey=[$fkey] pkey=[$pkey] is_array=[$is_array] map: ".print_r($map, true));
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
			// \rkphplib\lib\log_debug("TokHelper.getMapKeys:173> path_str=[$path_str] mkey=[$mkey] value=[$value] res: ".print_r($res, true));
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
