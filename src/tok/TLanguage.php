<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Exception.php';
require_once __DIR__.'/../Database.php';
require_once __DIR__.'/../Session.php';
require_once __DIR__.'/../File.php';

use rkphplib\Exception;
use rkphplib\Database;
use rkphplib\ADatabase;
use rkphplib\Session;
use rkphplib\File;


/**
 * Multilanguage plugin. Use Database and Session (site.language).
 * Set SETTINGS_LANGUAGE = de if unset.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TLanguage implements TokPlugin {

// @var ADatabase $db 
private $db = null;

// @var Session $sess 
private $sess = null;

// @var map $conf 
private $conf = [];

// @var Tokenizer $tok 
private $tok = null;



/**
 * Return {language:init|get|script}, {txt:js}, {t:} and {ptxt:}
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [
		'language:init' => TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY, 
		'language:get' => TokPlugin::NO_PARAM | TokPlugin::NO_BODY,
		'language:script' => TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO,
		'language' => 0,
		'txt:js' => 0,
		'txt' => 0,
		't' => 0,
		'ptxt' => TokPlugin::LIST_BODY
	];

	return $plugin;
}


/**
 * Prepare database connection.
 * 
 * @param string $dsn (default = SETTINGS_DSN)
 * @param map $opt (default = [ 'table' => 'language', 'language' => SETTINGS_LANGUAGE, 'default' =>  SETTINGS_LANGUAGE])
 * @param string $language (default = SETTINGS_LANGUAGE)
 */
public function setDSN($dsn = SETTINGS_DSN, $opt = [ 'table' => 'language', 'use' => SETTINGS_LANGUAGE, 'default' => SETTINGS_LANGUAGE ]) {
	
	$table = ADatabase::escape($opt['table']);
	$default = ADatabase::escape($opt['default']);
	$use = ADatabase::escape($opt['use']);

	$query_map = [
		'select' => "SELECT $use AS lang, $default AS default_lang, txt FROM $table WHERE id='{:=id}'",
		'insert' => "INSERT INTO $table (id, lchange, txt) VALUES ('{:=id}', NOW(), '{:=txt}')",
		'delete' => "DELETE FROM $table WHERE id='{:=id}'"
	];

	// \rkphplib\lib\log_debug("TLanguage.setDSN:80> table=$table use=$use default=$default select: ".$query_map['select']);
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
	$tconf = [];
	$tconf['@table'] = $table;
	$tconf['id'] = 'varbinary:50::3';
	$tconf['@timestamp'] = 2;
	$tconf['dir'] = 'varbinary:255::8';
	$tconf['txt'] = 'text:::1';

	foreach ($language_list as $lang) {
		$tconf[$lang] = 'text:::1';
	}

	$this->db->createTable($tconf);
}	


/**
 * Save language to session object (name=$name, scope=docroot and unlimited=1).
 * Update SESSION_LANGUAGE to $language if not defined. 
 * 
 * @throws
 * @see Session
 * @param hash $p (use p.use, p.default, p.table)
 * @return string(2) current language
 */
public function initSession($p) {
	$p['use'] = empty($p['use']) ? '' : strtolower($p['use']);

	if (!empty($p['use']) && strlen($p['use']) !== 2) {
		throw new Exception('invalid language', "language=".$p['use']);
	}

	$name = empty($p['table']) ? 'language' : $p['table'];

	$this->sess = new Session();
	$this->sess->init([ 'name' => $name, 'scope' => 'docroot', 'unlimited' => 1 ]);

	if (!empty($p['use'])) {
		$this->sess->set('language', $p['use']);
		$res = $p['use'];
	}
	else if (!$this->sess->has('language')) {
		$this->sess->set('language', $p['default']);
		$res = $p['default'];
	}
	else {
		$res = $this->sess->get('language');

		if (empty($res)) {
			$this->sess->set('language', $p['default']);
			$res = $p['default'];
		}
	}

	// \rkphplib\lib\log_debug("TLanguage.initSession:146> res=[$res] p: ".print_r($p, true));
	return $res;
}


/**
 * Initialize language plugin. Update SETTINGS_LANGUAGE. Parameter:
 * 
 *  table: language (default)
 *  dsn: SETTINGS_DSN (default)
 *  txt: language inside txt plugin (default = SETTINGS_LANGUAGE)
 *  default: SETTINGS_LANGUAGE (default)
 *  untranslated: mark untranslaged, e.g. keep (return {txt:$param}$arg{:txt}, 
 *    <font style="background-color:red">{:=txt}</font>) (default = '' = return $arg)
 *  use: switch language e.g. {get:language} - disable with _REQUEST[ignore_language] (default = '' = use p.default)
 *
 * @throws  
 * @param map<string:string> $p
 * @return ''
 */
public function tok_language_init($p) {

	$default = [ 'table' => 'language', 'dsn' => SETTINGS_DSN, 'txt' => SETTINGS_LANGUAGE, 'default' => SETTINGS_LANGUAGE, 
		'use' => '', 'untranslated' => '' ];

	$p = array_merge($this->conf, $p);
	$p = array_merge($default, $p);

	if (!empty($_REQUEST['ignore_language'])) {
		$p['use'] = '';
	}

	if (empty($p['default'])) {
		$p['default'] = SETTINGS_LANGUAGE;
	}

	$p['use'] = $this->initSession($p);
	$this->setDSN($p['dsn'], $p);
	$this->conf = $p;

	// \rkphplib\lib\log_debug("TLanguage.tok_language_init:186> conf=".print_r($p, true));
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
 * Return translated file path (if found). Try
 * {language:get}/$path and dirname($path)/basename($path).{language:get}.suffix($path).
 *
 * @param string $path
 * @return string
 */
public function tok_language_script($path) {
	$res = trim($path);
	$lang = $this->tok_language_get();

	if (File::exists("$lang/$res")) {
		$res = '<script src="'.$lang.'/'.$res.'"></script>';
	}
	else if (File::exists(dirname($res).'/'.File::basename($res, true).'/'.$lang.'/'.File::suffix($res, true))) {
		$res = '<script src="'.dirname($res).'/'.File::basename($res, true).'/'.$lang.'/'.File::suffix($res, true).'"></script>';
	}
	else {
		$res = '<script>{include:static}'.$res.'{:include}</script>';
	}

	return $res;
}


/**
 * Return txt|ptxt id. Translation id is either $param or md5(remove_whitespace($txt)) (unless $txt == '=id').
 *
 * @tok {txt:}=ID{:txt} = ID, {txt:}Something{:txt} -> ID=md5(Something)
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
			$id = md5($txt_id);
		}
		else {
			throw new Exception('empty txt id - look for {txt:}without closure', "param=[$param] txt=[$txt]");
		}
	}

	return $id;
}


/**
 * Same as {txt:} but result is javascript escaped.
 * 
 * @tok {txt:js}Are you Shure?{:txt} -> 'Are your Shure?'
 * @tok {txt:js}Don't do this!\nOk?{:txt} -> 'Don\'t do this!' + "\n" + 'Ok?'
 *
 * @throws
 * @see tok_txt
 * @param string $id
 * @param string $txt
 * @return string
 */
public function tok_txt_js($id, $txt) {
	$translation = str_replace("'", "\\'", $this->tok_txt($id, $txt));
	$lines = preg_split("/\r?\n/", $translation);
	$res = "'".join("' + \"\\n\" + '", $lines)."'";

	if (!empty($id) && substr($id, -1) == '=') {
		$res = "'".substr($id, 0, -1)."': ".$res;
	}

	return $res;
}


/**
 * @alias tok_txt
 */
public function tok_t($param, $txt) {
	return $this->tok_txt($param, $txt);
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
	// \rkphplib\lib\log_debug("TLanguage.tok_txt:317> param=$param txt=$txt is_null(this->db)=".is_null($this->db));
	if (is_null($txt)) {
		$txt = '';
	}

	if (is_null($this->db)) {
		return $txt;
	}

	if (empty($param) && empty($txt)) {
		return $txt;
	}

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
		$res = empty($trans['default_lang']) ? $this->untranslated('txt', $param, $txt) : $trans['default_lang'];
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
		return str_replace($this->tok->getTag('txt'), $arg, $this->conf['untranslated']);
	}
}


}
