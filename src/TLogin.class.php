<?php

namespace rkphplib;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/Database.class.php');
require_once(__DIR__.'/Session.class.php');

use rkphplib\Exception;


/**
 * Tokenizer Login/Session plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TLogin implements TokPlugin {

/* @var Session $sess */
var $sess = null;

/* @var ADatabase $db */
var $db = null;



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
  $plugin['login'] = TokPlugin::NO_BODY;
  $plugin['login_check'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['login_auth'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  return $plugin;
}


/**
 * Initialize/Check login session. Example:
 * 
 * {login_check:}redirect_login=...{:} -> check login authentication - if not found or expired redirect to redirect_login
 *
 * @see Session::init
 * @param string $param [|init]
 * @param map<string:string> $p
 * @return string javascript-login-refresh
 */
public function tok_login_check($p) {
	$this->sess = new Session();
	$this->sess->init($p);
	return "<script>\n".$this->sess->getJSRefresh('login/ajax/refresh.php', '', 10)."\n</script>";
}


/**
 * Compare login with database. Example:
 *
 * {login_auth:}login={get:login}|#|password={get:password}{:login_auth}
 *
 * @param map $p
 */
public function tok_login_auth($p) {
	$do_auth = !empty($p['login']) && !empty($p['password']);

	if (!$do_auth) {
		$this->db = Database::getInstance(SETTINGS_DSN);
		$this->createTable($this->sess->getConf('table'));
	}
}


/**
 * Return login key value. If key is empty return yes if login[id] is set.
 *
 * @param string $key
 * @return string
 */
public function tok_login($key) {
	$res = '';

	if (!empty($key)) {
		$res = $this->sess->get($key);
	}
	else if ($this->sess->has('id')) {
		$res = 'yes';
	}
	
	return $res;
}


/**
 * Create login table.
 * 
 * @param string $table
 */
public function createTable($table) {
	$tconf = [];
	$tconf['@table'] = $table;
	$tconf['@id'] = 1;
	$tconf['@timestamp'] = 3;
	$tconf['login'] = 'varchar(50):::1';
	$tconf['password'] = 'varchar(50):::';
	$tconf['type'] = 'varchar(30):admin::9';

	$this->db->createTable($tconf);
}


}
