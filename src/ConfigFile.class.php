<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/File.class.php';
require_once __DIR__.'/lib/conf2kv.php';

use function rkphplib\lib\conf2kv;



/**
 * Configuration file.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * 
 */
class ConfigFile {

// @var bool $abort_if_key_missing
public $abort_if_missing = false;

// @var map $conf
public $conf = [];

// @var string $file
public $file = '';



/**
 * Load configuration from $file.
 */
public function __construct(string $file) {
	$this->conf = conf2kv(File::load($file));
	$this->file = $file;
}


/**
 * Return value. If required is true throw exception if value is empty.
 */
public function get(string $key, bool $required = true) : string {

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
 * Add $key=$value. 
 */
public function set(string $key, string $value) : void {
	$this->conf[$key] = $value;
}


/**
 * Return true if value of $key is 1, true, "1", "true" or "y[es]".
 */
public function isTrue(string $key) : bool {

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
 * Return string|false (false = no such key - if not required).
 */
public function rm(string $key, bool $required = true) {

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
