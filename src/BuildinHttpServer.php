<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Curl.class.php';
require_once __DIR__.'/lib/execute.php';

use function rkphplib\lib\execute;


/**
 * Start|Stop|Check Buildin PHP Http Server
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
class BuildinHttpServer {

private $conf = [];


/**
 * Set webserver options.
 * @hash $opt …
 * script: optional (required for start)
 * port: required unless $host=HOST:PORT
 * docroot: required for start if empty use dirname(script)
 * log_dir: optional, if set create log_dir/HOST:PORT.[port.json|err|log]
 * ssl: 0, > 0 = run stunnel from ssl to port
 * @eol
 */
public function __construct(string $host = '0.0.0.0', array $opt = []) {
	$default = [
		'script' => '',
		'port' => 0,
		'host' => $host,
		'docroot' => '.',
		'log_dir' => '',
		'ssl' => 0
	];

	if (strpos($host, ':') > 0) {
		list ($default['host'], $default['port']) = explode(':', $host);
	}

	$this->conf = array_merge($default, $opt);

	if (!preg_match('/^[a-z0-9A-Z\.\-]+$/', $this->conf['host'])) {
		throw new Exception('invalid host '.$this->conf['host']);
	}

	if ($this->conf['port'] < 80) {
		throw new Exception('invalid port '.$this->conf['port']);
	}

	$this->conf['url'] = 'http://'.$this->conf['host'].':'.$this->conf['port'];
	if ($this->conf['ssl'] > 0) {
		$this->conf['url'] = 'https://'.$this->conf['host'].':'.$this->conf['ssl'];
		$this->conf['port'] = $this->conf['ssl'];
	}
}


/**
 * Return configuration value
 */
public function get(string $key) : string {
	if (!isset($this->conf[$key])) {
		throw new Exception("invalid configuration $key");
	}

  $server = $this->conf['host'].':'.$this->conf['port'];
	$cfile = $this->conf['log_dir']."/$server.json";

	if (!empty($this->conf[$key]) || empty($this->conf['log_dir']) || !File::exists($cfile)) {
		return $this->conf[$key];
	}

  $config = File::loadJSON($cfile);
	$this->conf[$key] = $config[$key];
	return $this->conf[$key];
}


/**
 * Update configuration key script|docroot
 */
public function set(string $key, string $value) : void {
	$allow = [ 'script', 'docroot' ];
	if (!in_array($key, $allow)) {
		throw new Exception("invalid key '$key' - use ".join('|', $allow));
	}

	$this->conf[$key] = $value;
}


/**
 * Return true if server is running
 */
public function check(int $ttl = 0) : bool {
	if ($ttl > 0 && !empty($this->conf['last_check']) && time() - $this->conf['last_check'] < $ttl) {
		return true;
	}

	$res = Curl::check($this->conf['url']);

	if ($res) {
		$this->conf['_last_check'] = time();
	}

	return $res;
}


/**
 * Return true if sever is running and configuration is same
 */
public function alive() : bool {
	if (!$this->check(2) || !($pid = $this->getPid())) {
		return false;
	}

	if (empty($this->conf['log_dir'])) {
		return true;
	}

	$server = $this->conf['host'].':'.$this->conf['port'];
	$config_file = $this->conf['log_dir']."/$server.json";
	if (!File::exists($config_file)) {
		return false;
	}

	$config = File::loadJSON($config_file);
	return $config['pid'] == $pid && (empty($this->conf['script']) ||
		$this->conf['script'] == $config['script']);
}


/**
 * Kill server
 */
public function stop() : void {
	if (($pid = $this->getPid(1)) == 0) {
		return;
	}

	execute('kill -9 '.$pid);

	$server = $this->conf['host'].':'.$this->conf['port'];

	if (is_dir('/proc/'.$pid)) {
		clearstatcache(true, '/proc/'.$pid);
		sleep(1);
		if (is_dir('/proc/'.$pid)) {
			throw new Exception("kill $server failed", 'kill -9 '.$pid);
		}
	}

	if (!empty($this->conf['log_dir'])) {
		foreach ([ '.log', '.json', '.pid' ] as $suffix) {
			$file = $this->conf['log_dir'].'/'.$server.$suffix;
			File::remove($file, false);
		}
	}
}


/**
 * Start server
 */
public function start(bool $restart = true) : void {
	if (empty($this->conf['docroot']) && !empty($this->conf['script'])) {
		$this->conf['docroot'] = dirname($this->conf['script']);
	}

	if (empty($this->conf['docroot'])) {
		throw new Exception('empty  docroot');
	}

	$this->conf['docroot'] = realpath($this->conf['docroot']);
	if (!is_dir($this->conf['docroot'])) {
		throw new Exception('no such directory '.$this->conf['docroot']);
	}

	if (!empty($conf['script'])) {
		$this->conf['script'] = File::realpath($this->conf['script']);
	}

	if (Curl::check($this->conf['url'])) {
		if ($this->alive()) {
			return;
		}

		if (!$restart) {
			throw new Exception('php_server is already running', "pid=".$this->getPid());
		}

		$this->stop();
	}

	$this->run();
}


/**
 * Return pid
 */
public function getPid(int $wait = 0) : int {
	$server = $this->conf['host'].':'.$this->conf['port'];
	$pid_file = empty($this->conf['log_dir']) ? '' :
		$pid_file = $this->conf['log_dir'].'/'.$server.'.pid';

	$pid = File::exists($pid_file) ? intval(File::load($pid_file)) : 0;
	
	if ($pid) {
		$has_pid = posix_getpgid($pid);

		if (!$has_pid) {
			clearstatcache(true, '/proc/'.$pid);
			$has_pid = file_exists('/proc/'.$pid);
		}

		if (!$has_pid && $wait) {
			sleep($wait);
			$has_pid = file_exists('/proc/'.$pid);
		}

		return ($has_pid && $pid > 79) ? $pid : 0;
	}

	// find pid in process list
	$ps = execute("ps aux | grep 'php -S $server' | grep -v grep", null, 0);
	if (preg_match('/^.+? +([0-9]+) +/', $ps, $match)) {
		$pid = intval($match[1]);
	}

	return (intval($pid) > 79) ? $pid : 0;
}


/**
 *
 */
private function run() : void {
	$root = realpath($this->conf['docroot']); 
	$script = empty($this->conf['script']) ? '' : " '".$this->conf['script']."'";
	$server = $this->conf['host'].':'.$this->conf['port'];
	$log = '';
	$pid = '';

	if (!empty($this->conf['log_dir'])) {
		$log_file = $this->conf['log_dir'].'/'.$server.'.log';
		$log = " 2>$log_file >$log_file";
		$pid = ' & echo $! >'."'".$this->conf['log_dir']."/$server.pid'";
	}

	$cmd = "php -t '$root' -S ".$server.$script.$log.$pid;
	execute($cmd);

	if (!empty($this->conf['ssl'])) {
		File::exists('/usr/bin/stunnel3', true);
		execute('stunnel3 -d '.$this->conf['ssl'].' -r '.$this->conf['port']); 
	}

	if (($pid = $this->getPid(2)) == 0) {
		throw new Exception('empty pid', $cmd);
	}

	if (!empty($this->conf['log_dir'])) {
		$this->conf['cmd'] = $cmd;
		$this->conf['pid'] = $pid;
		File::saveJSON($this->conf['log_dir'].'/'.$server.'.json', $this->conf);
	}
}

}

