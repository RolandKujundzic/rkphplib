<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once __DIR__.'/TokPlugin.iface.php';
require_once $parent_dir.'/Exception.class.php';
require_once $parent_dir.'/File.class.php';
require_once $parent_dir.'/JSON.class.php';
require_once $parent_dir.'/lib/htmlescape.php';
require_once $parent_dir.'/lib/split_str.php';
require_once $parent_dir.'/lib/redirect.php';
require_once $parent_dir.'/lib/conf2kv.php';
require_once $parent_dir.'/lib/kv2conf.php';
require_once $parent_dir.'/lib/entity.php';
require_once $parent_dir.'/lib/array_join.php';

use rkphplib\Exception;
use rkphplib\File;
use rkphplib\JSON;

use function rkphplib\lib\htmlescape;
use function rkphplib\lib\array_join;
use function rkphplib\lib\split_str;
use function rkphplib\lib\redirect;
use function rkphplib\lib\conf2kv;
use function rkphplib\lib\kv2conf;
use function rkphplib\lib\entity;


if (php_sapi_name() == 'cli' && !defined('SETTINGS_CLI_INPUT')) {
	require_once $parent_dir.'/lib/cli_input.php';
	\rkphplib\lib\cli_input();
}

if (!defined('SETTINGS_REQ_CRYPT')) {
  // @const SETTINGS_REQ_CRYPT = '' (''=default|cx)
  define('SETTINGS_REQ_CRYPT', '');
}

if (!defined('SETTINGS_CRYPT_SECRET')) {
  // @const SETTINGS_CRYPT_SECRET = md5(Server + Module Info) if undefined
  $tmp = function() {
    $module_list = array_intersect([ 'zlib', 'date' ], get_loaded_extensions());
    $secret = md5(PHP_OS.'_'.php_uname('r'));

    foreach ($module_list as $name) {
      $secret .= md5(print_r(ini_get_all($name), true));
    }

    return $secret;
  };

  define('SETTINGS_CRYPT_SECRET', $tmp());
}


/**
 * Basic Tokenizer plugins. Decode encrypted links in constructor.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TBase implements TokPlugin {

// @var Tokenizer $_tok
private $_tok = null;

// @var Hash $_tpl
private $_tpl = [];

// @var Hash $_conf
private $_conf = [];



/** 
 * Constructor. Decode crypted query data. Use either ?SETTINGS_REQ_CRYPT=CRYPTED or ?CRYPTED.
 */
public function __construct() {
	if (!empty($_REQUEST[SETTINGS_REQ_CRYPT])) {
		self::decodeHash($_REQUEST[SETTINGS_REQ_CRYPT], true);
	}
	else if (!empty($_SERVER['QUERY_STRING']) && self::isEncodedHash($_SERVER['QUERY_STRING'])) {
		self::decodeHash($_SERVER['QUERY_STRING'], true);
	}
	else {
		foreach ($_REQUEST as $key => $value) {
			if (is_string($value) && strlen($value) == 0 && self::isEncodedHash($key)) {
				self::decodeHash($key, true);
				unset($_REQUEST[$key]);
			}
		}
	}
}


/**
 * Return Tokenizer plugin list:
 *
 * @plugin row, row:init, tpl_set, tpl, tf, t, f, true, false
 * @plugin find, filter, plugin, escape, escape:tok, unescape
 * @plugin encode, decode, get, const, include, include_if, view, clear
 * @plugin ignore, if, switch, keep, load, link, redo, toupper, tolower
 * @plugin hidden, trim, join, set, set_default, redirect, var, esc, log
 * @plugin shorten, strlen, json, json:exit
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->_tok = $tok;

	$plugin = [];

	$plugin['row:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY | TokPlugin::IS_STATIC;
	$plugin['row'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_CSLIST | TokPlugin::REQUIRE_BODY | 
		TokPlugin::LIST_BODY | TokPlugin::IS_STATIC;

	$plugin['tpl_set'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_LIST | TokPlugin::REQUIRE_BODY | 
		TokPlugin::TEXT | TokPlugin::IS_STATIC;

	$plugin['tpl'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_LIST | TokPlugin::REDO | TokPlugin::IS_STATIC;

	$plugin['tf'] = TokPlugin::PARAM_LIST; 
	$plugin['t'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['true'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO; 
	$plugin['f'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO | TokPlugin::NO_PARAM; 
	$plugin['false'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO | TokPlugin::NO_PARAM;
	$plugin['find'] = TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['filter'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::CSLIST_BODY;
	$plugin['plugin'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::CSLIST_BODY;
	$plugin['escape:tok'] = TokPlugin::NO_PARAM | TokPlugin::TEXT;
	$plugin['escape'] = TokPlugin::REQUIRE_PARAM;
	$plugin['unescape'] = TokPlugin::REQUIRE_PARAM;
	$plugin['encode'] = TokPlugin::REQUIRE_PARAM;
	$plugin['decode'] = TokPlugin::REQUIRE_PARAM;
	$plugin['get'] = 0;
	$plugin['const'] = 0;
	$plugin['include'] = TokPlugin::REDO | TokPlugin::REQUIRE_BODY;
	$plugin['include_if'] = TokPlugin::REDO | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['view'] = TokPlugin::REDO | TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY;
	$plugin['clear'] = TokPlugin::NO_PARAM;
	$plugin['ignore'] = TokPlugin::NO_PARAM | TokPlugin::TEXT | TokPlugin::REQUIRE_BODY;
	$plugin['if'] = TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['switch'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_CSLIST | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['keep'] = TokPlugin::NO_PARAM | TokPlugin::TEXT | TokPlugin::REQUIRE_BODY;
	$plugin['load'] = TokPlugin::REQUIRE_BODY;
	$plugin['link'] = TokPlugin::PARAM_CSLIST | TokPlugin::KV_BODY;
	$plugin['redo'] = TokPlugin::NO_PARAM | TokPlugin::REDO | TokPlugin::REQUIRE_BODY;
	$plugin['toupper'] = TokPlugin::NO_PARAM;
	$plugin['tolower'] = TokPlugin::NO_PARAM;
	$plugin['hidden'] = TokPlugin::PARAM_CSLIST | TokPlugin::CSLIST_BODY;
	$plugin['trim'] = 0;
	$plugin['join'] = TokPlugin::LIST_BODY;
	$plugin['set_default'] =  0;
	$plugin['set'] =  0;
	$plugin['redirect'] =  TokPlugin::NO_PARAM;
	$plugin['var'] = 0;
	$plugin['esc'] = 0;
	$plugin['log'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['shorten'] = TokPlugin::REQUIRE_PARAM;
	$plugin['strlen'] = TokPlugin::NO_PARAM;
	$plugin['json:exit'] = TokPlugin::KV_BODY;
	$plugin['json'] = 0;

	return $plugin;
}


/**
 * Return mb_strlen(trim($txt)).
 * 
 * @tok {strlen:}abc{:strlen} 
 * @tok:result 3
 * @tok {strlen:}üäößµ{:strlen}
 * @tok:result 5
 */
public function tok_strlen(string $txt) : int {
	return mb_strlen(trim($txt));
}


/**
 * Return html escaped shortened text, wrappend in <span title="original text">shortened text</span>.
 * If maxlen < 20 and length($txt) > 20 use substr($txt, 0, maxlen - 3).' ...' as short text.
 * If maxlen >= 20 use substr($txt, 0, maxlen/2 - 2).' ... '.substr($txt, -maxlen/2 + 2).
 * If maxlen >= 60 use substr($txt, 0, maxlen - 25).' ... '.substr($txt, -20).
 *
 * @tok {shorten:10}Shorten this Text{:shorten} 
 * <span data-short="1" title="Shorten this Text">Shorten<span style="opacity:0.6"> ... </span></span>
 * @tok {shorten:20}Shorten this long Text{:shorten}
 * <span data-short="1" title="Shorten this long Text">Shorten <span style="opacity:0.6"> ... ong Text</span></span>
 */
public function tok_shorten(int $maxlen, string $txt) : string {
	$len = mb_strlen($txt);

	if ($len < $maxlen) {
		return htmlescape($txt);
	}

	if ($maxlen < 20) {
		$short = mb_substr($txt, 0, $maxlen - 3);
		$rest = '';
	}
	else if ($maxlen >= 60) {
		$short = mb_substr($txt, 0, $maxlen - 25);
		$rest = mb_substr($txt, -20); 
	}
	else {
		$len2 = floor($maxlen / 2 - 2);
		$short = mb_substr($txt, 0, $len2);
		$rest = mb_substr($txt, -1 * $len2);
	}

	$title = ($len > 512) ? '' : ' title="'.htmlescape($txt).'"';

	$res = '<span data-short="1"'.$title.'>'.htmlescape($short).
		'<span style="opacity:0.6"> ... '.htmlescape($rest).'</span></span>';

	return $res;
}


/**
 * Output response code header and $kv as json. Default code is 200.
 * 
 * @exit
 * @tok {json:exit:204}
 * @tok:result tok_json_exit_1
 * @tok "{json:exit}a=5|#|b=3{:json}"
 * @tok:result tok_json_exit_2
 */
public function tok_json_exit(string $code, array $kv) : void {
	if (empty($code)) {
		$code = '200';
	}

	JSON::output($kv, intval($code));
}


/**
 * Return hidden input.
 *
 * @tok:request { "a": "7", "b": "8", "c": "test" }
 * @tok {hidden:a,b,c}
 * @tok:result tok_hidden
 * @tok {hidden:}a, b, c{:hidden}
 * @tok:result tok_hidden
 */
public function tok_hidden(array $param, array $arg) : string {
	$list = (count($param) > 0) ? $param : $arg;
	$res = '';

	foreach ($list as $key) {
		$value = isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';

		if (strlen($value) > 0) {
			$res .= '<input type="hidden" name="'.$key.'" value="'.htmlescape($value).'">'."\n";
		}
  }

  return $res;
}


/**
 * Trim text. 
 *
 * @tok {trim:dlines|ignore_empty|lines|pre|space|whitespace|comma} ... {:trim}
 * @tok {trim:whitespace} 1 3\n5\r\n\r\n3 {:trim} = "1353"
 */
public function tok_trim(string $param, string $txt) : string {
	$res = trim($txt);

	if ($param == 'dlines') {
		$res = preg_replace("/\r?\n[\r\n]+/", "\r\n\r\n", $res);
	}
	else if ($param == 'ignore_empty') {
		$lines = preg_split("/\r?\n/", $res);
		$new_lines = array();

		for ($ln = 0; $ln < count($lines); $ln++) {
			if (trim($lines[$ln])) {
				array_push($new_lines, $lines[$ln]);
			}
		}

		$res = join("\r\n", $new_lines);
	}
	else if ($param == 'lines' || $param == 'pre') {
		$lines = preg_split("/\r?\n/", $res);
		$new_lines = array();

		for ($ln = 0; $ln < count($lines); $ln++) {
			$line = trim($lines[$ln]);

			if ($line) {
				array_push($new_lines, $line);
			}
		}

		$res = join("\r\n", $new_lines);

		if ($param == 'pre') {
			$res = '<pre style="padding:0;margin:0">'.$res.'</pre>';
		}
	}
	else if ($param == 'space') {
		$res = preg_replace("/[\r\n\t]+/", ' ', $res);
		$res = preg_replace("/ +/", ' ', $res);
	}
	else if ($param == 'whitespace') {
		$res = preg_replace("/\s/", '', $res);
	}
	else if ($param == 'comma') {
		$res = preg_replace("/[\r\n]+/", ', ', $res);
	}

	return $res;
}


/**
 * Define template. First parameter is template name, second parameter is number
 * of parameters and third parameter number or arguments. Save as this._tpl. 
 *
 * @tok {tpl_set:test}Nur ein Test{:tpl_set}
 * @tok {tpl_set:page:2:2}Page {:=param1}/{:=param2} line {:=arg1} column {:=arg2}{:tpl_set}
 */
public function tok_tpl_set(array $p, string $arg) : void {
	$key = array_shift($p);

	if (isset($this->_tpl[$key])) {
		throw new Exception("template [tpl_set:$key] is already defined");
	}

	$pnum = empty($p[0]) ? 0 : intval($p[0]);
	$anum = empty($p[1]) ? 0 : intval($p[1]);

	if ($pnum) {
		for ($i = 1; $i <= $pnum; $i++) {
			$tag = TAG_PREFIX.'param'.$i.TAG_SUFFIX;
			if (mb_strpos($arg, $tag) === false) {
				throw new Exception("[tpl_set:$key] is missing $tag", $arg);
			}
		}
	}

	if ($anum) {
		for ($i = 1; $i <= $anum; $i++) {
			$tag = TAG_PREFIX.'arg'.$i.TAG_SUFFIX;
			if (mb_strpos($arg, $tag) === false) {
				throw new Exception("[tpl_set:$key] is missing $tag", $arg);
			}
		}
	}

	$tag_list = $this->_tok->getTagList($arg, true);
	$tnum = count($tag_list) - $pnum - $anum;
	$tags = [];

	for ($i = 0; $i < count($tag_list); $i++) {
		$tag = $tag_list[$i];

		if (!preg_match('/^(arg|param)[1-9][0-9]*$/', $tag)) {
			array_push($tags, $tag);
		}
	}

	if ($tnum != count($tags)) {
		throw new Exception('Tag detection failed', "tnum=$tnum tags: ".print_r($tag_list, true));
	}

	$this->_tpl[$key] = [ 'pnum' => $pnum, 'anum' => $anum, 'tnum' => $tnum, 'tags' => $tags, 'tpl' => $arg ];
}


/**
 * Return filled and parsed template. First parameter is template name, other 
 * parameter are values of tag param1, param2, ...
 *
 * @tok {tpl_set:test}Nur ein Test{:tpl_set}
 * @tok {tpl:test} = Nur ein Test 
 *
 * @tok {tpl_set:page:2:2}Page {:=param1}/{:=param2} line {:=arg1} column {:=arg2}{:tpl_set}
 * @tok {tpl:page:3:72}15|#|39{:tpl} = Page 3/72 line 15 column 39
 * 
 * @tok {tpl_set:map}Hello {:=firstname} {:=lastname}{:tpl_set} // if arg counter > 0 place args first
 * @tok {tpl:map}firstname=John|#|lastname=Doe{:tpl} = Hello John Doe
 *
 * @tok {tpl_set:toc:0:1}Page {:=arg1} ... {:=title}{:tpl_set}
 * @tok {tpl:toc}1|#|title=Overview{:tpl} = Page 1 ... Overview 
 */
public function tok_tpl(array $p, ?string $arg) : string {
	$key = array_shift($p);

	if (!isset($this->_tpl[$key])) {
		throw new Exception("call [tpl_set:$key] first");
	}

	$pnum = $this->_tpl[$key]['pnum'];
	$anum = $this->_tpl[$key]['anum'];
	$tnum = $this->_tpl[$key]['tnum'];
	$tpl = $this->_tpl[$key]['tpl'];

	for ($i = 0; $i < $pnum; $i++) {
		$value = isset($p[$i]) ? $p[$i] : '';
		$tpl = str_replace(TAG_PREFIX.'param'.($i + 1).TAG_SUFFIX, $value, $tpl);
	}

	if ($anum > 0) {
		$list = split_str(HASH_DELIMITER, $arg);

		for ($i = 0; $i < $anum; $i++) {
			$value = isset($list[$i]) ? $list[$i] : '';
			$tpl = str_replace(TAG_PREFIX.'arg'.($i + 1).TAG_SUFFIX, $value, $tpl);
		}
	}

	if ($tnum > 0) {
		$tpl = $this->_tok->replaceTags($tpl, conf2kv($arg));
	}

	// \rkphplib\lib\log_debug("TBase.tok_tpl:407> return $tpl"); 
	return $tpl;
}


/**
 * Retrieve (or set) Tokenizer.vmap value. Examples:
 *
 * @tok set a=17: {var:=a}17{:var}
 * @tok set default: {var:=a?}5{:var} - set a=5 if unset
 * @tok set hash: {var:=#b}x=5|#|y=12|#|...{:var} (error if hash b exists)
 * @tok overwrite hash keys:  {var:=#b!}x=5|#|y=12|#|...{:var} (overwrite existing keys)
 * @tok set vector: {var:+=b}x{:var}, {var:+=b},y{:var} - {var:b} = x,y
 * @tok get optional a: {var:a} or {var:}a{:var}
 * @tok get required a: {var:a!} (abort if not found)
 * @tok set multi-map: {var:=person.age}42{:var}
 * @tok get multi-map: {var:person.age} or {var:person.}age{:var}
 */
public function tok_var(string $name, ?string $value) : string {

	if (substr($name, 0, 1) == '=' && substr($name, -1) == '?') {
		$name = substr($name, 0, -1); // name is now =abc or =#abc
		$key = (substr($name, 0, 1) == '#') ? substr($name, 2) : substr($name, 1);

		if ($this->_tok->getVar($key) !== false) {
			return '';
		}
	}

	if (substr($name, 0, 2) == '+=') {
		$this->setVar(substr($name, 2), $value, Tokenizer::VAR_APPEND);
	}
	else if (substr($name, 0, 1) == '=') {
		$name = substr($name, 1);

		if (substr($name, 0, 1) == '#') {
			$this->setVarHash(substr($name, 1), conf2kv($value));
		}
		else {
			$this->setVar($name, $value);
		}
	}
	else {
		return (string) $this->getVar($name, trim($value));
	}

	return '';
}


/**
 * Set variable $name = $value. If $flag = Tokenizer::VAR_APPEND append to existing value.
 */
public function setVar(string $name, string $value, int $flag = 0) : void {
	$this->_tok->setVar($name, $value, $flag);
}


/**
 * Return variable $name (use $name2 if empty) value (string|array). 
 * If suffix of $name is ! throw exception if not found. 
 * If suffix of $name is [.] use $name.$name2. 
 * Name may contain [.] (a.b = getVar(a)[b], a.b.c = getVar(a)[b][c], ...).
 *
 * @return mixed
 */
public function getVar(string $name, string $name2 = '') {
	if (empty($name)) {
		if (!empty($name2)) {
			$name = $name2;
		}
		else {
			throw new Exception("invalid plugin ".$this->_tok->getPluginTxt("var:", $value));
		}
	}

	if (substr($name, -1) == '.' && !empty($name2)) {
		$name .= $name2;
	}

	return $this->_tok->getVar($name);
}


/**
 * Set has $name. Abort if hash already exists. Use NAME! to merge existing 
 * key NAME with hash $p.
 */
public function setVarHash(string $name, array $p) : void {
	if (substr($name, -1) != '!') {
		$this->_tok->setVar($name, $p, Tokenizer::VAR_MUST_NOT_EXIST);
		return;
	}

	// merge old and new hash
	$name = substr($name, 0, -1);
	$old_p = $this->_tok->getVar($name);

	if ($old_p === false) {
		$old_p = [];
	}
	else if (is_string($old_p)) {
		$old_p = [ $old_p ];
	}

	$this->_tok->setVar($name, array_merge($old_p, $p));
}


/**
 * Initialize row plugin. Parameter: 
 *
 * mode: bootstrap4 (=default, or: bootstrap3|material|table)
 * colnum: 2 (=default)
 * border: 0
 * cellpadding: 0
 * cellspacing: 0 
 * rownum: 1 (=default = add header before and footer after each row)
 */
public function tok_row_init(array $p) : void {
	$default = [ 'mode' => 'bootstrap4', 'colnum' => 2, 'rownum' => 1, 'border' => 0, 'cellpadding' => 0, 'cellspacing' => 0 ];
	$this->_conf['row'] = array_merge($default, $p);
}


/**
 * Place $p into into bootstrap|table row grid.
 * If [row:init] was not called assume mode=bootstrap4.
 * 
 * @tok {row:6,6}1-6|#|7-12{:row} -> <div class="row"><div class="col-md-6">1-6</div><div class="col-md-6">7-12</div></div>
 */
public function tok_row(array $cols, array $p) : string {

	if (!isset($this->_conf['row']) || empty($this->_conf['row']['mode'])) {
		$this->tok_row_init([ 'mode' => 'bootstrap4' ]);
	}

	if ($this->_conf['row']['mode'] == 'bootstrap4' || $this->_conf['row']['mode'] == 'bootstrap3') {
		return $this->bootstrapRow($cols, $p);
	}
	else if ($this->_conf['row']['mode'] == 'material') {
		return $this->materialRow($cols, $p);
	}
	else if ($this->_conf['row']['mode'] == 'table') {
		return $this->tableRow($cols, $p);
	}
	else {
		throw new Exception('call [row:init]mode=... first');
	}
}


/**
 * Place $p into table row grid. Wrap with row:header and row:footer.
 * 
 * @tok {row:6,6}a|#|b{:row} = <tr class="row"><td colspan="6">a</td><td colspan="6">b</td></tr>
 */
private function tableRow(array $cols, array $p) : string {
	$attributes = [];
	$p_last = count($p) - 1;

	if (count($cols) == $p_last) {
		$attributes = $this->getRowColAttributes($p[$p_last]);
	}
	else if (count($cols) != count($p)) {
		throw new Exception('[row:'.join(',', $cols).']... - column count != argument count', join(HASH_DELIMITER, $p));
	}

	$colnum = 0;
	for ($i = 0; $i < count($cols); $i++) {
		$colnum += $cols[$i];
	}

	$conf = $this->_conf['row'];

	if ($colnum != $conf['colnum']) {
		throw new Exception('[row:'.join(',', $cols).']... - colnum (='.$colnum.') != '.$conf['colnum'], join(HASH_DELIMITER, $p));
	}

	$res = '';

	if (!empty($conf['rownum'])) {
		if (!isset($conf['current'])) {
			$this->_conf['row']['current'] = 1;
			$res = '<table border="'.$conf['border'].'" cellpadding="'.$conf['cellpadding'].'" cellspacing="'.$conf['cellspacing'].'">'."\n";
		}
		else {
			$this->_conf['row']['current']++;
		}
	}

	$res .= "<tr>\n";

	for ($i = 0; $i < count($cols); $i++) {
		$colspan = ($cols[$i] > 1) ? ' colspan="'.$cols[$i].'"' : '';
		$class = isset($attributes[$i]['class']) ? ' class="'.$attributes[$i]['class'].'"' : '';
		$other = isset($attributes[$i]['other']) ? ' '.$attributes[$i]['other'] : '';
		$res .= '<td'.$colspan.' valign="top"'.$other.$class.'>'.$p[$i].'</td>'."\n";
	}

	$res .= "</tr>\n";

	if (!empty($conf['rownum']) && $this->_conf['row']['current'] == $conf['rownum']) {
		unset($this->_conf['row']['current']);
		$res .= "</table>\n";
	}

	// \rkphplib\lib\log_debug("TBase.tableRow:614> return $res");
	return $res;
}


/**
 * Return extra attributes map vector (keys: class and other).
 *
 * @tok {row:init:material}
 * 
 * @tok {row:6,6}a|#|b|#|@1.class="mdl-cell--8-col-tablet" @2.class="mdl-cell--4-col-phone"{:row} = <div class="mdl-grid">
 *		<div class="mdl-cell mdl-cell--6-col mdl-cell--8-col-tablet">a</div>
 * 		<div class="mdl-cell mdl-cell--6-col mdl-cell--4-col-phone">b</div></div>
 * 
 * @tok {row:2,4}a|#|b|#|@1.style="background-image: url('bg.jpg')" @2.style="text-align:right"{:row} = <div class="mdl-grid">
 * 		<div class="mdl-cell mdl-cell--2-col" style="background-image: url('bg.jpg')">
 *		<div class="mdl-cell mdl-cell--4-col" style="text-align:right"></div>
 */
private function getRowColAttributes(string $extra) : array {
	$res = [];

	while (preg_match('/^@([0-9]+)\.([a-z_\-]+)\=\"(.+?)\"/', $extra, $match)) {
		$col = parseInt($match[1]) - 1;
		$attribute = $match[2];
		$class = ($attribute == 'class') ? $match[3] : '';
		$other = ($attribute == 'class') ? '' : ' '.$attribute.'="'.$match[3].'"';
		$res[$col] = [ 'class' => $class, 'other' => $other ];
		$len = mb_strlen($match[0]);
		$extra = mb_substr($extra, $len);
	} 

	return $res;
}


/**
 * Place $p into material row grid. Add extra attributes if necessary.
 * 
 * @tok {row:6,6}a|#|b{:row} = <div class="mdl-grid">
 * 		<div class="mdl-cell mdl-cell--6-col">a</div>
 * 		<div class="mdl-cell mdl-cell--6-col">b</div></div>
 */
private function materialRow(array $cols, array $p) : string {
	$attributes = [];
	$p_last = count($p) - 1;

	if (count($cols) == $p_last) {
		$attributes = $this->getRowColAttributes($p[$p_last]);
	}
	else if (count($cols) != count($p)) {
		throw new Exception('[row:'.join(',', $cols).']... - column count != argument count', join(HASH_DELIMITER, $p));
	}

	$res = '<div class="mdl-grid">'."\n";

	for ($i = 0; $i < count($cols); $i++) {
		$class = isset($attributes[$i]['class']) ? ' '.$attributes[$i]['class'] : '';
		$other = isset($attributes[$i]['other']) ? ' '.$attributes[$i]['other'] : '';
		$res .= '<div class="mdl-cell mdl-cell--'.$cols[$i].'-col'.$class.$other.'">'.$p[$i]."</div>\n";
	}

	$res .= "</div>\n";

	return $res;
}


/**
 * Place $p into bootstrap row grid.
 * 
 * @tok {row:6,6}a|#|b{:row} = <div class="row"><div class="col-md-6">a</div><div class="col-md-6">b</div></div>
 */
private function bootstrapRow(array $cols, array $p) : string {
	$attributes = [];
	$p_last = count($p) - 1;

	if (count($cols) == $p_last) {
		$attributes = $this->getRowColAttributes($p[$p_last]);
	}
	else if (count($cols) != count($p)) {
		throw new Exception('[row:'.join(',', $cols).']... - column count != argument count', join(HASH_DELIMITER, $p));
	}

	$res = '<div class="row">'."\n";

	for ($i = 0; $i < count($cols); $i++) {
		$class = isset($attributes[$i]['class']) ? ' '.$attributes[$i]['class'] : '';
		$other = isset($attributes[$i]['other']) ? ' '.$attributes[$i]['other'] : '';
		$res .= '<div class="col-md-'.$cols[$i].$class.'"'.$other.'>'.$p[$i]."</div>\n";
	}

	$res .= "</div>\n";

	return $res;
}


/**
 * Redirect to $url. Use ERROR_[401|404] for error status. Do nothing if $url is empty.
 *
 * @exit
 */
public function tok_redirect(string $url) : void {
	if ($url == 'ERROR_404') {
		header("HTTP/1.0 404 Not Found");
		header("Status: 404 Not Found");
		exit("<h1>404 Not Found</h1>\nThe page that you have requested could not be found.");
  }
	else if ($url == 'ERROR_401') {
		header("HTTP/1.0 404 Unauthorized");
		header("Status: 404 Unauthorized");
		exit("<h1>401 Unauthorized</h1>\nThe page that you have requested can not accessed.");
  }
	else if ($url) {
		redirect($url);
  }
}


/**
 * Join array $p. Delimiter is either param or $p[0]. Non-join param values are: ignore_empty.
 */
public function tok_join(string $param, array $p) : string {
	$delimiter = $param;
	$ignore_empty = false;

	if ($param == 'ignore_empty') {
		$delimiter = array_shift($p);
		$ignore_empty = true;
	}

	$res = '';

	for ($i = 0; $i < count($p); $i++) {
		if ($ignore_empty && empty($p[$i])) {
			continue;
		}

		if ($res) {
			$res .= $delimiter;
		}

		$res .= $p[$i];
	}

	return $res;
}


/**
 * Convert all characters in $txt into lowercase.
 */
public function tok_tolower(string $txt) : string {
	return mb_strtolower($txt);
}


/**
 * Convert all characters in $txt into uppercase.
 */
public function tok_toupper(string $txt) : string {
	return mb_strtoupper($txt);
}


/**
 * Return empty string (do nothing).
 *
 * @tok {ignore:}abc{:ignore} = [] 
 */
public function tok_ignore(string $txt) : string {
	return '';
}


/**
 * Return empty string (do nothing - alias for ignore).
 *
 * @tok {clear:}{date:now}{:ignore} = return '', execute {date:now} 
 * @tok_alias ignore
 */
public function tok_clear(string $txt) : string {
	return '';
}


/**
 * Return un-parsed text (do nothing).
 *
 * @tok {keep:}{find:a}{:keep} = {find:a}
 */
public function tok_keep(string $txt) : string {
	// \rkphplib\lib\log_debug("TBase.tok_keep:806> return $txt");
	return $txt;
}


/**
 * Re-parse text (done in Tokenizer).
 *
 * @tok {redo:}{dirname:}a/b{:dirname}{:redo} = a
 */
public function tok_redo(string $txt) : string {
	return $txt;
}


/**
 * Include view file.
 *
 * @tok {view:overview}name=Overview{:view} = 
 *   <div id="overview" class="view" data-name="Overview">{include:}{get:dir}/overview.inc.html{:include}</div>
 */
public function tok_view(string $name, array $p) : string {
	$file = self::getReqDir(true).'/'.$name.'.inc.html';
	$attrib = 'id="'.$name.'" class="view"';

	foreach ($p as $key => $value) {
		$attrib .= ' data-'.$key.'="'.htmlescape($value).'"';
	}
 
	return '<div '.$attrib.'>'.File::load($file).'</div>';
}


/**
 * Include file. Tokenize output. Use parameter "optional" or append "?" to parameter, 
 * if you do not want to abort if file is missing. Parameter "static" indicates include 
 * can be done at build time.
 *
 * @tok {include:static}a.html{:include} = return tokenized content of a.html (throw error if file does not exist)
 * @tok {include:optional}a.html{:include} = do not throw error if file does not exist (short version is "?" instead of optional)
 * @tok {include:}{find:a.html}{:include} 
 */
public function tok_include(string $param, string $file) : string {
	$this->tok_var('=_include.file', $file);
	return $this->tok_load($param, $file);
}


/**
 * Include file. Tokenize output.
 *
 * @tok {include_if:}|#|a.html{:include_if} = return tokenized content of a.html (throw error if file does not exist)
 * @tok {include_if:}1|#|a.html{:include_if} = return empty string
 * @tok {include_if:b}a|#|a.html|#|b.html{:include_if} = return tokenized content of b.html
 * @tok {include_if:a}a|#|a.html{:include_if} = return tokenized content of a.html 
 * @tok {include_if:?}a|#|a.html{:include_if} = return tokenized content of a.html 
 */
public function tok_include_if(string $param, array $a) : string {

	if (count($a) < 2) {
		throw new Exception('invalid include_if:'.$param, print_r($a, true));
	}

	if (count($a) == 2) {
		$a[2] = '';
	}

	if (strlen($param) == 0) {
		$file = empty($a[0]) ? $a[2] : $a[1];
	}
	else if ($param == '?') {
		$file = empty($a[0]) ? $a[2] : $a[1];
	}
	else {
		$file = ($param == $a[0]) ? $a[1] : $a[2];
	}
	
	if (empty($file)) {
		return '';
	}

	return File::load($file);
}


/**
 * Include raw file content. If "$param=missing" or last character of $param is "?" ignore missing.
 * Preprocess if param is static.
 * 
 * @tok {load:}a.html{:load} = return raw content of a.html (throw error if file does not exist)
 * @tok {load:optional}a.html{:load} = do not throw error if file does not exist (short version is "?" instead of optional)
 */
public function tok_load(string $param, string $file) : string {

	if (substr($param, -1) == '?') {
		$ignore_missing = true;
		$param = substr($param, 0, -1);
	}
	else {
		$ignore_missing = $param === 'optional';
	}

	if (!File::exists($file)) {
		if ($ignore_missing) {
			return '';
		}

		throw new Exception('file missing', $file);
	}

	return File::load($file);
}


/**
 * Return encoded link parameter (e.g. "_=index.php|#|dir=test|#|a=5" -> index.php?cx=ie84PGh3284).
 * If parameter "_" is missing assume "_" = index.php.
 *
 * {link:}dir=a/b/c|#|t=382{:link} -> index.php?cx=eiEveLHO83821
 * {link:}dir=a/b/c|#|t=382{:link} -> index.php?dir=a/b/c&t=382 (falls SETTINGS_REQ_CRYPT = '')
 * {link:}_=a/b/c|#|t=382{:link} -> ?eiEveLHO83821
 * {link:}@=a/b/c|#|t=382{:link} -> ?eiEveLHO83821 (append s_*, search if _REQUEST is set)
 *
 * {link:@,t} -> {link:}@={get:dir}|#|t={get:t}{:link}
 * {link:_,t} -> {link:}_={get:dir}|#|t={get:t}{:link}
 *
 * Use parameter @ to enable keep (_, dir = no keep mode).
 *
 * Keep mode: Add all s_* and sort parameter if _REQUEST value is not empty. Add all parameter
 * from vector tok->getVar(link_keep).
 *
 * If SETTINGS_REQ_CRYPT is empty do not encode link.
 */
public function tok_link(array $name_list, array $p) : string {
	// \rkphplib\lib\log_debug("TBase.tok_link:936> name_list: ".print_r($name_list, true)."\np: ".print_r($p, true));
	$res = 'index.php?'.SETTINGS_REQ_CRYPT.'=';
	$keep = false;
	$seo = '';

	foreach ($name_list as $name) {
		if ($name == '@' || $name == '_') {
			$name = SETTINGS_REQ_DIR;
			$keep = ($name == '@');
			$res = '?';
		}

		if (isset($_REQUEST[$name]) && !isset($p[$name])) {
			$p[$name] = $_REQUEST[$name];
		}
	}

	if (!empty($p['seo'])) {
		$seo = $p['seo'].',';
		unset($p['seo']);
	}

	if (isset($p['@']) || isset($p['_'])) {
		$keep = isset($p['@']);
		$res = '?';

		if (isset($p['@'])) {
			$p[SETTINGS_REQ_DIR] = $p['@'];
			unset($p['@']);
		}

		if (isset($p['_'])) {
			$p[SETTINGS_REQ_DIR] = $p['_'];
			unset($p['_']);
		}
	}

	if ($keep) {
		$kv = $this->_tok->getVar('link_keep');
		if (is_array($kv)) {
			foreach ($kv as $key => $value) {
				if (!isset($p[$key]) && strlen($value) > 0) {
					$p[$key] = $value;
				}	
			}
		}

		foreach ($_REQUEST as $key => $value) {
			if (is_array($value) || strlen($value) == 0 || isset($p[$key])) {
				continue;
			}

			if ((in_array($key, [ 'sort' ]) || substr($key, 0, 2) == 's_') && !isset($p[$key])) {
				$p[$key] = $value;
			}
		}
	}

	if (empty(SETTINGS_REQ_CRYPT)) {
		$dir = '';

		if (isset($p[SETTINGS_REQ_DIR])) {
			$dir = $p[SETTINGS_REQ_DIR];
			unset($p[SETTINGS_REQ_DIR]);
		}
		else if (!empty($_REQUEST[SETTINGS_REQ_DIR])) {
			$dir = $_REQUEST[SETTINGS_REQ_DIR];
		}

		if (!empty($dir)) {
			$p[SETTINGS_REQ_DIR] = $dir;
		}

		$res = '';
		foreach ($p as $key => $value) {
			if (empty($res)) {
				$res .= 'index.php?'.$key.'='.rawurlencode($value);
			}
			else {
				$res .= '&'.$key.'='.rawurlencode($value);
			}
		}

		if (empty($res)) {
			$res = 'index.php';
		}
	}
	else {
		$rbase = basename($_SERVER['SCRIPT_NAME']);

		if (!empty($rbase) && $res == '?') {
			$script_dir = dirname($_SERVER['SCRIPT_NAME']);
			// $res = ($script_dir == '/') ? '/?'.$seo : $script_dir.'/?'.$seo;
			$res = ($script_dir == '/') ? '/?' : $script_dir.'/?';
		}

		$res .= self::encodeHash($p);
	}

	// \rkphplib\lib\log_debug("TBase.tok_link:1035> return $res");
	return $res;
}


/**
 * Return true if string is encoded hash.
 */
private static function isEncodedHash(string $txt) : bool {
	if (strlen($txt) < 5) {
		return false;
	}

	$checksum = substr($txt, 0, 2);
	$txt = substr($txt, 2);

	return self::checksum($txt) == $checksum;
}


/**
 * Return text checksum (hex, 2 character in ]15,255[).
 */
private static function checksum(string $txt) : string {
	$res = array_sum(str_split($txt.':'.strlen($txt).':'.crc32($txt))) % 255;

	if ($res < 16) {
		$res += 16;
	}

	return dechex($res);
}


/**
 * Convert hash into encrypted string. 
 */
public static function encodeHash(array $p) : string {
	$query_string = http_build_query($p);
	$len = strlen($query_string);
	$secret = SETTINGS_CRYPT_SECRET;
	$slen = strlen($secret);

	for ($i = 0; $i < $len; $i++) {
		$query_string[$i] = chr(ord($query_string[$i]) ^ ord($secret[$i % $slen]));
	}

	$res = base64_encode($query_string);
	return rawurlencode(self::checksum($res).$res);
}


/**
 * Decode data encoded with self::encodeHash.
 *
 * @return mixed hash|false
 */
public static function decodeHash(string $data, bool $export_into_req = false) {
	
	if (($pos=strpos($data,',')) > 0) {

		$data = substr($data, $pos);
	}

	$data = rawurldecode($data);
	$checksum = substr($data, 0, 2);
	$data = substr($data, 2);

	if (self::checksum($data) != $checksum) {
		return false;
	}

	$data = base64_decode($data);
	$len = strlen($data);
	$secret = SETTINGS_CRYPT_SECRET;
	$slen = strlen($secret);

	for ($i = 0; $i < $len; $i++) {
		$data[$i] = chr(ord($data[$i]) ^ ord($secret[$i % $slen]));
	}

	$res = array();
	parse_str($data, $res);

	if ($export_into_req) {
		foreach ($res as $key => $value) {
			$_REQUEST[$key] = $value;
		}
	}

	// \rkphplib\lib\log_debug("TBase::decodeHash:1125> return ".print_r($res, true));
	return $res;
}


/**
 * Return result of switch plugin.
 * 
 * @tok {switch:a,b,c}value|#|if_eq_a|#|if_eq_b|#|if_eq_c|#|else{:switch}
 */
public function tok_switch(array $set, array $p) : string {
	$csa = count($set);
	$cp = count($p);

	if ($cp <= $csa || $cp > $csa + 2) {
		throw new Exception('invalid plugin [switch:]', 'set: '.join('|', $set).' p: '.join('|', $p));
  }

	$res = ($cp == $csa + 2) ? $p[$cp - 1] : '';
	$done = false;

	for ($i = 0; !$done && $i < $csa; $i++) {
		if ($p[0] == $set[$i]) {
			$res = $p[$i + 1];
			$done = true;
		}
	}

	return $res;
}


/**
 * Check condition and return true or false block. Beware: all plugins inside if
 * will be execute before condition comparision - use {tf:} and {true|false:} to
 * avoid this.
 * 
 * @tok {if:|eq|ne|in|in_set|le|lt|ge|gt|and|or|cmp|cmp:or}condition(s)|#|true|#|false{:if}
 *
 * @tok {if:}abc|#|true|#|false{:if} = true
 * @tok {if:}|#|true|#|false{:if} = false
 * @tok {if:eq:abc}abc|#|true{:if} = true
 * @tok {if:eq:abc}|#|true{:if} = ""
 * @tok {if:ne:abc}abc|#|true|#|false{:if} = false
 * @tok {if:ne:abc}|#|true{:if} = true
 * @tok {if:in:2,5}3|#|true|#|false{:if} = false 
 * @tok {if:in:2,5}2|#|true|#|false{:if} = true
 * @tok {if:in_set:3}2,5|#|true|#|false{:if} = false
 * @tok {if:in_set:5}2,5|#|true|#|false{:if} = true 
 * @tok {if:le}2|#|3|#|true|#|false{:if} = true - same as {if:le:2}3|#|true|#|false{:if}
 * @tok {if:lt:3}2|#|true|#|false{:if} = false - same as {if:lt}3|#|2|#|true|#|false{:if}
 * @tok {if:ge}2|#|3|#|true|#|false{:if} = false - same as {if:ge:2}3|#|true|#|false{:if}
 * @tok {if:gt:3}2|#|true|#|false{:if} = true - same as {if:gt}3|#|2|#|true|#|false{:if}
 * @tok {if:and:2}1|#|1|#|true|#|false{:if} = true
 * @tok {if:or:3}0|#|0|#|1|#|true|#|false{:if} = true
 * @tok {if:cmp}a|#|a|#|b|#|c|#|true|#|false{:if} = false - same as {if:cmp:and}...
 * @tok {if:cmp:a}a|#|true|#|false{:if} = true
 * @tok {if:cmp:or}a|#|a|#|b|#|c|#|true|#|false{:if} = true
 * @tok {if:match}roland.+@inkoeln.com|#|{get:email}|#|roland@inkoeln.com|#|{get:email}{:if}
 */
public function tok_if(string $param, array $p) : string {
	$has_empty_param = false;
	
	if (!empty($param)) {
		$tmp = split_str(':', $param);
		$do = $tmp[0];
		$param = isset($tmp[1]) ? $tmp[1] : '';
		$has_empty_param = (count($tmp) == 2) && ($tmp[1] == '');
	}
	else {
		$do = '';
	}

	$p_num = count($p);

	if ($p_num < 2) {
		throw new Exception('invalid if', "do=$do param=$param p=".print_r($p, true));
	}
	else if ($p_num === 2) {
		array_push($p, '');
		$p_num++;
	}

	if ($do === '') {
		$res = empty($p[0]) ? $p[2] : $p[1];
	}
	else if ($do === 'eq') {
		$res = ($param === $p[0]) ? $p[1] : $p[2];
	}
	else if ($do === 'ne') {
		$res = ($param === $p[0]) ? $p[2] : $p[1];
	}
	else if ($do === 'in') {
		$set = split_str(',', $param);
		$res = in_array($p[0], $set) ? $p[1] : $p[2];
	}
	else if ($do === 'in_set') {
		$set = split_str(',', $p[0]);
		$res = in_array($param, $set) ? $p[1] : $p[2];
	}
	else if ($do === 'le' || $do === 'lt' || $do === 'ge' || $do === 'gt') {
		if (strlen($param) > 0) {
			array_unshift($p, $param);
		}

		if ($p_num % 2 == 1) {
			array_push($p, '');
			$p_num++;
		}

		if ($p_num != 4) {
			throw new Exception('invalid if', "do=$do param=$param p=".print_r($p, true));
		}

		if ($do === 'le') {
			$res = ($p[0] <= $p[1]) ? $p[2] : $p[3];
		}
		else if ($do === 'lt') {
			$res = ($p[0] < $p[1]) ? $p[2] : $p[3];
		}
		else if ($do === 'ge') {
			$res = ($p[0] >= $p[1]) ? $p[2] : $p[3];
		}
		else if ($do === 'gt') {
			$res = ($p[0] > $p[1]) ? $p[2] : $p[3];
		}
	}
	else if ($do === 'and' || $do === 'or') {
		$cnum = intval($param);

		if (!$cnum && $p_num <= 4) {
			$cnum = 2;
		}

		if ($cnum + 1 === $p_num) {
			array_push($p, '');
			$p_num++;
		}

		if ($cnum < 2 || $cnum + 2 != $p_num) {
			throw new Exception('invalid if', "do=$do param=$param p=".print_r($p, true));
		}

		if ($do === 'or') {
			$cmp = false;

			for ($i = 0; !$cmp && $i < $cnum; $i++) {
				if (!empty($p[$i])) {
					$cmp = true;
				}
			}
		}
		else if ($do === 'and') {
			$cmp = true;

			for ($i = 0; $cmp && $i < $cnum; $i++) {
				if (empty($p[$i])) {
					$cmp = false;
				}
			}
		}

		$res = $cmp ? $p[$p_num - 2] : $p[$p_num - 1];
	}
	else if ($do === 'match') {
		if ($p_num < 3) {
			throw new Exception('invalid if', "do=$do p=".print_r($p, true));
		}

		if (preg_match('/'.$p[0].'/', $p[1])) {
			$res = $p[2];
		}
		else {
			$res = ($p_num == 4) ? $p[3] : '';
		}
	}
	else if ($do === 'cmp') {
		if ((strlen($param) > 0 || $has_empty_param) && $param != 'and' && $param != 'or') {
			array_unshift($p, $param);
			$param = '';
			$p_num++;
		}

		if ($p_num % 2 == 1) {
			array_push($p, '');
			$p_num++;
		}

		if ($p_num < 4) {
			throw new Exception('invalid if', "do=$do param=$param p=".print_r($p, true));
		}

		if (empty($param) || $param === 'and') {
			$cmp = true;

			for ($i = 0; $cmp && $i < $p_num - 3; $i = $i + 2) {
				if ($p[$i] != $p[$i + 1] && $p[$i] != '*' && $p[$i + 1] != '*') {
					$cmp = false;
				}
			}
		}
		else if ($param === 'or') {
			$cmp = false;

			for ($i = 0; !$cmp && $i < $p_num - 3; $i = $i + 2) {
				if ($p[$i] == $p[$i + 1] || $p[$i] == '*' || $p[$i + 1] == '*') {
					$cmp = true;
				}
			}
		}
		else {
			throw new Exception("invalid [if:cmp:$param]", join('|', $p));
		}

		$res = ($cmp) ? $p[$p_num - 2] : $p[$p_num - 1];
	}

	return $res;
}


/**
 * Define tag filter. Available filters:
 *
 * trim: trim(' abc ') = 'abc'
 * escape_html: escape_html('&<>"\'') = '&amp;&lt;&gt;&quot;&#39;'
 * default: reset tag filter to default value
 * escape_db: escape_db("a'b") = 'a''b'
 * escape_tok: escape_tok('{x:}') = '&#123;x&#58;&#125;'
 * escape_arg: escape_arg('a|#|b') = 'a&#124;&#35;&#124;b'
 * off: reset tag filter to required value
 *
 * Available tags: get and esc
 *
 * get.default = escape_html,escape_tok,escape_arg
 * get.required = 
 *
 * esc.default = trim,escape_html,escape_tok,escape_arg,escape_db
 * esc.required = escape_db
 *
 * @tok {filter:get}off{:filter} = no filter
 * @tok {filter:get}default{:filter} = use default filter
 * @tok {filter:esc}trim{:filter} = use trim and escape_db filter
 */
public function tok_filter(string $tag, array $filter_list) : void {

	$allow_tag = [ 'esc', 'get' ];
	if (!in_array($tag, $allow_tag)) {
		throw new Exception('no filter available for tag '.$tag);
	}

	if (empty($filter_list[0])) {
		throw new Exception('invalid filter list', 'tag='.$tag.' filter_list='.join('|', $filter_list));
	}

	$allow_filter['esc'] = [ 'trim', 'escape_html', 'escape_tok', 'escape_arg', 'escape_db' ]; 
	$allow_filter['get'] = [ 'trim', 'escape_html', 'escape_tok', 'escape_arg' ];

	if ($filter_list[0] == 'default') {
		$default_filter['esc'] = [ 'trim', 'escape_html', 'escape_tok', 'escape_arg', 'escape_db' ];
		$default_filter['get'] = [ 'escape_html', 'escape_tok', 'escape_arg' ];

		$this->_conf['filter.'.$tag] = $default_filter[$tag];
	}
	else if ($filter_list[0] == 'off') {
		$required_filter['esc'] = [ 'escape_db' ];
		$required_filter['get'] = [ ];

		$this->_conf['filter.'.$tag] = $required_filter[$tag];
	}
	else if (in_array($filter_list[0], $allow_filter[$tag])) {
		$this->_conf['filter.'.$tag] = $filter_list[0];
	}
	else {
		throw new Exception('invalid filter', 'i=0 tag='.$tag.' filter_list='.join('|', $filter_list));
	}

	for ($i = 1; $i < count($filter_list); $i++) {
		if (in_array($filter_list[$i], $allow_filter[$tag])) {
			$this->_conf['filter.'.$tag] .= ','.$filter_list[$i];
		}
		else {
			throw new Exception('invalid filter', "i=$i tag=$tag filter_list=".join('|', $filter_list));
		}
	}
}


/**
 * Apply filter.
 *
 * @see tag_filter
 */
private function applyFilter(string $tag, string $value) : string {
	// \rkphplib\lib\log_debug("TBase.applyFilter:1419> tag=$tag value=[$value]");
	if (!isset($this->_conf['filter.'.$tag])) {
		throw new Exception('no filter', "tag=$tag value=[$value] _conf: ".print_r($this->_conf, true));
	}

	foreach ($this->_conf['filter.'.$tag] as $filter) {

		if ($filter == 'trim') {
			$value = trim($value);
		}
		else if ($filter == 'escape_tok') {
			$value = $this->_tok->escape($value);
		}
		else if ($filter == 'escape_html') {
			$value = str_replace([ '&', '<', '>', '"', "'" ], [ '&amp;', '&lt;', '&gt;', '&quot;', '&#39;' ], $value);
		}
		else if ($filter == 'escape_arg') {
			$value = str_replace(HASH_DELIMITER, entity(HASH_DELIMITER), $value);
		}
		else if ($filter == 'escape_db') {
			require_once __DIR__.'/../ADatabase.class.php';
			$value = "'".\rkphplib\ADatabase::escape($value)."'";
		}	
		else {
			throw new Exception('invalid filter', "tag=$tag filter=$filter value=[$value]");
		}

		// \rkphplib\lib\log_debug("TBase.applyFilter:1446> filter=$filter value=[$value]");
	}

	return $value;
}


/**
 * Return sql escaped argument ('$arg'). If argument is empty use trim($_REQUEST[param]).
 * Trim argument if param = t and _REQUEST[t] is not set.
 * Null argument if empty and param = null and _REQUEST[null] is not set.
 *
 * @tok {esc:} ab'c {:esc} -> [ ab''c ]
 * @tok {esc:a} AND _REQUEST[a] = " x " -> ' x '
 * @tok {esc:t} AND _REQUEST[t] = " x " -> 'x'
 * @tok {esc:}null{:esc} -> NULL
 * @tok {esc:}NULL{:esc} -> NULL
 * @tok {esc:null}{:esc} -> NULL
 * 
 * @filter trim, escape_html, escape_tok, escape_arg, escape_db
 */
public function tok_esc(string $param, ?string $arg) : ?string {

	if (!empty($param) && substr($param, 0, 1) != '@') {
		if (!isset($_REQUEST[$param])) {
			$arg = null;
		}
		else {
			$arg = trim($_REQUEST[$param]);
		}
	}
	else if ($param == 'null' && trim($arg) == '') {
		$arg = null;
	}

	if (is_null($arg) || $arg === 'null' || $arg === 'NULL') {
		// \rkphplib\lib\log_debug("TBase.tok_esc:1482> return NULL");
		return 'NULL';
	}

	if (!isset($this->_conf['filter.esc'])) {
		$this->tok_filter('esc', [ 'default' ]);
	}

	$arg = $this->applyFilter('esc', $arg);

	// \rkphplib\lib\log_debug("TBase.tok_esc:1492> return [$arg]");
	return $arg;
}


/**
 * Set _REQUEST[$name] = $value if empty (use trailing ! for unset).
 * 
 * @tok {set_default:id!}value{:set_default} - abort if _REQUEST['id'] is set
 * @tok {set_default:key}value{:set_default}
 * @tok {set_default:}key=value|#|...{:set_default}
 * @tok {set_default:list[]}a,b,c{:set_default}
 */
public function tok_set_default(string $name, string $value) : void {

	if (strlen($value) == 0) {
		return;
	}

	if (substr($name, -1) == '!') {
		$name = substr($name, 0, -1);
		if (isset($_REQUEST[$name])) {
			throw new Exception('[set_default:'.$name.'!] - _REQUEST['.$name.'] is already set');
		}
	}

  if (empty($name)) {
		$kv = conf2kv($value);
		foreach ($kv as $key => $value) {
			if (!isset($_REQUEST[$key])) {
				$_REQUEST[$key] = $value;
			}
		}
	}
	else if (substr($name, -2) == '[]') {
		$name = substr($name, 0, -2);
		if (!isset($_REQUEST[$name])) {
			$_REQUEST[$name] = [];
		}
	
		if (!in_array($value, $_REQUEST[$name])) {
			array_push($_REQUEST[$name], $value);
		}		
	}
	else if (!isset($_REQUEST[$name]) || strlen($_REQUEST[$name]) == 0) {
		$_REQUEST[$name] = $value;
	}
}


/**
 * Set _REQUEST[$name] = $value.
 *
 * @tok {set:id}value{:set} - set _REQUEST['id']=value
 * @tok {set:}key=value|#|...{:set}
 */
public function tok_set(string $name, string $value) : void {

  if (empty($name)) {
		$kv = conf2kv($value);
		foreach ($kv as $key => $value) {
			$_REQUEST[$key] = $value;
		}
	}
	else {
		$_REQUEST[$name] = $value;
	}
}


/**
 * Return constant value. Constant name is either param or arg.
 */
public function tok_const(string $param, ?string $arg) : string {
	$name = empty($arg) ? $param : trim($arg);

	if (!defined($name)) {
		throw new Exception('undefined constant '.$name);
	}

	return constant($name);
}


/**
 * Return request value. Apply Tokenizer::escape to output.
 * If _REQUEST[name] is not set return _FILES[name]['name'] if set.
 * IF _REQUEST[name] is array and count == 1 return array element.
 * If _REQUEST[name] is array and name is [a.b] and _REQUEST[a][b] exists return _REQUEST[a][b].
 * If not found return empty string.
 *
 * @tok {get:a}, _REQUEST['a'] = 7: 7
 * @tok {get:a}, _REQUEST['a'] = '-{x:}-': -&#123;x&#58;&#125;-
 * @tok {get:a}, _REQUEST['a'] = [ 'x', 'y' ]: x,y
 * @tok {get:a}, !isset(_REQUEST['a']) && _FILES['a']['name'] = test.jpg: test.jpg
 * @tok {get:a.x}, _REQUEST['a'] = [ 'x' => 5, 'y' => 10 ]: 5
 * @tok {get:a}, _REQUEST['a'] = [ 3 ]: 3
 * @tok {get:a}, _REQUEST['a'] = [ 1, 2, 3 ]: ''
 * @tok {get:*}, return kv2conf(_REQUEST) 
 * @tok {get:a?}, (!isset(_REQUEST[a]) || strlen($_REQUEST[a]) == 0) = 0 : 1
 * @tok {get:xn--*}, return _REQUEST values where substr(key, 0, 4) == 'xn--' as hash string
 */
public function tok_get(string $param, ?string $arg) : string {
	$key = empty($arg) ? $param : trim($arg);
	$res = '';

	if (substr($key, -1) == '?') {
		$key = substr($key, 0, -1);
		$res = (!isset($_REQUEST[$key]) || is_array($_REQUEST[$key]) || strlen($_REQUEST[$key]) == 0) ? '0' : '1';
	}
	else if (isset($_REQUEST[$key])) {
		if (is_array($_REQUEST[$key])) {
			$res = array_join(',', $_REQUEST[$key]);
		}
		else {
			$res = $_REQUEST[$key];
		}
	}
	else if (isset($_FILES[$key]) && !empty($_FILES[$key]['name'])) {
		$res = $_FILES[$key]['name'];
	}
	else if (($pos = mb_strpos($key, '.')) !== false) {
		$key1 = mb_substr($key, 0, $pos);
		$key2 = mb_substr($key, $pos + 1);

		if (isset($_REQUEST[$key1]) && is_array($_REQUEST[$key1]) && isset($_REQUEST[$key1][$key2])) {
			$res = $_REQUEST[$key1][$key2];
		}
	}
	else if ($key == '*') {
		$res = $_REQUEST;
	}
	else if (substr($key, -1) == '*') {
		$search = substr($key, 0, -1);
		$found = [];

		foreach ($_REQUEST as $key => $value) {
			if (strpos($key, $search) === 0) {
				$found[$key] = $value;
			}
		}

		$res = kv2conf($found);
	}

  if (!isset($this->_conf['filter.get'])) {
    $this->tok_filter('get', [ 'default' ]);
  }

	if (is_string($res)) {
  	$res = $this->applyFilter('get', $res);
	}
	else if (is_array($res)) {
		foreach ($res as $key => $value) {
			if (is_array($value)) {
				$value = array_join(',', $value);
			}

			$res[$key] = $this->applyFilter('get', $value);
		}

		$res = kv2conf($res);
	}

	return $res;
}


/** 
 * Return escape value. No body tokenization. No redo.
 *
 * @tok {escape:tok}{get:t}{:escape} = &#123;get&#58;t&#125; 
 */
public function tok_escape_tok(string $txt) : string {
	return $res = $this->_tok->escape($txt);
}


/**
 * Return escaped value. Parameter:
 *
 * - entity: replace chars with &#N;
 * - arg: replace |#| with &#124;&#35;&#124;
 * - url: rawurlencode 
 * - js: entity escape
 * - html: replace [ '&', '<', '>', '"', "'" ] with [ '&amp;', '&lt;', '&gt;', '&quot;', '&#39;' ]
 *
 * @tok {escape:arg}a|#|b{:escape} = &#124;&#35;&#124; (|#| = HASH_DELIMITER)
 * @tok {escape:entity}|@||#|a|@|b{:escape} = a&#124;&#64;&#124b
 * @tok {escape:js}'; alert('test'); '{:escape} = '&#39;; alert&#40;&#39;test&#39;&#41;; &#39;'
 * @tok {escape:url}a b{:escape} = a%20b
 * @tok {escape:html}<a href="abc">{:escape} = &lt;a href=&quot;abc&quot;&gt;
 */
public function tok_escape(string $param, string $txt) : string {
	$res = $txt;

	if ($param == 'tok') {
		$res = $this->_tok->escape($txt);
	}
	else if ($param == 'url') {
		$res = rawurlencode($txt);
	}
	else if ($param == 'entity') {
		list ($entity, $txt) = explode(HASH_DELIMITER, $txt, 2);
		$res = str_replace($entity, entity($entity), $txt);
	}
	else if ($param == 'arg') {
		$res = str_replace(HASH_DELIMITER, entity(HASH_DELIMITER), $txt);
	}
	else if ($param == 'js') {
		$res = str_replace([ '"', "'", '\\', '(', ')', '{', '}', '=' ], [ '&#34;', '&#39;', '&#92;', '&#40;', '&#41;', '&#123;', '&#125;', '&#61;' ], $txt);
	}
	else if ($param == 'html') {
		$res = str_replace([ '&', '<', '>', '"', "'" ], [ '&amp;', '&lt;', '&gt;', '&quot;', '&#39;' ], $txt);
	}
	else {
		throw new Exception('invalid parameter', $param);
	}

	return $res;
}


/**
 * Return unescaped value. Parameter:
 * 
 * - tok: Tokenizer->unescape $txt
 * - js: entity unescape
 * - html: replace [ '&lt;', '&gt;', '&quot;', '&amp;', '&#39;' ] with [ '<', '>', '"', '&', "'" ]
 *
 * @tok {unescape:tok}&#123;x&#58;&#125;{:unescape} = {x:}
 * @tok {unescape:arg}a&#124;&#35;&#124;b{:unescape} = a|#|b 
 * @tok {unescape:entity}|@||#|a&#124;&#64;&#124;b{:unescape} = a|@|b
 * @tok {unescape:html}&lt;a href=&quot;abc&quot;&gt;{:unescape} = <a href="abc">
 * @tok {unescape:js}'&#39;; alert&#40;&#39;test&#39;&#41;; &#39;'{:unescape} =  '; alert('test'); '
 * @tok {unescape:url}a%20b{:unescape} = a b
 * @tok {unescape:utf8}R\u00FCssel{:unescape} = Rüssel
 */
public function tok_unescape(string $param, string $txt) : string {
	$res = '';

	if ($param == 'tok') {
		$res = $this->_tok->unescape($txt);
	}
	else if ($param == 'utf8') {
		$res = html_entity_decode(preg_replace("/\\\u([0-9a-fA-F]{4})/", '&#x\1;', $txt));
	}
	else if ($param == 'url') {
		$res = rawurldecode($txt);
	}
	else if ($param == 'arg') {
    $res = str_replace(entity(HASH_DELIMITER), HASH_DELIMITER, $txt);
	}
	else if ($param == 'entity') {
		list ($entity, $txt) = explode(HASH_DELIMITER, $txt, 2);
		$res = str_replace(entity($entity), $entity, $txt);
	}
	else if ($param == 'js') {
		$res = str_replace([ '&#34;', '&#39;', '&#92;', '&#40;', '&#41;', '&#123;', '&#125;', '&#61;' ], [ '"', "'", '\\', '(', ')', '{', '}', '=' ], $txt);
	}
	else if ($param == 'html') {
		$res = str_replace([ '&lt;', '&gt;', '&quot;', '&amp;', '&#39;' ], [ '<', '>', '"', '&', "'" ], $txt);
	}
	else {
		throw new Exception('invalid parameter', $param);
	}

	return $res;
}


/**
 * Return encoded text. Parameter:
 *
 * - base64: base64 encode $txt
 *
 * @tok {encode:base64}hello{:encode} = aGVsbG8=
 */
public function tok_encode(string $param, string $txt) : string {
	$res = '';

	if ($param == 'base64') {
		$res = base64_encode($txt);
	}
	else {
		throw new Exception('invalid parameter', $param);
	}

	return $res;
}


/**
 * Return decoded text. Parameter:
 *
 * - base64: base64 decode $txt
 *
 * @tok {decode:base64}aGVsbG8={:decode} = hello
 */
public function tok_decode(string $param, string $txt) : string {
	$res = '';

	if ($param == 'base64') {
		$res = base64_decode($txt);
	}
	else {
		throw new Exception('invalid parameter', $param);
	}

	return $res;
}


/**
 * Load plugin class. Examples:
 * 
 * {plugin:}TLogin, TLanguage, PHPLIB:TShop, inc/abc.php:\custom\XY{:plugin}
 * require_once PATH_RKPHPLIB.'tok/TLogin.class.php'; $this->tok->register(new \rkphplib\tok\TLogin());
 * require_once PATH_RKPHPLIB.'tok/TLanguage.class.php'; $this->tok->register(new \rkphplib\tok\TLanguage());
 * require_once PATH_PHPLIB.'tok/TShop.class.php'; $this->tok->register(new \phplib\tok\TShop());
 * require_once 'inc/abc.php'; $this->tok->register(new \custom\XY());
 */
public function tok_plugin(array $p) : void {
	foreach ($p as $plugin) {
		if (mb_strpos($plugin, ':') === false) {
			require_once __DIR__."/$plugin.class.php";
			$obj = '\\rkphplib\\tok\\'.$plugin;
			// \rkphplib\lib\log_debug("TBase.tok_plugin:1805> require_once('".PATH_RKPHPLIB.'tok/'.$plugin.".class.php'); new $obj();");
		}
		else {
			list ($path, $obj) = explode(':', $plugin);

			if (basename($path) === $path && defined("PATH_$path")) {
				$incl_path = constant("PATH_$path").'tok/'.$obj.'.class.php';
				// \rkphplib\lib\log_debug("TBase.tok_plugin:1812> path=$path incl_path=$incl_path");
				require_once $incl_path;
				$obj = '\\'.strtolower($path).'\\tok\\'.$obj;
				// \rkphplib\lib\log_debug("TBase.tok_plugin:1815> require_once('$incl_path'); new $obj();");
			}
			else {
				throw new Exception("invalid path=[$path] or undefined PATH_$path");
			}
		}

		$this->_tok->register(new $obj());
	}
}


/**
 * Return self::findPath(file, self::getReqDir(true)). 
 * If file is empty and dir is not set use file = dir and dir = ''.
 * Examples {find:main.html} = {find:}main.html{:}
 *
 * - _REQUEST[dir] = a/b/c, b/test.html exists: a/b/test.html
 * - _REQUEST[dir] = a/b/c, c/test.html exists: a/b/c/test.html
 * - _REQUEST[dir] = a/b/c, ./test.html exists: test.html
 */
public function tok_find(string $file, ?string $file2 = '') : string {
	if (empty($file) && !empty($file2)) {
		$file = $file2;
	}
	
	$is_required = true;
	if (substr($file, -1) == '?') {
		$file = substr($file, 0, -1);
		$is_required = false;
	}

	$res = self::findPath($file, self::getReqDir(true));

	if (empty($res) && $is_required) {
		$plugin = $this->_tok->getPluginTxt('find:'.$file);
		throw new Exception("result of $plugin is empty - create $file in document root");
	}

	return $res;
}


/**
 * Return $_REQUEST[SETTINGS_REQ_DIR]. If $use_dot_prefix = true return [.] 
 * (if result is empty) or prepend [./].
 */
public static function getReqDir(bool $use_dot_prefix = false) : string {
	if (empty($_REQUEST[SETTINGS_REQ_DIR])) {
		$res = $use_dot_prefix ? '.' : '';
	}
	else {
		$res = $use_dot_prefix ? './'.$_REQUEST[SETTINGS_REQ_DIR] : $_REQUEST[SETTINGS_REQ_DIR];
	}

	return $res;
}


/**
 * Search path = (dir/file) in dir until found or dir = [.]. Throw Exception if path is not 
 * relative or has [../] or [\]. Return found path. 
 */
public static function findPath(string $file, string $dir = '.') : string {

	if (mb_substr($dir, 0, 1) === '/' || mb_substr($dir, 0, 3) === './/') {
		throw new Exception('invalid absolute directory path', $dir);
	}

	if (mb_strpos($dir, '../') !== false || mb_strpos($file, '../') !== false) {
		throw new Exception('../ is forbidden in path', $dir.':'.$file);
	}

	if (mb_strpos($dir, '\\') !== false || mb_strpos($file, '\\') !== false) {
		throw new Exception('backslash is forbidden in path', $dir.':'.$file);
	}

	$res = '';

	while (!$res && mb_strlen($dir) > 0) {
		$path = $dir.'/'.$file;

		if (file_exists($path) && is_readable($path)) {
			$res = $path;
		}

		$pos = mb_strrpos($dir, '/');
		if ($pos > 0) {
			$dir = mb_substr($dir, 0, $pos);
		}
		else {
			$dir = '';
		}
	}

	if (mb_substr($res, 0, 2) == './') {
		$res = mb_substr($res, 2);
	}

	return $res;
}


/**
 * Evaluate condition. Use tf, t(rue) and f(alse) as control structure plugin. 
 * Evaluation result is saved in _tok.callstack and reused in tok_t[true]() and tok_f[false]().
 * Merge p with split('|#|', $arg).
 *
 * @test:t1 p.length == 0: true if !empty($arg)
 * @test:t2 p.length == 1 and p[0] == !: true if empty($arg)
 * @test:t3 p.length == 1 and p[0] == switch: compare true:param with arg later (if arg is empty use f:)
 * @test:t4 p.length 1|2 and p[0] == cmp: true if p[1] == $arg
 * @test:t5 p.length 1|2 and p[0] in (eq, ne, lt, le, gt, ge): floatval($arg) p[0] floatval(p[1])
 * @test:t6 p.length >= 1 and p[0] == in_arr: true if end(p) in p[]
 * @test:t7 p[0] == set: search true:param in p[1..n] later
 * @test:t8 p.length == 2 and p[0] == in: set is split(',', p[1]) true if p[0] in set
 * @test:t9 p.length == 2 and p[0] == in_set: set is split(',', p[0]) true if p[1] in set
 * @test:t10 p.length >= 2 and p[0] == or: true if one entry in p[1...n] is not empty
 * @test:t11 p.length >= 2 and p[0] == and: true if every entry in p[1...n] is not empty
 * @test:t12 p.length >= 3 and p[0] == cmp_or: true if one p[i] == p[i+1] (i+=2)
 * @test:t13 p.length >= 3 and p[0] == cmp_and: true if every p[i] == p[i+1] (i+=2)
 * - p.length == 2 and p[0] == prev[:n]: modify result of previous evaluation
 *
 * @tok {tf:eq:5}3{:tf} = false, {tf:lt:3}1{:tf} = true, {tf:}0{:tf} = false, {tf:}00{:tf} = true
 */
public function tok_tf(array $p, string $arg) : void {
	$tf = false;

	$ta = trim($arg);
	$do = '';

	if (count($p) == 1) {
		if ($p[0] === '') {
			$tf = !empty($ta);
		}
		else if ($p[0] === '!') {
			$tf = empty($ta);
		}
		else if ($p[0] === 'switch') {
			$tf = empty($ta) ? false : 'switch:'.$ta;
		}
		else if ($p[0] === 'set') {
			$tf = split_str(HASH_DELIMITER, $arg);
		}
		else if (!empty($p[0])) {
			if (in_array($p[0], [ 'cmp', 'set', 'in_arr', 'in', 'in_set', 'and', 'or', 'cmp_and', 'cmp_or' ])) {
				$do = $p[0];
				$ap = split_str(HASH_DELIMITER, $arg);
			}
			else if (in_array($p[0], [ 'eq', 'ne', 'lt', 'gt', 'le', 'ge' ])) {
				$do = $p[0];
				$tmp = split_str(HASH_DELIMITER, $arg);
				$ap = [];

				foreach ($tmp as $value) {
					$value = strpos($value, '.') ? floatval($value) : intval($value);
					array_push($ap, $value);
				}
			}
			else {
				throw new Exception('invalid operator', 'arg=['.$arg.'] p: '.print_r($p, true));
			}
		}
		else {
			throw new Exception('invalid operator', 'arg=['.$arg.'] p: '.print_r($p, true));
		}
	}
	else if (count($p) > 1) {
		$do = array_shift($p);
		// even if arg is empty we need [] as ap - e.g. {tf:cmp:}{:tf} = true
		$ap = array_merge($p, split_str(HASH_DELIMITER, $arg));
	}

	if (empty($do)) {
		$this->_tok->setCallStack('tf', $tf);
		return;
	}

	if ($do == 'cmp') {
		if (count($ap) % 2 != 0) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		$tf = true;
		for ($i = 0; $tf && $i < count($ap); $i = $i + 2) {
			$tf = ($ap[$i] === $ap[$i + 1]) || $ap[$i] == '*' || $ap[$i + 1] == '*';
		}
	}
	else if ($do == 'set') {
		$tf = $ap;
	}
	else if (in_array($do, array('eq', 'ne', 'lt', 'le', 'gt', 'ge'))) {
		if (count($ap) != 2) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		if (!is_numeric($ap[0]) || !is_numeric($ap[1])) {
			if ($do == 'eq' || $do == 'ne') {
				// eq and ne can be used for non-numeric string comparison too - backward compatibility - better use cmp
				$fva = $ap[0];
				$fvb = $ap[1];
			}
			else {
				throw new Exception("invalid number comparison", '{tf:'.$do.':'.$ap[0].'}'.$ap[1].'{:tf}');
			}
		}
		else { 
			$fva = floatval($ap[0]);
			$fvb = floatval($ap[1]);
		}

		if ($do == 'eq') {
			$tf = ($fva === $fvb); 
		}
		else if ($do == 'ne') {
			$tf = ($fva !== $fvb); 
		}
		else if ($do == 'lt') {
			$tf = ($fva < $fvb); 
		}
		else if ($do == 'le') {
			$tf = ($fva <= $fvb); 
		}
		else if ($do == 'gt') {
			$tf = ($fva > $fvb); 
		}
		else if ($do == 'ge') {
			$tf = ($fva >= $fvb); 
		}
	}
	else if ($do == 'in_arr') {
		if (count($ap) < 2) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		$x = array_pop($ap);
		$tf = in_array($x, $ap);
	}
	else if ($do == 'in' || $do == 'in_set') {
		if (count($ap) != 2) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		if ($do == 'in') {
			$set = split_str(',', $ap[0]);
			$tf = in_array($ap[1], $set);
		}
		else {
			$set = split_str(',', $ap[1]);
			$tf = in_array($ap[0], $set);
		}
	}
	else if ($do == 'and' || $do == 'or') {
		$apn = count($ap);

		if ($do == 'or') {
			for ($i = 0, $tf = false; !$tf && $i < $apn; $i++) {
				$tf = !empty($ap[$i]);
			}
		}
		else {
			for ($i = 0, $tf = true; $tf && $i < $apn; $i++) {
				$tf = !empty($ap[$i]);
			}
		}
	}
	else if ($do == 'cmp_or' || $do == 'cmp_and') {
		$apn = count($ap);

		if ($apn < 2 || $apn % 2 != 0) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		if ($do == 'cmp_or') {
			for ($i = 0, $tf = false; !$tf && $i < $apn - 1; $i = $i + 2) {
				$tf = ($ap[$i] == $ap[$i + 1]) || $ap[$i] == '*' || $ap[$i + 1] == '*';
			}
		}
		else {
			for ($i = 0, $tf = true; $tf && $i < $apn - 1; $i = $i + 2) {
				$tf = ($ap[$i] == $ap[$i + 1]) || $ap[$i] == '*' || $ap[$i + 1] == '*';
			}
		}
	}

	$this->_tok->setCallStack('tf', $tf);
	return;
}


/**
 * Same as tok_true().
 * @alias tok_true()
 */
public function tok_t(string $param, string $arg) : string {
	return $this->tok_true($param, $arg);
}


/**
 * Return $out if last tf from tok.callstack is: $tf = true or (is_string(top($tf)) && $val = top($tf)).
 */
public function tok_true(string $val, string $out) : string {
	$tf = $this->_tok->getCallStack('tf');

	if (is_string($tf) && strpos($tf, 'switch:') === 0) {
		if (substr($val, 0, 4) == 'var:') {
			$val = split_str(',', $this->_tok->getVar(substr($val, 4)));
		}
		else {
			$val = split_str(',', $val);
		}

		$tf = substr($tf, 7);

		if (count($val) == 1 && substr($val[0], 0, 1) == '!' && substr($val[0], 1) != $tf) {
			$val[0] = $tf;
		}
	}

	// \rkphplib\lib\log_debug('TBase.tok_true:2135> val='.print_r($val, true).' tf='.print_r($tf, true));
	return ((is_bool($tf) && $tf) || (is_array($val) && in_array($tf, $val)) || (is_string($tf) && $tf === $val) || 
		(is_array($tf) && !empty($val) && in_array($val, $tf))) ? $out : '';
}


/**
 * Same as tok_false().
 * @alias tok_false()
 */
public function tok_f(string $out) : string {
	return $this->tok_false($out);
}


/**
 * Return $out if last tf from tok.callstack is false. Otherwise return empty string.
 */
public function tok_false(string $out) : string {
	$tf = $this->_tok->getCallStack('tf');
	return (is_bool($tf) && !$tf) ? $out : '';
}


/**
 * Write message via log_debug.
 */
public function tok_log(string $txt) : void {
	\rkphplib\lib\log_debug("tok_log> $txt"); // @keep
}


}

