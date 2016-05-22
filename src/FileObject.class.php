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
public $path_absolute = '';

/** @var string $md5 checksum */
public $md5 = null;

/** @var bool $modified true if file was modified */
public $is_modified = null;

/** @var bool $synchronized true if file was synchronized */
public $is_synchronized = null;

/** @var string $json_format (e.g. misl = md5 + (width + height) + size + last_modified) */
public $json_format = null;



/**
 * Load file data. If self::$sync.server is set synchronize. Parameter:
 *
 * - cwd: change current working directory ($path_absolute = realpath(getcwd().'/'.$path))
 * - remote_path: sync if set and self::$sync.server is not empty
 * - json_format: don't sync (retrieve only file information)
 * - old_md5: 
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
				$this->is_synchronized = false;
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

	if (!$this->is_modified && !empty($opt['old_md5']) && $opt['old_md5'] != $this->md5) {
		$this->is_modified = true;
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
}


/**
 * Syncronize with self::$sync.server if md5($cwd/$this.path) changed. Retrieve remote md5 with
 * "self::$sync[server]/self::$sync[bin]?path=$remote_path/$this.path". Auto create local file
 * directory if necessary. If remote_only is true retrieve only file information and save as
 * $this.path.json. 
 *
 * @param map $opt
 * 
 */
private function synchronize($opt) {

	$remote_path = $opt['remote_path'];
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

	$remote_file = empty($remote_path) ? $this->path : $remote_path.'/'.$this->path;
	$remote_file_url = self::$sync['server'].'/'.$remote_file;
	$dir = $cwd.'/'.dirname($this->path); 

	if (!Dir::exists($dir)) {
		Dir::create($dir, 0, true);
	}

	$this->path_absolute = realpath($dir).'/'.basename($this->path);
	$server_bin = self::$sync['server'].'/'.self::$sync['bin']; 

	if (!empty($this->json_format)) {
		$json_str = File::fromURL($server_bin.'?format='.$this->json_format.'&path='.urlencode($remote_file));
		File::save($this->path_absolute.'.json', $json_str);
		$this->scanJSON(JSON::decode($json_str));
	}
	else if (!File::exists($this->path_absolute)) {
		File::save($this->path_absolute, File::fromURL($remote_file_url));
		$this->is_modified = true;
		$this->scanFile();
	}
	else {
		$this->scanFile();
		$remote_md5 = File::fromURL($server_bin.'?path='.urlencode($remote_file));

		if ($remote_md5 != $this->md5) {
			File::save($this->path_absolute, File::fromURL($remote_file_url));
			$this->is_modified = true;
			$this->scanFile();
		}
		else {
			$this->is_modified = false;
		}
	}

	$this->is_synchronized = true;
}


}

