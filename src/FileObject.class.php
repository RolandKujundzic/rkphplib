<?php

namespace rkphplib;

require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/Dir.class.php');
require_once(__DIR__.'/JSON.class.php');



/**
 * File Object. Basic File information and synchronization.
 * Enable synchronization with FileObject::$sync['server'] = 'https://remote.tld'.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class FileObject {

/** @var string $sync = [ 'server' => '', 'bin' => 'bin/md5sum.php', 'abort' => false ] */
public static $sync = [ 'server' => '', 'bin' => 'bin/md5sum.php', 'abort' => false ];

/** @var string $name basename */
public $name = null;

/** @var string $path original file path */
public $path = null;

/** @var string $path_absolute realpath */
public $path_absolute = null;

/** @var string $remote_path */
public $remote_path = null;

/** @var string $md5 checksum */
public $md5 = null;

/** @var int $last_modified */
public $last_modified = null;

/** @var int $size */
public $size = null;

/** @var bool $modified true if file was modified */
public $is_modified = null;

/** @var bool $synchronized true if file was synchronized */
public $is_synchronized = null;

/** @var string $json_format (e.g. misl = md5 + (width + height) + size + last_modified) */
public $json_format = null;



/**
 * Return original filename when (string) cast occurs.
 * @return string
 */
public function __toString() {
	return $this->path;
}


/**
 * Load file data. If self::$sync.server is set synchronize. Parameter:
 *
 * - cwd: change current working directory ($path_absolute = realpath(getcwd().'/'.$path))
 * - remote_path: sync if set and self::$sync.server is not empty
 * - remote_file: if relative filename is different on remote server
 * - json_format: don't sync (retrieve only file information)
 * - md5_old: 
 *
 * @param string $path (default = '')
 * @param string $cwd (default = '' = use current working directory)
 */
public function __construct($path = '', $opt = []) {

	if (empty($path)) {
    return;
  }

  $this->name = basename($path);
	$this->path = $path;

	$cwd = empty($opt['cwd']) ? getcwd() : $opt['cwd'];

	if (!empty($opt['json_format'])) {
		$this->json_format = $opt['json_format'];
	}

	if (!empty(self::$sync['server']) && isset($opt['remote_path'])) {
		try {
			$this->synchronize($opt);
		}
		catch (Exception $e) {
			if (!empty(self::$sync['abort'])) {
				throw $e;
			}
			else {
				$this->is_synchronized = 0;
			}
		}
	}
	else if (!empty($this->json_format)) {
		$this->path_absolute = realpath($cwd.'/'.dirname($path)).'/'.$this->name;
		$this->scanJSON();
	}
	else {
		$this->path_absolute = realpath($cwd.'/'.$path);
		$this->scanFile();
	}

	if (!$this->is_modified && (isset($opt['md5_old']) || array_key_exists('md5_old', $opt))) {
		$this->is_modified = ($opt['md5_old'] != $this->md5) ? 1 : 0;
	}
}


/**
 * Initialize object with hash values.
 *
 * @param map $map
 */
public function fromHash($map) {
	foreach ($map as $key => $value) {
		if (property_exists($this, $key)) {
			$this->$key = $value;
		}
	}
}


/**
 * Scan either $json or decode $this->path_absolute.'.json'.
 * Set all properties with same name as json key.
 *
 * @param JSON $json (default = null)
 */
protected function scanJSON($json = null) {

	if (is_null($json)) {
		$json = JSON::decode(File::load($this->path_absolute.'.json'));
	}

	foreach ($json as $key => $value) {
		if (property_exists($this, $key)) {
			$this->$key = $value;
		}
	}
}


/**
 * Scan $this->path_absolute and set $this->md5.
 */
protected function scanFile() {
	$this->md5 = File::md5($this->path_absolute);
	$this->size = File::size($this->path_absolute);
	$this->last_modified = File::lastModified($this->path_absolute);
}


/**
 * Syncronize with self::$sync.server if md5($cwd/$this.path) changed. Retrieve remote md5 with
 * "self::$sync[server]/self::$sync[bin]?path=$remote_path/$this.path|$remote_file". 
 * Auto create local file directory if necessary. If remote_only is true retrieve only file 
 * information and save as $this.path.json. Use $this->remote_path if set and remote_path is empty.
 *
 * @param map $opt
 * 
 */
private function synchronize($opt) {

	$cwd = empty($opt['cwd']) ? getcwd() : $opt['cwd'];

	if (empty($this->path)) {
		throw new Exception('empty path');
	}

	if (empty(self::$sync['server']) || empty(self::$sync['bin'])) {
		throw new Exception('invalid sync configuration', print_r(self::$sync, true));
	}

	if (empty($cwd)) {
		$cwd = getcwd();
	}

	if (isset($opt['remote_path'])) {
		$rpath = empty($opt['remote_file']) ? $this->path : $opt['remote_file'];
		$this->remote_path = empty($opt['remote_path']) ? $rpath : $opt['remote_path'].'/'.$rpath;
	}

	$remote_file_url = self::$sync['server'].'/'.$this->remote_path;
	$dir = $cwd.'/'.dirname($this->path); 

	if (!Dir::exists($dir)) {
		Dir::create($dir, 0, true);
	}

	$this->path_absolute = realpath($dir).'/'.basename($this->path);
	$server_bin = self::$sync['server'].'/'.self::$sync['bin']; 

	if (!empty($this->json_format)) {
		$json_file = $this->path_absolute.'.json';

		if (File::exists($json_file)) {
			$this->scanJSON();
		}

		$remote_json_str = File::fromURL($server_bin.'?format='.$this->json_format.'&path='.urlencode($this->remote_path));
		$remote_json = JSON::decode($remote_json_str);

		if ($this->md5 != $remote_json['md5']) {
			File::save($json_file, $remote_json_str);
			$this->scanJSON($remote_json);
			$this->is_modified = 1;
		}
		else {
			$this->is_modified = 0;
		}
	}
	else if (!File::exists($this->path_absolute)) {
		File::save($this->path_absolute, File::fromURL($remote_file_url));
		$this->is_modified = 1;
		$this->scanFile();
	}
	else {
		$this->scanFile();
		$remote_json = JSON::decode(File::fromURL($server_bin.'?path='.urlencode($this->remote_path)));

		if (empty($remote_json['md5'])) {
			throw new Exception('remote md5 missing', print_r($remote_json, true));
		}

		if ($remote_json['md5'] != $this->md5) {
			File::save($this->path_absolute, File::fromURL($remote_file_url));
			$this->is_modified = 1;
			$this->scanFile();
		}
		else {
			$this->is_modified = 0;
		}
	}

	$this->is_synchronized = 1;
}


}

