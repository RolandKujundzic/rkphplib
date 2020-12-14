<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Curl.class.php';
require_once __DIR__.'/Dir.class.php';
require_once __DIR__.'/lib/execute.php';

use function rkphplib\lib\execute;


/**
 * Start|Stop|Check Server
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
abstract class AServerJob {

protected $conf = [];

protected $env = [];


/**
 * Call in constructor
 * @require this.conf log_dir, server
 * @set this.env.file json, pid, log
 */
final protected function prepare() : void {
	foreach ([ 'log_dir', 'server' ] as $key) {
		if (empty($this->conf[$key])) {
			throw new Exception('missing '.$key, print_r($this->conf, true));
		}
	}

	Dir::exists($this->conf['log_dir'], true);
	$this->conf['log_dir'] = realpath($this->conf['log_dir']);

	$this->env['file'] = [];
	$log_dir = $this->conf['log_dir'].'/'.$this->conf['server'];
	$this->env['file']['log'] = $log_dir.'.log';
	$this->env['file']['pid'] = $log_dir.'.pid';
	$this->env['file']['json'] = $log_dir.'.json';

	if (File::exists($log_dir.'.json')) {
	  $this->env['conf'] = File::loadJSON($log_dir.'.json');
	}
}


/**
 * Return configuration value. Keys:
 * log_dir, conf.KEY, ps.KEY, …
 */
final public function get(string $key, $required = true) : ?string {
	$skey = '';
	if (strpos($key, '.') > 0) {
		list ($key, $skey) = explode('.', $key);
	}

	$res = null;
	if (isset($this->conf[$key])) {
		$res = $this->conf[$key];
	}
	else if ($skey && isset($this->env[$key]) && isset($this->env[$key][$skey])) {
		$res = $this->env[$key][$skey];
	}
	else if (isset($this->env[$key])) {
		$res = $this->env[$key];
	}
	else if ($required) {
		$ckey = $skey ? $key.$skey : $key;
		throw new Exception("missing $ckey", print_r($this->conf, true)."\n".print_r($this->env, true));
	}

	return $res;
}


/**
 * Return true if server is running
 * @required this.conf url
 */
final public function checkHttp(int $ttl = 0) : bool {
	if ($ttl > 0 && !empty($this->env['checkHttp']) && time() - $this->env['checkHttp'] < $ttl) {
		return true;
	}

	$res = Curl::check($this->conf['url']);

	if ($res) {
		$this->env['checkHttp'] = time();
	}

	return $res;
}


/**
 * Return pid or 0
 * @example checkPid(get('ps.pid'))
 */
final public function checkPid(int $pid = 0, int $wait = 0) : int {
	if ($pid < 1) {
		return 0;
	}

	$has_pid = posix_getpgid($pid);
	if (!$has_pid) {
		clearstatcache(true, '/proc');
		$has_pid = file_exists('/proc/'.$pid);
	}

	if (!$has_pid && $wait) {
		sleep($wait);
		$has_pid = file_exists('/proc/'.$pid);
	}

	return $pid;
}


/**
 * Return pid or 0 and set env.ps.pid|user|match.
 */
final public function checkProcess(string $pname) : int {	
	$found = [];
	$pid = 0;

	$ps = execute("ps aux | grep '$pname' | grep -v grep", null, 0);
	if (!preg_match('/^(.+?) +([0-9]+) +/', $ps, $match)) {
		$pid = intval($match[2]);
		$found['user'] = $match[1];
		$found['match'] = $match[0];
		$found['pid'] = $pid;
	}

	$this->env['ps'] = $found;
	return $pid;
}


/**
 * Return pid or 0
 */
final public function getPid(int $wait = 0) : int {
	$server = $this->conf['host'].':'.$this->conf['port'];
	$pid_file = empty($this->conf['log_dir']) ? '' :
		$pid_file = $this->conf['log_dir'].'/'.$server.'.pid';

	$pid = File::exists($this->env['file']['pid']) ? intval(File::load($this->env['file']['pid'])) : 0;
	if (($pid = $this->checkPid($pid, $wait)) > 0) {
		return $pid;
	}

	list ($pid, $pname) = $this->checkProcess('php -t '.$this->conf['docroot'].' -S '.$server);
	return (intval($pid) > 79) ? $pid : 0;
}


/**
 * Start server
 */
abstract public function start(bool $restart = true) : void;


/**
 * Stop (kill) server
 */
abstract public function stop() : void;

}

