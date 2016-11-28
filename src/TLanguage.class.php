<?php

namespace rkphplib;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/Database.class.php');
require_once(__DIR__.'/Session.class.php');

use rkphplib\Exception;


/**
 * Multilanguage plugin. Use Database and Session (site.language).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TLanguage implements TokPlugin {

/** @var ADatabase $db */
private $db = null;

/** @var Session $sess */
private $sess = null;

/** @var map $conf */
private $conf = [];

/** @var Tokenizer $tok */
private $tok = null;



/**
 * Return Tokenizer plugin list:
 * 
 *  language:init|get, txt, ptxt, dtxt
 * 
 * @param Tokenizer $tok
 * @return map <string:int>
 */
public function getPlugins($tok) {

	$this->tok = $tok;

	$plugin = [
		'language:init' => TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY, 
		'language:get' => TokPlugin::NO_PARAM | TokPlugin::NO_BODY,
		'language' => 0,
		'txt' => 0,
		'ptxt' => TokPlugin::LIST_BODY
	];

	return $plugin;
}


/**
 * Prepare database connection.
 * 
 * @param string $dsn (default = SETTINGS_DSN)
 * @param map $opt (default = [ 'table' => 'language', 'language' => SETTINGS_LANGUAGE, 'default' => 'txt' ])
 * @param string $language (default = SETTINGS_LANGUAGE)
 */
public function setDSN($dsn = SETTINGS_DSN, $opt = [ 'table' => 'language', 'use' => SETTINGS_LANGUAGE, 'default' => 'txt' ]) {
	
	$query_map = [
		'@query_prefix' => '',

		'escape_name@table' => $opt['table'], 
		'escape_name@use' => $opt['use'],
		'escape_name@default' => $opt['default'],

		'select' => "SELECT {:=@use} AS lang, {:=@default} AS default, txt FROM {:=@table} WHERE id='{:=id}'",
		'insert' => "INSERT INTO {:=@table} (id, lchange, txt) VALUES ('{:=id}', NOW(), '{:=txt}')",
		'delete' => "DELETE FROM {:=@table} WHERE id='{:=id}'"
	];

	$this->db = Database::getInstance($dsn, $query_map);
	$this->createTable($opt['table']);
}


/**
 * Create language table.
 * 
 * @param string $table (default = 'language')
 * @param vector<string[2]> $language_list (default = [ de, en ])
 */
public function createTable($table = 'language', $language_list = [ 'de', 'en' ]) {

	if ($this->db->hasTable($table)) {
		return;
	}

	$tconf = [];
	$tconf['@table'] = $table;
	$tconf['@timestamp'] = 2;
	$tconf['id'] = 'varchar:50::3';
	$tconf['txt'] = 'text:::1';

	foreach ($language_list as $lang) {
		$tconf[$lang] = 'text:::1';
	}

	$this->db->createTable($tconf);
}	


/**
 * Save language to session object (name=$name, scope=docroot and unlimited=1).
 * Update SESSION_LANGUAGE to $language if necessary. 
 * 
 * @throws
 * @see Session
 * @param string $language
 * @param string $name (default = language)
 */
public function initSession($language, $name = 'language') {

	if (empty($language) || mb_strlen($language) !== 2) {
		throw new Exception('invalid language', "language=$language");
	}

	$this->sess = new Session();
	$this->sess->init([ 'name' => $name, 'scope' => 'docroot', 'unlimited' => 1 ]);

	if (!$this->sess->has('language') || !$this->sess->get('language') !== $language) {
		$this->sess->set('language', $language);
		define('SESSION_LANGUAGE', $language);
	}
}


/**
 * Initialize language plugin. Update SETTINGS_LANGUAGE. Parameter:
 * 
 *  table: language (default)
 *  dsn: SETTINGS_DSN (default)
 *  txt: language inside txt plugin (default = SETTINGS_LANGUAGE)
 *  default: 'txt' (default)
 *  untranslated: mark untranslaged, e.g. keep (return {txt:$param}$arg{:txt}, 
 *    <font style="background-color:red">{:=txt}</font>) (default = '' = return $arg)
 *  use: switch language e.g. {get:language} (default = '' = use p.default)
 *
 * @throws  
 * @param map<string:string> $p
 * @return ''
 */
public function tok_language_init($p) {

	$default = [ 'table' => 'language', 'dsn' => SETTINGS_DSN, 'txt' => SETTINGS_LANGUAGE, 'default' => 'txt', 'use' => '', 'untranslated' => '' ];

	foreach ($default as $key => $value) {
		if (empty($p[$key])) {
			$p[$key] = $value;
		}
	}

	$p['use'] = empty($p['use']) ? $p['default'] : $p['use'];
	$this->setDSN($p['dsn'], $p);
	$this->initSession($p['use'], $p['table']);
	$this->conf = $p;

	return '';
}


/**
 * Return current language (SETTINGS_LANGUAGE).
 *
 * @return string
 */
public function tok_language_get() {

	if (is_null($this->sess) || !$this->sess->has('language')) {
		return SETTINGS_LANGUAGE;
	}

	return $this->sess->get('language');
}


/**
 * Return txt|ptxt id. Translation id is either $param or md5(remove_whitespace($txt)) (unless $txt == '=id').
 * 
 * @throws
 * @param string $param
 * @param string $txt
 * @return string
 */
public function getTxtId($param, $txt) {

	if (!empty($param)) {
		$id = $param;
	}
	else {
		$txt_id = preg_replace("/[\s]+/", '', $txt);

		if (mb_substr($txt, 0, 1) === '=') {
			$id = mb_substr($txt_id, 1);
		}
		else if (!empty($txt_id)) {
			$id = $txt_id;
		}
		else {
			throw new Exception('empty txt id');
		}
	}

	return $id;
}


/**
 * Return translation. Select language or default based on getTxtId(). 
 * If Text is not translated return untranslated(). If text has changed
 * remove translation in database. Text $txt must be static otherwise use tok_ptxt(). 
 * Examples:
 *
 * - {txt:}Hallo {get:firstname} {get:lastname}{:txt} -> invalid !!
 * - {txt:}Hallo{:txt} -> id = md5('Hallo')
 * - {txt:hi}Hallo{:txt} -> id = hi
 * - {txt:}=hi{:txt} -> id = hi 
 *
 * @throws
 * @see getTxtId
 * @see untranslated
 * @see tok_ptxt
 * @param string $param custom id or empty
 * @param string $txt default text or empty
 * @return string
 */
public function tok_txt($param, $txt) {

	$id = $this->getTxtId($param, $txt);

	if (($trans = $this->db->query('select', [ 'id' => $id ], -1)) === false) {
		// no translation - insert untranslated text and return raw
		$this->db->query('insert:exec', [ 'id' => $id, 'txt' => $txt ]);
		return $this->untranslated('txt', $param, $txt);
	}

	if ($trans['txt'] !== $txt) {
		// text has changed: remove translation, insert untranslated text and return raw
		$this->db->query('delete:exec', [ 'id' => $id ]);
		$this->db->query('insert:exec', [ 'id' => $id, 'txt' => $txt ]);
		return $this->untranslated('txt', $param, $txt);
	}

	$res = $trans['lang'];
	if (empty($res)) {
		$res = empty($trans['default']) ? $this->untranslated('txt', $param, $txt) : $trans['default'];
	}

	return $res;
}


/**
 * Return translation of text with parameters. Example:
 * 
 * {ptxt:}Hallo $p1x $p2x|#|{get:firstname}|#|{get:lastname}{:ptxt} -> {txt:}Hallo $p1x $p2x{:txt} 
 *   with replacement $p1x={get:firstname} and $p2x={get:lastname}
 * 
 * @see txt
 * @param string $param
 * @param vector $p
 * @return string
 */
public function tok_ptxt($param, $p) {
	$res = $this->tok_txt($param, array_shift($p));

	for ($i = 0; $i < count($p); $i++) {
		$res = str_replace('$p'.($i + 1).'x', $p[$i], $res);
	}

	return $res;
}


/**
 * Return string. If conf.untranslated is empty return $arg.
 * If conf.untranslated=keep return {$action:$param}$arg{:$action}.
 * Otherwise replace {:=txt} in conf.untranslated and return.
 *
 * @throws
 * @param string $action
 * @param string $param
 * @param string $arg
 * @return string
 */
public function untranslated($action, $param, $arg) {

	if (empty($this->conf['untranslated'])) {
		return $arg;
	}
	else if ($this->conf['untranslated'] === 'keep') {
		return $tok->getPluginTxt($action.$tok->rx[2].$param, $arg);
	}
	else {
		return str_replace('{:=txt}', $arg, $this->conf['untranslated']);
	}
}


}
