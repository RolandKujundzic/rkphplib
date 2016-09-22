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
protected $conf = [];


/**
 * Return javascript code (window.setInterval(...), JQuery required).
 * Implement function php_session_refresh(data) { ... } (data = OK or EXPIRED)
 * and set $on_success = 'php_session_refresh' if you want to track refresh success.
 *
 * @param string $url (urlescaped ajax url)
 * @param string $on_success (default = '')
 * @param int $minutes (default = 10)
 * @return string
 */
public function getJSRefresh($url, $on_success = '', $minutes = 10) {

	$millisec = 60000 * $minutes;
	$success = '';

	if ($on_success) {
		$success = ", success: $on_success";
	}

	$code = <<<END
window.setInterval( function() {
	$.ajax({
		cache: false,
		type: "GET",
			url: "{$url}"
			{$success}
    });
}, {$millisec});
END;

	return $code;
}


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

	if (!empty($this->conf['init_meta'])) {
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

	$this->conf['init_meta'] = 1;
}


/**
 * Get configuration value. Parameter: name, scope, inactive, ttl.
 * 
 * @param string $key
 * @return int|string
 */
public function getConf($key) {

	if (count($this->conf) === 0) {
		throw new Exception('call setConf first');
	}

	if (!isset($this->conf[$key])) {
		throw new Exception('no such configuration key', $key);
	}

	return $this->conf[$key];
}


/**
 * Set session configuration. Use $conf = [] to initialize. Required Parameter:
 *
 *  name: Session Name - required
 *  scope: file|dir|subdir|host|docroot (default = docroot)
 *  inactive: seconds of inactivity. Session expires after lchange + inactive. Range [1-14400] (default = 7200 = 2 h)
 *	ttl: time to live in seconds. Session expires after start + ttl. Range [1, 345600] (default = 172800 = 48 h)
 *  redirect_expired: 
 *  redirect_forbidden:
 * 
 *  Check inactive and ttl with hasExpired().
 *
 * @throws rkphplib\Exception if check fails
 * @param map $conf
 */
protected function setConf($conf) {

	$default = [ 'name' => '', 'scope' => 'docroot', 'inactive' => 7200, 'ttl' => 172800, 'init_meta' => 0, 
		'redirect_expired' => '',  'redirect_forbidden' => '' ];

	foreach ($default as $key => $value) {
		$this->conf[$key] = $value;
	}

	if (empty($conf['name'])) {
		throw new Exception('name is empty');
	}

	$this->conf['name'] = $conf['name'];

	if (isset($conf['scope'])) {
		$allow_scope = array('script', 'dir', 'subdir', 'host', 'docroot');
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
 * @throws if setConf or initMeta was not called
 * @return bool
 */
public function validScope() {

	if (empty($this->conf['scope'])) {
		throw new Exception('call setConf first');
	}

	$script = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
	$host = empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST'];
	$docroot = empty($_SERVER['DOCUMENT_ROOT']) ? '' : $_SERVER['DOCUMENT_ROOT'];
	$ok = false;

	switch ($this->conf['scope']) {
		case 'script':
			if ($script == $this->getMeta('script')) {
				$ok = true;
			}
			break;
		case "dir":
			if (dirname($script) == dirname($this->getMeta('script'))) {
				$ok = true;
			}
			break;
		case "subdir":
			if (mb_strpos(dirname($script), dirname($this->getMeta('script'))) === 0) {
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
	else if ($now - $this->getMeta('last') > $this->conf['inactive']) {
		$expire_reason = 'inactive';
	}
	else {
		$this->setMeta('last', time());
	}

	return $expire_reason;
}


/**
 * Return (meta) session key. Key is md5(conf.name:conf.scope)[_meta].
 *
 * @throws if setConf was not called
 * @param bool $meta (default = true)
 * @return string
 */
public function getSessionKey($meta = false) {

	if (empty($this->conf['name'])) {
		throw new Exception('call setConf first');
	}

	$skey = md5($this->conf['name'].':'.$this->conf['scope']);

	if ($meta) {
		$skey .= '_meta';
	}

	return $skey;
}


/**
 * Initialize session.
 *
 * @param map $conf (default = array())
 * @throws rkphplib\Exception if error
 * @see setConf
 * @return map
 */
abstract public function init($conf);


/**
 * Set session value.
 *
 * @param string $key
 * @param any $value
 */
abstract public function set($key, $value);


/**
 * Set session map. Overwrite existing.
 *
 * @param map<string:any> $key
 * @param any $value
 */
abstract public function setHash($p);


/**
 * Get session value.
 * 
 * @throws if key is not set and required
 * @param string $key
 * @return any
 */
abstract public function get($key, $required = true);


/**
 * Get session hash.
 * 
 * @return map<string:any>
 */
abstract public function getHash();


/**
 * True if session key exists.
 * 
 * @param string $key
 * @return bool
 */
abstract public function has($key);


/**
 * Set session metadata value.
 *
 * @param string $key
 * @param any $value
 */
abstract public function setMeta($key, $value);


/**
 * Get session metadata value.
 * 
 * @throws if key is not set
 * @param string $key
 * @return any
 */
abstract public function getMeta($key);


/**
 * Get session metadata hash.
 * 
 * @return map<string:any>
 */
abstract public function getMetaHash();


/**
 * True if session metadata key exists.
 * 
 * @param string $key
 * @return bool
 */
abstract public function hasMeta($key);


/**
 * Destroy session data.
 */
abstract public function destroy();

}
