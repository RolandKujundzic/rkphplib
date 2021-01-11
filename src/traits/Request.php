<?php

namespace rkphplib\traits;

require_once dirname(__DIR__).'/Exception.class.php';

use rkphplib\Exception;


/**
 * Trait for tokenizer plugin request and configuration handling.
 * Use this.plugin_conf(req.name) as real name if set in [get|set|has]Req().
 * 
 * @code …
 * require_once(PATH_RKPHPLIB.'traits/Request.php');
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
 * Use 'NAME?' for optional parameter.
 *
 * @return string|array
 */
private function getPConf(string $name) {
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

	$required = true;
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

}

