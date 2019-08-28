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
 * Constructor. If $opt is not set use default options:
 * 
 * - host=
 * - login=
 * - password=
 * - use_cache=
 * - log= null
 * - passive= true
 * - ssl= true
 * - port= 21
 * - timeout= 30
 *
 * @throws
 * @see setConf
 */
public function __construct(array $opt = []) {

	$this->conf = [
		'host' => '',
		'login' => '',
		'password' => '',
		'use_cache' => '',
		'log' => null,
		'passive' => true,
		'ssl' => true,
		'port' => 21,
		'timeout' => 30 ];

	if (count($opt) > 0) {
		$this->setConf($opt);
	}

	if (!empty($this->conf['host']) && !empty($this->conf['login']) && !empty($this->conf['password'])) {
		$this->open($this->conf['host'], $this->conf['login'], $this->conf['password']);
	}
}


/**
 * Connect to FTP Server. Use ssl + passive mode by default.
 */
public function open(string $host, string $user, string $pass) : void {

	$this->setConf(['host' => $host, 'login' => $user, 'password' => $pass]);

	$required = [ 'host', 'login', 'password', 'port', 'timeout' ];
	foreach ($required as $key) {
		if (empty($this->conf[$key])) {
			throw new Exception('empty conf parameter', $key);
		}
	}

	if ($this->conf['ssl']) {
		$this->ftp = @ftp_ssl_connect($this->conf['host'], $this->conf['port'], $this->conf['timeout']);
	}
	else {
		$this->ftp = @ftp_connect($this->conf['host'], $this->conf['port'], $this->conf['timeout']);
	}

	if ($this->ftp === false) {
		throw new Exception('FTP connect failed', 'host='.$this->conf['host'].' port='.$this->conf['port']);
	}

	if (!@ftp_login($this->ftp, $this->conf['login'], $this->conf['password'])) {
		$ssl_msg = $this->conf['ssl'] ? ' - try without ssl' : ' - try with ssl';
		throw new Exception('FTP login failed'.$ssl_msg, 'login='.$this->conf['login'].' password='.
			mb_substr($this->conf['password'], 0, 2).'***');
	}
	
	if ($this->conf['passive'] && !@ftp_pasv($this->ftp, true)) {
		throw new Exception('Failed to switch to passive FTP mode');
	}

	$this->_log('FTP connected to '.$this->conf['host']);
}


/**
 * Change remote directory to path.
 */
public function chdir(string $path) : void {
	if (!@ftp_chdir($this->ftp, $path)) {
		throw new Exception('FTP chdir failed', $path);
	}
}


/**
 * Return current directory.
 */
public function pwd() : string {
	if (($res = @ftp_pwd($this->ftp)) === false) {
		throw new Exception('pwd failed');
	}

	return $res;
}


/**
 * Create directory path in ftp root directory.
 * Preserve current working directory. 
 * Path starts with / and must not end with /.
 */
public function mkdir(string $path) : void {

	$curr = $this->pwd();
	$path = trim($path);
	
	if (mb_substr($path, 0, 1) !== '/') {
		throw new Exception('path must start with /', $path);
	}
	
	if (mb_substr($path, -1) == '/') {
		throw new Exception('path must not end with /', $path);
	}
	
	$path_parts = explode('/', $path);
	$path = '';

	$this->chdir('/');
	
	for ($i = 1; $i < count($path_parts); $i++) {
		$path .= '/'.$path_parts[$i];

		if (!@ftp_chdir($this->ftp, $path)) {
			if (@ftp_mkdir($this->ftp, $path) === false) {
				throw new Exception('failed to create FTP directory', $path);
			}
		}
	}	
	
	$this->chdir($curr);
}


/**
 * Return true if remote file exists. If md5 of local file is not empty check md5 value.
 */
public function hasCache(string $file, string $md5 = '') : bool {

	if (empty($this->conf['use_cache']) || !isset($this->cache[$file])) {
		return false;
	}

	return empty($md5) || $this->cache[$file][0] === $md5;
}


/**
 * Upload local file. Create remote directory path if necessary.
 * Remote file path must start with root directory. 
 */
public function put(string $local_file, string $remote_file) : void {
	
	if ($this->hasCache($remote_file, File::md5($local_file))) {
		$this->_log($remote_file.' exists');
		return;
	}
	
	$this->mkdir(dirname($remote_file));

	if (!empty($this->conf['use_cache']) && isset($this->cache[$remote_file])) {
		unset($this->cache[$remote_file]);
	}

	$this->_log("upload $local_file as $remote_file");	

	$ret = @ftp_nb_put($this->ftp, $remote_file, $local_file, FTP_BINARY);

	while ($ret === FTP_MOREDATA) {
		$ret = @ftp_nb_continue($this->ftp);
	}

	if ($ret !== FTP_FINISHED) {
		throw new Exception('put file failed', "local_file=$local_file remote_file=$remote_file");
	}

	$this->cache[$remote_file] = [ File::md5($local_file), File::size($local_file), File::lastModified($local_file) ];
	$this->updateCache();
}


/**
 * Upload directory.
 */
public function putDir(string $local_dir, string $remote_dir) : void {
	$entries = Dir::entries($local_dir);

	$this->_log("recursive directory upload $local_dir to $remote_dir\n");	
  
	foreach ($entries as $entry) {
		if (Dir::exists($entry)) {
			$this->putDir($entry, $remote_dir.'/'.basename($entry));
		}
		else if (File::exists($entry)) {
			$this->put($entry, $remote_dir.'/'.basename($entry));
		}
	}
}


/**
 * Download $remote_path directory recursive as $local_path.
 */
public function getDir(string $remote_path, string $local_path) : array {

	$entries = $this->ls($remote_path);

	Dir::create($local_path, 0, true);

	foreach ($entries as $path => $info) {
		if ($info['type'] === 'f') {
			$this->get($path, $local_path.'/'.$info['name']);
		}
		else if ($info['type'] === 'l') {
			if (FSEntry::isLink($local_path.'/'.$info['name'], false)) {
				File::remove($local_path.'/'.$info['name']);
			}

			$this->_log("link ".$info['link']." ".$local_path.'/'.$info['name']);
			FSEntry::link($info['link'], $local_path.'/'.$info['name']);
		}
		else if ($info['type'] === 'd') {
			$this->getDir($path, $local_path.'/'.$info['name']);
		}
	}
}


/**
 * Download remote file to local file.
 */
public function get(string $remote_file, string $local_file) : void {

	$lfile = File::exists($local_file);

	if ($lfile && $this->hasCache($remote_file, File::md5($local_file))) {
		$this->_log($remote_file.' exists');
		return;
	}

	$this->_log("download $remote_file as $local_file");

	$ret = @ftp_nb_get($this->ftp, $local_file, $remote_file, FTP_BINARY);

	while ($ret === FTP_MOREDATA) {
		$ret = @ftp_nb_continue($this->ftp);
	}

	if ($ret !== FTP_FINISHED) {
		throw new Exception('FTP download failed', "local_file=$local_file remote_file=$remote_file");
	}

	$this->cache[$remote_file] = [ File::md5($local_file), File::size($local_file), File::lastModified($local_file) ];
	$this->updateCache();
}


/**
 * Enable cache. If $cache is file:/path/cache/file.ser load
 * serialized cache file ( { file_path1 = [md5, size, since], ... }).
 */
public function useCache(string $cache) : void {

	if (count($this->cache) > 0) {
		throw new Exception('cache was already loaded');
	}

	if (mb_substr($cache, 0, 5) === 'file:') {
		$this->cache = [];
		
		$cache_file = mb_substr($cache, 5);
		if ($cache_file && File::exists($cache_file)) {
			$this->cache = File::unserialize($cache_file);
		}
		else if (!Dir::exists(dirname($cache_file))) {
			Dir::create(dirname($cache_file), 0, true);
		}
	}
	else {
		throw new Exception('invalid cache', $cache);
	}

	$this->conf['cache_load'] = microtime(true);
	$this->conf['use_cache'] = $cache;
}


/**
 * Set options. Default options:
 *
 * - host & login & password: if all three values are set auto execute open(host, login, password)
 * - log: default = null = no output | true (or 1) = STDOUT | file pointer
 * - passive: default = true (connection mode)
 * - use_cache: default = '' (file:abc.ser = use serialized cache file)
 * - port: default = 21
 * - timeout: default = 30 (connection timeout)
 */
public function setConf(array $conf) : void {

	foreach ($conf as $key => $value) {
		if (array_key_exists($key, $this->conf)) {
			if ($key === 'log' && ($value === true || $value === '1' || $value === 1)) {
				$this->conf['log'] = STDOUT;
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
 * Return configuration hash (key = null) or value.
 */
public function getConf(?string $key = null) {

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
 */
private function _log(string $msg) : void {
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
 * Update serialized cache file if last cache save > 10 sec or force = true.
 */
private function updateCache(bool $force = false) : void {

	if (empty($this->conf['use_cache']) || count($this->cache) === 0) {
		return;
	}

	if (!$force && microtime(true) - $this->conf['cache_load'] < 10) {
		return;
	}

	if (mb_substr($this->conf['use_cache'], 0, 5) === 'file:') {
		File::serialize(mb_substr($this->conf['use_cache'], 5), $this->cache);
		$this->conf['cache_load'] = microtime(true);
	}
}


/**
 * Close FTP connection. 
 */
public function close() : void {

	if (is_null($this->ftp)) {
		return;
	}

	$this->updateCache(true);
	
	if ($this->ftp) {
		if (!@ftp_close($this->ftp)) {
			throw new Exception('FTP close failed');
		}

		$this->ftp = null;
	}

	$this->_log("FTP connection closed");
}


/**
 * Return escaped path. Put backslash before whitespace.
 */
public static function escapePath(string $path) : string {
	$res = str_replace(' ', '\\ ', $path);
	return $res;
}


/**
 * Return directory listing (use / for document root). If recursive is true return tree.
 * Return empty hash if directory is empty.
 */
public function ls(string $directory) : array {

	if (empty($directory)) {
		throw new Exception('empty directory', 'use [.] or [/]');
	}

	if (($lsout = @ftp_rawlist($this->ftp, '-a '.self::escapePath($directory))) === false) {
		throw new Exception('invalid directory', $directory);
	}

	$pdir = ($directory === '/') ? '/' : $directory.'/';
	$res = [];

	// lsout: [0] => -rwxrwxrwx    1 1000       user          265535 Apr 19  2016 716238.jpg ...
	$dir = null;
	$parent_dir = null;

	foreach ($lsout as $entry) {
		$chunks = preg_split("/\s+/", $entry); 
		$info = [];

		list ($info['priv'], $info['num'], $info['uid'], $info['gid'], $info['size'], $info['month'], $info['day'], $info['time'], 
			$info['name']) = $chunks;

		$info['date'] = (mb_strlen($info['time']) === 4) ? date('Y-m-d', strtotime($info['month'].'-'.$info['day'].'-'.$info['time'])) :
			date('Y-m-d', strtotime($info['month'].'-'.$info['day'].'-'.date('Y'))).' '.$info['time'];

		unset($info['num']);
		unset($info['month']);
		unset($info['day']);
		unset($info['time']);

		$mode = mb_substr($info['priv'], 0, 1);

		if (($mode === '-' || $mode === 'd') && count($chunks) > 9) {
			// basename contains whitespace		
			$pos = mb_strpos($entry, $info['name']);
			$info['name'] = mb_substr($entry, $pos);
		}

		if ($mode === '-') {
			$info['type'] = 'f';
		}
		else if ($mode === 'd') {
			$info['type'] = 'd';
		}
		else if ($mode === 'l' && count($chunks) > 9 && $chunks[9] === '->') {
			$info['link'] = $chunks[10];
			$info['type'] = 'l';
		}

		if ($info['name'] === '.') {
			$dir = $info;
		}
		else if ($info['name'] === '..') {
			$parent_dir = $info;
		}
		else {
			$res[$pdir.$info['name']] = $info;
		}
	}

	if (is_null($dir)) {
		throw new Exception('invalid directory', $directory);
	}

	return $res; 
}


/**
 * Return true if remote file exists.
 */
public function hasFile(string $path) : bool {
	return !empty($path) && @ftp_mdtm($this->ftp, $path) > 0;
}


/**
 * Return true if remote directory exists.
 */
public function hasDirectory(string $path) : bool {
	$tmp = @ftp_rawlist($this->ftp, '-a '.$path);

	if ($tmp === false) {
		return false;
	}

	// true if [.] and [..] entries exist
	return count($tmp) > 1;
}


/**
 * Delete remote file.
 */
public function removeFile(string $file) : void {
	if ($this->hasFile($file)) {
		if (!@ftp_delete($this->ftp, $file)) {
			throw new Exception('delete file failed', $file);
		}
	}
	else {
		throw new Exception('no such file', $file);
	}
}


/**
 * Remove remote directory (including all subdirectories).
 */
public function removeDirectory(string $path) : void {

	if (empty($path)) {
		throw new Exception('empty path', $path);
	}

	if (@ftp_delete($this->ftp, $path)) {
		$this->_log('delete file '.$path);
	}
	else if (@ftp_rmdir($this->ftp, $path)) {
		$this->_log('delete empty directory '.$path);
	}
	else {
		// non empty directory or non existing ...
		$entries = $this->ls($path);

		foreach ($entries as $entry) {
			if ($entry !== '.' && $entry !== '..') {
				$this->removeDirectory($path.'/'.$entry);
			}
		}

		if (@ftp_rmdir($this->ftp, $path)) {
			$this->_log('delete empty directory '.$path);
		}
	}
}


}

