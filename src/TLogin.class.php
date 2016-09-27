<?php

namespace rkphplib;

require_once(__DIR__.'/iTokLogin.iface.php');
require_once(__DIR__.'/Session.class.php');

use rkphplib\Exception;


/**
 * Tokenizer Login/Session plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TLogin {


/**
 * Return Tokenizer plugin list:
 *
 *  login, login_init
 *
 * @param Tokenizer &$tok
 * @return map<string:int>
 */
public function getPlugins(&$tok) {
	throw new Exception('ToDo');
}


/**
 * Initialize/Check login session.
 *
 * @param map $p
 */
public function tok_login_init($p) {
	throw new Exception('ToDo');
}


/**
 * Return login key value.
 *
 * @param string $key
 * @return string
 */
public function tok_login($key) {
	throw new Exception('ToDo');
}

}
