<?php

namespace rkphplib;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/Session.class.php');

use rkphplib\Exception;


/**
 * Tokenizer Login/Session plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TLogin {

/* @var Session $sess */
var $sess = null;


/**
 * Return Tokenizer plugin list:
 *
 *  login, login_init
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
  $plugin = [];
  $plugin['login'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
  $plugin['login_check'] = TokPlugin::KV_BODY;
  return $plugin;
}


/**
 * Initialize/Check login session. Example:
 * 
 * {login_check:init} -> initialize but do not check authentication
 * {login_check:}redirect_expired=...{:} -> check login authentication - if not found redirect to redirect_expired
 *
 * @see Session::init
 * @param string $param [|init]
 * @param map<string:string> $p
 * @return string javascript-login-refresh
 */
public function tok_login_check($param, $p) {

	$this->sess = new Session();
	$this->sess->init($p);

	$res = "<script>\n".$this->sess->getJSRefresh('login/ajax/refresh.php', '', 10)."\n</script>";

	if ($param === 'init') {
		return $res;
	}

	if (!$sess->hasMeta('start') || ($expired = $sess->hasExpired()))
		throw new Exception('ToDo');
	}

	return $res;
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
