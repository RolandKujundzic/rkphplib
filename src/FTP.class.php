<?php

namespace rkphplib;

require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/Dir.class.php');


/**
 *
 * FTP client.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class FTP {

/** @var resource $ftp */
private $ftp = null; 

/** @var map $conf (@see setConf) */
private $conf = [];

/** @var map<string:[md5,size,since]> $cache */
private $cache = [];



/**
 * Constructor. 
 *
 * @throws
 * @see setConf
 * @param map $opt (default = [] = use default options)
 */
public function __construct($opt = []) {

	$this->setConf([
		'host' => '',
		'login' => '',
		'password' => '',
		'use_cache' => '',
		'log' => null,
		'passive' => true,
		'retry' => true,
		'async' => true,
		'ssl' => true,
		'port' => 21,
		'timeout' => 30 ]);

	if (count($opt) > 0) {
		$this->setConf($opt);
	}

	if (!empty($this->conf['host']) && !empty($this->conf['login']) && !empty($this->conf['password'])) {
		$this->open($this->conf['host'], $this->conf['login'], $this->conf['password']);
	}
}


/**
 * Connect to FTP Server. Use ssl + passive mode by default.
 *
 * @throws 
 * @see __construct
 * @param string $host
 * @param string $user
 * @param string $pass
 */
public function open($host, $user, $pass) {

	$this->setConf(['host' => $host, 'login' => $user, 'password' => $pass]);

	$required = [ 'host', 'login', 'password', 'port', 'timeout' ];
	foreach ($required as $key) {
		if (empty($this->conf[$key])) {
			throw new Exception('empty conf parameter', $key);
		}
	}

	if ($this->conf['ssl']) {
		$this->ftp = ftp_ssl_connect($this->conf['host'], $this->conf['port'], $this->conf['timeout']);
	}
	else {
		$this->ftp = ftp_connect($this->conf['host'], $this->conf['port'], $this->conf['timeout']);
	}

	if (($this->ftp === false) {
		throw new Exception('FTP connect failed', 'host='.$this->conf['host'].' port='.$this->conf['port']);
	}
	
	if (!ftp_login($this->ftp, $this->conf['login'], $this->conf['password'])) {
		throw new Exception('FTP login failed', 'login='.$this->conf['login'].' password='.mb_substr($this->conf['password'], 0, 2).'***');
	}
	
	if ($this->conf['passive'] && !ftp_pasv($this->ftp, true)) {
		throw new Exception('Failed to switch to passive FTP mode');
	}

	$this->_log('FTP connected to '.$this->conf['host']);
}


/**
 * Create directory path in ftp root directory.
 * Preserve current working directory. 
 * Path starts with / and must not end with /.
 * 
 * @throws
 * @param string $path
 */
public function mkdir($path) {

	$curr = ftp_pwd($this->ftp);
	$path = trim($path);
	
	if (mb_substr($path, 0, 1) !== '/') {
		throw new Exception('path must start with /', $path);
	}
	
	if (mb_substr($path, -1) == '/') {
		throw new Exception('path must not end with /', $path);
	}
	
	$path_parts = explode('/', $path);
	$path = '';

	if (!ftp_chdir($this->ftp, '/')) {
		throw new Exception('FTP chdir / failed');
	}
	
	for ($i = 1; $i < count($path_parts); $i++) {
		$path .= '/'.$path_parts[$i];

		if (!ftp_chdir($this->ftp, $path)) {
			if (ftp_mkdir($this->ftp, $path) === false) {
				throw new Exception('failed to create FTP directory', $path);
			}
		}
	}	
	
	if (!ftp_chdir($this->ftp, $curr)) {
		throw new Exception('FTP chdir failed', $curr);
	}
}


/**
 * Return true if remote file exists. If md5 is not empty check md5 value.
 * 
 * @param $file
 * @param $md5 (default = '')
 * @return bool
 */
public function has($file, $md5 = '') {

	if (empty($this->conf['use_cache']) || !isset($this->cache[$file])) {
		return false;
	}

	return empty($md5) || $this->cache[$file][0] === $md5;
}


/**
 * Upload local file. Create remote directory path if necessary.
 * Remote file path must start with root directory. 
 * 
 * @throws
 * @param string $local_file
 * @param string $remote_file
 */
public function put($local_file, $remote_file) {
	
	if ($this->has($remote_file, File::md5($local_file))) {
		$this->_log($remote_file.' exists');
		return;
	}
	
	$this->mkdir(dirname($remote_file));

	if (!empty($this->conf['use_cache']) && isset($this->cache[$remote_file])) {
		unset($this->cache[$remote_file]);
	}

	if ($this->conf['retry']) {
		$ret = ftp_nb_put($this->ftp, $remote_file, $local_file, FTP_BINARY, FTP_AUTORESUME);

		while ($ret === FTP_MOREDATA) {
			$ret = ftp_nb_continue($this->ftp);
		}

		if ($ret !== FTP_FINISHED) {
			throw new Exception('put file failed', "local_file=$local_file remote_file=$remote_file");
		}
	}
	else {
		if (!ftp_put($this->ftp, $remote_file, $local_file, FTP_BINARY)) {
			throw new Exception('put file failed', "local_file=$local_file remote_file=$remote_file");
		}
	}

	$this->cache[$remote_file] = [ File::md5($local_file), File::size($local_file), File::lastModified($local_file) ];

	$this->_log("put $remote_file");	
}


/**
 * Upload directory.
 * 
 * @param string $local_dir
 * @param string $remote_dir
 */
public function putDir($local_dir, $remote_dir) {
	$entries = Dir::entries($local_dir);

	$this->_log("recursive directory upload $local_dir to $remote_dir\n");	
  
	foreach ($entries as $entry) {
		if (FSEntry::isDir($entry, false)) {
			$this->putDir($entry, $remote_dir.'/'.basename($entry));
		}
		else if (FSEntry::isFile($entry, false)) {
			$this->put($entry, $remote_dir.'/'.basename($entry));
		}
	}
}


/**
 * Download remote file to local file.
 *
 * @throws
 * @param string $remote_file
 * @param string $local_file
 */
public function get($remote_file, $local_file) {

	if (!empty($this->conf['use_cache']) && isset($this->cache[$remote_file])) {
		if (File::md5($local_file) === $this->cache[$remote_file][0]) {
			return;
		}
	}

	if (!ftp_get($this->ftp, $local_file, $remote_file, FTP_BINARY)) {
		throw new Exception('FTP download failed', "local_file=$local_file remote_file=$remote_file");
	}
}


/**
 * Enable cache. If $cache is file:/path/cache/file.ser load
 * serialized cache file ( { file_path1 = [md5, size, since], ... }).
 *
 * @throws 
 * @param string $cache
 */
public function useCache($cache) {

	if (count($this->cache) > 0) {
		throw new Exception('cache was already loaded');
	}

	if (mb_substr($cache, 0, 5) === 'file:') {
		$this->conf['use_cache'] = $cache;
		$this->cache = [];
		
		$cache_file = mb_substr($cache, 6);
		if ($cache_file && File::exists($cache_file)) {
			$this->cache = File::unserialize($cache_file);
		}
	}
	else {
		throw new Exception('invalid cache', $cache);
	}
}


/**
 * Set options. Default options:
 *
 * - host & login & password: if all three values are set auto execute open(host, login, password)
 * - log: default = null = no output | true = 'php://STDOUT' | file pointer
 * - passive: default = true (connection mode)
 * - use_cache: default = '' (file:abc.ser = use serialized cache file)
 * - retry: default = true (retry put|get)
 * - async: default = true (async put|get)
 * - port: default = 21
 * - timeout: default = 30 (connection timeout)
 *
 * @param map $conf
 */
public function setConf($conf) {

	foreach ($conf as $key => $value) {
		if (array_key_exists($key, $this->conf)) {
			if ($key === 'log' && $value === true) {
				$this->conf['log'] = 'php://STDOUT';
			}
			else {
				$this->conf[$key] = $value;
			}
		}
		else {
			throw new Exception('invalid option ', $key);
		}
	}
}


/**
 * Return configuration map (key = null) or value.
 *
 * @throws
 * @see setConf
 * @param string $key (default = null)
 * @return any|map (if key is null)
 */
public function getConf($key = null) {

	if (is_null($key)) {
		return $this->conf;
	}

	if (!array_key_exists($key, $this->conf)) {
		throw new Exception('no such configuration key', $key);
	}

	return $this->conf[$key];
}


/**
 * Print message if conf.log is null null.
 *
 * @param string $msg
 */
private function _log($msg) {
	if (is_null($this->conf['log'])) {
		return;
	}

	fprintf($this->conf['log'], "%s\n", $msg); 
}


/**
 * Close ftp connection.
 */
public function __destruct() {
	$this->close();
}


/**
 * Close FTP connection. 
 */
public function close() {

	if (is_null($this->ftp)) {
		return;
	}

	if (!empty($this->conf['use_cache']) && count($this->cache) > 0) {
		if (mb_substr($this->conf['use_cache'], 0, 5) === 'file:') {
			File::serialize(mb_substr($this->conf['use_cache'], 6), $this->cache);
		}
	}
	
	if ($this->ftp) {
		if (!ftp_close($this->ftp)) {
			throw new Exception('FTP close failed');
		}

		$this->ftp = null;
	}

	$this->_log("FTP connection closed");
}


/**
 * Return directory listing.
 * 
 * @param string $directory
 * @return array|false
 */
public function ls($directory) {
	return ftp_nlist($this->ftp, $directory);
}


/**
 * Delete remote file.
 *
 * @param string $file
 * @return bool
 */
public function rm($file) {
	return ftp_delete($this->ftp, $file);
}


/**
 * Remove remote directory (including all subdirectories).
 *
 * @param string $directory
 */
public function rmdir($directory) {

	if (ftp_delete($this->ftp, $directory)) {
		$this->_log('delete file '.$directory);
	}
	else if (ftp_rmdir($this->ftp, $directory)) {
		$this->_log('delete empty directory '.$directory);
	}
	else {
		// non empty directory ...
		$entries = $this->ls($directory);

		foreach ($entries as $entry) {
			if ($entry !== '.' && $entry !== '..') {
				$this->rmdir($directory.'/'.$entry);
			}
		}

		if (ftp_rmdir($this->ftp, $directory)) {
			$this->_log('delete empty directory '.$directory);
		}
	}
}


}

