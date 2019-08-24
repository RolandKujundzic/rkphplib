<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
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
 * Start long running shell jobs.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TJob implements TokPlugin {

/** @var Tokenizer $tok */
private $tok = null;

/** @var hash $conf */
private $conf = [];

	
/**
 * Return job plugin.
 */
public function getPlugins(object $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['job'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;

	return $plugin;
}


/**
 * Job action. Parameter:
 *
 * lockfile= path/to/lock.json (required, e.g. DOCROOT/data/.log/job.json)
 * do.remove= lock=remove (use _REQUEST[lock] = "remove" to remove lock file)
 * do.run = run=yes (use _REQUEST[run] = "yes" to start job)
 * execute= bin/import.php
 * zip_dir= path/to/dir
 * zip_file= path/to/file.zip
 * include= bin/include.php
 *
 * Example:
 *
 * {job:}flag_path=data/.log/job_{login:id}{:job}
 *
 * Run execute, zip_dir or include. Lockfile (*.json) Parameter:
 *
 * if= ... (optional - export status=prepare and do nothing if empty)
 * status= prepare|start|running|done|remove
 * message= ...
 * progress=  NNN (increasing integer number)
 * finish= NNN|empty (if not empty = final progress number)
 * start= timestamp (microtime)
 * end= [timestamp] (microtime)
 *
 * Export all lockfile parameter as _REQUEST[job_NAME] e.g. _REQUEST[job_status] = running
 *
 * @param hash $conf
 * @return ''
 */
public function tok_job($conf) {
	$default = [ 'do.run' => 'run=yes', 'do.remove' => 'lock=remove' ];
	// \rkphplib\lib\log_debug("TJob.tok_job> default: ".print_r($default, true)." conf: ".print_r($conf, true));
	$this->conf = array_merge($default, $conf);  

	if (isset($this->conf['if']) && empty($this->conf['if'])) {
		$_REQUEST['job_status'] = 'prepare';
		return;
	}

	$required = [ 'lockfile', 'do.run', 'do.remove' ];
	foreach ($required as $key) {
		if (empty($this->conf[$key])) {
			throw new Exception('missing required parameter '.$key);
		}
	}

	$lockfile = $this->conf['lockfile'];
	list ($key, $value) = explode('=', $this->conf['do.remove']);
	if (!empty($_REQUEST[$key]) && $_REQUEST[$key] == $value && File::exists($lockfile)) {
		File::move($lockfile, dirname($lockfile).'/'.File::basename($lockfile, true).'.remove.json');
		$_REQUEST['job_status'] = 'remove';
		$_REQUEST['job_message'] = 'lockfile has been removed';
	}

	list ($key, $value) = explode('=', $this->conf['do.run']);
	$run = !empty($_REQUEST[$key]) && $_REQUEST[$key] == $value;

	if (!$this->running() && $run) {
		$this->run();
	}
}


/**
 * Return true if lock file exists. Report job status via _REQUEST[job_*].
 * Move lockfile to *.done.json if status=done. Export _REQUEST[job_timeout]
 * if lock.progress has not changed since last check (> 5sec).
 *
 * @return boolean
 */
private function running() {
	// \rkphplib\lib\log_debug("TJob.running> lockfile=".$this->conf['lockfile']);

	if (!File::exists($this->conf['lockfile'])) {	
		$_REQUEST['job_status'] = 'prepare';
		return false;
	}

	$lock = JSON::decode(File::load($this->conf['lockfile']));
	$res = true;

	if (!isset($lock['progress'])) {
		$lock['progress'] = 0;
	}

	if ($lock['status'] == 'done') {
		File::move($this->conf['lockfile'], File::basename($this->conf['lockfile'], true).'.done.json');
		$res = false;
	}

	$duration = 0;
	if (!empty($lock['last_access'])) {
		list($usec2, $sec2) = explode(" ", $lock['last_access']);
		list($usec1, $sec1) = explode(" ", $lock['start']);
		$duration = (float)$usec2 + (float)$sec2 - (float)$usec1 - (float)$sec1;
	}

	$lock['last_access'] = microtime();
	$lock['progress'] = $lock['progress'];
	self::updateLock($this->conf['lockfile'], $lock);

	foreach ($lock as $key => $value) {
		$_REQUEST['job_'.$key] = $value;
	}
	
	if ($duration > 5) {
		$_REQUEST['job_timeout'] = (int)$duration;
	}

	return $res;
}


/**
 * Execute job according to conf. Either via conf.execute (background shell job), 
 * conf.zip_dir (=path to directory to be zipped, shell job, zip_file=basename_directory.zip) or
 * via conf.include (run php script via include).
 * Create conf.lock file with shell jobs. 
 */
private function run() {
	$cmd = '';

	$bg_pid = ' && echo $! > /dev/null 2>&1 &';

	if (!empty($this->conf['execute'])) {
		$cmd = $this->conf['execute'].$bg_pid;
		$this->lock([ 'execute' => $cmd, 'start' => microtime(), 'status' => 'start' ]);
		$pid = \rkphplib\lib\execute($cmd);
		$ps = \rkphplib\lib\ps($pid);
		if (isset($ps['PID']) && $ps['PID'] == $pid) {
			$this->lock([ 'pid' => $pid, 'status' => 'running' ]);
		}
	}
	else if (!empty($this->conf['zip_dir'])) {
		Dir::exists($this->conf['zip_dir'], true);

		if (empty($this->conf['zip_file'])) {
			throw new Exception('missing parameter zip_file');
		}

		Dir::create(dirname($this->conf['zip_file']), 0777, true);
		$cmd = "cd '".dirname($this->conf['zip_dir'])."' && zip -r '".$this->conf['zip_file']."' '".
			basename($this->conf['zip_dir']).$bg_pid; 
		$this->lock([ 'execute' => $cmd, 'start' => microtime(), 'status' => 'start' ]);
		$pid = \rkphplib\lib\execute($cmd);
		$ps = \rkphplib\lib\ps($pid);
		if (isset($ps['PID']) && $ps['PID'] == $pid) {
			$this->lock([ 'pid' => $pid, 'status' => 'running' ]);
		}
	}
	else if (!empty($this->conf['include'])) {
		$this->lock([ 'start' => microtime(), 'status' => 'start' ]);
		include($this->conf['include']);
		$this->lock([ 'status' => 'done', 'end' => microtime() ]);
		return;
	}
	else {
		throw new Exception('missing exec|exec_zip|include parameter');
	}
}


/**
 * Create|Update lockfile.
 *
 * @param hash $p
 */
public function lock($p = []) {
	$lockfile = $this->conf['lockfile'];

	if (File::exists($lockfile)) {
		$lock = array_merge(JSON::decode(File::load($lockfile)), $p);	
	}
	else {
		$lock = array_merge($this->conf, $p);
		Dir::create(dirname($lockfile), 0777, true);
	}

	File::save_rw($lockfile, JSON::encode($lock));

	foreach ($lock as $key => $value) {
		$_REQUEST['job_'.$key] = $value;
	}
}


/**
 * Update lockfile. Create lockfile if it does not exist.
 * Increment progress counter if message exist.
 *
 * @param string $file
 * @param hash $p
 */
public static function updateLock($file, $p) {
	if (!File::exists($file)) {
		Dir::create(dirname($file), 0777, true);
		$lock = [];
	}
	else {
		$lock = array_merge(JSON::decode(File::load($file)), $p);
	}

	if (!isset($lock['progress'])) {
		$lock['progress'] = 0;
	}

	if (!empty($p['message']) && empty($p['progress'])) {
		$lock['progress']++;
	}

	File::save_rw($file, JSON::encode($lock));
}



}

