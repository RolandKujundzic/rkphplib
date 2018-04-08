<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/Exception.class.php');
require_once($parent_dir.'/File.class.php');
require_once($parent_dir.'/lib/htmlescape.php');
require_once($parent_dir.'/lib/split_str.php');
require_once($parent_dir.'/lib/redirect.php');
require_once($parent_dir.'/lib/conf2kv.php');
require_once($parent_dir.'/lib/kv2conf.php');
require_once($parent_dir.'/lib/entity.php');

use \rkphplib\Exception;
use \rkphplib\File;



if (!defined('SETTINGS_REQ_CRYPT')) {
  /** @const SETTINGS_REQ_CRYPT = 'cx' if undefined */
  define('SETTINGS_REQ_CRYPT', 'cx');
}

if (!defined('SETTINGS_CRYPT_SECRET')) {
  /** @const SETTINGS_CRYPT_SECRET = md5(Server + Module Info) if undefined */
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

/** @var Tokenizer $_tok */
private $_tok = null;

/** @var Hash $_tpl */
private $_tpl = [];

/** @var Hash $_conf  */
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
			if (strlen($value) == 0 && self::isEncodedHash($key)) {
				self::decodeHash($key, true);
				unset($_REQUEST[$key]);
			}
		}
	}
}


/**
 * Return Tokenizer plugin list:
 *
 * - tf: PARAM_LIST
 * - t, true: REQUIRE_BODY, TEXT, REDO
 * - f, false: REQUIRE_BODY, TEXT, REDO, NO_PARAM
 * - find: TEXT, REDO
 * - plugin: NO_PARAM, REQUIRE_BODY, CSLIST_BODY
 * - escape: REQUIRE_PARAM
 * - unescape: REQUIRE_PARAM
 * - encode: REQUIRE_PARAM
 * - decode: REQUIRE_PARAM
 * - get: 0
 * - include: REDO, REQUIRE_BODY
 * - include_if: REDO, REQUIRE_BODY, KV_BODY
 * - ignore: NO_PARAM, TEXT, REQUIRE_BODY
 * - if: REQUIRE_BODY, LIST_BODY
 * - keep: TEXT, REUIRE_BODY
 * - load: TEXT, REQUIRE_BODY
 * - link: PARAM_CSLIST, KV_BODY
 * - redo: NO_PARAM, REDO 
 * - toupper: NO_PARAM
 * - tolower: NO_PARAM
 * - hidden: PARAM_CSLIST, CSLIST_BODY
 * - join: KV_BODY
 * - var: REQUIRE_PARAM
 * - view: REQUIRE_PARAM, KV_BODY, REDO
 * - esc: 0
 * - row:n,n: REQUIRE_PARAM, PARAM_CSLIST, REQUIRE_BODY, LIST_BODY, IS_STATIC
 * - tpl_set: REQUIRE_PARAM, PARAM_LIST, REQUIRE_BODY, TEXT, IS_STATIC
 * - tpl: REQUIRE_PARAM, PARAM_LIST, LIST_BODY, IS_STATIC
 * - shorten: REQUIRE_PARAM
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$this->_tok = $tok;

	$plugin = [];

	$plugin['row:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['row'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_CSLIST | TokPlugin::REQUIRE_BODY | 
		TokPlugin::LIST_BODY | TokPlugin::IS_STATIC;

	$plugin['tpl_set'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_LIST | TokPlugin::REQUIRE_BODY | 
		TokPlugin::TEXT | TokPlugin::IS_STATIC;

	$plugin['tpl'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_LIST | TokPlugin::LIST_BODY | 
		TokPlugin::REDO | TokPlugin::IS_STATIC;

	$plugin['tf'] = TokPlugin::PARAM_LIST; 
	$plugin['t'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['true'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO; 
	$plugin['f'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO | TokPlugin::NO_PARAM; 
	$plugin['false'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO | TokPlugin::NO_PARAM;
	$plugin['find'] = TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['plugin'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::CSLIST_BODY;
	$plugin['escape'] = TokPlugin::REQUIRE_PARAM;
	$plugin['unescape'] = TokPlugin::REQUIRE_PARAM;
	$plugin['encode'] = TokPlugin::REQUIRE_PARAM;
	$plugin['decode'] = TokPlugin::REQUIRE_PARAM;
	$plugin['get'] = 0;
	$plugin['const'] = 0;
	$plugin['include'] = TokPlugin::REDO | TokPlugin::REQUIRE_BODY;
	$plugin['include_if'] = TokPlugin::REDO | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['view'] = TokPlugin::REDO | TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY;
	$plugin['clear'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
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
	$plugin['join'] = TokPlugin::KV_BODY;
	$plugin['set_default'] =  0;
	$plugin['redirect'] =  TokPlugin::NO_PARAM;
	$plugin['var'] = 0;
	$plugin['esc'] = 0;
	$plugin['log'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['shorten'] = TokPlugin::REQUIRE_PARAM;

	return $plugin;
}


/**
 * Return html escaped shortened text, wrappend in <span title="original text">shortened text</span>.
 * If maxlen < 20 and length($txt) > 20 use substr($txt, 0, maxlen - 3).' ...' as short text.
 * If maxlen >= 20 use substr($txt, 0, maxlen/2 - 2).' ... '.substr($txt, -maxlen/2 + 2).
 * If maxlen >= 60 use substr($txt, 0, maxlen - 25).' ... '.substr($txt, -20).
 *
 * @tok {shorten:10}Shorten this Text{:shorten} -> <span title="Shorten this Text">Shorten ...</span>
 * @tok {shorten:20}Shorten this long Text{:shorten} -> <span title="Shorten this long Text">Shorten ... ong Text</span> 
 * 
 * @param int $maxlen
 * @param string $txt
 * @return string
 */
public function tok_shorten($maxlen, $txt) {
	$len = mb_strlen($txt);

	if ($len < $maxlen) {
		return \rkphplib\lib\htmlescape($txt);
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

	$title = ($len > 512) ? '' : ' title="'.\rkphplib\lib\htmlescape($txt).'"';

	$res = '<span data-short="1"'.$title.'>'.\rkphplib\lib\htmlescape($short).
		'<span style="opacity:0.6"> ... '.\rkphplib\lib\htmlescape($rest).'</span></span>';

	return $res;
}


/**
 * Write message via log_debug.
 * 
 * @param string $txt
 * @return ''
 */
public function tok_log($txt) {
	// \rkphplib\lib\log_debug("tok_log> $txt");
}


/**
 * Return hidden input.
 *
 * @param array $param
 * @param array $arg
 * @return string
 */
public function tok_hidden($param, $arg) {
	$list = (count($param) > 0) ? $param : $arg;
	$res = '';

	foreach ($list as $key) {
		$value = isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';

		if (strlen($value) > 0) {
			$res .= '<input type="hidden" name="'.$key.'" value="'.\rkphplib\lib\htmlescape($value).'">'."\n";
		}
  }

  return $res;
}


/**
 * Trim text. 
 *
 * @tok {trim:dlines|ignore_empty|lines|pre|space|comma} ... {:trim}
 *
 * @param string $param
 * @param string $txt
 * @return string
 */
public function tok_trim($param, $txt) {
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
 * 
 * @throws if same template is defined twice.
 * @param vector $p 
 * @param string $arg
 * @return ''
 */
public function tok_tpl_set($p, $arg) {
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

	$this->_tpl[$key] = [ 'pnum' => $pnum, 'anum' => $anum, 'tpl' => $arg ];
}


/**
 * Return filled and parsed template. First parameter is template name, other 
 * parameter are values of tag param1, param2, ...
 *
 * @tok {tpl:test} = Nur ein Test
 * @tok {tpl:page:3:72}15|#|39{:tpl} = Page 3/72 line 15 column 39
 * 
 * @throws
 * @see tok_tpl_set
 * @param vector $p 
 * @param vector $arg
 * @return string
 */
public function tok_tpl($p, $arg) {
	$key = array_shift($p);

	if (!isset($this->_tpl[$key])) {
		throw new Exception("call [tpl_set:$key] first");
	}

	$pnum = $this->_tpl[$key]['pnum'];
	$anum = $this->_tpl[$key]['anum'];
	$tpl = $this->_tpl[$key]['tpl'];

	for ($i = 0; $i < $pnum; $i++) {
		$value = isset($p[$i]) ? $p[$i] : '';
		$tpl = str_replace(TAG_PREFIX.'param'.($i + 1).TAG_SUFFIX, $value, $tpl);
	}

	for ($i = 0; $i < $anum; $i++) {
		$value = isset($arg[$i]) ? $arg[$i] : '';
		$tpl = str_replace(TAG_PREFIX.'arg'.($i + 1).TAG_SUFFIX, $value, $tpl);
	}

	// \rkphplib\lib\log_debug("TBase.tok_tpl> $tpl"); 
	return $tpl;
}


/**
 * Retrieve (or set) Tokenizer.vmap value. Examples:
 *
 * @tok set a=17: {var:=a}17{:var}
 * @tok set hash: {var:=#b}x=5|#|y=12|#|...{:var}
 * @tok set vector: {var:+=b}x{:var}, {var:+=b},y{:var} - {var:b} = x,y
 * @tok get optional a: {var:a} or {var:}a{:var}
 * @tok get required a: {var:a!} (abort if not found)
 * @tok set multi-map: {var:=person.age}42{:var}
 * @tok get multi-map: {var:person.age}
 *
 * @throws
 * @param string $name
 * @param string $value
 */
public function tok_var($name, $value) {
	if (substr($name, 0, 2) == '+=') {
		$name = substr($name, 2);
		$this->_tok->setVar($name, $value, Tokenizer::VAR_APPEND);
	}
	else if (substr($name, 0, 1) == '=') {
		$name = substr($name, 1);

		if (substr($name, 0, 1) == '#') {
			$name = substr($name, 1);
			$this->_tok->setVar($name, \rkphplib\lib\conf2kv($value), Tokenizer::VAR_MUST_NOT_EXIST);
		}
		else {
			$this->_tok->setVar($name, $value);
		}
	}
	else {
		if (empty($name)) {
			if (!empty($value)) {
				$name = trim($value);
			}
			else {
				throw new Exception("invalid plugin ".$this->_tok->getPluginTxt("loop:", $value));
			}
		}

		$res = (string) $this->_tok->getVar($name);
		return $res;
	}
}


/**
 * Initialize row plugin. Parameter: 
 *
 * mode: bootstrap4 (=default, or: bootstrap3|table)
 * colnum: 2 (=default)
 * border: 0
 * cellpadding: 0
 * cellspacing: 0 
 * rownum: 1 (=default = add header before and footer after each row)
 *
 * @param hash $p
 */
public function tok_row_init($p) {
	$default = [ 'mode' => 'bootstrap4', 'colnum' => 2, 'rownum' => 1, 'border' => 0, 'cellpadding' => 0, 'cellspacing' => 0 ];
	$this->_conf['row'] = array_merge($default, $p);
}


/**
 * Place $p into into bootstrap|table row grid.
 * If [row:init] was not called assume mode=bootstrap4.
 * 
 * @tok {row:6,6}1-6|#|7-12{:row} -> <div class="row"><div class="col-md-6">1-6</div><div class="col-md-6">7-12</div></div>
 *
 * @param string $cols
 * @param vector $p
 */
public function tok_row($cols, $p) {

	if (!isset($this->_conf['row']) || empty($this->_conf['row']['mode'])) {
		$this->tok_row_init([ 'mode' => 'bootstrap4' ]);
	}

	if ($this->_conf['row']['mode'] == 'bootstrap4' || $this->_conf['row']['mode'] == 'bootstrap3') {
		return $this->bootstrapRow($cols, $p);
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
 * 
 * @throws
 * @param vector<int> $cols
 * @param vector<string> $p
 * @return string
 */
private function tableRow($cols, $p) {

	if (count($cols) != count($p)) {
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
		$res .= '<td'.$colspan.'>'.$p[$i].'</td>'."\n";
	}

	$res .= "</tr>\n";

	if (!empty($conf['rownum']) && $this->_conf['row']['current'] == $conf['rownum']) {
		unset($this->_conf['row']['current']);
		$res .= "</table>\n";
	}

	\rkphplib\lib\log_debug("TBase.tableRow> $res");
	return $res;
}


/**
 * Place $p into bootstrap row grid. Convert into form-row if possible.
 * 
 * @tok {row:6,6}a|#|b{:row} = <div class="row"><div class="col-md-6">a</div><div class="col-md-6">b</div></div>
 * 
 * @throws
 * @param vector<int> $cols
 * @param vector<string> $p
 * @return string
 */
private function bootstrapRow($cols, $p) {

	if (count($cols) != count($p)) {
		throw new Exception('[row:'.join(',', $cols).']... - column count != argument count', join(HASH_DELIMITER, $p));
	}

	$is_form_row = true;

	for ($i = 0; $is_form_row && $i < count($p); $i++) {
		if (strpos($p[$i], '<div class="form-group">') === false) {
			$is_form_row = false;
		}
	}

	if ($is_form_row) {
		$res = '<div class="form-row">'."\n";

		for ($i = 0; $i < count($cols); $i++) {
			$col = 'col-md-'.$cols[$i].' ';
			$res .= str_replace('<div class="form-group">', '<div class="form-group '.$col.'">', $p[$i]);
		}

		$res .= "</div>\n";
	}
	else {
		$res = '<div class="row">'."\n";

		for ($i = 0; $i < count($cols); $i++) {
			$res .= '<div class="col-md-'.$cols[$i].'">'.$p[$i]."</div>\n";
		}

		$res .= "</div>\n";
	}

	return $res;
}


/**
 * Redirect to $url. Use ERROR_[401|404] for error status.
 * Do nothing if $url is empty.
 *
 * @exit
 * @param string $url
 */
public function tok_redirect($url) {
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
		\rkphplib\lib\redirect($url);
  }
}


/**
 * Join array $p. Delimiter is either param or $p[0].
 * Non-join param values are: ignore_empty.
 *
 * @param string $param
 * @param array $p
 * @return string
 */
public function tok_join($param, $p) {
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
 *
 * @param string $txt
 * @return string
 */
public function tok_tolower($txt) {
	return mb_strtolower($txt);
}


/**
 * Convert all characters in $txt into uppercase.
 *
 * @param string $txt
 * @return string
 */
public function tok_toupper($txt) {
	return mb_strtoupper($txt);
}


/**
 * Don't parse body, return empty string.
 *
 * @tok {ignore:}abc{:ignore} = [] 
 * 
 * @param string $txt
 * @return empty-string
 */
public function tok_ignore($txt) {
	return '';
}


/**
 * Return empty text.
 *
 * @tok {clear:}{date:now}{:ignore} = return '', execute {date:now} 
 * 
 * @param string $txt
 * @return empty-string
 */
public function tok_clear($txt) {
	return '';
}


/**
 * Return un-parsed text.
 *
 * @tok {keep:}{find:a}{:keep} = {find:a}
 * 
 * @param string $txt
 * @return string
 */
public function tok_keep($txt) {
	// \rkphplib\lib\log_debug("keep=[$txt]");
	return $txt;
}


/**
 * Re-parse text.
 *
 * @tok {redo:}{dirname:}a/b{:dirname}{:redo} = a
 * 
 * @param string $txt
 * @return string
 */
public function tok_redo($txt) {
	return $txt;
}


/**
 * Include view file.
 *
 * @tok {view:overview}name=Overview{:view} = 
 *   <div id="overview" class="view" data-name="Overview">{include:}{get:dir}/overview.inc.html{:include}</div>
 * 
 * @throws if file {get:dir}/$name.inc.html does not exists
 * @param string $name
 * @param map $p
 * @return string
 */
public function tok_view($name, $p) {
	$file = self::getReqDir(true).'/'.$name.'.inc.html';
	$attrib = 'id="'.$name.'" class="view"';

	foreach ($p as $key => $value) {
		$attrib .= ' data-'.$key.'="'.\rkphplib\lib\htmlescape($value).'"';
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
 * 
 * @throws if file does not exists (unless param = ?) 
 * @param string $param optional=?|static
 * @param string $file
 * @return string
 */
public function tok_include($param, $file) {
	
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

		throw new Exception('include file missing', $file);
	}

	return File::load($file);
}


/**
 * Include file. Tokenize output.
 *
 * @tok {include_if:}|#|a.html{:include_if} = return tokenized content of a.html (throw error if file does not exist)
 * @tok {include_if:}1|#|a.html{:include_if} = return empty string
 * @tok {include_if:b}a|#|a.html|#|b.html{:include_if} = return tokenized content of b.html
 * @tok {include_if:a}a|#|a.html{:include_if} = return tokenized content of a.html 
 * 
 * @throws if file does not exists 
 * @param array $param
 * @param array $a a[0]=condition, a[1]=true path, a[2]=false path
 * @return string return true_path_content|false_path_content|''
 */
public function tok_include_if($param, $a) {

	if (count($a) < 2) {
		throw new Exception('invalid include_if:'.$param, print_r($a, true));
	}

	if (count($a) == 2) {
		$a[2] = '';
	}

	$file = ($param == $a[0]) ? $a[1] : $a[2];
	
	if (empty($file)) {
		return '';
	}

	return File::load($file);
}


/**
 * Include raw file content.
 * 
 * @tok {load:}a.html{:load} = return raw content of a.html (throw error if file does not exist)
 * @tok {load:optional}a.html{:load} = do not throw error if file does not exist (short version is "?" instead of optional)
 * 
 * @throws if file does not exists (unless param = ?) 
 * @param string $param
 * @param string $file
 * @return string
 */
public function tok_load($param, $file) {

	if (!File::exists($file)) {
		if ($param === 'optional' || $param === '?') {
			return '';
		}

		throw new Exception('load file missing', $file);
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
 *
 * @param array $name_list
 * @param array[string]string $p
 * @return string
 */
public function tok_link($name_list, $p) {
	// \rkphplib\lib\log_debug([ "TBase.tok_link|enter> ", $name_list, $p ]);
	$res = 'index.php?'.SETTINGS_REQ_CRYPT.'=';
	$keep = false;

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
			if (strlen($value) == 0 || isset($p[$key])) {
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

		$res = 'index.php?'.SETTINGS_REQ_DIR.'='.rawurlencode($dir);

		foreach ($p as $key => $value) {
			$res .= '&'.$key.'='.rawurlencode($value);
		}
	}
	else {
		$rbase = basename($_SERVER['SCRIPT_NAME']);

		if (!empty($rbase) && $res == '?') {
			$script_dir = dirname($_SERVER['SCRIPT_NAME']);
			$res = ($script_dir == '/') ? '/?' : $script_dir.'/?';
		}

		$res .= self::encodeHash($p);
	}

	// \rkphplib\lib\log_debug([ "TBase.tok_link|exit> $res", $name_list, $p ]);
	return $res;
}


/**
 * Return true if string is encoded hash.
 * 
 * @param string $txt
 * @return boolean
 */
private static function isEncodedHash($txt) {
	if (strlen($txt) < 5) {
		return false;
	}

	$checksum = substr($txt, 0, 2);
	$txt = substr($txt, 2);

	return self::checksum($txt) == $checksum;
}


/**
 * Return checksum (hex, 2 character) for text.
 * 
 * @param string $txt
 * @return char(2) (hex in ]15,255[)
 */
private static function checksum($txt) {
	$res = array_sum(str_split($txt.':'.strlen($txt).':'.crc32($txt))) % 255;

	if ($res < 16) {
		$res += 16;
	}

	return dechex($res);
}


/**
 * Convert map into encrypted string. 
 *
 * @param array[string]string $p
 * @return string
 */
public static function encodeHash($p) {
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
 * @param string $data
 * @param bool export into _REQUEST
 * @return hash|false
 */
public static function decodeHash($data, $export_into_req = false) {
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

	// \rkphplib\lib\log_debug("decodeHash: ".print_r($res, true));
	return $res;
}


/**
 * Return result of switch plugin. Example:
 * 
 * @tok {switch:a,b,c}value|#|if_eq_a|#|if_eq_b|#|if_eq_c|#|else{:switch}
 *
 * @throws 
 * @param vector $set
 * @param vector $p
 * @return string
 */
public function tok_switch($set, $p) {
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
 *
 * @throws 
 * @param string $param
 * @param string $arg
 * @return string
 */
public function tok_if($param, $p) {
	
	if (!empty($param)) {
		$tmp = \rkphplib\lib\split_str(':', $param);
		$do = $tmp[0];
		$param = isset($tmp[1]) ? $tmp[1] : '';
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
		$set = \rkphplib\lib\split_str(',', $param);
		$res = in_array($p[0], $set) ? $p[1] : $p[2];
	}
	else if ($do === 'if_in_set') {
		$set = \rkphplib\lib\split_str(',', $param);
		$res = in_array($param, $set) ? $p[1] : $p[2];
	}
	else if ($do === 'le' || $do === 'lt' || $do === 'ge' || $do === 'gt') {

		if (!empty($param)) {
			array_shift($p, $param);
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
		else if ($do === 'and')  {
			$cmp = true;

			for ($i = 0; $cmp && $i < $cnum; $i++) {
				if (empty($p[$i])) {
					$cmp = false;
				}
			}
		}

		$res = $cmp ? $p[$p_num - 2] : $p[$p_num - 1];
	}
	else if ($do === 'cmp') {
		if (!empty($param) && $param != 'and' && $param != 'or') {
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
				if ($p[$i] != $p[$i + 1]) {
					$cmp = false;
				}
			}
		}
		else if ($param === 'or') {
			$cmp = false;

			for ($i = 0; !$cmp && $i < $p_num - 3; $i = $i + 2) {
				if ($p[$i] == $p[$i + 1]) {
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
 * Return sql escaped argument ('$arg'). If argument is empty use trim($_REQUEST[param]).
 * Trim argument if param = t and _REQUEST[t] is not set.
 * Null argument if empty and param = null and _REQUEST[null] is not set.
 *
 * @tok {esc:} ab'c {:esc} -> [ ab''c ]
 * @tok {esc:t} 'a"' {:esc} -> [''a"'']
 * @tok {esc:a} AND _REQUEST[a] = " x " -> ' x '
 * @tok {esc:t} AND _REQUEST[t] = " x " -> 'x'
 * @tok {esc:}null{:esc} -> NULL
 * @tok {esc:}NULL{:esc} -> NULL
 * @tok {esc:null}{:esc} -> NULL
 *  
 * @param string $param
 * @param string $arg
 * @return string|null
 */
public function tok_esc($param, $arg) {

	if (!empty($param) && isset($_REQUEST[$param])) {
		$arg = trim($_REQUEST[$param]);
	}
	else if ($param == 't') {
		$arg = trim($arg);
	}
	else if ($param == 'null' && trim($arg) == '') {
		$arg = null;
	}

	if (is_null($arg) || $arg === 'null' || $arg === 'NULL') {
		$res = 'NULL';
	}
	else {
		require_once(PATH_RKPHPLIB.'ADatabase.class.php');
		$res = "'".\rkphplib\ADatabase::escape($arg)."'";
	}

	return $res;
}


/**
 * Set _REQUEST[$name] = $value if unset.
 *
 * @tok {set_default:id!}value{:set_default} - abort if _REQUEST['id'] is set
 * @tok {set_default:key}value{:set_default}
 * @tok {set_default:}key=value|#|...{:set_default}
 *
 * @throws if name! and _REQUEST[name] already defined
 * @param string $name
 * @param string $value
 * @return ''
 */
public function tok_set_default($name, $value) {

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
		$kv = \rkphplib\lib\conf2kv($value);
		foreach ($kv as $key => $value) {
			if (!isset($_REQUEST[$key])) {
				$_REQUEST[$key] = $value;
			}
		}
	}
	else if (!isset($_REQUEST[$name])) {
		$_REQUEST[$name] = $value;
	}

	return '';
}


/**
 * Return constant value. Constant name is either param or arg.
 *
 * @throws if undefined constant
 * @param string $param
 * @param string $arg
 * @return string
 */
public function tok_const($param, $arg) {
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
 * @tok {get:a}, !isset(_REQUEST['a']) && _FILES['a']['name'] = test.jpg: test.jpg
 * @tok {get:a.x}, _REQUEST['a'] = [ 'x' => 5, 'y' => 10 ]: 5
 * @tok {get:a}, _REQUEST['a'] = [ 3 ]: 3
 * @tok {get:a}, _REQUEST['a'] = [ 1, 2, 3 ]: ''
 * @tok {get:*}, return kv2conf(_REQUEST) 
 *
 * @param string $param
 * @param string $arg
 * @return string
 */
public function tok_get($param, $arg) {
	$key = empty($arg) ? $param : trim($arg);
	$res = '';

	if (isset($_REQUEST[$key])) {
		if (is_array($_REQUEST[$key])) {
			if (count($_REQUEST[$key]) === 1 && isset($_REQUEST[$key][0])) {
				$res = $_REQUEST[$key];
			}
		}
		else {
			$res = $_REQUEST[$key];
		}
	
		$res = $this->_tok->escape($res);
	}
	else if (isset($_FILES[$key]) && !empty($_FILES[$key]['name'])) {
		$res = $this->_tok->escape($_FILES[$key]['name']);
	}
	else if (($pos = mb_strpos($key, '.')) !== false) {
		$key1 = mb_substr($key, 0, $pos);
		$key2 = mb_substr($key, $pos + 1);

		if (isset($_REQUEST[$key1]) && is_array($_REQUEST[$key1]) && isset($_REQUEST[$key1][$key2])) {
			$res = $_REQUEST[$key1][$key2];
		}

		$res = $this->_tok->escape($res);
	}
	else if ($key == '*') {
		$res = \rkphplib\lib\kv2conf($_REQUEST);
	}

	return $res;
}


/**
 * Return escaped value. Parameter:
 *
 * - url: rawurlencode 
 * - js: same as javascript encodeURIcomponent = rawurlencode without "!,*,',(,)"
 * - tok: Tokenizer->escape $txt
 * - html: replace [ '&lt;', '&gt;', '&quot;' ] with [ '<', '>', '"' ]
 *
 * @tok {escape:tok}{x:}{:escape} = &#123;x&#58;&#125; 
 * @tok {escape:arg}a|#|b{:escape} = &#124;&#35;&#124; (|#| = HASH_DELIMITER)
 * @tok {escape:entity}|@||#|a|@|b{:escape} = a&#124;&#64;&#124b
 * @tok {escape:js}-_.|~!*'();:@&=+$,/?%#[]{:escape} = -_.%7C~!*'()%3B%3A%40%26%3D%2B%24%2C%2F%3F%25%23%5B%5D
 * @tok {escape:url}a b{:escape} = a%20b
 * @tok {escape:html}<a href="abc">{:escape} = &lt;a href=&quot;abc&quot;&gt;
 *  
 * @throws
 * @param string $param
 * @param string $txt
 * @return string
 */
public function tok_escape($param, $txt) {
	$res = $txt;

	if ($param == 'tok') {
		$res = $this->_tok->escape($txt);
	}
	else if ($param == 'url') {
		$res = rawurlencode($txt);
	}
	else if ($param == 'entity') {
		list ($entity, $txt) = explode(HASH_DELIMITER, $txt, 2);
		$res = str_replace($entity, \rkphplib\lib\entity($entity), $txt);
	}
	else if ($param == 'arg') {
		$res = str_replace(HASH_DELIMITER, \rkphplib\lib\entity(HASH_DELIMITER), $txt);
	}
	else if ($param == 'js') {
		// exclude "!,*,',(,)" to make it same as javascript encodeURIcomponent()
		$res = strtr(rawurlencode($txt), [ '%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')' ]);
	}
	else if ($param == 'html') {
		$res = str_replace('<', '&lt;', $txt);
		$res = str_replace('>', '&gt;', $res);
		$res = str_replace('"', '&quot;', $res);
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
 * - js: rawurldecode($txt)
 * - html: replace [ '&lt;', '&gt;', '&quot;' ] with [ '<', '>', '"' ]
 *
 * @tok {unescape:tok}&#123;x&#58;&#125;{:unescape} = {x:}
 * @tok {unescape:arg}a&#124;&#35;&#124;b{:unescape} = a|#|b 
 * @tok {unescape:entity}|@||#|a&#124;&#64;&#124;b{:unescape} = a|@|b
 * @tok {unescape:html}&lt;a href=&quot;abc&quot;&gt;{:unescape} = <a href="abc">
 * @tok {unescape:js}-_.%7C~!*'()%3B%3A%40%26%3D%2B%24%2C%2F%3F%25%23%5B%5D{:unescape} = -_.|~!*'();:@&=+$,/?%#[]
 * @tok {unescape:url}a%20b{:unescape} = a b
 * 
 * @throws 
 * @param string $param
 * @param string $txt
 * @return string
 */
public function tok_unescape($param, $txt) {
	$res = '';

	if ($param == 'tok') {
		$res = $this->_tok->unescape($txt);
	}
	else if ($param == 'url') {
		$res = rawurldecode($txt);
	}
	else if ($param == 'arg') {
    $res = str_replace(\rkphplib\lib\entity(HASH_DELIMITER), HASH_DELIMITER, $txt);
	}
	else if ($param == 'entity') {
		list ($entity, $txt) = explode(HASH_DELIMITER, $txt, 2);
		$res = str_replace(\rkphplib\lib\entity($entity), $entity, $txt);
	}
	else if ($param == 'js') {
		$res = rawurldecode($txt);
	}
	else if ($param == 'html') {
		$res = str_replace('&lt;', '<', $txt);
		$res = str_replace('&gt;', '>', $res);
		$res = str_replace('&quot;', '"', $res);
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
 * @tok {escape:base64}hello{:escape} = aGVsbG8=
 *
 * @throws
 * @param string $param
 * @param string $txt
 * @return string
 */
public function tok_encode($param, $txt) {
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
 * @tok {escape:base64}aGVsbG8={:escape} = hello
 *
 * @throws
 * @param string $param
 * @param string $txt
 * @return string
 */
public function tok_decode($param, $txt) {
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
 * {plugin:}TLogin, TLanguage, PHPLIB:TShop, inc/abc.php:\custom\XY{:plugin} -> 
 *   require_once(PATH_RKPHPLIB.'TLogin.class.php'); $this->tok->register(new \rkphplib\TLogin());
 *   require_once(PATH_RKPHPLIB.'TLanguage.class.php'); $this->tok->register(new \rkphplib\TLanguage());
 *   require_once(PATH_PHPLIB.'TShop.class.php'); $this->tok->register(new \phplib\TShop());
 *   require_once('inc/abc.php', $this->tok->register(new \custom\XY());
 *
 * @param vector $p
 * @return ''
 */
public function tok_plugin($p) {
	
	foreach ($p as $plugin) {

		if (mb_strpos($plugin, ':') === false) {
			require_once(PATH_RKPHPLIB.'tok/'.$plugin.'.class.php');
			$obj = '\\rkphplib\\tok\\'.$plugin;
			// \rkphplib\lib\log_debug("tok_plugin> require_once('".PATH_RKPHPLIB.'tok/'.$plugin.".class.php'); new $obj();");
		}
		else {
			list ($path, $obj) = explode(':', $plugin);

			if (basename($path) === $path && defined("PATH_$path")) {
				$incl_path = constant("PATH_$path").'tok/'.$obj.'.class.php';
				require_once($incl_path);
				$obj = '\\'.strtolower($path).'\\tok\\'.$obj;
				// \rkphplib\lib\log_debug("tok_plugin> require_once('$incl_path'); new $obj();");
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
 *
 * @throws if nothing was found (avoid with "?" suffix)
 * @see self::getReqDir
 * @see self::findPath
 * @param string $file
 * @param string $file2 (default = '')
 * @return string self::findPath(file, self::getReqDir(true))
 */
public function tok_find($file, $file2 = '') {

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
 *
 * @param bool $use_dot_prefix (default = false)
 * @return string
 */
public static function getReqDir($use_dot_prefix = false) {

	if (empty($_REQUEST[SETTINGS_REQ_DIR])) {
		$res = $use_dot_prefix ? '.' : '';
	}
	else {
		$res = $use_dot_prefix ? './'.$_REQUEST[SETTINGS_REQ_DIR] : $_REQUEST[SETTINGS_REQ_DIR];
	}

	return $res;
}


/**
 * Search path = (dir/file) in dir until found or dir = [.].
 * Throw Exception if path is not relative or has [../] or [\].
 * Return found path. 
 *
 * @throws
 * @param string $file
 * @param string $dir (default = '.')
 * @return string
 */
public static function findPath($file, $dir = '.') {

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
 * @param array $p
 * @param string $arg
 * @return empty
 */
public function tok_tf($p, $arg) {
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
			$tf = empty($ta) ? false : $ta;
		}
		else if ($p[0] === 'set') {
			$tf = \rkphplib\lib\split_str(HASH_DELIMITER, $arg);
		}
		else if (!empty($p[0])) {
			if (in_array($p[0], [ 'cmp', 'set', 'in_arr', 'in', 'in_set', 'and', 'or', 'cmp_and', 'cmp_or' ])) {
				$do = $p[0];
				$ap = \rkphplib\lib\split_str(HASH_DELIMITER, $arg);
			}
			else if (in_array($p[0], [ 'eq', 'ne', 'lt', 'gt', 'le', 'ge' ])) {
				$do = $p[0];
				$tmp = \rkphplib\lib\split_str(HASH_DELIMITER, $arg);
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
		$ap = array_merge($p, \rkphplib\lib\split_str(HASH_DELIMITER, $arg));
	}

	if (empty($do)) {
		$this->_tok->setCallStack('tf', $tf);
		return '';
	}

	if ($do == 'cmp') {
		if (count($ap) != 2) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		$tf = ($ap[0] === $ap[1]);
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
			$set = \rkphplib\lib\split_str(',', $ap[0]);
			$tf = in_array($ap[1], $set);
		}
		else {
			$set = \rkphplib\lib\split_str(',', $ap[1]);
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
				$tf = ($ap[$i] == $ap[$i + 1]);
			}
		}
		else {
			for ($i = 0, $tf = true; $tf && $i < $apn - 1; $i = $i + 2) {
				$tf = ($ap[$i] == $ap[$i + 1]);
			}
		}
	}

	$this->_tok->setCallStack('tf', $tf);
	return '';
}


/**
 * Same as tok_true().
 * @alias tok_true()
 */
public function tok_t($param, $arg) {
	return $this->tok_true($param, $arg);
}


/**
 * Return $out if last tf from tok.callstack is: $tf = true or (is_string(top($tf)) && $val = top($tf)).
 *
 * @param string $val
 * @param string $out
 * @return $out|empty
 */
public function tok_true($val, $out) {
	$tf = $this->_tok->getCallStack('tf');
	return ((is_bool($tf) && $tf) || (is_string($tf) && $tf === $val) || 
		(is_array($tf) && !empty($val) && in_array($val, $tf))) ? $out : '';
}


/**
 * Same as tok_false().
 * @alias tok_false()
 */
public function tok_f($out) {
	return $this->tok_false($out);
}


/**
 * Return $out if last tf from tok.callstack is false.
 *
 * @param string $out
 * @return $out|empty
 */
public function tok_false($out) {
	$tf = $this->_tok->getCallStack('tf');
	return (is_bool($tf) && !$tf) ? $out : '';
}


}

