<?php

namespace rkphplib;

require_once(__DIR__.'/ASession.class.php');

use rkphplib\Exception;


/**
 * Session wrapper.
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

	throw new Exception("Not implemented");
}


/**
 *
 */
public function destroy() {
	throw new Exception("Not implemented");
}


}

