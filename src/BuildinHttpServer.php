<?php

namespace rkphplib;

require_once __DIR__.'/AServerJob.php';


/**
 * Start|Stop|Check Buildin PHP Http Server
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
class BuildinHttpServer extends AServerJob {

/**
 * Set webserver options.
 * @hash $opt …
 * host: 0.0.0.0 (required)
 * docroot: required for start if empty use dirname(script)
 * script: optional (required for start)
 * port: required unless $host=HOST:PORT
 * log_dir: required
 * ssl: 0, > 0 = run stunnel from ssl to port
 * @eol
 * @see ServerJob::__construct()
 */
public function __construct(array $opt = []) {
	$default = [
		'host' => '0.0.0.0',
		'docroot' => '.',
		'script' => '',
		'port' => 0,
		'ssl' => 0
	];

	$this->conf = array_merge($default, $opt);

	if (strpos($this->conf['host'], ':') > 0) {
		list ($this->conf['host'], $this->conf['port']) = explode(':', $this->conf['host']);
	}

	if (!preg_match('/^[a-z0-9A-Z\.\-]+$/', $this->conf['host'])) {
		throw new Exception('invalid host '.$this->conf['host']);
	}

	if ($this->conf['port'] < 80) {
		throw new Exception('invalid port '.$this->conf['port']);
	}

	$this->conf['server'] = $this->conf['host'].':'.$this->conf['port'];
	$this->conf['url'] = 'http://'.$this->conf['server'];

	if ($this->conf['ssl'] > 0) {
		$this->conf['server'] = $this->conf['host'].':'.$this->conf['ssl'];
		$this->conf['url'] = 'https://'.$this->conf['server'];
		$this->conf['port'] = $this->conf['ssl'];
	}

	$this->prepare();
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
 * Return true if sever is running and configuration is same
 */
public function alive() : bool {
	if (!$this->checkHttp(2) || !($pid = $this->getPid())) {
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

	$process = 'php -t /home/rk/workspace/php/rkphplib/test -S 0.0.0.0:15081';
	if (!$this->checkProcess($process)) {
		throw new Exception('run: '.$process);
	}
	else {
		return;
	}

	// ToDo: 
	if ($this->checkHttp()) {
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

