<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/Dir.class.php';
require_once __DIR__.'/lib/split_str.php';
require_once __DIR__.'/lib/redirect.php';
require_once __DIR__.'/lib/is_ssl.php';

use function rkphplib\lib\split_str;
use function rkphplib\lib\redirect;
use rkphplib\Dir;


/**
 * PHP Session wrapper
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016-2021 Roland Kujundzic
 */
class Session {

// @var hash $conf
private $conf = [];


/**
 * Set options.
 */
public function __construct(array $options = []) {
	$this->conf = $options;
}


/**
 * Return javascript code (window.setInterval(...), JQuery required).
 * Implement function php_session_refresh(data) { ... } (data = OK or EXPIRED)
 * and set $on_success = 'php_session_refresh' if you want to track refresh success.
 */
public function getJSRefresh(string $url, string $on_success = '', int $minutes = 10) : string {

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
 * Get configuration value. Parameter: name, scope, inactive, ttl.
 *
 * @return mixed
 */
public function getConf(string $key) {

	if (count($this->conf) === 0) {
		throw new Exception('call init first');
	}

	if (!isset($this->conf[$key])) {
		throw new Exception('no such configuration key', $key);
	}

	return $this->conf[$key];
}


/**
 * Return expiration reason (ttl|inactive|) if session has become invalid. Update lchange.
 */
public function hasExpired() : string {
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
public function getSessionKey(string $map = '') : string {

	if (empty($this->conf['name'])) {
		throw new Exception('call init first');
	}

	$skey = md5($this->conf['name'].':'.$this->conf['scope']);

	if ($map != '') {
		$skey .= '_'.$map;
	}

	return $skey;
}


/**
 * Return session file. Throw exception if ini_get("session.serialize_handler") != 'php'.
 */
public static function readPHPSessionFile(string $file) : array {
	require_once __DIR__.'/File.class.php';

	if (ini_get('session.serialize_handler') != 'php') {
		throw new Exception('set session.serialize_handler = php');
	}

	$res = [];
	$offset = 0;
	$session_data = File::load($file);

	while ($offset < strlen($session_data)) {
		if (!strstr(substr($session_data, $offset), "|")) {
			throw new Exception("invalid data, remaining: " . substr($session_data, $offset));
		}

		$pos = strpos($session_data, "|", $offset);
		$num = $pos - $offset;
		$varname = substr($session_data, $offset, $num);
		$offset += $num + 1;
		$data = unserialize(substr($session_data, $offset));
		$res[$varname] = $data;
		$offset += strlen(serialize($data));
	}

	return $res;
}


/**
 * Initialize session. If required session parameter does not exist 
 * redirect to redirect_login or throw exception.
 * 
 * @see hasExpired()
 * @hash $conf …
 * allow_dir: login  # list of allowed directories
 * cross_site: 1  # allow cross site cookies - works only on ssl
 * handler: files(|Database)
 * inactive: 7200  # Session expires if last_change + 2h < now
 * name: required  # or name=table
 * redirect: 
 * required: # list of session parameter - if one is empty redirect to login page)
 * redirect_login: index.php?dir=login
 * redirect_logout: index.php?dir=login/exit (default: redirect_login.'/exit')
 * redirect_forbidden: index.php?dir=login/access_denied
 * save_path: # or SESSION_SAVE_PATH
 * scope: docroot(|file|dir|subdir|host)
 * table: 
 * ttl: 172800  # Session expires if start + 48h < now
 * unlimited:   # if set use inactive=21600 and ttl=345600
 * @eol
 * 
 */
public function init(array $conf) : void {
	// \rkphplib\lib\log_debug([ 'Session.init:293> <1>', $conf);
	$this->initConf($conf);
	$this->start();

	$skey = $this->getSessionKey();
	if (!isset($_SESSION[$skey]) || !is_array($_SESSION[$skey])) {
    $_SESSION[$skey] = [];
  }

	$mkey =$this->getSessionKey('meta'); 
	if (!isset($_SESSION[$mkey]) || !is_array($_SESSION[$mkey])) {
    $_SESSION[$mkey] = [];
  }

	$this->initMeta();
	$this->checkScope();

	// \rkphplib\lib\log_debug([ "Session.init:198> skey=$skey mkey=$mkey <1>", $this->conf);	
	if (!empty($_REQUEST[SETTINGS_REQ_DIR])) {
		$dir = $_REQUEST[SETTINGS_REQ_DIR];
		foreach ($this->conf['allow_dir'] as $allow_dir) {
			if (!empty($allow_dir) && mb_strpos($dir, $allow_dir) === 0) {
				return;
			} 
		}
	}

	if (count($this->conf['required']) > 0) {
		foreach ($this->conf['required'] as $name) {
    	if (empty($_SESSION[$skey][$name])) {
				$this->redirectLogin("missing session parameter $name");
			}
		}
	}

	if (($expired = $this->hasExpired())) {
		$this->redirectLogin('expired', [ 'expired' => $expired ]);
	}
}


/**
 * If conf.redirect_login is set redirect otherwise throw exception.
 */
private function redirectLogin(string $reason, array $p = []) : void {
	// destroy session
	$skey = $this->sessKey();
	$mkey = $this->sessKey('meta');

	if (isset($_SESSION[$mkey]['map'])) {
		foreach ($_SESSION[$mkey]['map'] as $map) {
			$mkey = $this->sessKey($map);
			unset($_SESSION[$mkey]);
		}
	}

	unset($_SESSION[$skey]);
	unset($_SESSION[$mkey]);

	if (!empty($this->conf['redirect_login'])) {
		// \rkphplib\lib\log_debug('Session.redirectLogin:241> redirect '.$this->conf['redirect_login'], $reason);
		redirect($this->conf['redirect_login'], $p);
	}
	else {
		// \rkphplib\lib\log_debug('Session.redirectLogin:245> invalid session', $reason);
		throw new Exception($reason);
	}
}


/**
 * Set this.conf
 */
private function initConf(array $conf) : void {
	$this->conf = array_merge([
		'cross_site' => 1,
		'handler' => 'files', 
		'inactive' => 7200, 
		'name' => '', 
		'redirect_login' => 'index.php?dir=login',  
		'redirect_logout' => 'index.php?dir=login/exit',
		'redirect_forbidden' => 'index.php?dir=login/access_denied',
		'table' => '', 
		'required' => '', 
		'allow_dir' => 'login',
		'scope' => 'docroot', 
		'ttl' => 172800, 
		'init_meta' => 0, 
	], $conf);

	if (empty($this->conf['name'])) {
		if (!empty($conf['table'])) {
			$this->conf['name'] = $conf['table'];
		}
		else {
			throw new Exception('name is empty');
		}
	}

	$allow_scope = [ 'script', 'dir', 'subdir', 'host', 'docroot' ];
	if (!in_array($this->conf['scope'], $allow_scope)) {
		throw new Exception('invalid scope - use docroot|dir|host|script|subdir', $this->conf['scope']);
	}

	$this->conf['required'] = split_str(',', $this->conf['required'], true);
	$this->conf['allow_dir'] = split_str(',', $this->conf['allow_dir'], true);

	if (!empty($this->conf['unlimited'])) {
		$this->conf['inactive'] = 21600;
		$this->conf['ttl'] = 345600;
	}

	$time_range = [ 'inactive' => [ 1, 21600 ], 'ttl' => [ 1, 345600] ];
	foreach ($time_range as $key => $range) {
		if (isset($this->conf[$key])) {
			$sec = intval($this->conf[$key]);
			if ($sec < $range[0] || $sec > $range[1]) {
				throw new Exception("$key outside of range", $sec." not in [".$range[0].",".$range[1]."]");
			}
		}
	}

	if (defined('SESSION_SAVE_PATH')) {
		$this->conf['save_path'] = SESSION_SAVE_PATH;
	}

	$save_path = ini_get('session.save_path');
	if (!empty($this->conf['save_path']) && $save_path != $this->conf['save_path']) {
		Dir::exists($this->conf['save_path'], true);
		ini_set('session.save_path', $this->conf['save_path']);
	}
}


/**
 * Redirect to redirect_forbidden or throw exeption
 */
private function checkScope() : void {
	if (empty($this->conf['scope'])) {
		throw new Exception('call init first');
	}

	$script = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
	$docroot = empty($_SERVER['DOCUMENT_ROOT']) ? '' : $_SERVER['DOCUMENT_ROOT'];
	$host = empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST'];

	$m_script = $this->get('script', true, 'meta');

	$ok = false;
	switch ($this->conf['scope']) {
		case 'script':
			$ok = $script == $m_script;
			break;
		case "dir":
			$ok = dirname($script) == dirname($m_script);
			break;
		case "subdir":
			$ok = mb_strpos(dirname($script), dirname($m_script)) === 0;
			break;
		case "host":
			$ok = $host == $this->get('host', true, 'meta');
			break;
		case 'docroot':
			$ok = $docroot == $this->get('docroot', true, 'meta');
			break;
	}

	if (!$ok) {
		if (!empty($this->conf['redirect_forbidden'])) {
			// \rkphplib\lib\log_debug('Session.checkScope:350> invalid scope, redirect: '.$this->conf['redirect_forbidden']);
			redirect($this->conf['redirect_forbidden']);
		}
		else {
			throw new Exception('forbidden');
		}
	}
}


/**
 * Call session_start()
 */
private function start() {
	if (session_id()) {
		return;
	}

	// \rkphplib\lib\log_debug('Session.start:368> start session');
	$secure = intval(\rkphplib\lib\is_ssl());
	$same_site = $secure && $this->conf['cross_site'] ? 'none' : 'strict';
	$sess_opt = [
		'cookie_httponly' => 1,
		'cookie_secure' => $secure,
		// these options failed ...
		// 'strict_mode' => 0,
		// 'cookie_samesite' => $same_site,
		'cache_expire' => max(ini_get('session.cache_expire'), $this->conf['ttl']),
		'gc_maxlifetime' => max(ini_get('session.gc_maxlifetime'), $this->conf['inactive'])
	];

	if ($this->conf['handler'] != 'files') {
 		$handler = $this->conf['handler'].'SessionHandler';
		new $handler();
	}

	if (!session_start($sess_opt)) {
		throw new Exception('session_start() failed');
	}
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
private function initMeta() : void {
	if (!empty($this->conf['init_meta'])) {
		// \rkphplib\lib\log_debug('Session.initMeta:403> use existing'); 
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
 * Return session key. Use map = meta for meta data. 
 */
private function sessKey(string $map = '') : string {
	$skey = $this->getSessionKey($map);

	if (!isset($_SESSION[$skey])) {
		if ($map == '' || $map == 'meta') {
			throw new Exception("call init $map first", "skey=$skey");
		}
		else {
			// create new map ... record maps in meta.map
			$_SESSION[$skey] = [];	
			$this->push('map', $map, 'meta');
		}
	}

	if (!is_array($_SESSION[$skey])) {
		throw new Exception("session map [$map] conflict", "skey=$skey");
	}

	return $skey;
}


/**
 * Set session value. Use $map=meta for metadata.
 */
public function set(string $key, $value, string $map = '') : void {
	$skey = $this->sessKey($map);
	$_SESSION[$skey][$key] = $value;
}


/**
 * Push value into session key. If value is pair assume session hash (otherwise vector).
 * Use map=meta for metadata.
 */
public function push(string $key, $value, string $map = '') : void {
	$skey = $this->sessKey($map);
	
	if (!isset($_SESSION[$skey][$key])) {
		$_SESSION[$skey][$key] = [];
	}

	if (!is_array($_SESSION[$skey][$key])) {
		throw new Exception('session key is not array', "key=$key map=$map skey=$skey");
	}

	if (is_array($value) && count($value) == 2) {
		$_SESSION[$skey][$key][$value[0]] = $value[1];
	}
	else {
		array_push($_SESSION[$skey][$key], $value);
	}
}


/**
 * Get session key value. Use suffix '?' on key to prevent exception if key is not found.
 * @return any 
 */
public function get(string $key, bool $required = true, string $map = '') {
	$skey = $this->sessKey($map);
	$res = null;

	if (mb_substr($key, -1) == '?') {
		$key = mb_substr($key, 0, -1);
		$required = false;
	}

	if (array_key_exists($key, $_SESSION[$skey])) {
		$res = $_SESSION[$skey][$key];
	}
	else if ($required) {
		throw new Exception('[login:'.$key.'] no such key in session (use '.$key.'?)', "skey=$skey map=$map");
	}

	return $res;
}


/**
 * True if session key exists. If key is null return true if session hash is empty.
 */
public function has(string $key, string $map = '') : bool {
	$skey = $this->sessKey($map);

	if (is_null($key) && $map != '') {
		return count($_SESSION[$skey]) == 0;
	}

	return array_key_exists($key, $_SESSION[$skey]);
}


/**
 * Return number of session keys.
 */
public function count(string $key, string $map = '') : int {
	$skey = $this->sessKey($map);
	return count($_SESSION[$skey]);
}


/**
 * Remove session key.
 */
public function remove(string $key, string $map = '') : void {
	$skey = $this->sessKey($map);

	if (!isset($_SESSION[$skey][$key])) {
		throw new Exception('no such key in session map', "key=$key map=$map skey=$skey");
	}

	unset($_SESSION[$skey][$key]);
}


/**
 * Get session hash.
 */
public function getHash(string $map = '') : array {
	$skey = $this->sessKey($map);
	return $_SESSION[$skey];
}


/**
 * Set session hash. Overwrite existing unless merge = true.
 */
public function setHash(array $p, bool $merge = false, string $map = '') : void {
	$skey = $this->sessKey($map);

	if (!is_array($p)) {
		throw new Exception('invalid parameter');
	}

	if ($merge) {
		$_SESSION[$skey] = array_merge($_SESSION[$skey], $p);
	}
	else {
		$_SESSION[$skey] = $p;
	}
}


}

