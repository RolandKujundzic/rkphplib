<?php

namespace rkphplib\lib;

$pdir = dirname(__DIR__);
require_once $pdir.'/File.class.php';
require_once $pdir.'/Curl.class.php';

use rkphplib\Exception;
use rkphplib\File;
use rkphplib\Curl;


/**
 * Start standalone php server in background.
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @hash $opt …
 * script: required
 * port: required or use host=host:port
 * pid: required e.g. /tmp/php_server.pid
 * host: 0.0.0.0 (=default) or localhost
 * docroot: '.'
 * log: optional e.g. /tmp/php_server.log
 * running: kill (= default), stop (= only kill), check (return true|false) or abort
 * @eol
 */
function php_server(array $opt) : bool {
	$default = [
		'script' => '',
		'port' => '',
		'host' => '0.0.0.0',
		'docroot' => '.',
		'pid' => '',
		'log' => '',
		'running' => 'kill'
	];

	$conf = array_merge($default, $opt);
	$conf['docroot'] = realpath($conf['docroot']);
	$conf['script'] = File::realpath($conf['script']);

	if (strpos($conf['host'], ':') > 0) {
		list ($conf['host'], $conf['port']) = explode(':', $conf['host']);
	}

	$required = [ 'script', 'port', 'pid', 'host', 'docroot', 'running' ];
	foreach ($required as $key) {
		if (empty($conf[$key])) {
			throw new Exception('invalid (empty) parameter '.$key);
		}
	}

	if (!file_exists($conf['pid'])) {
		throw new Exception('missing pid file', $conf['pid']);
	}
	else {
		$pid = intval(file_get_contents($conf['pid']));
		if (is_dir('/proc/'.$pid)) {
			if ($conf['running'] == 'abort') {
				throw new Exception('php_server is already running', "pid=$pid");
			}

			exec('kill -9 '.$pid);
			sleep(1);
			clearstatcache(true, '/proc/'.$pid);
			if (is_dir('/proc/'.$pid)) {
				throw new Exception('kill php_server failed', 'kill -9 '.$pid);
			}
		}
	}

	if ($conf['running'] == 'stop') {
		return true;
	}

	$log = empty($conf['log']) ? '' : " 2>'".$conf['log']."' >'".$conf['log']."'";
	$cmd = 'php -S '.$conf['host'].':'.$conf['port']." '".$conf['script'].'"'.$log.' & echo $! >"'.$conf['pid']."'";
	if (system($cmd) === false) {
		throw new Exception('php_server start failed', $cmd);
	}

	sleep(2);
	$pid = intval(file_get_contents($conf['pid']));
	clearstatcache(true, '/proc/'.$pid);
	if (!is_dir('/proc/'.$pid)) {
		throw new Exception('php_server start failed - missing /proc/'.$pid, $cmd);
	}

	return true;
}


