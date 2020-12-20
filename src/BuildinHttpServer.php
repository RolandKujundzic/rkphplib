<?php

namespace rkphplib;

require_once __DIR__.'/AServerJob.php';

use function rkphplib\lib\execute;


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

	if (!empty($conf['script'])) {
		$this->conf['script'] = File::realpath($this->conf['script']);
	}

	if (empty($this->conf['docroot']) && !empty($this->conf['script'])) {
		$this->conf['docroot'] = dirname($this->conf['script']);
	}

	if (empty($this->conf['docroot'])) {
		throw new Exception('empty docroot');
	}

	$this->conf['docroot'] = realpath($this->conf['docroot']);
	Dir::exists($this->conf['docroot'], true);

	$this->conf['process'] = 'php -t '.$this->conf['docroot'].' -S '.$this->conf['server'];

	$this->prepare();
}


/**
 * Return true if sever is running and configuration is same
 */
public function alive() : bool {
	if (!File::exists($this->env['file']['json'])) {
		return false;
	}

	if (!$this->checkHttp(2) || !($pid = $this->getPid())) {
		return false;
	}

	$config = File::loadJSON($this->env['file']['json']);
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

	if (is_dir('/proc/'.$pid)) {
		clearstatcache(true, '/proc/'.$pid);
		sleep(1);
		if (is_dir('/proc/'.$pid)) {
			throw new Exception('kill '.$this->conf['process'].' failed', 'kill -9 '.$pid);
		}
	}

	foreach ($this->env['file'] as $log) {
		File::remove($log, false);
	}
}


/**
 * Start server. Wait until server is reachable,
 * abort after 3 sec.
 */
public function start(bool $restart = true, $wait = 3) : void {
	if ($this->checkProcess() && !$restart) {
		return;
	}

	if ($this->checkHttp()) {
		if ($this->alive()) {
			return;
		}

		if (!$restart) {
			throw new Exception($this->conf['process'].' is already running', 'pid='.$this->getPid());
		}

		$this->stop();
	}

	$this->run();

	$alive = $this->checkHttp();
	$n = 0;

	while ($wait > 0 && $n < $wait && !$alive) {
		$alive = $this->checkHttp();
		sleep(1);
		$n++;
	}

	if (!$alive) {
		throw new Exception($this->conf['server'].' no response');
	}
}


/**
 *
 */
private function run() : void {
	$script = empty($this->conf['script']) ? '' : " '".$this->conf['script']."'";
	$log_file = "'".$this->env['file']['log']."'";
	$log = " 2>$log_file >$log_file";
	$pid = ' & echo $! >'."'".$this->env['file']['pid']."'";

	$cmd = $this->conf['process'].$script.$log.$pid;
	execute($cmd);

	if (!empty($this->conf['ssl'])) {
		File::exists('/usr/bin/stunnel3', true);
		execute('stunnel3 -d '.$this->conf['ssl'].' -r '.$this->conf['port']); 
	}

	if (($pid = $this->getPid(2)) == 0) {
		throw new Exception('empty pid', $cmd);
	}

	$this->conf['cmd'] = $cmd;
	$this->conf['pid'] = $pid;
	File::saveJSON($this->env['file']['json'], $this->conf);
}

}

