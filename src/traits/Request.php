<?php

namespace rkphplib\traits;

require_once dirname(__DIR__).'/Exception.class.php';

use rkphplib\Exception;


/**
 * Trait for tokenizer plugin request and configuration handling.
 * Use this.plugin_conf(req.name) as real name if set in [get|set|has]Req().
 * 
 * @code …
 * require_once 'traits/Request.php';
 *
 * class SomeClass {
 * use \rkphplib\traits\Request;
 * @EOL
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
trait Request {

// @var array $plugin_conf
private $plugin_conf = [];


/**
 * Set|Update this.plugin_conf default values. 
 * Set $group to overwrite group configuration.
 * Use GROUP/NAME as key to define $group.
 */
private function setPConf(array $p, string $group = '') : void {
	$path = empty($group) ? [] : explode('/', $group);

	$conf =& $this->plugin_conf;
	foreach ($path as $name) {
		if (!isset($conf[$name])) {
			$conf[$name] = [];
		}

		$conf =& $conf[$name];
	}

	foreach ($p as $key => $value) {
		$kconf =& $conf;

		if (strpos($key, '/') !== false) {
			$kpath = explode('/', $key);
			$key = array_pop($kpath);

			foreach ($kpath as $name) {
				if (!isset($conf[$name])) {
					$kconf[$name] = [];
				}

				$kconf =& $kconf[$name];
			}
		}

		$kconf[$key] = $value;
	}
}


/**
 * Return default plugin_conf[$name] value[s]. Return array if $name = 'block/'.
 * Use $required = false or 'NAME?' for optional parameter.
 *
 * @return string|array
 */
private function getPConf(string $name, bool $required = true) {
	if ($name == '/') {
		return $this->plugin_conf;
	}

	$error = 'no such plugin option '.$name;
	$conf =& $this->plugin_conf;

	if (strpos($name, '/') !== false) {
		$path = explode('/', $name);
		$name = array_pop($path);

		foreach ($path as $key) {
			if (!isset($conf[$key])) {
				throw new \Exception($error);
			}

			$conf =& $conf[$key];
		}
	}

	if ($name == '') {
		return $conf;
	}

	$res = '';

	if (substr($name, -1) == '?') {
		$name = substr($name, 0, -1);
		$required = false;
	}

	if (isset($conf[$name])) {
		$res = $conf[$name];
	}
	else if ($required) {
		throw new \Exception($error);
	}

	return $res;
}


/**
 * Return plugin_conf[req.$name] if set. Abort if $required = true and not set.
 */
private function getPCRKey(string $name, bool $required = false) : string {
  if (isset($this->plugin_conf['req.'.$name])) {
		$name = $this->plugin_conf['req.'.$name];
	}
	else if ($required) {
		throw new \Exception('missing plugin option req.'.$name);
	}

	return $name;
}


/**
 * Return request value.
 */
private function getReq(string $name) : string {
	$name = $this->getPCRKey($name);
  return isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
}


/**
 * Return true if request key exists (0 = true, '' = false).
 */
private function hasReq(string $name) : bool {
	$name = $this->getPCRKey($name);
  return isset($_REQUEST[$name]) && strlen($_REQUEST[$name]) > 0;
}


/**
 * Set _REQUEST[$name] = $value.
 */
private function setReq(string $name, string $value) : void {
	$name = $this->getPCRKey($name);
  $_REQUEST[$name] = $value;
}


/**
 * Return submap or value. It does not matter if map is multimap or multimap keys are used. 
 *
 * @code
 * $x = [ 'a' => 7, 'a.b.0' => 18, 'a.b.1' => 19, 'c' => [ 0 => 5, 1 => 6 ], 'd' => [ 'x' => 3, 'y' => 4 ] ];
 * $this->getPSub('a', $x) == 7;
 * $this->getPSub('a.b') == [ 18, 19 ];
 * $this->getPSub('c') == [ 5, 6 ];
 * $this->getPSub('c.0', $x) == 5;
 * $this->getPSub('c.x', $x) == 3;
 * $this->getPSub('d') == [ 'x' => 3, 'y' => 4 ]
 * @eol
 *
 * @return map|string|false
 */
private function getPSub(string $path_str, array $map = null) {
	if (!is_null($map)) {
		$map = $this->plugin_conf;
	}

	if (empty($path_str)) {
		throw new Exception('empty path', 'map: '.print_r($map, true));
	}

	$path = explode('.', $path_str);
	$is_array = true;
	$found = true; 
	$fkey = '';
	$pkey = '';

	// \rkphplib\lib\log_debug("Request.getPSub:189> path_str=[$path_str] path=[".join('|', $path)."] map: ".print_r($map, true));
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

	// \rkphplib\lib\log_debug("Request.getPSub:208> found=[$found] fkey=[$fkey] pkey=[$pkey] is_array=[$is_array] map: ".print_r($map, true));
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
			// \rkphplib\lib\log_debug("Request.getPSub:229> path_str=[$path_str] mkey=[$mkey] value=[$value] res: ".print_r($res, true));
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

