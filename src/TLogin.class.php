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
  $plugin['login_init'] = TokPlugin::KV_BODY;
  return $plugin;
}


/**
 * Initialize/Check login session.
 *
 * @param string $param
 * @param map<string:string> $p
 * @return ''
 */
public function tok_login_init($param, $p) {
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
