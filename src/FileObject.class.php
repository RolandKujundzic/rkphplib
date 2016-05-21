<?php

namespace rkphplib;

require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/Dir.class.php');


/**
 * File Object. Basic File information and syncronization.
 * Enable syncronization with FileObject::$sync['server'] = 'https://remote.tld'.
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
public $modified = null;

/** @var bool $syncronized true if file was syncronized */
public $syncronized = null;



/**
 * Load file data. If self::$sync.server is set syncronize. Options:
 *
 * - cwd: change current working directory ($path_absolute = realpath(getcwd().'/'.$path))
 * - remote_path: sync if set and self::$sync.server is not empty
 * 
 * @param string $path
 * @param string $cwd (default = '' = use current working directory)
 */
public function __construct($path, $opt = []) {

	if (empty($path)) {
    return;
  }

  $this->name = basename($path);
	$this->path = $path;

	$cwd = empty($opt['cwd']) ? getcwd() : $opt['cwd'];

	if (!empty(self::$sync['server']) && isset($opt['remote_path'])) {
		try {
			$this->syncronize($opt['remote_path'], $cwd);
		}
		catch (Exception $e) {
			if (!empty(self::$sync['abort'])) {
				throw $e;
			}
			else {
				// $this->syncronized = false;
			}
		}
	}
	else {
		$this->path_absolute = realpath($cwd.'/'.$path);
		$this->md5 = File::md5($this->path_absolute);
	}
}


/**
 * Syncronize with self::$sync.server if md5($cwd/$this.path) changed. Retrieve remote md5 with
 * "self::$sync[server]/self::$sync[bin]?path=$remote_path/$this.path". Auto create local file
 * directory if necessary.
 *
 * @param string $remote_path (default = '')
 * @param string $cwd (default = '' = current working directory)
 * 
 */
public function syncronize($remote_path = '', $cwd = '') {

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

	if (!File::exists($this->path_absolute)) {
		File::save($this->path_absolute, File::fromURL($remote_file_url));
		$this->md5 = File::md5($this->path_absolute);
		$this->modified = true;
	}
	else {
		$this->md5 = File::md5($this->path_absolute);
		$remote_md5 = File::fromURL(self::$sync['server'].'/'.self::$sync['bin'].'?path='.urlencode($remote_file));

		if ($remote_md5 != $this->md5) {
			print "download $remote_file_url to ".$this->path_absolute."\n";
			File::save($this->path_absolute, File::fromURL($remote_file_url));
			$this->md5 = $remote_md5;
			$this->modified = true;
		}
		else {
			$this->modified = false;
		}
	}

	$this->syncronized = true;
}


}

