<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/TokHelper.trait.php');
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
use TokHelper;

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
	$plugin['login_access'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['login_update'] = TokPlugin::KV_BODY;
	$plugin['login_clear'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;

	return $plugin;
}


/**
 * Check login access. 
 *
 * @tok {login_access:}redirect={link:}@=login/access_denied{:link}|#|allow={:=super}{:login_access} = redirect to login/access_denied if no super priv
 * @tok {login_access:}privilege={:=super}{:login_access} = 1
 *
 * @param map $p
 * @return 1|''
 */
public function tok_login_access($p) {
	$res = '';

	if (!empty($p['allow']) && !$this->hasPrivileges($p['allow'])) {
		$redir_url = empty($p['redirect_access_denied']) ? 'login/access_denied' : $p['redirect_access_denied'];
		\rkphplib\lib\redirect($redir_url, [ '@link' => 1, '@back' => 1 ]);
	}
	else if (!empty($p['privilege'])) {
		$res = $this->hasPrivileges($p['privilege']);
	}

	return $res;
}


/**
 * Return 1|'' if privileges do (not) exist. Check sess.priv for 2^N privileges.
 * Value of 2^N privileges: super=1, ToDo=2. Check sess.role.app for app privileges.
 *
 * @param string $require_priv boolean expression e.g (priv1 | priv2) & !priv3 
 * @param boolean $ignore_super (default = false)
 * @return 1|''
 */
public function hasPrivileges($require_priv, $ignore_super = false) {

	if (strlen(trim($require_priv)) == 0) {
		return 1;
	}

	$priv = intval($this->sess->get('priv')); // 2^n | 2^m | ...

	if (!$ignore_super && ($priv & 1)) {
		// super can do anything ...
		return 1;
	}

	$tmp = \rkphplib\lib\conf2kv($this->tok_login('conf.role'));
	$privileges = str_replace('=,', '', join(',', $tmp)); // app1.priv1,app1.priv2,app2.priv1,...

	// \rkphplib\lib\log_debug("TLogin.hasPrivileges> require_priv=[$require_priv] priv=[$priv] privileges=[$privileges]");
	$priv_list  = explode(',', $privileges);
	$priv_expr  = $require_priv;

	foreach ($priv_list as $pname) {
		$priv_expr = str_replace($this->tok->getTag($pname), '1', $priv_expr);
	}

	// \rkphplib\lib\log_debug("TLogin.hasPrivileges> priv=[$priv] priv_expr=[$priv_expr] after @privileges");
	$priv_map = [ 'super' => 1, 'ToDo' => 2 ];
	foreach ($priv_map as $pname => $pval) {
		$pval = ($priv & $pval) ? 1 : 0;
		$priv_expr = str_replace($this->tok->getTag($pname), $pval, $priv_expr);
  }

	// \rkphplib\lib\log_debug("TLogin.hasPrivileges> priv_expr=[$priv_expr] after @priv");
	$priv_expr = $this->tok->removeTags($priv_expr, '0');
  $priv_expr = str_replace(' ', '', $priv_expr);

  $rp_check = trim(strtr($priv_expr, '01)(&|!', '       '));
  if ($rp_check != '') {
    throw new Exception('invalid privilege ['.$rp_check.']', "priv_expr=[$priv_expr] require_priv=[$require_priv]");
  }

  $res = eval('return '.$priv_expr.';');
  // \rkphplib\lib\log_debug("TLogin.hasPrivileges> res=[$res] priv_expr=[$priv_expr]");
  return $res;
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
 * @tok {login_clear:}
 *
 * @return ''
 */
public function tok_login_clear() {
	if ($this->sess) {
		$this->setLoginHistory('LOGOUT');
		$this->sess->destroy();
		$this->sess = null;
	}

	return '';
}


/**
 * Insert entry into login history table.
 *
 * @param string $info
 * @param string $data (default = null)
 */
private function setLoginHistory($info, $data = null) {
	if (!$this->sess || !$this->sess->has('id') || !$this->sess->has('login_history_table')) {
		return;
	}

	$r = [];
	$r['mid'] = null;
	$r['lid'] = $this->sess->get('id');
	$r['fingerprint'] = $this->sess->get('fingerprint');
	$r['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
	$r['info'] = $info;
	$r['data'] = $data;
	$r['session_md5'] = md5(session_id());
	$r['_table'] = ADatabase::escape_name($this->sess->get('login_history_table'));

	if ($this->sess->has('admin2user')) {
		$admin = $this->sess->get('admin2user');
		$r['mid'] = $admin['id'];
	}

	$this->db->execute($this->db->getQuery('login_history', $r));
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
			'select_login' => "SELECT *, PASSWORD({:=password}) AS password_input FROM $table ".
				"WHERE login={:=login} AND (status='active' OR status='registered')",
			'registered2active' => "UPDATE $table SET status='active' WHERE id={:=id}",
			'insert' => "INSERT INTO $table (login, password, type, person, language, priv) VALUES ".
				"({:=login}, PASSWORD({:=password}), {:=type}, {:=person}, {:=language}, {:=priv})",
			'login_history' => "INSERT INTO {:=_table} (lid, mid, session_md5, fingerprint, ip, info, data) VALUES ".
				"({:=lid}, {:=mid}, {:=session_md5}, {:=fingerprint}, {:=ip}, {:=info}, {:=data})"
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

	if (!is_null($this->db)) {
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

		if (count($kv) > 1) {
			$query = $this->db->buildQuery($table, 'update', $kv);	
			// \rkphplib\lib\log_debug("tok_login_update> update $table: $query");
			$this->db->execute($query);
		}

		if ($do == 'reload') {
			$kv = $this->db->selectOne("SELECT * FROM $table ".$kv['@where']);
		}
	}

	if (count($kv) > 0) {
		// \rkphplib\lib\log_debug("tok_login_update> #kv=".(count($kv))." update session: ".print_r($kv, true));
		$this->sess->setHash($kv, true);
	}

	return '';
}


/**
 * Compare login with database. If successfull load all columns 
 * from select_login result (except password) into session. Example:
 *
 * @tok {login_auth:}login={get:login}|#|password={get:password}|#|redirect=...|#|log_table=...{:login_auth}
 * @tok {login_auth:}login={get:login}|#|password={get:pass}|#|callback=cms,tok_cms_conf2login{:login_auth}
 * @tok {login_auth:}admin2user=admin:user:visitor:...|#|...{:login_auth}
 *
 * If admin2user is set any admin account can login as user account if login is ADMIN_LOGIN:=USER_LOGIN and
 * password is ADMIN_PASSWORD.
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

	if (!empty($p['callback'])) {
		$tmp = \rkphplib\lib\split_str(',', $p['callback']);
		$plugin = array_shift($tmp);
		$method = array_shift($tmp);
		$this->tok->callPlugin($plugin, $method, $tmp);
	}

	if (!empty($p['log_table'])) {
		$this->logAuth($p['log_table'], $user);
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
 * @param map $user
 */
private function logAuth($table, $user) {
	$fingerprint = '';

	foreach (getallheaders() as $key => $value) {
  	$fingerprint = md5("$fingerprint:$key=$value");
	}

	$this->sess->set('login_history_table', $table);
	$this->sess->set('fingerprint', $fingerprint);

	$this->setLoginHistory('LOGIN');
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
 * Select user from database. Parameter: login, password. Allow admin2user if set.
 * Use ADMIN_LOGIN:=USER_LOGIN as login for admin2user mode, if successfull add
 * user.admin2user = [ id, status, type, ... ]. 
 * 
 * @param array $p
 * @return array|null
 */
private function selectFromDatabase($p) {

	$admin2user = false;

	if (!empty($p['admin2user']) && ($pos = mb_strpos($p['login'], ':=')) > 0) {
		list ($admin_login, $user_login) = explode(':=', $p['login']);
		$admin2user = explode(':', $p['admin2user']);
		$p['login'] = $admin_login;
	}

	$dbres = $this->db->select($this->db->getQuery('select_login', $p));
	if (count($dbres) == 0) {
		$this->tok->setVar('login_error', 'error');
		return null;
	}

 	if (count($dbres) != 1 || empty($dbres[0]['password']) || $dbres[0]['password'] != $dbres[0]['password_input']) {
		$this->tok->setVar('password_error', 'error');
		return;
	}

	if ($admin2user !== false) {
		$admin_type = array_shift($admin2user);
		$admin = $dbres[0];
		unset($admin['password_input']);
		unset($admin['password']);

		if ($admin_type != $admin['type']) {
			throw new Exception("Only $admin_type can use admin2user mode");
		}

		$p['login'] = $user_login;
		$dbres = $this->db->select($this->db->getQuery('select_login', $p));
		if (count($dbres) != 1) {
			$this->tok->setVar('login_error', 'error');
			return null;
		}

		if (!in_array($dbres[0]['type'], $admin2user)) {
			throw new Exception('admin2user is forbidden for user type '.$dbres[0]['type']);
		}

		$dbres[0]['admin2user'] = $admin;
	}

	if ($dbres[0]['status'] == 'registered') {
		// auto-activate
		$this->db->execute($this->db->getQuery('registered2active', $dbres[0]));
	}

	// login + password ok ... update login session
	unset($dbres[0]['password_input']);
	unset($dbres[0]['password']);

	return $dbres[0];
}


/**
 * Return login key value. If key is empty return yes if login[id] is set.
 * Forbidden session value keys in {login:key} are "is_null" and "getConf".
 * Append suffix "?" to prevent Exception if key is missing.
 *
 * @tok {login:} -> yes (if logged in)
 * @tok {login:id} -> ID (value of session key id)
 * @tok {login:*} -> key=value|#|... (show all key value pairs)
 * @tok {login:conf.role.*} -> conf.role.a=value|#|... (show all key value pairs)
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
		$res = $this->sess->getHash();
	}
	else if (strpos($key, '.') > 0) {
		if (substr($key, -2) == '.*') {
			$key = substr($key, 0, -2);
		}

		$required = true;
		if (substr($key, -1) == '?') {
			$key = substr($key, 0, -1);
			$required = false;
		}

		$res = $this->getMapKeys($key, $this->sess->getHash());

		if (($res === false || (is_string($res) && strlen($res) == 0)) && $required) {
			throw new Exception('[login:'.$key.'] no such key in session (use '.$key.'?)');
		}
	}
	else if (substr($key, 0, 1) == '?') {
		$name = substr($key, 1);
		$res = is_null($this->sess->get($name)) ? 1 : '';
	}
	else if (substr($key, 0, 1) == '@') {
		$mkey = substr($key, 1);

		if ($mkey == '*') {
			$res = $this->sess->getHash('meta');
		}
		else if ($mkey == 'since') {
			$res = date('d.m.Y H:i:s', $this->sess->get('start', true, 'meta'));
		}
		else if ($mkey == 'lchange') {
			$res = date('d.m.Y H:i:s', $this->sess->get('last', true, 'meta'));
		}
		else if ($this->sess->has($mkey, 'meta')) {
			$res = $this->sess->get($mkey, true, 'meta');
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

	if (is_array($res)) {
		$res = \rkphplib\lib\kv2conf($res);
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
