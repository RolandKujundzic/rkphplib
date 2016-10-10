<?php

namespace rkphplib;

require_once(__DIR__.'/ASession.class.php');
require_once(__DIR__.'/lib/redirect.php');

use rkphplib\Exception;


/**
 * PHP Session wrapper ($_SESSION).
 *
 * ASession Implementation based of $_SESSION.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Session extends ASession {


/**
 * Initialize session. Parameter: name, scope(=docroot), ttl(=172800), inactive(=7200), redirect_[forbidden|expired](='').
 * @param map $conf
 */
public function init($conf) {
	$this->setConf($conf);

	$sess_ttl = ini_get('session.gc_maxlifetime');
	if ($sess_ttl > 0 && $sess_ttl < $this->conf['inactive']) {
		// avoid session garbage collection during session lifetime 
		ini_set('session.gc_maxlifetime', $this->conf['inactive']);
	}

	if (!session_id()) {
		session_start();
	}

	$skey = $this->getSessionKey();
	$skey_meta =$this->getSessionKey(true); 

	if (!isset($_SESSION[$skey]) || !is_array($_SESSION[$skey])) {
    $_SESSION[$skey] = [];
  }

	if (!isset($_SESSION[$skey_meta]) || !is_array($_SESSION[$skey_meta])) {
    $_SESSION[$skey_meta] = [];
  }

	$this->initMeta();

	if (!$this->validScope()) {
		if (!empty($this->conf['redirect_forbidden'])) {
			\rkphplib\lib\redirect($this->conf['redirect_forbidden']);
		}
		else {
			throw new Exception('forbidden');
		}
	}

	if (($expired = $this->hasExpired())) {
		$this->destroy();

		if (!empty($this->conf['redirect_expired'])) {
			\rkphplib\lib\redirect($this->conf['redirect_expired'], [ 'expired' => $expired ]);
		}
		else {
			throw new Exception('expired');
		}
	}
}


/**
 * Return (meta) session key. If $key is not empty check if key was set.
 * 
 * @param bool $meta (default = false)
 * @param string $key (default = '')
 * @return string
 */
private function sessKey($meta = false, $key = '') {
	$skey = $this->getSessionKey($meta);

	if (!isset($_SESSION[$skey]) || !is_array($_SESSION[$skey])) {
		throw new Exception('call init first', "skey=$skey");
	}

	if (!empty($key) && !array_key_exists($key, $_SESSION[$skey])) {
		throw new Exception('no such session key', "skey=$skey key=$key");
	}

	return $skey;
}


/**
 * Destroy dession data.
 */
public function destroy() {
	$skey = $this->sessKey();
	$skey_meta = $this->sessKey(true);

	unset($_SESSION[$skey]);
	unset($_SESSIOn[$skey_meta]);
}


/**
 * Set session metadata.
 *
 * @param string $key
 * @param any $value
 */
public function setMeta($key, $value) {
	$skey = $this->sessKey(true);
	$_SESSION[$skey][$key] = $value;
}


/**
 * Get session metadata.
 * 
 * @throws if key is not set
 * @param string $key
 * @return any
 */
public function getMeta($key) {
	$skey = $this->sessKey(true, $key);
	return $_SESSION[$skey][$key];
}


/**
 * Get session metadata hash.
 * 
 * @return map<string:any>
 */
public function getMetaHash() {
	$skey = $this->sessKey(true);
	return $_SESSION[$skey];
}


/**
 * True if session metadata key exists.
 * 
 * @param string $key
 * @return bool
 */
public function hasMeta($key) {
	$skey = $this->sessKey(true);
	return array_key_exists($key, $_SESSION[$skey]);
}


/*
 * Set session value.
 *
 * @param string $key
 * @param any $value
 */
public function set($key, $value) {
	$skey = $this->sessKey();
	$_SESSION[$skey][$key] = $value;
}


/**
 * Set session map. Overwrite existing.
 *
 * @param map<string:any> $key
 * @param any $value
 */
public function setHash($p) {
	$skey = $this->sessKey();

	if (!is_array($p)) {
		throw new Exception('invalid parameter');
	}

	$_SESSION[$skey] = $p;
}


/**
 * Get session value.
 * 
 * @throws if key is not set and required
 * @param string $key
 * @return any|null (if not found)
 */
public function get($key, $required = true) {
	$skey = $this->sessKey();
	$res = null;

	if (array_key_exists($key, $_SESSION[$skey])) {
		$res = $_SESSION[$skey][$key];
	}
	else if ($required) {
		throw new Exception('no such session key', "skey=$skey key=$key");
	}

	return $res;
}


/**
 * Get session hash.
 * 
 * @return map<string:any>
 */
public function getHash() {
	$skey = $this->sessKey();
	return $_SESSION[$skey];
}


/**
 * True if session key exists.
 * 
 * @param string $key
 * @return bool
 */
public function has($key) {
	$skey = $this->sessKey();
	return array_key_exists($key, $_SESSION[$skey]);
}


}

