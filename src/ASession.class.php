<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/lib/split_str.php';
require_once __DIR__.'/lib/redirect.php';




/**
 * Abstract Session wrapper class.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ASession {

/** @var map $conf */
protected $conf = [];


/**
 * Set (default) options.
 */
public function __construct($options = []) {
	$this->conf = $options;
}


/**
 * Return javascript code (window.setInterval(...), JQuery required).
 * Implement function php_session_refresh(data) { ... } (data = OK or EXPIRED)
 * and set $on_success = 'php_session_refresh' if you want to track refresh success.
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
 */
public function initMeta() {

	if (!empty($this->conf['init_meta'])) {
		// \rkphplib\lib\log_debug('ASession.initMeta:74> use existing'); 
		return;
	}

	$script = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
	$this->set('script', $script, 'meta');

	$docroot = empty($_SERVER['DOCUMENT_ROOT']) ? '' : $_SERVER['DOCUMENT_ROOT'];
	$this->set('docroot', $docroot, 'meta');

	$host = empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST'];
	$this->set('host', $host, 'meta');

	$this->set('start', time(), 'meta');
	$this->set('last', time(), 'meta');

	$this->conf['init_meta'] = 1;
}


/**
 * Get configuration value. Parameter: name, scope, inactive, ttl.
 *
 * @return mixed
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
 *  name|table: Session Name - required
 *  scope: file|dir|subdir|host|docroot (default = docroot)
 *  inactive: seconds of inactivity. Session expires after lchange + inactive. Range [1-21600] (default = 7200 = 2 h)
 *	ttl: time to live in seconds. Session expires after start + ttl. Range [1, 345600] (default = 172800 = 48 h)
 *  unlimited: optional - if set use inactive=21600 and ttl=345600
 *  allow_dir: 'login' (list of allowed directories)
 *  redirect_login: index.php?dir=login
 *  redirect_logout: index.php?dir=login/exit (default: redirect_login.'/exit')
 *  redirect_forbidden: index.php?dir=login/access_denied
 *  required: '' (list of session parameter - if one is empty redirect to login page)
 * 
 *  Check inactive and ttl with hasExpired().
 */
protected function setConf($conf) {
	// \rkphplib\lib\log_debug('ASession.setConf:130> enter - conf: '.print_r($conf, true));

	$default = [ 'name' => '', 'table' => '', 'scope' => 'docroot', 'inactive' => 7200, 'ttl' => 172800, 'init_meta' => '0', 
		'redirect_login' => 'index.php?dir=login',  'redirect_logout' => 'index.php?dir=login/exit',
		'redirect_forbidden' => 'index.php?dir=login/access_denied', 
		'required' => '', 'allow_dir' => 'login' ];

	foreach ($default as $key => $value) {
		if (isset($conf[$key])) {
			$this->conf[$key] = $conf[$key];
		}
		else if (!isset($this->conf[$key])) {
			$this->conf[$key] = $value;
		}
	}

	if (empty($this->conf['name'])) {
		if (!empty($conf['table'])) {
			$this->conf['name'] = $conf['table'];
		}
		else {
			throw new Exception('name is empty');
		}
	}

	$allow_scope = array('script', 'dir', 'subdir', 'host', 'docroot');
	if (!in_array($this->conf['scope'], $allow_scope)) {
		throw new Exception('no such scope', $this->conf['scope']);
	}

	$this->conf['required'] = \rkphplib\lib\split_str(',', $this->conf['required'], true);
	$this->conf['allow_dir'] = \rkphplib\lib\split_str(',', $this->conf['allow_dir'], true);

	$time_keys = [ 'inactive' => [1, 21600], 'ttl' => [1, 345600] ];

	if (!empty($this->conf['unlimited'])) {
		$this->conf['inactive'] = 21600;
		$this->conf['ttl'] = 345600;
	}

	foreach ($time_keys as $key => $range) {
		if (isset($this->conf[$key])) {
			$sec = intval($this->conf[$key]);

			if ($sec < $range[0] || $sec > $range[1]) {
				throw new Exception("parameter $key outside of range", $sec." not in [".$range[0].",".$range[1]."]");
			}
		}
	}

	// \rkphplib\lib\log_debug('ASession.setConf:180> exit - this.conf: '.print_r($this->conf, true));
}


/**
 * Return true if scope is valid.
 */
public function validScope() {

	if (empty($this->conf['scope'])) {
		throw new Exception('call setConf first');
	}

	$script = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
	$host = empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST'];
	$docroot = empty($_SERVER['DOCUMENT_ROOT']) ? '' : $_SERVER['DOCUMENT_ROOT'];
	$ok = false;

	$m_script = $this->get('script', true, 'meta');

	switch ($this->conf['scope']) {
		case 'script':
			if ($script == $m_script) {
				$ok = true;
			}
			break;
		case "dir":
			if (dirname($script) == dirname($m_script)) {
				$ok = true;
			}
			break;
		case "subdir":
			if (mb_strpos(dirname($script), dirname($m_script)) === 0) {
				$ok = true;
			}
			break;
		case "host":
			if ($host == $this->get('host', true, 'meta')) {
				$ok = true;
			}
			break;
		case 'docroot':
			if ($docroot == $this->get('docroot', true, 'meta')) {
				$ok = true;
			}
			break;
	}

	return $ok;
}


/**
 * If conf.redirect_forbidden is set redirect otherwise throw exception. 
 */
public function redirectForbidden() {

	if (!empty($this->conf['redirect_forbidden'])) {
		\rkphplib\lib\redirect($this->conf['redirect_forbidden']);
	}
	else {
		throw new Exception('forbidden');
	}
}


/**
 * If conf.redirect_login is set redirect otherwise throw exception.
 */
public function redirectLogin($reason, $p = []) {
	// \rkphplib\lib\log_debug('ASession.redirectLogin:248> reason='.$reason.' - conf: '.print_r($this->conf, true)."\np: ".print_r($p, true));
	$this->destroy();

	if (!empty($this->conf['redirect_login'])) {
		\rkphplib\lib\redirect($this->conf['redirect_login'], $p);
	}
	else {
		throw new Exception($reason);
	}
}


/**
 * Return expiration reason (ttl|inactive|) if session has become invalid. Update lchange.
 */
public function hasExpired() {
	$now = time();
	$expire_reason = '';

	if ($now - $this->get('start', true, 'meta') > $this->conf['ttl']) {
		$expire_reason = 'ttl';
	}
	else if ($now - $this->get('last', true, 'meta') > $this->conf['inactive']) {
		$expire_reason = 'inactive';
	}
	else {
		$this->set('last', time(), 'meta');
	}

	return $expire_reason;
}


/**
 * Return (meta) session key. Key is md5(conf.name:conf.scope)[_meta].
 */
public function getSessionKey($map = '') {

	if (empty($this->conf['name'])) {
		throw new Exception('call setConf first');
	}

	$skey = md5($this->conf['name'].':'.$this->conf['scope']);

	if ($map != '') {
		$skey .= '_'.$map;
	}

	return $skey;
}


/**
 * Initialize session (see setConf). Parameter: name, scope(=docroot), ttl(=172800), inactive(=7200), redirect_[forbidden|login](='').
 * If required session parameter does not exist redirect to redirect_login or throw exception.
 * New parameter "save_path" (overwrite with define('SESSION_SAVE_PATH', '...')).
 */
abstract public function init($conf);


/**
 * Set session value. Use $map=meta for metadata.
 */
abstract public function set($key, $value, $map = '');


/**
 * Push value into session key. If value is pair assume session hash (otherwise vector).
 * Use map=meta for metadata.
 */
abstract public function push($key, $value, $map = '');


/**
 * Set session hash. Overwrite existing unless merge = true.
 */
abstract public function setHash($p, $merge = false, $map = '');


/**
 * Get session key value. Use suffix '?' on key to prevent exception if key is not found.
 */
abstract public function get($key, $required = true, $map = '');


/**
 * Get session hash.
 */
abstract public function getHash($map = '');


/**
 * True if session key exists. If key is null return true if session hash is empty.
 */
abstract public function has($key, $map = '');


/**
 * Remove session key.
 */
abstract public function remove($key, $map = '');


/**
 * Return number of session keys.
 */
abstract public function count($key, $map = '');


/**
 * Destroy session data.
 */
abstract public function destroy();

}
