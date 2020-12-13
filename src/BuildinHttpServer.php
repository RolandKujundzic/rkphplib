<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Curl.class.php';
require_once __DIR__.'/execute.php';


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
 * log_dir: optional, if set create log_dir/php_server.[port.json|err|log]
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
 * Return true if server is running
 */
public function check() : bool {
	return Curl::check($this->conf['url']);
}


/**
 * Kill server
 */
public function stop() : void {
	if (($pid = $this->getPid(1)) == 0) {
		return;
	}

	execute('kill -9 '.$pid);

	if (is_dir('/proc/'.$pid)) {
		clearstatcache(true, '/proc/'.$pid);
		sleep(1);
		if (is_dir('/proc/'.$pid)) {
			throw new Exception('kill php_server failed', 'kill -9 '.$pid);
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
	$config = empty($this->conf['log_dir']) ? '' : 
		$this->conf['log_dir'].'/'.$server.'.json';

	$pid = 0;
	if (File::exists($config)) {
		$json = File::loadJSON($config);

		if ($server == $json['host'].':'.$json['port']) {
			if (empty($json['pid'])) {
				throw new Exception('invalid pid in configuration file', $config);
			}

			$pid = $json['pid'];
		}
	}

	if ($pid) {
		$has_pid =  posix_getpgid($pid);

		if (!$has_pid) {
			clearstatcache(true, '/proc/'.$pid);
			$has_pid = file_exists('/proc/'.$pid);
		}

		if (!$has_pid && $wait) {
			sleep($wait);
			$has_pid = file_exists('/proc/'.$pid);
		}

		return $has_pid;
	}

	// find pid in process list
	system("ps aux | grep 'php -S $server' | grep -v grep", $pid);
	return intval($pid) > 79;
}


/**
 *
 */
private function run() : void {
	$root = realpath($this->conf['docroot']); 
	$cwd = $root == getcwd() ? '' : "cd '$root' && ";
	$script = empty($this->conf['script']) ? '' : " '{$this->conf['script']}'";
	$server = $this->conf['host'].':'.$this->conf['port'];
	$log = '';
	$pid = '';

	if (!empty($this->conf['log_dir'])) {
		$log_file = $this->conf['log_dir'].'/php_server.log';
		$log = " 2>$log_file >$log_file";
		$pid = ' & echo $! >'."'{$conf['pid']}'";
	}

	$cmd = $cwd.'php -S '.$server.$script.$log.$pid;
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

