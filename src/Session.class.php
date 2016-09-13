<?php

namespace rkphplib;

require_once(__DIR__.'/ASession.class.php');

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
 * Initialize session. Parameter: name, scope(=docroot), ttl(=172800), invalid(=7200).
 * @param map $conf
 */
public function init($conf) {
	$this->setConf($conf);

	if (!session_id()) {
		session_start();
	}

	$this->setConf($conf);
	$skey = $this->getSessionKey();
	$skey_meta = $skey.'_meta';

	if (!isset($_SESSION[$skey]) || !is_array($_SESSION[$skey])) {
    $_SESSION[$skey] = [];
  }

	if (!isset($_SESSION[$skey_meta]) || !is_array($_SESSION[$skey_meta])) {
    $_SESSION[$skey_meta] = [];
  }

	$this->initMeta();

	if (!$this->validScope()) {
		throw new Exception('forbidden');
	}

	if ($this->hasExpired()) {
		throw new Exception('expired');
	}
}


/**
 *
 */
public function destroy() {
	throw new Exception("Not implemented");
}

/**
 * Set session metadata.
 *
 * @param string $key
 * @param any $value
 */
public function setMeta($key, $value) {
	$skey = $this->getSessionKey().'_meta';

	if (!isset($_SESSION[$skey])) {
		throw new Exception('call init first', "set $key=[$value]");
	}

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
	$skey = $this->getSessionKey().'_meta';

	if (!isset($_SESSION[$skey]) || !isset($_SESSION[$skey][$key]) || !array_key_exists($key, $_SESSION[$skey])) {
		throw new Exception('No such key', "[$key] not in _SESSION[$skey]");
	}

	return $_SESSION[$skey];
}


/**
 * True if session metadata key exists.
 * 
 * @param string $key
 * @return bool
 */
public function hasMeta($key) {
	$skey = $this->getSessionKey().'_meta';
	return isset($_SESSION[$skey]) && array_key_exists($key, $_SESSION[$skey]);
}


}

