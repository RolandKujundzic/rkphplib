<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Database.class.php');
require_once(__DIR__.'/../Session.class.php');

use \rkphplib\Exception;
use \rkphplib\Session;
use \rkphplib\ADatabase;
use \rkphplib\Database;



/**
 * Tokenizer Login/Session plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TLogin implements TokPlugin {

/** @var Tokenizer $tok */
var $tok = null;

/** @var Session $sess */
var $sess = null;

/** @var ADatabase $db */
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
	$this->tok = $tok;

  $plugin = [];
  $plugin['login'] = TokPlugin::NO_BODY;
  $plugin['login_check'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['login_auth'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['login_clear'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;

  return $plugin;
}


/**
 * Logout. Example:
 *
 * {login_clear:}
 *
 */
public function tok_login_clear() {
	if ($this->sess) {
		$this->sess->destroy();
		$this->sess = null;
	}
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

	$this->sess = new Session([ 'required' => [ 'id', 'type' ], 'allow_dir' => [ 'login' ] ]);
	$this->sess->init($p);

	$table = ADatabase::escape($p['table']);

  $query_map = [
    'select_login' => "SELECT *, PASSWORD({:=password}) AS password_input FROM $table WHERE login={:=login}",
    'insert' => "INSERT INTO $table (login, password, type, person, language, priv) VALUES ".
			"({:=login}, PASSWORD({:=password}), {:=type}, {:=person}, {:=language}, {:=priv})"
  ];
	
	$this->db = Database::getInstance(SETTINGS_DSN, $query_map);

	return "<script>\n".$this->sess->getJSRefresh('login/ajax/refresh.php', '', 10)."\n</script>";
}


/**
 * Compare login with database. Example:
 *
 * {login_auth:}login={get:login}|#|password={get:password}{:login_auth}
 *
 * If login is invalid set {var:login_error} = error.
 * If password is invalid set {var:password_error} = error. 
 *
 * @param map $p
 */
public function tok_login_auth($p) {

	if (empty($p['login'])) {
		$this->createTable($this->sess->getConf('table'));
		return;
	}

	$dbres = $this->db->select($this->db->getQuery('select_login', $p));
	if (count($dbres) == 0) {
		$this->tok->setVar('login_error', 'error');
		return;
	}

	if (empty($p['password'])) {
		return;
	}

 	if (count($dbres) != 1 || empty($dbres[0]['password']) || $dbres[0]['password'] != $dbres[0]['password_input']) {
		$this->tok->setVar('password_error', 'error');
		return;
	}

	// login + password ok ... update login session
	unset($dbres[0]['password_input']);
	unset($dbres[0]['password']);
	$this->sess->setHash($dbres[0]);
}


/**
 * Return login key value. If key is empty return yes if login[id] is set.
 *
 * @param string $key
 * @return string
 */
public function tok_login($key) {
	$res = '';

	if (is_null($this->sess)) {
		// do nothing ...
	}
	else if (!empty($key)) {
		$res = $this->sess->get($key);
	}
	else if ($this->sess->has('id')) {
		$res = 'yes';
	}
	
	return $res;
}


/**
 * Create login table. Create admin user admin|admin (id=1).
 * 
 * @param string $table
 */
public function createTable($table) {
	$tconf = [];
	$tconf['@table'] = $table;
	$tconf['@id'] = 1;
	$tconf['@timestamp'] = 3;
	$tconf['login'] = 'varbinary:50::5';
	$tconf['password'] = 'varbinary:50::';
	$tconf['type'] = 'varbinary:30:admin:9';
	$tconf['language'] = 'char:2:de:1';
	$tconf['priv'] = 'int:::1';
	$tconf['person'] = 'varchar:120::1';

	if ($this->db->createTable($tconf)) {
		$this->db->execute($this->db->getQuery('insert',
			[ 'login' => 'admin', 'password' => 'admin', 'type' => 'admin', 
				'person' => 'Administrator', 'language' => 'de', 'priv' => 3 ]));
	}
}


}
