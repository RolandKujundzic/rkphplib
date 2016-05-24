<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/lib/conf2kv.php');



/**
 * Configuration file.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * 
 */
class ConfigFile {

/** @var bool $abort_if_key_missing */
public $abort_if_missing = false;

/** @var map $conf */
public $conf = [];

/** @var string $file */
public $file = '';



/**
 * Load configuration from file.
 *
 * @return map
 */
public function __construct($file) {
	$this->conf = lib\conf2kv(File::load($file));
	$this->file = $file;
}


/**
 * Return value. If required is true throw exception if value is empty.
 * 
 * @param string $key
 * @param bool $required (default = true)
 * @return string
 *
 */
public function get($key, $required = true) {

	if ($this->abort_if_missing && !isset($this->conf[$key]) && !array_key_exists($key, $this->conf)) {
		throw new Exception('missing configuration key', "key=$key conf_file".$this->file);
	}

	if (empty($this->conf[$key])) {
		if ($required) {
			throw new Exception('empty configuration key', "key=$key conf_file".$this->file);
		}

		return '';
	}

	return $this->conf[$key];
}


/**
 * Return and remove value. If required is true throw exception if value is empty.
 *
 * @param string $key
 * @param boolean $required (default = true)
 * @param boolean $rm (default = true = unset)
 * @return string|false (false = no such key - if not required)
 */
private function rm($key, $required = true) {

	if ($this->abort_if_missing && !isset($this->conf[$key]) && !array_key_exists($key, $this->conf)) {
		throw new Exception('missing configuration key', "key=$key conf_file".$this->file);
	}

  if (empty($this->conf[$key])) {
		if ($required) {
			throw new Exception('empty configuration key', "key=$key conf_file".$this->file);
		}

		if (!isset($this->conf[$key]) && !array_key_exists($key, $this->conf)) {
      return '';
    }
  }

  $res = $this->conf[$key];
	unset($this->conf[$key]);

  return $res;
}


}
