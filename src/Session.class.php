<?php

namespace rkphplib;

require_once(__DIR__.'/ASession.class.php');

use rkphplib\Exception;


/**
 * PHP Session wrapper ($_SESSION).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Session extends ASession {


/**
 *
 */
public function init($conf = array()) {
	$this->setConf($conf);

	if (!session_id()) {
		session_start();
	}

	$skey = $this->getSessionKey();

	if (!isset($_SESSION[$skey]) || !is_array($_SESSION[$skey])) {
    $_SESSION[$skey] = array();
  }

	throw new Exception("ToDo: implement ttl, expire, max_duration, start, lchange, reload");
}


/**
 *
 */
public function destroy() {
	throw new Exception("Not implemented");
}


}

