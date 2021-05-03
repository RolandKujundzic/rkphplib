<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Dir.class.php';
require_once __DIR__.'/JSON.class.php';

use rkphplib\Exception;


/**
 * Persistant Hash. 
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Map {

// @var array $map use if != null
private $map = null;

// @var array $option
private $option = null;



/**
 * Constructor. Options:
 *
 * - file: path/to/map_file (suffix is either .json or .ser)
 * - file_suffix: auto set (.ser|.json)
 * - expire: max duration of map value in seconds (default = 3 h, empty = no expiration)
 */
public function __construct(array $option) {

	if (!isset($option['expire'])) {
	  $option['expire'] = time() - 60 * 60 * 3;  // expire after 3h
	}

	if (!empty($option['file'])) {
		$suffix = File::suffix($option['file']);
	
		if ($suffix != 'ser' && $suffix != 'json') {
			throw new Exception('invalid file suffix ['.$suffix.'] use (.ser|.json)', print_r($options, true));
		}

		Dir::create(dirname($option['file']), 0, true);

		$option['file.suffix'] = $suffix;
		$this->option = $option;

		if (!File::exists($this->option['file'])) {
			$this->map = [];
			$this->saveFile();
		}
		else {
			$this->loadFile();
		}
	}
}


/**
 * Set key value (any).
 */
public function set(string $key, $value) {
	if (empty($key)) {
		throw new Exception('empty key');
	}

	if (!is_null($this->map)) {
		$this->map[$key] = [ time(), $value ];

		if (!empty($this->option['file'])) {
			$this->saveFile();
		}
	}
	else {
		throw new Exception('ToDo');
	}
}


/**
 * Return key value (any).
 */
public function get(string $key) {
	if (empty($key)) {
		throw new Exception('empty key');
	}

	if (!is_null($this->map)) {
		if (!isset($this->map[$key]) && !array_key_exists($key, $this->map)) {
			throw new Exception('no such key '.$key);
		}

		return $this->map[$key][1];
	}
	else {
		throw new Exception('ToDo');
	}
}


/**
 * Save this.map to option.file.
 */
private function saveFile() : void {
	if ($this->option['file.suffix'] == 'ser') {
		File::serialize($this->option['file'], $this->map);
	}
	else if ($this->option['file.suffix'] == 'json') {
		File::save($this->option['file'], JSON::encode($this->map));
	}
}


/**
 * Load map from option.file.
 */
private function loadFile() : void {

	if ($this->option['file.suffix'] == 'ser') {
		$this->map = File::unserialize($this->option['file']);
	}
	else if ($this->option['file.suffix'] == 'json') {
		$this->map = JSON::decode(File::load($this->option['file']));
	}

	if (!empty($this->option['expire'])) {
		$expire = time() - $this->option['expire'];
		$update = false;

    foreach ($this->map as $key => $info) {
      if ($info[0] < $expire) {
        unset($this->map[$key]);
				$update = true;
      }
    }

		if ($update) {
			$this->saveFile();
		}
	}
}


}

