<?php

namespace rkphplib\lib;

$pdir = dirname(__DIR__);
require_once $pdir.'/File.class.php';
require_once $pdir.'/Curl.class.php';
require_once __DIR__.'/execute.php';

use rkphplib\Exception;
use rkphplib\File;
use rkphplib\Curl;


/**
 * Start standalone php server in background.
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @hash $opt …
 * script: optional
 * port: required or use host=host:port
 * pid: required e.g. /tmp/php_server.pid
 * host: 0.0.0.0 (=default) or localhost
 * docroot: required, if empty use dirname(script)
 * log: optional e.g. /tmp/php_server.log
 * running: kill (= default), stop (= only kill), check (return true|false) or abort
 * ssl: 0, > 0 = run stunnel from ssl to port
 * @eol
 */
function php_server(array $opt) : bool {
	$conf = _php_server_getopt($opt);

	if (Curl::check($url)) {
		if ($conf['running'] == 'check') {
			return true;
		}

		if ($conf['running'] == 'abort') {
			throw new Exception('php_server is already running', "pid=$pid");
		}

		if (!file_exists($conf['pid'])) {
			throw new Exception('missing pid file - kill server on port '.$port, $conf['pid']);
		}

		$pid = intval(file_get_contents($conf['pid']));
		if (is_dir('/proc/'.$pid)) {
			execute('kill -9 '.$pid);
			sleep(1);
			clearstatcache(true, '/proc/'.$pid);
			if (is_dir('/proc/'.$pid)) {
				throw new Exception('kill php_server failed', 'kill -9 '.$pid);
			}
		}
	}
	else if ($conf['running'] == 'check') {
		return false;
	}

	if ($conf['running'] == 'stop') {
		return true;
	}

	$cwd = realpath($conf['docroot']) == getcwd() ? '' : "cd '{$conf['docroot']}' && ";
	$script = empty($conf['script']) ? '' : " '{$conf['script']}'";
	$log = empty($conf['log']) ? '' : " 2>'".$conf['log']."' >'".$conf['log']."'";
	$cmd = $cwd.'php -S '.$conf['host'].':'.$conf['port'].$script.$log.' & echo $! >'."'{$conf['pid']}'";
	execute($cmd);

	if (!empty($conf['ssl'])) {
		if (!file_exists('/usr/bin/stunnel3')) {
			throw new Exception('missing /usr/bin/stunnel3 - try: apt install stunnel4');
		}

		execute('stunnel3 -d '.$conf['ssl'].' -r '.$conf['port']); 
	}

	sleep(2);
	$pid = intval(file_get_contents($conf['pid']));
	clearstatcache(true, '/proc/'.$pid);
	if (!is_dir('/proc/'.$pid)) {
		throw new Exception('php_server start failed - missing /proc/'.$pid, $cmd);
	}

	if (!empty($conf['log'])) {
		$conf['cmd'] = $cmd;
		$conf['pid'] = $pid;
		File::saveJSON(str_replace('.log', '.json', $conf['log']), $conf);
	}

	return true;
}


/**
 * Check options and return modified configuration.
 */
function php_server_getopt(array $opt) : array {
	$default = [
		'script' => '',
		'port' => '',
		'host' => '0.0.0.0',
		'docroot' => '',
		'pid' => '',
		'log' => '',
		'running' => 'kill',
		'ssl' => 0
	];

	$conf = array_merge($default, $opt);
	$conf['docroot'] = realpath($conf['docroot']);

	if (!empty($conf['script'])) {
		$conf['script'] = File::realpath($conf['script']);
	}

	if (empty($conf['docroot']) && !empty($conf['script'])) {
		$conf['docroot'] = dirname($conf['script']);
	}

	if (strpos($conf['host'], ':') > 0) {
		list ($conf['host'], $conf['port']) = explode(':', $conf['host']);
	}

	if (empty($conf['running']) || !in_array($conf['running'], [ 'abort', 'check', 'kill', 'stop' ])) {
		throw new Exception('invalid parameter running - use abort|check|kill|stop');
	} 

	$required  = [ 'port', 'host' ];
	if ($conf['running'] !== 'check') {
		array_push($required, 'pid');
	}

	if ($conf['running'] !== 'stop') {
		array_push($required, 'docroot');
	}

	foreach ($required as $key) {
		if (empty($conf[$key])) {
			throw new Exception('invalid (empty) parameter '.$key);
		}
	}

	$url = 'http://'.$conf['host'].':'.$conf['port'];
	$port = $conf['port'];

	if ($conf['ssl'] > 0) {
		$url = 'https://'.$conf['host'].':'.$conf['ssl'];
		$port = $conf['ssl'];
	}

	$conf['url'] = $url;
	return $conf;
}

