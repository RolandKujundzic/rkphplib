<?php

namespace rkphplib;

require_once(__DIR__.'/JSON.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/Dir.class.php');
require_once(__DIR__.'/lib/ps.php');
require_once(__DIR__.'/lib/execute.php');
require_once(__DIR__.'/lib/kv2conf.php');

use \rkphplib\JSON;
use \rkphplib\File;
use \rkphplib\Dir;



/**
 * Shell job wrapper.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright Roland Kujundzic 2018
 * @date 2018/11/01
 *
 */
class Job {

/** @var hash $conf */
private $conf = [];



/**
 * Create job. Job options:
 *
 * - name: required, use as logfile name
 * - execute: optional, e.g. [path/to/executable param1 param2]
 * - docker: optional docker image name and parameter, e.g. [ubuntu:latest]. 
 *		If set, run command in docker container e.g. [bash -c "shell command"]
 * - user: optional user id (if set used in logfile name)
 * - action: optional action (if set used in logfile name)
 * - logfile: optional (default: data/log/job/yyyymmdd/NAME[.USER][.ACTION].log)
 * - lockfile: auto - data/log/job/yyyymmdd/NAME[.USER][.ACTION].lock)
 * - file_mode: 0 (e.g. 0666)
 * - dir_mode: 0 (e.g. 0777)
 * - pid: 0 (set after run - always run as background job)
 * - status: done|continue|...
 * - progress: 0
 * - last_progress: if lockfile.last.json exists >= 0
 * - message:
 * - error: 
 *
 * If old lockfile exists status must be done (move lockfile to lockfile.done) or 
 * continue (move lockfile to lockfile.old) otherwise throw exception.
 *
 * @throws
 * @param hash $options
 */
public function __construct($options) {
	$required = [ 'name' ];

	foreach ($required as $key) {
		if (empty($options[$key])) {
			throw new Exception("missing parameter $key");
		}	
	}

	$default = [
		'last_progress' => '',
		'file_mode' => 0,
		'dir_mode' => 0
		];

	$this->conf = array_merge($default, $options);

	$file_prefix = 'data/log/job/'.date('Ymd');
	$file_suffix = '';

	if (!empty($this->conf['pid'])) {
		$file_suffix .= '.'.$this->conf['pid'];
	}

	if (!empty($this->conf['action'])) {
		$file_suffix .= '.'.$this->conf['action'];
	}

	if (empty($this->conf['logfile'])) {
		$this->conf['logfile'] = $file_prefix.'/'.$this->conf['name'].$file_suffix.'.log';
	}

	if (empty($this->conf['lockfile'])) {
		$this->conf['lockfile'] = $file_prefix.'/'.$this->conf['name'].$file_suffix.'.lock';
	}

	Dir::create(dirname($this->conf['logfile']), $this->conf['dir_mode'], true);
	Dir::create(dirname($this->conf['lockfile']), $this->conf['dir_mode'], true);

	if (File::exists($this->conf['lockfile'])) {
		$old_conf = $this->loadLock([ 'done', 'continue' ]);
		$this->conf['last_progress'] = $old_conf['progress'];	
	}

	$this->updateLock([ 'status' => 'prepare', 'since' => microtime(), 'progress' => 0 ]);
}


/**
 * Return configuration key. Access old keys with old.NAME.
 * 
 * @param string $name
 * @return 
 */
public function get($name) {
	if (!isset($this->conf[$name])) {
		throw new Exception('no suche conf key '.$name);
	}

	return $this->conf[$name];
}


/**
 * Start job in background according to conf. Update lock file.
 * 
 * @throws
 */
public function run() {
	$cmd = empty($this->conf['docker']) ? $this->conf['execute'] : 'docker run -rm '.$this->conf['docker'].' '.$this->conf['execute'];

	$cmd .= ' && echo $! > "'.$this->conf['logfile'].'" 2>&1 &';

	$lock_keys = array_keys($this->conf);
	$lock = [];

	foreach ($lock_keys as $key) {
		$lock[$key] = $this->conf[$key];
	}

	$lock['cmd'] = $cmd;
	$lock['start'] = microtime();
	$lock['status'] = 'start';

	try {
		$lock['pid'] = \rkphplib\lib\execute($cmd);

		$ps = \rkphplib\lib\ps($lock['pid']);
		if (!isset($ps['PID']) || $ps['PID'] != $lock['pid']) {
			throw new Exception('could not determine pid', "ps: ".print_r($ps, true)."\nlock: ".print_r($lock, true));
		}

		$lock['status'] = 'running';
	}
	catch (\Exception $e) {
		$lock['status'] = 'start_failed';
		$this->updateLock($lock);
		throw $e;
	}

	$this->updateLock($lock, [ 'prepare' ]);
}


/**
 * Create|Update lockfile. 
 *
 * Common Keys
 *
 * message: 
 * error:
 * status: 
 *
 * Keys set in construct
 *
 * progress: 0
 * status: prepare
 * since:
 *
 * Keys set in run
 *
 * status: running|start_failed
 * cmd: 
 * start: 
 * pid: 
 *
 * @throws
 * @param hash $p
 */
public function updateLock($p, $allow_status = []) {

	$current = $this->loadLock($allow_status);
	$current['date'] = microtime();

	$conf = array_merge($current, $p);

	$conf['progress']++;

	File::save_rw($this->conf['lockfile'], JSON::encode($conf), $this->conf['file_mode']);
}


/**
 * Load lockfile. If allow_status is set and current status is not allowed throw exception.
 *
 * @throws
 * @param vector $allow_status (default = [])
 * @return hash
 */
public function loadLock($allow_status = []) {
	$conf = [ 'status' => '' ];

	if (File::exists($this->conf['lockfile'])) {
		$conf = JSON::decode(File::load($this->conf['lockfile']));	
	}

	if (count($allow_status) > 0 && !in_array($conf['status'], $allow_status)) {
		throw new Exception('unexpected status '.$conf['status'].' in lock file', 'lock_file='.$this->conf['lockfile'].
			' allow_status='.join(', ', $allow_status));
	}
	
	return $conf;
}


}

