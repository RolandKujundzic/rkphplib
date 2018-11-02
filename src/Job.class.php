<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once($parent_dir.'/Exception.class.php');
require_once($parent_dir.'/JSON.class.php');
require_once($parent_dir.'/File.class.php');
require_once($parent_dir.'/Dir.class.php');
require_once($parent_dir.'/lib/ps.php');
require_once($parent_dir.'/lib/execute.php');
require_once($parent_dir.'/lib/kv2conf.php');

use \rkphplib\Exception;
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
 * - execute: required, e.g. [path/to/executable param1 param2]
 * - docker: optional docker image name and parameter, e.g. [ubuntu:latest]. 
 *		If set, run command in docker container e.g. [bash -c "shell command"]
 * - user: optional user id (if set used in logfile name)
 * - action: optional action (if set used in logfile name)
 * - logfile: optional (default: data/log/job/yyyymmdd/NAME[.USER][.ACTION].log)
 * - lockfile: auto - data/log/job/yyyymmdd/NAME[.USER][.ACTION].lock)
 * - file_mode: 0 (e.g. 0666)
 * - dir_mode: 0 (e.g. 0777)
 * - pid: 0 (set after run - always run as background job)
 * - status: ''
 * - progress: 0
 * - last_progress: if lockfile.last.json exists >= 0
 * - message:
 * - error: 
 *
 * @throws
 * @param hash $options
 */
public function __construct($options) {
	$required = [ 'name', 'execute' ];

	foreach ($required as $key) {
		if (empty($options[$key])) {
			throw new Exception("missing parameter $key");
		}	
	}

	$default = [
		'last_progress' => '',
		'progress' => 0,
		'message' => '',
		'status' => '',
		'error' => '',
		'file_mode' => 0,
		'dir_mode' => 0
		];

	$this->conf = array_merge($default, $options);

	$file_prefix = 'data/log/job/'.date('ymd');
	$file_suffix = '';

	if (!empty($this->conf['pid'])) {
		$file_suffix .= '.'.$this->conf['pid'];
	}

	if (!empty($this->conf['action'])) {
		$file_suffix .= '.'.$this->conf['action'];
	}

	if (!isset($this->conf['logfile'])) {
		$this->conf['logfile'] = $file_prefix.'/'.$this->conf['name'].$file_suffix.'.log';
	}

	if (!isset($this->conf['logfile'])) {
		$this->conf['logfile'] = $file_prefix.'/'.$this->conf['name'].$file_suffix.'.log';
	}

	$this->conf['lockfile'] = $file_prefix.'/'.$this->conf['name'].$file_suffix.'.json';

	Dir::create(dirname($this->conf['logfile']), $this->conf['dir_mode'], true);
	Dir::create(dirname($this->conf['lockfile']), $this->conf['dir_mode'], true);
}


/**
 * Start job in background according to conf. Create lock and log file.
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
	$lock['start'] => microtime()
	$lock['status'] = 'start';

	try {
		$lock['pid'] = \rkphplib\lib\execute($cmd);

		$ps = \rkphplib\lib\ps($lock['pid']);
		if (!isset($ps['PID']) || $ps['PID'] != $lock['pid']) {
			throw new Exception('could not determine pid', "ps: ".print_r($ps, true)."\nlock: ".print_r($lock, true));
		}

		$lock['status'] = 'running';
	}
	catch ($e) {
		$lock['status'] = 'start_failed';
		$this->updateLock($lock);
		throw $e;
	}

	$this->updateLock($lock);
}


/**
 * Create|Update lockfile.
 *
 * @throws
 * @param hash $p
 */
public function updateLock($p = []) {
	if (empty($p['lockfile'])) {
		throw new Exception('missing key lockfile');
	}

	$mode = isset($p['file_mode']) ? $p['file_mode'] : $this->conf['file_mode'];
	File::save_rw($p['lockfile'], JSON::encode($p), $mode);
}


/**
 * Load lockfile.
 *
 * @throws
 * @param hash $p
 * @return false|hash
 */
public function loadLock() {
	if (empty($this->conf['lockfile'])) {
		throw new Exception('missing key lockfile');
	}

	if (!File::exists($this->conf['lockfile'])) {
		return false;
	}

	return JSON::decode(File::load($this->conf['lockfile']));	
}


}

