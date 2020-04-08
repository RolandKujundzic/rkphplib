<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Dir.class.php';
require_once __DIR__.'/JSON.class.php';


/**
 * File Object. Basic File information and synchronization.
 * Enable synchronization with FileObject::$sync['server'] = 'https://remote.tld'.
 * If file does not exists path_absolute is empty.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class FileObject {

// @var string $sync = [ 'server' => '', 'bin' => 'bin/md5sum.php', 'abort' => false, 'cwd' => '' ]
public static $sync = [ 'server' => '', 'bin' => 'bin/md5sum.php', 'abort' => true, 'cwd' => '' ];

// @var string $name basename 
public $name = null;

// @var string $path original file path 
public $path = null;

// @var string $path_absolute realpath 
public $path_absolute = null;

// @var string $remote_path 
public $remote_path = null;

// @var string $md5 checksum
public $md5 = null;

// @var int $last_modified 
public $last_modified = null;

// @var int $size 
public $size = null;

// @var bool $modified true if file was modified 
public $is_modified = null;

// @var bool $synchronized true if file was synchronized 
public $is_synchronized = null;

// @var string $json_format (e.g. misl = md5 + (width + height) + size + last_modified) 
public $json_format = null;



/**
 * Return original filename when (string) cast occurs.
 */
public function __toString() : string {
	return $this->path;
}


/**
 * Load file data. If self::$sync.server or $opt[remote_server] is set synchronize. Parameter:
 *
 * - cwd: change current working directory ($path_absolute = realpath(getcwd().'/'.$path))
 * - remote_server: default = self::$sync[server]
 * - remote_bin: default = self::$sync[bin] = bin/md5sump.php
 * - remote_abort: default = self::$sync[abort] = true (throw exception if sync failed)
 * - remote_path: default = self::$sync[cwd] = null = use local path
 * - remote_file: default = $path (use if $path is different on remote server)
 * - json_format: is set retrieve only file information via remote_bin and save in $path.json.
 *     Use m=md5, i=width+height, s=size and l=last_modified, e.g. "misl" or "msl".
 * - md5_old: if set avoid unnecessary downloads
 */
public function __construct(string $path = '', array $opt = []) {

	if (empty($path)) {
		return;
	}

	$this->name = basename($path);
	$this->path = $path;

	$cwd = empty($opt['cwd']) ? getcwd() : $opt['cwd'];

	if (!empty($opt['json_format'])) {
		$this->json_format = $opt['json_format'];
	}

	if (!empty(self::$sync['server']) || !empty($opt['remote_server'])) {
		$this->synchronize($opt);
	}
	else if (!empty($this->json_format)) {
		$dir = realpath($cwd.'/'.dirname($path));

		if (File::exists($dir.'/'.$this->name.'.json')) {
			$this->path_absolute = $dir.'/'.$this->name;
			$this->scanJSON();
		}
	}
	else {
		$rpath = realpath($cwd.'/'.$path);

		if ($rpath) {
			$this->path_absolute = $rpath;
			$this->scanFile();
		}
		else if (mb_strlen($cwd) > 3 && mb_strpos($path, $cwd) !== false) {
			throw new Exception('path is not relative', "cwd=[$cwd] path=[$path]");
		}
	}

	if (!is_null($this->md5) && !$this->is_modified && (isset($opt['md5_old']) || array_key_exists('md5_old', $opt))) {
		$this->is_modified = ($opt['md5_old'] != $this->md5) ? 1 : 0;
	}
}


/**
 * Initialize object with hash values.
 */
public function fromHash(array $map) : void {
	foreach ($map as $key => $value) {
		if (property_exists($this, $key)) {
			$this->$key = $value;
		}
	}
}


/**
 * Scan either $json or decode $this->path_absolute.'.json'.
 * Set all properties with same name as json key.
 */
protected function scanJSON(object $json = null) : void {

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
 * Scan $this->path_absolute and set md5, size and last_modified properties.
 * If with and height property exists retrieve image dimensions.
 */
protected function scanFile() : void {
	$this->md5 = File::md5($this->path_absolute);
	$this->size = File::size($this->path_absolute);
	$this->last_modified = File::lastModified($this->path_absolute);

	if (property_exists($this, 'width') && property_exists($this, 'height')) {
		$ii = File::imageInfo($this->path_absolute);
		$this->width = intval($ii['width']);
		$this->height = intval($ii['height']);
	}
}


/**
 * Syncronize with remote server if $opt[remote_server] (self::$sync[server]) is set.
 * Local path is $opt[cwd]|getcwd().'/'.$this->path.
 * Remote path is $opt[remote_server]|self::$sync[server].'/'.$opt[remote_path]|self::$sync[cwd].'/'.$opt[remote_file]|$this->path
 * Auto create local file directory if necessary. Retrieve remote md5 with remote_server.'/'.self::$sync[bin]?path=remote_file. 
 * If $this->json_format is set save file information to local_file.json. Use md5_old to avoid unnecessary downloads.
 */
public function synchronize(array $opt) : void {

	if (is_null($this->path) || mb_strlen($this->path) == 0) {
		throw new Exception('empty path');
	}
	
	if (empty(self::$sync['server']) && empty($opt['remote_server'])) {
		return;
	}
	
try {
	
	$cwd = empty($opt['cwd']) ? getcwd() : $opt['cwd'];

	$rserver = empty($opt['remote_server']) ? self::$sync['server'] : $opt['remote_server'];
	$rfile = empty($opt['remote_file']) ? $this->path : $opt['remote_file'];
	$rpath = '';

	if (isset($opt['remote_path'])) {
		$rpath = $opt['remote_path'];
	}
	else if (!empty(self::$sync['cwd'])) {
		$rpath = self::$sync['cwd'];
	}

	$this->remote_path = empty($rpath) ? $rfile : $rpath.'/'.$rfile;

	$dir = $cwd.'/'.dirname($this->path); 
	if (!Dir::exists($dir)) {
		Dir::create($dir, 0, true);
	}

	$this->path_absolute = empty(realpath($dir)) ? $dir.'/'.$this->path : realpath($dir).'/'.basename($this->path);
	$file_exists = File::exists($this->path_absolute);	

	if (empty(self::$sync['bin']) && ($file_exists || !empty($this->json_format))) {
		throw new Exception('missing sync.bin', print_r(self::$sync, true));
	}
	else {
		$server_bin = $rserver.'/'.self::$sync['bin'];
	}

	if (!empty($this->json_format)) {
		$json_file = $this->path_absolute.'.json';

		if (File::exists($json_file)) {
			$this->scanJSON();
		}

		$json_query = $server_bin.'?format='.$this->json_format.'&path='.rawurlencode($this->remote_path);

		try {
			$remote_json_str = File::fromURL($json_query);
			$remote_json = JSON::decode($remote_json_str);
		}
		catch (\Exception $e) {
			throw new Exception('failed to retrive file information', "query=$json_query result=$remote_json_str");
		}

		if ($this->md5 != $remote_json['md5']) {
			File::save($json_file, $remote_json_str);
			$this->scanJSON($remote_json);
			$this->is_modified = 1;
		}
		else {
			$this->is_modified = 0;
		}
	}
	else if (!$file_exists) {
		File::save($this->path_absolute, File::fromURL($rserver.'/'.$this->remote_path));
		$this->is_modified = 1;
		$this->scanFile();
	}
	else {
		$this->scanFile();
		$remote_json = JSON::decode(File::fromURL($server_bin.'?path='.rawurlencode($this->remote_path)));

		if (empty($remote_json['md5'])) {
			throw new Exception('remote md5 missing', print_r($remote_json, true));
		}

		if ($remote_json['md5'] != $this->md5) {
			File::save($this->path_absolute, File::fromURL($rserver.'/'.$this->remote_path));
			$this->is_modified = 1;
			$this->scanFile();
		}
		else {
			$this->is_modified = 0;
		}
	}

	$this->is_synchronized = 1;
}
catch (\Exception $e) {
	if (!empty(self::$sync['abort'])) {
		throw $e;
	}
	else {
		$this->is_synchronized = 0;
	}
}

}


}
