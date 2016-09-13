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
protected $conf = [ 'name' => '', 'scope' => 'docroot', 'inactive' => 7200, 'ttl' => 172800 ];


/**
 * Initialize session metadata. Only on first session start. Parameter:
 *
 *  script: $_SERVER['SCRIPT_FILENAME']
 *  docroot: $_SERVER['DOCUMENT_ROOT']
 *  host: $_SERVER['HTTP_HOST']
 *  start: time()
 *  last: time()
 *
 */
public function initMeta() {

	if ($this->hasMeta('start')) {
		return;
	}

	$script = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
	$this->setMeta('script', $script);

	$docroot = empty($_SERVER['DOCUMENT_ROOT']) ? '' : $_SERVER['DOCUMENT_ROOT'];
	$this->setMeta('docroot', $docroot);

	$host = empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST'];
	$this->setMeta('host', $host);

	$this->setMeta('start', time());
	$this->setMeta('last', time());
}


/**
 * Set session configuration. Use $conf = [] to initialize. Required Parameter:
 *
 *  name: Session Name - required
 *  scope: file|dir|subdir|host|docroot (default = docroot)
 *  inactive: seconds of inactivity. Session expires after lchange + inactive. Range [1-14400] (default = 7200 = 2 h)
 *	ttl: time to live in seconds. Session expires after start + ttl. Range [1, 345600] (default = 172800 = 48 h)
 * 
 *  Check inactive and ttl with hasExpired().
 *
 * @throws rkphplib\Exception if check fails
 * @param map $conf
 */
protected function setConf($conf) {

	$this->conf = [ 'name' => '', 'scope' => 'docroot', 'inactive' => 7200, 'ttl' => 172800 ];

	if (empty($conf['name'])) {
		throw new Exception('name is empty');
	}

	$this->conf['name'] = $conf['name'];

	if (isset($conf['scope'])) {
		$allow_scope = array('file', 'dir', 'subdir', 'host', 'docroot');
		if (!in_array($conf['scope'], $allow_scope)) {
			throw new Exception('no such scope', $conf['scope']);
		}

		$this->conf['scope'] = $conf['scope'];
	}

	$time_keys = [ 'inactive' => [1, 21600], 'ttl' => [1, 345600] ];
	foreach ($time_keys as $key => $range) {
		if (isset($conf[$key])) {
			$sec = intval($conf[$key]);

			if ($sec < $range[0] || $sec > $range[1]) {
				throw new Exception("parameter $key outside of range", $sec." not in [".$range[0].",".$range[1]."]");
			}

			$this->conf[$key] = $conf[$key];
		}
	}
}


/**
 * Return true if scope is valid.
 *
 * @throws if initMeta was not called
 * @return bool
 */
public function validScope() {

	$script = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
	$host = empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST'];
	$docroot = empty($_SERVER['DOCUMENT_ROOT']) ? '' : $_SERVER['DOCUMENT_ROOT'];
	$ok = false;

	switch ($this->conf['scope']) {
		case 'file':
			if ($script == $this->getMeta('script')) {
				$ok = true;
			}
			break;
		case "dir":
			if (dirname($script) == dirname($this->getMeta('script')) {
				$ok = true;
			}
			break;
		case "subdir":
			if (mb_strpos($script, dirname($this->getMeta('script')) === 0) {
				$ok = true;
			}
			break;
		case "host":
			if ($host == $this->getMeta('host')) {
				$ok = true;
			}
			break;
		case 'docroot':
			if ($docroot == $this->getMeta('docroot')) {
				$ok = true;
			}
			break;
	}

	return $ok;
}


/**
 * Return expiration reason if session has become invalid. Update lchange.
 * 
 * @throws if initMeta was not called
 * @return string ttl|inactive|empty = session is valid
 */
public function hasExpired() {

	$now = time();
	$expire_reason = '';

	if ($now - $this->getMeta('start') > $this->conf['ttl']) {
		$expire_reason = 'ttl';
	}
	else if ($now - $this->getMeta('last_change') > $this->conf['inactive']) {
		$expire_reason = 'inactive';
	}
	else {
		$this->setMeta('last_change', time());
	}

	return $expire_reason;
}


/**
 * Return session key. Key is md5(conf.name:conf.scope).
 *
 * @throws if setConf was not called
 * @return string
 */
public function getSessionKey() {

	if (empty($this->conf['name'])) {
		throw new Exception('call setConf first');
	}

	return md5($this->conf['name'].':'.$this->conf['scope']);
}


/**
 * Initialize session.
 *
 * @param map $conf (default = array())
 * @throws rkphplib\Exception if error
 * @see setConf
 * @return map
 */
abstract public function init($conf = array());


/**
 * Set session metadata.
 *
 * @param string $key
 * @param any $value
 */
abstract public function setMeta($key, $value);


/**
 * Get session metadata.
 * 
 * @throws if key is not set
 * @param string $key
 * @return any
 */
abstract public function getMeta($key);


/**
 * True if session metadata key exists.
 * 
 * @param string $key
 * @return bool
 */
abstract public function hasMeta($key);


/**
 * Destroy session.
 */
abstract public function destroy();

}
