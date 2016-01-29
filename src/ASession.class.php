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
protected $conf = [ 'name' => '',
	'scope' => 'domain',
	'type' => '',
	'ttl' => 7200,
	'expire' => 3600,
	'max_duration' => 172000,
	'start' => 0,
	'lchange' => 0,
	'reload' => 0 ];


/**
 * Set session configuration. Parameter:
 *
 *  name: Session Name (default = empty)
 *  scope: dir|file|subdir|domain (default = domain)
 *	type: login type or group (default = empty)
 *  ttl: expiration date increase [1-14400] after activity (default = 7200 sec = 2 h)
 *  expire: expiration after inactivity [1-14400] (default = 3600 = 1 h)
 *	max_duration: maximum session duration [1, 345600] (default = 172000 = 4 h)
 * 
 * @throws rkphplib\Exception if check fails
 * @param map $conf
 */
public function setConf($conf) {

	$allow_scope = array('dir', 'file', 'subdir', 'domain');
	if (isset($conf['scope']) && !in_array($conf['scope'], $allow_scope)) {
		throw new Exception('no such scope', $conf['scope']);
	}

	$key_list = [ 'name', 'type', 'scope' ];
	foreach ($key_list as $key) {
		if (isset($conf[$key])) {
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
 * Initialize session.
 *
 * Call setConf() or use $conf to initialize session.
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
