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
 * Set $group to overwerite group configuration.
 */
private function setPConf(array $p, string $group = '') : void {
	foreach ($p as $key => $value) {
		$gkey = $group;

		if (!$gkey && strpos($key, '/') !== false) {
			list ($gkey, $key) = explode('/', $key);
		}

		if ($gkey) {
			if (!isset($this->plugin_conf[$gkey])) {
				$this->plugin_conf[$gkey] = [];
			}

			$this->plugin_conf[$gkey][$key] = $value;
		}
		else {
			$this->plugin_conf[$key] = $value;
		}
	}
}


/**
 * Return default plugin_conf[$name] value[s]. Return array if $name = 'block/'.
 * @return string|array
 */
private function getPConf(string $name) {
	if (substr($name, -1) == '/') {
		$name = substr($name, 0, -1);
		if (!is_array($this->plugin_conf[$name])) {
			throw new \Exception('no such plugin option block '.$name);
		}
	}

	if (!isset($this->plugin_conf[$name])) {
		throw new \Exception('no such plugin option '.$name);
	}

	return $this->plugin_conf[$name];
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
 * Return true if request key exists.
 */
private function hasReq(string $name) : bool {
	$name = $this->getPCRKey($name);
  return !empty($_REQUEST[$name]);
}


/**
 * Set _REQUEST[$name] = $value.
 */
private function setReq(string $name, string $value) : void {
	$name = $this->getPCRKey($name);
  $_REQUEST[$name] = $value;
}

}

