<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/TokHelper.trait.php';
require_once $parent_dir.'/Database.class.php';
require_once $parent_dir.'/Session.class.php';
require_once $parent_dir.'/lib/kv2conf.php';
require_once $parent_dir.'/lib/redirect.php';
require_once $parent_dir.'/lib/split_str.php';

use rkphplib\Exception;
use rkphplib\Session;
use rkphplib\ADatabase;
use rkphplib\Database;

use function rkphplib\lib\kv2conf;
use function rkphplib\lib\redirect;
use function rkphplib\lib\split_str;



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

// @var Session $sess 
private $sess = null;

// @var ADatabase $db 
private $db = null;

// @var array $account 
private $account = [];



/**
 * Return {login:}, {login_account:}, {login_check:}, {login_access:}, {login_update:} and {login_clear:}
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['login'] = 0;
	$plugin['login_account'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['login_check'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['login_auth'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['login_auth:basic'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['login_auth:digest'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['login_access'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['login_update'] = TokPlugin::KV_BODY;
	$plugin['login_clear'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;

	return $plugin;
}


/**
 * Send basic auth request if not authenticated.
 *
 * @tok …
 * {login_account:}id=1|#|type=admin|#|login=admin|#|password=secret{:login_account}
 * {login_auth:basic} …
 * realm= Administration|#|
 * message= Bitte geben Sie Ihre Zugangsdaten ein
 * {:login_auth}
 * @EOL
 */
public function tok_login_auth_basic(array $p) : void {
	$default = [
		'realm' => 'Administration',
		'message' => 'Bitte geben Sie Ihre Zugangsdaten ein',
		'required' => ''
	];

	$conf = array_merge($default, $p);

	$this->prepareAuth($conf);

	$ok = empty($_SERVER['PHP_AUTH_USER']) ? -2 : -1;
	for ($i = 0; $ok == -1 && $i < count($this->account); $i++) {
		if ($_SERVER['PHP_AUTH_USER'] == $this->account[$i]['login'] &&
				$_SERVER['PHP_AUTH_PW'] == $this->account[$i]['password']) {
			$ok = $i;
		}
	}

	if ($ok < 0) {
		header('WWW-Authenticate: Basic realm="'.$conf['realm'].'"');
		header('HTTP/1.0 401 Unauthorized');
		print $conf['message'];
		exit;
	}

	$this->authOk($p, $this->account[$ok]);
}


/**
 * Send digest auth request if not authenticated.
 *
 * @ToDo
 * @tok …
 * {login_account:}id=1|#|type=admin|#|login=admin|#|password=secret{:login_account}
 * {login_auth:digest}
 * realm=Administration|#|
 * abort=Authentifizierung abgebrochen|#|
 * invalid=Falsche Zugangsdaten
 * {:login_auth}
 * @EOL
 */
public function tok_login_auth_digest(array $p) : void {
	$default = [
		'realm' => 'Administration',
		'abort' => 'Authentifizierung abgebrochen',
		'invalid' => 'Falsche Zugangsdaten',
		'required' => ''
	];

	$conf = array_merge($default, $p);

	$this->prepareAuth($conf);

	if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
		header('WWW-Authenticate: Digest realm="'.$conf['realm'].'",qop="auth",nonce="'.
			uniqid().'",opaque="'.md5($conf['realm']).'"');
		header('HTTP/1.1 401 Unauthorized');
		die($conf['message.abort']);
	}

	$auth = http_digest_parse($_SERVER['PHP_AUTH_DIGEST']);
	$ok = -1;

	for ($i = 0; is_array($auth) && $ok == -1 && $i < count($this->account); $i++) {
		if ($this->account[$i]['login'] == $auth['username']) {
			$ok = $i;
		}
	}

	if ($ok == -1) {
		die($conf['message.invalid']);
	}

	// see: https://www.php.net/manual/de/features.http-auth.php
	throw new Exception('ToDo ...');

	$this->authOk($p, $this->account[$ok]);
}


/**
 * Check login access. Return ''|1. 
 *
 * @tok {login_access:}redirect={link:}@=login/access_denied{:link}|#|allow={:=super}{:login_access} = redirect to login/access_denied if no super priv
 * @tok {login_access:}privilege={:=super}{:login_access} = 1
 * @tok {login_access:}type=seller{:login_access} -> redirect if login.type != seller
 *
 * @redirect login/access_denied or p.redirect
 */
public function tok_login_access(array $p) : string {
	$redir_url = empty($p['redirect_access_denied']) ? 'login/access_denied' : $p['redirect_access_denied'];
	$res = '';

	if (!empty($p['allow']) && !$this->hasPrivileges($p['allow'])) {
		redirect($redir_url, [ '@link' => 1, '@back' => 1 ]);
	}
	else if (!empty($p['privilege'])) {
		$res = $this->hasPrivileges($p['privilege']);
	}
	else if (!empty($p['type']) && $this->tok_login('type?') != $p['type']) {
		redirect($redir_url, [ '@link' => 1, '@back' => 1 ]);
	}

	return $res;
}


/**
 * Return 1|'' if privileges do (not) exist. Check sess.priv for 2^N privileges.
 * Value of 2^N privileges: super=1, ToDo=2. Check sess.role.app for app privileges.
 */
public function hasPrivileges(string $require_priv, bool $ignore_super = false) : string {

	if (strlen(trim($require_priv)) == 0) {
		return 1;
	}

	$priv = intval($this->sess->get('priv?')); // 2^n | 2^m | ...

	if (!$ignore_super && ($priv & 1)) {
		// super can do anything ...
		return 1;
	}

	$tmp = \rkphplib\lib\conf2kv($this->tok_login('conf.role?'));
	$privileges = str_replace('=,', '', join(',', $tmp)); // app1.priv1,app1.priv2,app2.priv1,...

	// \rkphplib\lib\log_debug("TLogin.hasPrivileges:210> require_priv=[$require_priv] priv=[$priv] privileges=[$privileges]");
	$priv_list  = explode(',', $privileges);
	$priv_expr  = $require_priv;

	foreach ($priv_list as $pname) {
		$priv_expr = str_replace($this->tok->getTag($pname), '1', $priv_expr);
	}

	// \rkphplib\lib\log_debug("TLogin.hasPrivileges:218> priv=[$priv] priv_expr=[$priv_expr] after @privileges");
	$priv_map = [ 'super' => 1, 'ToDo' => 2 ];
	foreach ($priv_map as $pname => $pval) {
		$pval = ($priv & $pval) ? 1 : 0;
		$priv_expr = str_replace($this->tok->getTag($pname), $pval, $priv_expr);
  }

	// \rkphplib\lib\log_debug("TLogin.hasPrivileges:225> priv_expr=[$priv_expr] after @priv");
	$priv_expr = $this->tok->removeTags($priv_expr, '0');
  $priv_expr = str_replace(' ', '', $priv_expr);

  $rp_check = trim(strtr($priv_expr, '01)(&|!', '       '));
  if ($rp_check != '') {
    throw new Exception('invalid privilege ['.$rp_check.']', "priv_expr=[$priv_expr] require_priv=[$require_priv]");
  }

  $res = eval('return '.$priv_expr.';');
  // \rkphplib\lib\log_debug("TLogin.hasPrivileges:235> res=[$res] priv_expr=[$priv_expr]");
  return $res;
}


/**
 * Set session key value.
 *
 * @param any $value
 */
public function set(string $key, $value) : void {
	$this->sess->set($key, $value);
}


/**
 * Logout. Example:
 *
 * @tok {login_clear:}
 * @tok {login_clear:}if={get:logout}|#|redirect=login/byebye{:login_clear}
 *
 * @param hash $p
 * @return ''
 */
public function tok_login_clear(array $p) : void {
	// \rkphplib\lib\log_debug([ "TLogin.tok_login_clear:260> <1>", $p ]);
	if (isset($p['if']) && empty($p['if'])) {
		return;
	}

	if ($this->sess) {
		$this->setLoginHistory('LOGOUT');
		$this->sess->destroy();
		$this->sess = null;
	}

	if (!empty($p['redirect'])) {
		redirect($p['redirect']);
	}
}


/**
 * Insert entry into login history table.
 */
private function setLoginHistory(string $info, string $data = null) : void {
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
 */
public function tok_login_account(array $p) : void {
	$required = [ 'id', 'type', 'login', 'password' ];
	foreach ($required as $key) {
		if (!isset($p[$key])) {
			throw new Exception('[login_account:] missing parameter '.$key);
		}
	}

	array_push($this->account, $p);
}


/**
 * Initialize/Check login session. If no login free area exists,
 * login_check parameter can be use directly in {login_auth:}.
 *
 * @tok …
 * {login_check:}
 * name=login|#|
 * allow_dir=login|#|
 * redirect_login=index.php?dir=login|#|
 * redirect_logout=index.php?dir=login/exit|#|
 * redirect_forbidden=index.php?dir=login/access_denied|#|
 * table=|#|
 * {:login_check}
 * @EOL
 *
 * @tok {login_check:}refresh=login/ajax/refresh.php|#|...{:}
 * @tok {login_check:}redirect_login=...{:} …
 *   check login authentication - if not found or expired redirect to redirect_login
 * @EOL
 */
public function tok_login_check(array $p) : string {
	if (empty($p['name'])) {
		$p['name'] = 'login';
	}

	$this->sess = new Session([ 'required' => [ 'id', 'type' ], 'allow_dir' => [ 'login' ] ]);
	$this->sess->init($p);

	if (!empty($p['table'])) {
		$this->setDBVariable($p['table']);
	}
	else if ($this->sess->has('table', 'meta')) {
		$this->setDBVariable($this->sess->get('table', true, 'meta'));
	}

	return empty($p['refresh']) ? '' : "<script>\n".$this->sess->getJSRefresh($p['refresh'], '', 10)."\n</script>";
}


/**
 * Set db variable.
 */
private function setDBVariable(string $table) : void {
	$table = ADatabase::escape($table);

	$query_map = [
		'select_login' => "SELECT *, PASSWORD({:=password}) AS password_input ".
			"FROM $table WHERE login={:=login} AND (status='active' OR status='registered')",
		'registered2active' => "UPDATE $table SET status='active' WHERE id={:=id}",
		'insert' => "INSERT INTO $table (login, password, type, person, language, priv) VALUES ".
			"({:=login}, PASSWORD({:=password}), {:=type}, {:=person}, {:=language}, {:=priv})",
		'login_history' => "INSERT INTO {:=_table} (lid, mid, session_md5, fingerprint, ip, info, data) VALUES ".
			"({:=lid}, {:=mid}, {:=session_md5}, {:=fingerprint}, {:=ip}, {:=info}, {:=data})"
		];

	$this->db = Database::getInstance(SETTINGS_DSN, $query_map);
}


/**
 * Update login table and session. Use $_REQUEST[key] (key = session key).
 * Overwrite $_REQUEST session map with values from $p. Export _REQUEST['login_update_cols'].
 *
 * @tok {login_update:} -> Use $_REQUEST[key] (key = session key) 
 *   short syntax: @request_keys= a,b,... instead of a={get:a}|#|b={get:b}|#|...
 * @tok {login_update:reload}password=PASSWORD({esc:password}){:login_update} -> update password
 * @tok {login_update:}if=|#|name=Mr. T{:login_update} -> do nothing because if is empty
 * @tok {login_update:}type=admin|#|...{:login_update} -> throw exception if previous type != 'admin'
 * @tok {login_update:}@allow_cols= login, password, ...|#|{sql:password}|#|@where= WHERE id={esc:id}{:login_update}
 *
 * Overwrite default table with @table=custom_table 
 */
public function tok_login_update(string $do, array $p) : void {
	$table = $this->sess->getConf('table');
	$sess = $this->sess->getHash();
	$kv = [];

	if (isset($p['if']) && empty($p['if'])) {
		return;
	}

	if (is_string($p) && strlen(trim($p)) == 0) {
		return;
	}

	if (!empty($p['@table'])) {
		$table = $p['@table'];
		unset($p['@table']);
	}

	if (!empty($p['@where'])) {
		$kv['@where'] = $p['@where'];
		unset($p['@where']);
	}

	if (!empty($p['@type_switch'])) {
		unset($p['@type_switch']);
		$sess = [];
	}

	if (isset($p['type']) && isset($sess['type']) && $p['type'] != $sess['type']) {
		throw new Exception('[login_update:]type='.$p['type'].' != '.$sess['type'].' - session change is forbidden');
	}

	$allow_cols = [];
	if (!empty($p['@allow_cols'])) {
		$allow_cols = split_str(',', $p['@allow_cols']);
		unset($p['@allow_cols']);	
	}

	if (!empty($p['@request_keys'])) {
		$request_keys = split_str(',', $p['@request_keys']);

		foreach ($request_keys as $key) {
			$p[$key] = isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';
		}

		unset($p['@request_keys']);
	}

	// \rkphplib\lib\log_debug("TLogin.tok_login_update:439> table=$table, kv ".print_r($kv, true)."\np ".print_r($p, true));
	$where = empty($kv['@where']) ? '' : $kv['@where'];

	// only add (key,value) to kv where value has changed
	foreach ($sess as $key => $value) {
		if (isset($_REQUEST[$key]) && $value != $_REQUEST[$key]) {
			$kv[$key] = $_REQUEST[$key];
			// \rkphplib\lib\log_debug("TLogin.tok_login_update:446> kv (sess + request) - $key=".$_REQUEST[$key]);
		}
	}

	foreach ($p as $key => $value) {
		// e.g. !isset(kv['password'])
		if (substr($key, 0, 1) != '@' && (!isset($kv[$key]) || $kv[$key] != $value)) {
			$kv[$key] = $value;
			// \rkphplib\lib\log_debug("TLogin.tok_login_update:454> kv (p) - $key=$value");
		}
	}

	$session_cols = [];
	if (!empty($p['@session_cols'])) {
		$sess_col_list = split_str(',', $p['@session_cols']);
		foreach ($sess_col_list as $col) {
			if (isset($kv[$col])) {
				$session_cols[$col] = $kv[$col];
			}
		}
	}

	if (count($allow_cols) > 0) {
		$has_cols = array_keys($kv);

		foreach ($has_cols as $col) {
			if (!in_array($col, $allow_cols)) {
				// \rkphplib\lib\log_debug("TLogin.tok_login_update:473> unset forbidden column $col");
				unset($kv[$col]);
			}
		}
	}

	if (isset($kv['password']) && empty($kv['password'])) {
		unset($kv['password']);
	}

	if (!is_null($this->db)) {
		if (!$where) {
			$id = empty($p['id']) ? '' : $p['id'];
			if (!$id) {
				$id = empty($sess['id']) ? '' : $sess['id'];
			}

			if ($id && is_numeric($id)) {
				$where = "WHERE id='".intval($id)."'";
				// \rkphplib\lib\log_debug("TLogin.tok_login_update:492> id=$id where=$where");
			}
		}

		if (empty($where)) {
			throw new Exception('missing @where parameter (= WHERE primary_key_of_'.$table."= '...')");
		}

		// \rkphplib\lib\log_debug("TLogin.tok_login_update:500> do=$do, table=$table, where=$where, kv: ".print_r($kv, true));
		if (count($kv) > 0 && !empty($table) && $do != 'no_db') {
			$kv['@where'] = $where;

			$dbres = $this->db->select("SELECT * FROM $table $where");

			$query = (count($dbres) == 1) ? $this->db->buildQuery($table, 'update', $kv) : $this->db->buildQuery($table, 'insert', $kv);

			// \rkphplib\lib\log_debug("TLogin.tok_login_update:508> query=$query");
			if (!empty($query)) {
				$this->db->execute($query);
			}

			unset($kv['@where']);
		}

		if ($do == 'reload') {
			$kv = $this->db->selectOne("SELECT * FROM $table ".$where);
		}
	}

	if (count($session_cols) > 0) {
		// \rkphplib\lib\log_debug("TLogin.tok_login_update:522> use session_cols: ".print_r($session_cols, true));
		$this->sess->setHash($session_cols, true);
	}
	else if (count($kv) > 0) {
		// \rkphplib\lib\log_debug("TLogin.tok_login_update:526> #kv=".(count($kv))." update session: ".print_r($kv, true));
		$this->sess->setHash($kv, true);
	}
}


/**
 * Compare login with database. If successfull load all columns 
 * from select_login result (except password) into session. Example:
 *
 * @tok {login_auth:}login={get:login}|#|password={get:password}|#|redirect=...|#|log_table=...{:login_auth}
 * @tok {login_auth:}login={get:login}|#|password={get:pass}|#|callback=cms,tok_cms_conf2login{:login_auth}
 * @tok {login_auth:}login={get:email}|#|password={get:postcode}|#|select_login= SELECT *, postcode AS password_input, email AS login ...
 * @tok {login_auth:}admin2user=admin:user:visitor:...|#|...{:login_auth}
 * @tok {login_auth:}create_table= @1 cms_conf, cms_login_history|#|...{:login_auth}
 *
 * If admin2user is set any admin account can login as user account if login is ADMIN_LOGIN:=USER_LOGIN and
 * password is ADMIN_PASSWORD.
 *
 * Use multi_table=table1, table2, ... to check multiple tables (and select_login_TABLE= ... if necessary).
 *
 * If login is invalid set {var:login_error} = invalid.
 * If password is invalid set {var:password_error} = invalid.
 * If redirect is set - redirect after successfull login or if still logged in.
 * If user.redirect is set - redirect after successfull login or if still logged in.
 * If log_table is set - insert log entry into log table. 
 * If select_list=a,b is set execute p.select_a and p.select_b (replace {:=id}).
 *
 * @tok …
 * {login_auth:}
 * login={get:login}|#|
 * password={get:password}|#|
 * select_list=contract,bag|#|
 * select_contract= SELECT sum(item_num) AS `contract.item_num` FROM shop_contract 
 *   WHERE customer={:=id} AND start <= NOW() and end > NOW()|#|
 * select_bag= SELECT * FROM shop_bag WHERE customer={:=id} ORDER BY id DESC LIMIT 1
 * {:login_auth}
 * <pre>{login:*}</pre> = id=...|#|login=...|#|type=...|#|priv=...|#|language=...
 * @EOL
 * 
 * @redirect if $p[redirect], $p[redirect.$user[type]] or $user[redirect]
 */
public function tok_login_auth(array $p) : void {

	$this->prepareAuth($p);

	if (empty($p['login'])) {
		if (!is_null($this->db)) {
			$this->createTable($this->sess->getConf('table'));
			if (isset($p['create_table']) && is_array($p['create_table'])) {
				foreach ($p['create_table'] as $table) {
					$this->db->createTable([ '@table' => $table ]);
				}
			}
		}

		if (!empty($p['password'])) {
			$this->tok->setVar('login_error', 'required');
		}

		return;
	}

	if (empty($p['password'])) {
		if (!empty($p['login'])) {
			$this->tok->setVar('password_error', 'required');
		}

		return;
	}

	if (is_null($this->db) && !empty($p['table'])) {
		$this->setDBVariable($p['table']);
		$this->sess->set('table', $p['table'], 'meta');
	}

	// \rkphplib\lib\log_debug("TLogin.tok_login_auth:602> p: ".print_r($p, true));
	if (!is_null($this->db)) {
		$user = $this->selectFromDb($p);
	}
	else {
		$user = $this->selectFromAccount($p);
	}

	$this->authOk($p, $user);
}


/**
 * Call login_check: if session is not set.
 * Redirect if session.id and p.redirect or p.redirect_$session[type].
 */
private function prepareAuth(array $p) : void {

	if (is_null($this->sess)) {
		$this->tok_login_check($p);
	}

	if ($this->sess->has('id')) {
		if (($type = $this->sess->has('type'))) {
			if (!empty($p['redirect_'.$type])) {
				redirect($p['redirect_'.$type]);
			}
		}
		
		if (!empty($p['redirect'])) {
			redirect($p['redirect']);
		}
	}
}


/**
 * Save $user (except password) to session. 
 * If $p[log_table] call setLoginHistory.
 * Redirect if $p[redirect], $user[redirect] or $p[redirect_$user[type]].
 * Execute plugin call if callback= plugin, method, arg.
 */
private function authOk(array $p, ?array $user) : void {
	if (is_null($user)) {
		return;
	}

	unset($user['password']);

	$this->sess->setHash($user);

	if (!empty($p['callback'])) {
		$tmp = split_str(',', $p['callback']);
		$plugin = array_shift($tmp);
		$method = array_shift($tmp);
		$this->tok->callPlugin($plugin, $method, $tmp);
	}

	if (!empty($p['log_table'])) {
		$fingerprint = '';
		foreach (getallheaders() as $key => $value) {
  		$fingerprint = md5("$fingerprint:$key=$value");
		}

		$this->sess->set('login_history_table', $p['log_table']);
		$this->sess->set('fingerprint', $fingerprint);
		$this->setLoginHistory('LOGIN');
	}

	$redirect_to = '';
	if (!empty($user['redirect'])) {
		$redirect_to = $this->tok->replaceTags($user['redirect'], $user);
	}
	else if (!empty($user['type']) && !empty($p['redirect_'.$user['type']])) {
		$redirect_to = $p['redirect_'.$user['type']];
	}
	else if (!empty($p['redirect'])) {
		$redirect_to = $this->tok->replaceTags($p['redirect'], $user);
	}

	if ($redirect_to) {
		redirect($redirect_to);
	}
}


/**
 * Select user from database
 */
private function selectFromDb(array $p) : ?array {
	$user = null;

	if (!empty($p['multi_table'])) {
		if (!is_null($this->db) && !empty($p['login']) && !empty($p['password'])) {
			$list = split_str(',', $p['multi_table']);

			for ($i = 0; is_null($user) && $i < count($list); $i++) {
				$p['table'] = $list[$i];
				$table = ADatabase::escape_name($p['table']);
				$p['select_login'] = !empty($p['select_login_'.$p['table']]) ? $p['select_login_'.$p['table']] : 
					"SELECT *, PASSWORD({:=password}) AS password_input FROM $table ".
					"WHERE login={:=login} AND (status='active' OR status='registered')";
				$p['registered2active'] = "UPDATE $table SET status='active' WHERE id={:=id}";
				$user = $this->selectFromDatabase($p);
			}
		}
	}
	else {
		$user = $this->selectFromDatabase($p);
	}

	if (!is_null($user) && !empty($p['select_list'])) {
		$list = split_str(',', $p['select_list']);
		$r = $user;

		foreach ($list as $prefix) {
			$user = array_merge($user, $this->selectExtraData('select_'.$prefix, $p, $r));
		}
	}

	return $user;
}


/**
 * Select user from account. Parameter: login, password.
 */
private function selectFromAccount(array $p) : ?array {
	$found = false;

	if (count($this->account) == 0) {
		throw new Exception('no account defined - use [login_account:]...');
	}

	$login_ok = false;
	$password_ok = false;

	for  ($i = 0; $found === false && $i < count($this->account); $i++) {
		if ($this->account[$i]['login'] == $p['login'] && $this->account[$i]['password'] == $p['password']) {
			$found = $i;
			$login_ok = true;
			$password_ok = true;
		}
		else if (!$login_ok && $this->account[$i]['login'] == $p['login']) {
			$login_ok = true;
		}
		else if (!$password_ok && $this->account[$i]['password'] == $p['password']) {
			$password_ok = true;
		}
	}

	if (!$login_ok) {
		$this->tok->setVar('login_error', 'invalid');
	}

	if (!$password_ok) {
		$this->tok->setVar('password_error', 'invalid');
	}

	return ($found !== false) ? $this->account[$found] : null;
}


/**
 * Select extra data from database. Query is p[qkey].
 */
private function selectExtraData(string $qkey, array $p, array $replace) : array {

	$this->db->setQuery('extra_data', $p[$qkey]);
	$dbres = $this->db->select($this->db->getQuery('extra_data', $replace));
	if (count($dbres) == 0) {
		return $dbres;
	}

 	if (count($dbres) > 1) {
		throw new Exception('more than one result row', "query=".$this->db->getQuery('extra_data', $replace)."\ndbres: ".print_r($dbres, true));
	}

	$res = [];
	foreach ($dbres[0] as $key => $value) {
		$res[$key] = $value;
	}

	// \rkphplib\lib\log_debug("TLogin.selectExtraData:785> qkey=$qkey, return ".print_r($res, true));
	return $res;
}


/**
 * Select user from database. Parameter: login, password. Allow admin2user if set.
 * Use ADMIN_LOGIN:=USER_LOGIN as login for admin2user mode, if successfull add
 * user.admin2user = [ id, status, type, ... ]. Use p.master_password to login as
 * someone else.
 * 
 * @param hash $p
 * @return hash|null
 */
private function selectFromDatabase(array $p) : ?array {
	$admin2user = false;

	if (!empty($p['admin2user']) && ($pos = mb_strpos($p['login'], ':=')) > 0) {
		list ($admin_login, $user_login) = explode(':=', $p['login']);
		$admin2user = explode(':', $p['admin2user']);
		$p['login'] = $admin_login;
	}

	$query = $this->db->getCustomQuery('select_login', $p);
	$dbres = $this->db->select($query);
	// \rkphplib\lib\log_debug("TLogin.selectFromDatabase:810> query=$query - ".print_r($dbres, true));
	if (count($dbres) == 0) {
		$this->tok->setVar('login_error', 'invalid');
		return null;
	}

	// \rkphplib\lib\log_debug('TLogin.selectFromDatabase:816> use master_password = PASSWORD('.$p['password'].') = '.$dbres[0]['password_input']);
	if (!empty($p['master_password']) && $dbres[0]['password_input'] == $p['master_password']) {
		$dbres[0]['password'] = $p['master_password'];
	}

 	if (count($dbres) != 1 || empty($dbres[0]['password']) || $dbres[0]['password'] != $dbres[0]['password_input']) {
		$this->tok->setVar('password_error', 'invalid');
		return null;
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
		$query = $this->db->getCustomQuery('select_login', $p);
		$dbres = $this->db->select($query);
		if (count($dbres) != 1) {
			$this->tok->setVar('login_error', 'invalid');
			return null;
		}

		if (!in_array($dbres[0]['type'], $admin2user)) {
			throw new Exception('admin2user is forbidden for user type '.$dbres[0]['type']);
		}

		$dbres[0]['admin2user'] = $admin;
	}

	if ($dbres[0]['status'] == 'registered') {
		$query = $this->db->getCustomQuery('registered2active', $dbres[0]);
		// \rkphplib\lib\log_debug("TLogin.selectFromDatabase:853> auto-activate user: ".$query);
		$this->db->execute($query);
	}

	// login + password ok ... update login session
	unset($dbres[0]['password_input']);
	unset($dbres[0]['password']);

	// \rkphplib\lib\log_debug("TLogin.selectFromDatabase:861> return user: ".print_r($dbres[0], true));
	return $dbres[0];
}


/**
 * Return login key value. If key is empty return yes if login[id] is set.
 * Forbidden session value keys in {login:key} are "is_null" and "getConf".
 * Append suffix "?" to prevent Exception if key is missing.
 *
 * @tok {login:} -> yes (if logged in)
 * @tok {login:id} or {login:}id{:login} -> ID (value of session key id)
 * @tok {login:*} -> key=value|#|... (show all key value pairs)
 * @tok {login:conf.role.*} -> conf.role.a=value|#|... (show all key value pairs)
 *
 * @tok {login:?age} -> 1|'' (1: {login:age} == null)
 *
 * @tok {login:@*} -> key=value|#|... (show all meta data)
 * @tok {login:@[name|table]} -> show configuration value
 * @tok {login:@since} -> date('d.m.Y H:i:s', @start)
 * @tok {login:@lchange} -> date('d.m.Y H:i:s', @last)
 */
public function tok_login(string $key, ?string $alt_key = '') : ?string {
	// \rkphplib\lib\log_debug("TLogin.tok_login:884> key=$key alt_key=$alt_key");
	$res = '';

	if (strlen($key) == 0 && strlen($alt_key) > 0) {
		$key = trim($alt_key);
	}

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
		$res = kv2conf($res);
	}

	// \rkphplib\lib\log_debug("TLogin.tok_login:948> res=[$res]");
	return $res;
}


/**
 * Create login table. Create admin user admin|admin (id=1).
 */
public function createTable(string $table) : void {
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

	if (!is_null($this->db) && ($ct = $this->db->createTable($tconf))) {
		if ($ct != 2) {
			$this->db->execute($this->db->getQuery('insert',
				[ 'login' => 'admin', 'password' => 'admin', 'type' => 'admin',
					'person' => 'Administrator', 'language' => 'de', 'priv' => 3 ]));
		}
	}
}


}
