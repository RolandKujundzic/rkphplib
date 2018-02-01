<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);

require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/Database.class.php');
require_once($parent_dir.'/Session.class.php');
require_once($parent_dir.'/lib/kv2conf.php');
require_once($parent_dir.'/lib/redirect.php');
require_once($parent_dir.'/lib/split_str.php');

use \rkphplib\Exception;
use \rkphplib\Session;
use \rkphplib\ADatabase;
use \rkphplib\Database;



/**
 * Tokenizer Login/Session plugin. Examples:
 *
 * {login_check:}table=xyz|#|required={:login_check} - optional login
 * {login_check:}table=xyz|#|required=id,type|#|allow_dir=login{:login_check} - mandatory login
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

/** @var array $account */
var $account = [];



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
	$plugin['login_account'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['login_check'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['login_auth'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['login_update'] = TokPlugin::KV_BODY;
	$plugin['login_clear'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;

	return $plugin;
}


/**
 * Set session key value.
 *
 * @param string $key
 * @param any $value
 */
public function set($key, $value) {
	$this->sess->set($key, $value);
}


/**
 * Logout. Example:
 *
 * {login_clear:}log_table=cms_login_history{:login_clear}
 *
 * @return ''
 */
public function tok_login_clear($p) {
	if ($this->sess) {
		if (!empty($p['log_table'])) {
			$r = [];
			$r['lid'] = $this->sess->get('id');
			$r['session_md5'] = md5(session_id());
			$r['_table'] = ADatabase::escape_name($p['log_table']);
 			$this->db->execute($this->db->getQuery('log_logout', $r));
		}

		$this->sess->destroy();
		$this->sess = null;
	}

	return '';
}


/**
 * Add account. Required id, login, password and type.
 *
 * @throws
 * @param array $p
 * @return ''
 */
public function tok_login_account($p) {
	$required = [ 'id', 'type', 'login', 'password' ];
	foreach ($required as $key) {
		if (!isset($p[$key])) {
			throw new Exception('[login_account:] missing parameter '.$key);
		}
	}

	array_push($this->account, $p);
	return '';
}


/**
 * Initialize/Check login session. Example:
 * 
 * {login_check:}redirect_login=...{:} -> check login authentication - if not found or expired redirect to redirect_login
 * {login_check:}refresh=login/ajax/refresh.php|#|...{:}
 *
 * @see Session::init
 * @param string $param [|init]
 * @param map<string:string> $p
 * @return string javascript-login-refresh
 */
public function tok_login_check($p) {
	$this->sess = new Session([ 'required' => [ 'id', 'type' ], 'allow_dir' => [ 'login' ] ]);
	$this->sess->init($p);

	if (!empty($p['table'])) {
		$table = ADatabase::escape($p['table']);

		$query_map = [
			'select_login' => "SELECT *, PASSWORD({:=password}) AS password_input FROM $table WHERE login={:=login}",
			'insert' => "INSERT INTO $table (login, password, type, person, language, priv) VALUES ".
				"({:=login}, PASSWORD({:=password}), {:=type}, {:=person}, {:=language}, {:=priv})",
			'log_login' => "INSERT INTO {:=_table} (lid, session_md5, fingerprint, ip) VALUES ".
				"({:=lid}, {:=session_md5}, {:=fingerprint}, {:=ip})",
			'log_logout' => "UPDATE {:=_table} SET logout=NOW() WHERE lid={:=lid} AND session_md5={:=session_md5}"
			];

		$this->db = Database::getInstance(SETTINGS_DSN, $query_map);
	}

	return empty($p['refresh']) ? '' : "<script>\n".$this->sess->getJSRefresh($p['refresh'], '', 10)."\n</script>";
}


/**
 * Update login table and session. Use $_REQUEST[key] (key = session key).
 * Overwrite $_REQUEST session map with values from $p. Export _REQUEST['login_update_cols'].
 *
 * @tok {login_update:} -> Use $_REQUEST[key] (key = session key)
 * @tok {login_update:reload}password=PASSWORD({esc:password}){:login_update} -> update password
 * @tok {login_update:}if=|#|name=Mr. T{:login_update} -> do nothing because if is empty
 * @tok {login_update:}type=admin|#|...{:login_update} -> throw exception if previous type != 'admin'
 * @tok {login_update:}@allow_cols= login, password, ...|#|{sql:password}|#|@where= WHERE id={esc:id}{:login_update}
 * 
 * @throws
 * @param string $do reload
 * @param map $p (optional)
 * @return ''
 */
public function tok_login_update($do, $p) {
	$table = $this->sess->getConf('table');
	$sess = $this->sess->getHash();
	$kv = [];

	if (isset($p['if']) && empty($p['if'])) {
		return '';
	}

	if (isset($p['type']) && isset($sess['type']) && $p['type'] != $sess['type']) {
		throw new Exception('[login_update:]type='.$p['type'].' != '.$sess['type'].' - session change is forbidden');
	}

	$allow_cols = [];
	if (!empty($p['@allow_cols'])) {
		$allow_cols = \rkphplib\lib\split_str(',', $p['@allow_cols']);
		unset($p['@allow_cols']);	
	}

	// only add (key,value) to kv where value has changed
	foreach ($sess as $key => $value) {
		if (isset($_REQUEST[$key]) && $value != $_REQUEST[$key]) {
			$kv[$key] = $_REQUEST[$key];
		}
	}

	foreach ($p as $key => $value) {
		// e.g. !isset(kv['password'])
		if (!isset($kv[$key]) || $kv[$key] != $value) {
			$kv[$key] = $value;
		}
	}

	if (count($allow_cols) > 0) {
		$has_cols = array_keys($kv);

		foreach ($has_cols as $col) {
			if (!in_array($col, $allow_cols)) {
				throw new Exception('[login_update:] of column '.$col.' is forbidden (see @cols)');
			}
		}
	}

	if (isset($kv['password']) && empty($kv['password'])) {
		unset($kv['password']);
	}

	if (empty($kv['@where'])) {
		$id = empty($p['id']) ? '' : $p['id'];
		if (!$id) {
			$id = empty($sess['id']) ? '' : $sess['id'];
		}

		if ($id && is_numeric($id)) {
			$kv['@where'] = "WHERE id='".intval($id)."'";
		}
	}

	if (empty($kv['@where'])) {
		throw new Exception('missing @where parameter (= WHERE primary_key_of_'.$table."= '...')");
	}

	if (!is_null($this->db) && count($kv) > 1) {
		$query = $this->db->buildQuery($table, 'update', $kv);	
		\rkphplib\lib\log_debug("tok_login_update> update $table: $query");
		$this->db->execute($query);
	}

	if (!is_null($this->db) && $do == 'reload') {
		$kv = $this->db->selectOne("SELECT * FROM $table ".$kv['@where']);
	}

	if (count($kv) > 1) {
		\rkphplib\lib\log_debug("tok_login_update> #kv=".(count($kv))." update session: ".print_r($kv, true));
		$this->sess->setHash($kv, true);
	}

	return '';
}


/**
 * Compare login with database. If successfull load all columns 
 * from select_login result (except password) into session. Example:
 *
 * @tok {login_auth:}login={get:login}|#|password={get:password}|#|redirect=...|#|log_table=...{:login_auth}
 * @tok {login_auth:}login={get:login}|#|password={get:pass}|#|callback=cms,conf2login{:login_auth}
 *
 * If login is invalid set {var:login_error} = error.
 * If password is invalid set {var:password_error} = error.
 * If redirect is set - redirect after successfull login or if still logged in.
 * If user.redirect is set - redirect after successfull login or if still logged in.
 * If log_table is set - insert log entry into log table. 
 *
 * @tok <pre>{login:*}</pre> = id=...|#|login=...|#|type=...|#|priv=...|#|language=...
 * 
 * @param map $p
 */
public function tok_login_auth($p) {

	if ($this->sess->has('id')) {
		if (!empty($p['redirect'])) {
			\rkphplib\lib\redirect($p['redirect']);
		}
	}

	if (empty($p['login'])) {
		if (!is_null($this->db)) {
			$this->createTable($this->sess->getConf('table'));
		}

		return;
	}

	if (empty($p['password'])) {
		return;
	}

	if (!is_null($this->db)) {
		$user = $this->selectFromDatabase($p);
	}
	else {
		$user = $this->selectFromAccount($p);
	}

	if (is_null($user)) {
		return;
	}

	$this->sess->setHash($user);

	if (empty($p['conf_query'])) {
		$tmp = \rkphplib\lib\split_str(',', $p['conf_query']);
		$plugin = array_shift($tmp);
		$method = array_shift($tmp);
		$this->tok->callPlugin($plugin, $method, $tmp);
	}

	if (!empty($p['log_table'])) {
		$this->logAuthInTable($p['log_table'], $user['id']);
	}

	if (!empty($user['redirect'])) {
		\rkphplib\lib\redirect($user['redirect']);	
	}
	else if (!empty($p['redirect'])) {
		\rkphplib\lib\redirect($p['redirect']);	
	}
}


/**
 * Log successfull authentication in table.
 *
 * @throws
 * @param string $table
 * @param string $id
 */
private function logAuthInTable($table, $id) {
	$fingerprint = '';

	foreach (getallheaders() as $key => $value) {
  	$fingerprint = md5("$fingerprint:$key=$value");
	}

	$r = [ 'fingerprint' => $fingerprint, 'lid' => $id ];
	$r['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
	$r['_table'] = ADatabase::escape_name($table);
	$r['session_md5'] = md5(session_id()); 

	$this->db->execute($this->db->getQuery('log_login', $r));
}


/**
 * Select user from account. Parameter: login, password.
 *
 * @param array $p
 * @return array|null
 */
private function selectFromAccount($p) {
	$found = false;

	if (count($this->account) == 0) {
		lib_abort("no account defined - use [login_account:]...");
	}

	$login_ok = false;
	$password_ok = false;

	for  ($i = 0; $found === false && $i < count($this->account); $i++) {
		if ($this->account[$i]['login'] == $p['login'] && $this->account[$i]['password'] == $p['password']) {
			$found = $i;
			$login_ok = true;
			$password_ok = false;
		}
		else if (!$login_ok && $this->account[$i]['login'] == $p['login']) {
			$login_ok = true;
		}
		else if (!$password_ok && $this->account[$i]['password'] == $p['password']) {
			$password_ok = true;
		}
	}

	if (!$login_ok) {
		$this->tok->setVar('login_error', 'error');
	}

	if (!$password_ok) {
		$this->tok->setVar('password_error', 'error');
	}

	return ($found !== false) ? $this->account[$found] : null;
}


/**
 * Select user from database. Parameter: login, password.
 *
 * @param array $p
 * @return array|null
 */
private function selectFromDatabase($p) {

	$dbres = $this->db->select($this->db->getQuery('select_login', $p));
	if (count($dbres) == 0) {
		$this->tok->setVar('login_error', 'error');
		return null;
	}

 	if (count($dbres) != 1 || empty($dbres[0]['password']) || $dbres[0]['password'] != $dbres[0]['password_input']) {
		$this->tok->setVar('password_error', 'error');
		return;
	}

	// login + password ok ... update login session
	unset($dbres[0]['password_input']);
	unset($dbres[0]['password']);

	return $dbres[0];
}


/**
 * Return conf path value.
 *
 * @tok {login:getConf:*.privileges} = cms.super,cms.translation,shop.admin,shop.super,...
 * @tok {login:getConf:cms.apps} 
 *
 * @throws
 * @param string $path
 * @param string $arg
 * @return string
 */
public function tok_login_getConf($path) {
	$sess = $this->sess->getHash();
	$priv = [];

	foreach ($sess as $key => $value) {
		if (substr($key, 0, 5) == 'conf.' && substr($key, -11) == '.privileges') {
			$app = substr($key, 5, -11);
			$priv_list = \rkphplib\lib\split_str(',', $value);

			foreach ($priv_list as $entry) {
				array_push($priv, $app.'.'.$entry);
			}
		}
	}

	return join(',', $priv);
}


/**
 * Return login key value. If key is empty return yes if login[id] is set.
 * Forbidden session value keys in {login:key} are "is_null" and "getConf".
 *
 * @tok {login:} -> yes (if logged in)
 * @tok {login:id} -> ID (value of session key id)
 * @tok {login:*} -> key=value|#|... (show all key value pairs)
 *
 * @tok {login:?age} -> 1|'' (1: {login:age} == null)
 *
 * @tok {login:@*} -> key=value|#|... (show all meta data)
 * @tok {login:@[name|table]} -> show configuration value
 * @tok {login:@since} -> date('d.m.Y H:i:s', @start)
 * @tok {login:@lchange} -> date('d.m.Y H:i:s', @last)
 *
 * @throws if key does not exist (append ? to key to prevent)
 * @param string $key
 * @return string
 */
public function tok_login($key) {
	$res = '';

	if (is_null($this->sess)) {
		// do nothing ...
	}
	else if ($key == '*') {
		$res = \rkphplib\lib\kv2conf($this->sess->getHash());
	}
	else if (substr($key, 0, 1) == '?') {
		$name = substr($key, 1);
		$res = is_null($this->sess->get($name)) ? 1 : '';
	}
	else if (substr($key, 0, 1) == '@') {
		$mkey = substr($key, 1);
		if ($mkey == '*') {
			$res = \rkphplib\lib\kv2conf($this->sess->getMetaHash());
		}
		else if ($mkey == 'since') {
			$res = date('d.m.Y H:i:s', $this->sess->getMeta('start'));
		}
		else if ($mkey == 'lchange') {
			$res = date('d.m.Y H:i:s', $this->sess->getMeta('last'));
		}
		else if ($this->sess->hasMeta($mkey)) {
			$res = $this->sess->getMeta($mkey);
		}
		else {
			$res = $this->sess->getConf($mkey);
		}
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

	if (!is_null($this->db) && $this->db->createTable($tconf)) {
		$this->db->execute($this->db->getQuery('insert',
			[ 'login' => 'admin', 'password' => 'admin', 'type' => 'admin',
				'person' => 'Administrator', 'language' => 'de', 'priv' => 3 ]));
	}
}


}
