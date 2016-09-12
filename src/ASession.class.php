<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;


/**
 * Abstract Session wrapper class.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ASession {

/** @var map $conf */
protected $conf = [ 'name' => 'ASession',
	'type' => 'ASession',
	'host' => '',
	'script' => '',
	'docroot' => '',
	'scope' => 'docroot',
	'ttl' => 7200,
	'expire' => 3600,
	'max_duration' => 172000,
	'start' => 0,
	'lchange' => 0,
	'reload' => 0 ];


/**
 * Set session configuration. Use empty map as $conf to initialize. Parameter:
 *
 *  name: Session Name (default = ASession) - required
 *	type: login type/group (default = ASession) - required
 *  scope: file|dir|subdir|host|docroot (default = docroot)
 *  ttl: expiration date increase [1-14400] after activity (default = 7200 sec = 2 h)
 *  expire: expiration after inactivity [1-14400] (default = 3600 = 1 h)
 *	max_duration: maximum session duration [1, 345600] (default = 172000 = 4 h)
 * 
 *  Internal Parameter (set when first called):
 *
 *  script: $_SERVER[SCRIPT_FILENAME]
 *  host: $_SERVER[HTTP_HOST]
 *  docroot: $_SERVER[DOCUMENT_ROOT]
 *  start: now()
 *  lchange: now()
 *  reload: now()
 *
 * @throws rkphplib\Exception if check fails
 * @param map $conf
 */
public function setConf($conf) {

	$default = [ 'name' => 'ASession', 'type' => 'ASession', 'scope' => 'docroot', 'ttl' => 7200, 'expire' => 3600, 'max_duration' => 172000 ];
	$default['script'] = $_SERVER['SCRIPT_FILENAME'];
	$default['docroot'] = $_SERVER['DOCUMENT_ROOT'];
	$default['host'] = $_SERVER['HTTP_HOST'];
	$default['start'] = time();
	$default['reload'] = time();
	$default['lchange'] = time();

	foreach ($default as $key => $value) {
		if (empty($this->conf[$key])) {
			$this->conf[$key] = $value;
		}
	}

	if (isset($conf['scope'])) {
		$allow_scope = array('file', 'dir', 'subdir', 'host', 'docroot');
		if (!in_array($conf['scope'], $allow_scope)) {
			throw new Exception('no such scope', $conf['scope']);
		}

		$this->conf['scope'] = $conf['scope'];
	}

	$key_list = [ 'name', 'type' ];
	foreach ($key_list as $key) {
		if (isset($conf[$key])) {
			if (empty($conf[$key])) {
				throw new Exception($key.' is empty');
			}

			$this->conf[$key] = $conf[$key];
		}
	}

	$time_keys = [ 'ttl' => [1, 14400], 'expire' => [1, 21600], 'max_duration' => [1, 345600] ];
	foreach ($time_keys as $key => $range) {
		if (isset($conf[$key])) {
			$sec = intval($conf[$key]);

			if ($sec < $range[0] || $sec > $range[1]) {
				throw new Exception("parameter outside range", $sec." not in [".$range[0].",".$range[1]."]");
			}

			$this->conf[$key] = $conf[$key];
		}
	}
}


/**
 * Return session key. Key is md5 value and depends on conf.scope and conf.name.
 * If scope and name is empty it is md5(ASession:any).
 *
 * @return string
 */
public function getSessionKey() {
	$scope = '';

  switch ($this->conf['scope']) {
		case 'file':
      if ($_SERVER['SCRIPT_FILENAME'] == $this->conf['script']) {
				$scope = md5($_SERVER['SCRIPT_FILENAME']);	
			}
			break;
		case "dir":
			if (dirname($_SERVER['SCRIPT_FILENAME']) == dirname($this->conf['script'])) {
				$scope = md5(dirname($_SERVER['SCRIPT_FILENAME']));
			}
			break;
		case "subdir":
			if (mb_strpos($_SERVER['SCRIPT_FILENAME'], dirname($this->conf['script'])) === 0) {
				$scope = md5(dirname($this->conf['script']).'/*');
			}
			break;
		case "host":
			if ($this->conf['host'] == $_SERVER['HTTP_HOST']) {
				$scope = md5($this->conf['host']);
			}
			break;
		case 'docroot':
			if ($this->conf['docroot'] == $_SERVER['DOCUMENT_ROOT']) {
				$scope = md5($this->conf['docroot'].'/*');
			}
			break;
	}

	if (empty($scope)) {
		throw new Exception('invalid scope', print_r($this->conf, true));
	}

	return md5($this->conf['name'].':'.$this->conf['type'].':'.$scope);
}


/**
 * Initialize session.
 *
 * Call setConf() or use $conf for custom session.
 *
 * @param map $conf (default = array())
 * @throws rkphplib\Exception if error
 * @see setConf
 * @return map
 */
abstract public function init($conf = array());


/**
 * Destroy session.
 */
abstract public function destroy();

}
