<?php

namespace rkphplib;

require_once(__DIR__.'/ASession.class.php');
require_once(__DIR__.'/Dir.class.php');


if (!defined('SETTINGS_REQ_DIR')) {
	/** @const SETTINGS_REQ_DIR = 'dir' */
	define('SETTINGS_REQ_DIR', 'dir');
}


use \rkphplib\Dir;


/**
 * PHP Session wrapper ($_SESSION).
 *
 * ASession Implementation based of $_SESSION.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Session extends ASession {


/**
 * Return session file. 
 *
 * @throws if ini_get("session.serialize_handler") != 'php'
 */
public static function readPHPSessionFile($file) {
	require_once(__DIR__.'/File.class.php');

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
 * Initialize session. Parameter: name, scope(=docroot), ttl(=172800), inactive(=7200), redirect_[forbidden|login](='').
 * If required session parameter does not exist redirect to redirect_login or throw exception.
 * New parameter "save_path" (overwrite with define('SESSION_SAVE_PATH', '...')).
 * 
 * @see ASession::setConf 
 * @throws
 * @param map $conf
 */
public function init($conf) {
	$this->setConf($conf);

	$sess_ttl = ini_get('session.gc_maxlifetime');
	if ($sess_ttl > 0 && $sess_ttl < $this->conf['inactive']) {
		// avoid session garbage collection during session lifetime 
		ini_set('session.gc_maxlifetime', $this->conf['inactive']);
	}

	if (defined('SESSION_SAVE_PATH')) {
		$this->conf['save_path'] = SESSION_SAVE_PATH;
	}

	$save_path = ini_get('session.save_path');
	if (!empty($this->conf['save_path']) && $save_path != $this->conf['save_path']) {
		Dir::exists($this->conf['save_path'], true);
		ini_set('session.save_path', $this->conf['save_path']);
	}

	if (!session_id()) {
		session_start();
		// \rkphplib\lib\log_debug('Session::init> start session');
	}
	
	$skey = $this->getSessionKey();
	$skey_meta =$this->getSessionKey('meta'); 

	// \rkphplib\lib\log_debug('Session::init> session_id='.session_id()." in ".ini_get('session.save_path')." - skey=$skey meta=$skey_meta");

	if (!isset($_SESSION[$skey]) || !is_array($_SESSION[$skey])) {
		// \rkphplib\lib\log_debug('Session::init> create skey map');
    $_SESSION[$skey] = [];
  }

	if (!isset($_SESSION[$skey_meta]) || !is_array($_SESSION[$skey_meta])) {
		// \rkphplib\lib\log_debug('Session::init> create skey_meta map');
    $_SESSION[$skey_meta] = [];
  }

	$this->initMeta();

	if (!empty($_REQUEST[SETTINGS_REQ_DIR])) {
		$dir = $_REQUEST[SETTINGS_REQ_DIR];
		// \rkphplib\lib\log_debug('Session::init> check if '.$dir.' is in allowed: '.print_r($this->conf['allow_dir'], true));
		foreach ($this->conf['allow_dir'] as $allow_dir) {
			if (!empty($allow_dir) && mb_strpos($dir, $allow_dir) === 0) {
				// we are in login-free directory - return without checks
				return;
			} 
		}
	}

	if (!$this->validScope()) {
		// \rkphplib\lib\log_debug('Session::init> invalid scope - redirectForbidden');
		$this->redirectForbidden();
	}

	if (count($this->conf['required']) > 0) {
		foreach ($this->conf['required'] as $name) {
    	if (empty($_SESSION[$skey][$name])) {
				// \rkphplib\lib\log_debug('Session::init> required parameter '.$skey.'.'.$name.' empty - redirectLogin');
				$this->redirectLogin('no_login');
			}
		}
	}

	if (($expired = $this->hasExpired())) {
		// \rkphplib\lib\log_debug('Session::init> exipred - redirectLogin');
		$this->redirectLogin('expired', [ 'expired' => $expired ]);
	}
}


/**
 * Return (map) session key. Use map = meta for meta data. 
 *
 * @throws 
 * @param string $map (default = '')
 * @return string
 */
private function sessKey($map = '') {
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
 * Destroy dession data.
 */
public function destroy() {
	$skey = $this->sessKey();
	$skey_meta = $this->sessKey('meta');

	if (isset($_SESSION[$skey_meta]['map'])) {
		foreach ($_SESSION[$skey_meta]['map'] as $map) {
			$mkey = $this->sessKey($map);
			unset($_SESSION[$mkey]);
		}
	}

	unset($_SESSION[$skey]);
	unset($_SESSION[$skey_meta]);
}


/*
 * Set session value. Use map=meta for metadata.
 *
 * @throws 
 * @param string $key
 * @param any $value
 * @param string $map (default = '' = no session map)
 */
public function set($key, $value, $map = '') {
	$skey = $this->sessKey($map);
	$_SESSION[$skey][$key] = $value;
}


/*
 * Push value to session vector|map $key. If value is pair assume session map. 
 * Auto create vector|map if missing. Use map=meta for metadata.
 *
 * @throws 
 * @param string $key
 * @param any|pair $value
 * @param string $map (default = '' = no session map)
 */
public function push($key, $value, $map = '') {
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
 * Get session value. Use suffix '?' on key to 
 * prevent exception if key is not found.
 * 
 * @throws if key is not set and required
 * @param string $key
 * @param bool $required (default = true)
 * @param string $map (default = '')
 * @return any|null (if not found)
 */
public function get($key, $required = true, $map = '') {
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
 * True if session key exists.
 * 
 * @param string $key
 * @param string $map (default = '')
 * @return bool
 */
public function has($key, $map = '') {
	$skey = $this->sessKey($map);
	return array_key_exists($key, $_SESSION[$skey]);
}


/**
 * Remove session key.
 * 
 * @throws
 * @param string $key
 * @param string $map (default = '')
 * @return bool
 */
public function count($key, $map = '') {
	$skey = $this->sessKey($map);
	return count($_SESSION[$skey]);
}


/**
 * Remove session key.
 * 
 * @throws
 * @param string $key
 * @param string $map (default = '')
 * @return bool
 */
public function remove($key, $map = '') {
	$skey = $this->sessKey($map);

	if (!isset($_SESSION[$skey][$key])) {
		throw new Exception('no such key in session map', "key=$key map=$map skey=$skey");
	}

	unset($_SESSION[$skey][$key]);
}


/**
 * Get session hash.
 * 
 * @param map (default = '')
 * @return map<string:any>
 */
public function getHash($map = '') {
	$skey = $this->sessKey($map);
	return $_SESSION[$skey];
}


/**
 * Set session map. Overwrite existing unless merge = true.
 *
 * @param map<string:any> $key
 * @param bool $merge (default = false)
 * @param string $map (default = '')
 * @param any $value
 */
public function setHash($p, $merge = false, $map = '') {
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

