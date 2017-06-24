<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Exception.class.php');
require_once(__DIR__.'/../lib/split_str.php');
require_once(__DIR__.'/../File.class.php');

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
 * Basic Tokenizer plugins.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TBase implements TokPlugin {

/** @var Tokenizer $_tok */
private $_tok = null;


/** 
 * Constructor. Decode crypted query data.
 */
public function __construct() {
	if (!empty($_REQUEST[SETTINGS_REQ_CRYPT])) {
		self::decodeHash($_REQUEST[SETTINGS_REQ_CRYPT], true);
	}
}


/**
 * Return Tokenizer plugin list:
 *
 * - tf: PARAM_LIST
 * - t, true: REQUIRE_BODY, TEXT, REDO
 * - f, false: REQUIRE_BODY, TEXT, REDO, NO_PARAM
 * - find: 
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$this->_tok = $tok;

	$plugin = [];
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
	$plugin['include'] = TokPlugin::REDO | TokPlugin::REQUIRE_BODY;
	$plugin['ignore'] = TokPlugin::TEXT | TokPlugin::REQUIRE_BODY;
	$plugin['if'] = TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['keep'] = TokPlugin::TEXT | TokPlugin::REQUIRE_BODY;
	$plugin['load'] = TokPlugin::TEXT | TokPlugin::REQUIRE_BODY;
	$plugin['link'] = TokPlugin::KV_BODY;

	return $plugin;
}


/**
 * Return empty string.
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
 * Return un-parsed text.
 *
 * @tok {keep:}{find:a}{:keep} = {find:a}
 * 
 * @param string $txt
 * @return string
 */
public function tok_keep($txt) {
	return $txt;
}


/**
 * Include file. Tokenize output.
 *
 * @tok {include:}a.html{:include} = return tokenized content of a.html (throw error if file does not exist)
 * @tok {include:optional}a.html{:include} = do not throw error if file does not exist (short version is "?" instead of optional)
 * @tok {include:}{find:a.html}{:include} 
 * 
 * @throws if file does not exists (unless param = ?) 
 * @param string $param
 * @param string $file
 * @return string
 */
public function tok_include($param, $file) {

	if (!File::exists($file)) {
		if ($param === 'optional' || $param === '?') {
			return '';
		}

		throw new Exception('include file missing', $file);
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
 * @param array[string]string
 * @return string
 */
public function tok_link($p) {
	$res = 'index.php?'.SETTINGS_REQ_CRYPT.'=';

	if (!empty($p['_'])) {
		$res  = $p['_'].'?'.SETTINGS_REQ_CRYPT.'=';
		unset($p['_']);
	}

	return $res.self::encodeHash($p);
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

  return urlencode(base64_encode($query_string));
}


/**
 * Decode data encoded with self::encodeHash.
 *
 * @param string $data
 * @param bool export into _REQUEST
 * @return hash
 */
public static function decodeHash($data, $export_into_req = false) {
  $data = base64_decode(urldecode($data));
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

	return $res;
}


/**
 * Check condition and return true or false block. Beware: all plugins inside if
 * will be execute before condition comparision - use {tf:} and {true|false:} to
 * avoid this.
 * 
 * {if:|eq|ne|in|in_set|le|lt|ge|gt|and|or|cmp|cmp:or}condition(s)|#|true|#|false{:if}
 *
 * {if:}abc|#|true|#|false{:if} = true
 * {if:}|#|true|#|false{:if} = false
 * {if:eq:abc}abc|#|true{:if} = true
 * {if:eq:abc}|#|true{:if} = ""
 * {if:ne:abc}abc|#|true|#|false{:if} = false
 * {if:ne:abc}|#|true{:if} = true
 * {if:in:2,5}3|#|true|#|false{:if} = false 
 * {if:in:2,5}2|#|true|#|false{:if} = true
 * {if:in_set:3}2,5|#|true|#|false{:if} = false
 * {if:in_set:5}2,5|#|true|#|false{:if} = true 
 * {if:le}2|#|3|#|true|#|false{:if} = true - same as {if:le:2}3|#|true|#|false{:if}
 * {if:lt:3}2|#|true|#|false{:if} = false - same as {if:lt}3|#|2|#|true|#|false{:if}
 * {if:ge}2|#|3|#|true|#|false{:if} = false - same as {if:ge:2}3|#|true|#|false{:if}
 * {if:gt:3}2|#|true|#|false{:if} = true - same as {if:gt}3|#|2|#|true|#|false{:if}
 * {if:and:2}1|#|1|#|true|#|false{:if} = true
 * {if:or:3}0|#|0|#|1|#|true|#|false{:if} = true
 * {if:cmp}a|#|a|#|b|#|c|#|true|#|false{:if} = false - same as {if:cmp:and}...
 * {if:cmp:or}a|#|a|#|b|#|c|#|true|#|false{:if} = true
 *
 * @throws 
 * @param string $param
 * @param string $arg
 * @return string
 */
public function tok_if($param, $p) {
	
	if (!empty($param)) {
		list ($do, $param) = \rkphplib\lib\split_str(':', $param, false, 2);
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

		if ($p_num % 2 == 1) {
			array_push($p, '');
			$p_num++;
		}

		if ($p_num < 4) {
			throw new Exception('invalid if', "do=$do param=$param p=".print_r($p, true));
		}

		if (empty($param) || $param === 'and') {
			$cmp = true;

			for ($i = 0; $cmp && $i < $pnum - 3; $i = $i + 2) {
				if ($p[$i] != $p[$i + 1]) {
					$cmp = false;
				}
			}
		}
		else if ($param === 'or') {
			$cmp = false;

			for ($i = 0; !$cmp && $i < $pnum - 3; $i = $i + 2) {
				if ($p[$i] == $p[$i + 1]) {
					$cmp = true;
				}
			}
		}

		$res = ($cmp) ? $p[$pnum - 2] : $p[$pnum - 1];
	}

	return $res;
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

	return $res;
}


/**
 * Return escaped value. Parameter:
 * 
 * - js: same as javascript encodeURIcomponent = rawurlencode without "!,*,',(,)"
 * - tok: Tokenizer->escape $txt
 * - html: replace [ '&lt;', '&gt;', '&quot;' ] with [ '<', '>', '"' ]
 *
 * @tok {escape:tok}{x:}{:escape} = &#123;x&#58;&#125; 
 * @tok {escape:js}-_.|~!*'();:@&=+$,/?%#[]{:escape} = -_.%7C~!*'()%3B%3A%40%26%3D%2B%24%2C%2F%3F%25%23%5B%5D
 * @tok {escape:html}<a href="abc">{:escape} = &lt;a href=&quot;abc&quot;&gt;
 *  
 * @throws
 * @param string $param
 * @param string $txt
 * @return string
 */
public function tok_escape($param, $txt) {
	$res = $txt;

	if ($param == 'tok_html') {
		$res = $this->_tok->escape($txt);
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
 * @tok {unescape:html}&lt;a href=&quot;abc&quot;&gt;{:unescape} = <a href="abc">
 * @tok {unescape:js}-_.%7C~!*'()%3B%3A%40%26%3D%2B%24%2C%2F%3F%25%23%5B%5D{:unescape} = -_.|~!*'();:@&=+$,/?%#[]
 * 
 * @throws 
 * @param string $param
 * @param string $txt
 * @return string
 */
public function tok_unescape($param, $txt) {
	$res = '';

	if ($param == 'tok_html') {
		$res = $this->_tok->unescape($txt);
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
		}
		else {
			list ($path, $obj) = explode(':', $plugin);

			if (basename($path) === $path && defined("PATH_$path")) {
				require_once(constant("PATH_$path").$obj);
				$obj = '\\'.strtolower($path).'\\'.$obj;
			}
			else {
				require_once($path);
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

	return self::findPath($file, self::getReqDir(true));
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
 * @test:t11 p.legnth >= 2 and p[0] == and: true if every entry in p[1...n] is not empty
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
			$tf = \rkphplib\lib\split_str('|#|', $arg);
		}
		else if (!empty($p[0]) && in_array($p[0], [ 'cmp', 'set', 'in_arr', 'in', 'in_set', 'and', 'or', 'cmp_and', 'cmp_or' ])) {
			$do = $p[0];
			$ap = \rkphplib\lib\split_str('|#|', $arg);
		}
		else {
			throw new Exception('invalid operator', 'use cmp');
		}
	}
	else if (count($p) > 1) {
		$do = array_shift($p);
		// even if arg is empty we need [] as ap - e.g. {tf:cmp:}{:tf} = true
		$ap = array_merge($p, \rkphplib\lib\split_str('|#|', $arg));
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

