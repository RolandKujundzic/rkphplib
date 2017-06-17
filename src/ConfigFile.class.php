<?php

namespace \rkphplib;

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
	$this->conf = \rkphplib\lib\conf2kv(File::load($file));
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
		throw new Exception('missing configuration key', "key=$key in ".$this->file);
	}

	if (empty($this->conf[$key])) {
		if ($required) {
			throw new Exception('empty configuration key', "key=$key in ".$this->file);
		}

		return '';
	}

	return $this->conf[$key];
}


/**
 * Add key, value. 
 * 
 * @param string $key
 * @param string $value 
 */
public function set($key, $value) {
	$this->conf[$key] = $value;
}


/**
 * Return true if value of $key is 1, true, "1", "true" or "y[es]".
 * 
 * @param string $key
 * @param string $value 
 */
public function isTrue($key) {

	if (empty($this->conf[$key]) || !array_key_exists($key, $this->conf)) {
		return false;
	}

	if ($this->conf[$key] === 1 || $this->conf[$key] === true) {
		return true;
	}

	$val = mb_strtolower(trim($this->conf[$key]));
	return ($val === '1' || $val === 'true' || $val === 'y' || $val === 'yes'); 
}


/**
 * Return and remove value. If required is true throw exception if value is empty.
 *
 * @param string $key
 * @param boolean $required (default = true)
 * @param boolean $rm (default = true = unset)
 * @return string|false (false = no such key - if not required)
 */
public function rm($key, $required = true) {

	if ($this->abort_if_missing && !isset($this->conf[$key]) && !array_key_exists($key, $this->conf)) {
		throw new Exception('missing configuration key', "key=$key in ".$this->file);
	}

  if (empty($this->conf[$key])) {
		if ($required) {
			throw new Exception('empty configuration key', "key=$key in ".$this->file);
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
