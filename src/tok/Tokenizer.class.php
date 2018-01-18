<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Exception.class.php');
require_once(__DIR__.'/../File.class.php');

use \rkphplib\Exception;
use \rkphplib\File;


/**
 * String Tokenizer.
 *
 * Token Structure: [prefix][name][delimiter][parameter][suffix][body][prefix][delimiter][name][suffix].
 * Default prefix = "{", delimiter = ":", suffix = "}". Parameter and body are optional. Parsing is bottom-up
 * (but can be changed by plugin). Tokens can be nested. Tokens are replaced with result of associated plugin.
 *
 * Tag {action:param}body{:action} will be replaced with result of Plugin->tok_action(param, body).
 * Tag parameter and body are optional, e.g. {action:} or {action:param}. If body is empty close tag {:action} is 
 * not required. Escaped Tag is: &#123;action&#58;param&#125; (html-utf8-escape).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Tokenizer {

/** @var vector $rx Token expression (regular expression for start+end token, prefix, delimiter, suffix, esc-prefix, esc-delimiter, esc-suffix) */
public $rx = [ "/\{([a-zA-Z0-9_]*\:.*?)\}/s", '{', ':', '}', '&#123;', '&#58;', '&#125;' ];

/** @var string $file Token data from $file - defined if load($file) was used */
public $file = '';


/** @const TOK_IGNORE remove unkown plugin */
const TOK_IGNORE = 2;

/** @const TOK_KEEP keep unknown plugin */
const TOK_KEEP = 4;

/** @const TOK_DEBUG debug unknown plugin */
const TOK_DEBUG = 8;


/** @var map $vmap plugin variable interchange */
private $vmap = [];

/** @var map<string:map<object:int>> $_plugin */
private $_plugin = [];

/** @var vector<string> $_tok */
private $_tok = [];

/** @var map<int:int> $_endpos */
private $_endpos = [];

/** @var table<string:any> $callstack */
private $_callstack = [];

/** @var int $_config constructor config flag */
private $_config = 0;

/** @var array $_postprocess */
private $_postprocess = [];



/**
 * Constructor. Set behavior for unknown plugin (TOK_[IGNORE|KEEP|DEBUG).
 * Default is to abort if unknown plugin is found.
 *
 * @param int $config (0=default, Tokenizer::TOK_[IGNORE|KEEP|DEBUG])
 */
public function __construct($config = 0) {
	$this->_config = $config;
}


/**
 * Return this.vmap[$name]. If $name is "a.b.c" return this.vmap[a][b][c].
 * If variable does not exist return ''. If variable ends with ! throw 
 * exception if it does not exist.
 *
 * @throws
 * @param string
 * @return any
 */
public function getVar($name) {
	$required = false;

	if (substr($name, -1) == '!') {
		$required = true;
		$name = substr($name, 0, -1);
	}

	if (empty($name)) {
		throw new Exception('empty vmap name');
	}

	$path = explode('.', $name);
	$map = $this->vmap;
	$done = [];

	while (count($path) > 0) {
		$key = array_shift($path);
		array_push($done, $key);

		if (array_key_exists($key, $map)) {
			if (count($path) == 0) {
				return $map[$key];
			}
			else {
				$map = $map[$key];
			}
		}
		else {
			if (!$required) {
				return '';
			}
			
			throw new Exception('missing vmap.'.join('.', $done)." (vmap.$name)");
		}
	}
}


/**
 * Set this.vmap[$name] = $value. If $name is "a.b.c" set this.vmap[a][b][c] = $value.
 *
 * @throws
 * @param string
 * @param any
 * @param boolean must_not_exist (=false)
 */
public function setVar($name, $value, $must_not_exists = false) {

	if (empty($name)) {
		throw new Exception('empty vmap name');
	}

	$path = explode('.', $name);
	$map =& $this->vmap;

	while (count($path) > 0) {
		$key = array_shift($path);

		if (count($path) == 0) {
			if (isset($map[$key]) && $must_not_exists) {
				throw new Exception('setVar('.$name.') ({var:='.$name.'}) already exists', $value);
			}

			$map[$key] = $value;
		}
		else {
			if (!isset($map[$key]) || !is_array($map[$key])) {
				$map[$key] = [ ];
			}

			$map =& $map[$key];
		}
	}
}


/**
 * Return callstack.
 * 
 * @return vector<string>
 */
public function printCallStack() {
	$cs_rownum = count($this->_callstack);
	$res = '';

	for ($i = 0; $i < $cs_rownum; $i++) {
		$cs_row = $this->_callstack[$i];
		$res .= $i.': ';

		for ($j = 0; $j < count($cs_row); $j++) {
			$res .= ($j > 0) ? ', '.$cs_row[$j][0] : $cs_row[$j][0];
	
			if ($cs_row[$j][1] !== null) {
				$res .= ':'.json_encode($cs_row[$j][1]);
			}
		}

		$res .= "\n";
	}

	return trim($res);
}


/**
 * Set value to first found name from end of callstack. 
 * 
 * @throws if name is not found in callstack
 */
public function setCallStack($name, $value) {
	$cs_rownum = count($this->_callstack);

	for ($i = $cs_rownum - 1; $i >= 0; $i--) {
		$cs_row = $this->_callstack[$i];
		for ($j = count($cs_row) - 1; $j >= 0; $j--) {
			if ($cs_row[$j][0] === $name) {
				$this->_callstack[$i][$j][1] = $value;
				return;
			}
		}
	}

	throw new Exception('plugin missing in callstack', "set $name=$value");
}


/**
 * Get value of first found name from end of callstack. 
 * 
 * @throws if name is not found in callstack
 * @return any
 */
public function getCallStack($name) {
	$cs_rownum = count($this->_callstack);

	for ($i = $cs_rownum - 1; $i >= 0; $i--) {
		$cs_row = $this->_callstack[$i];
		for ($j = count($cs_row) - 1; $j >= 0; $j--) {
			if ($cs_row[$j][0] === $name) {
				return $cs_row[$j][1];
			}
		}
	}

	throw new Exception('plugin missing in callstack', "get $name");
}


/**
 * Tokenize file content according to $rx ({[a-zA-Z0-9_]*:.*}).
 * 
 * @param string $txt
 */
public function load($file) {
	$this->setText(File::load($file));
	$this->file = $file;
}


/**
 * Tokenize text according to $rx ({[a-zA-Z0-9_]*:.*}).
 * 
 * @param string $txt
 */
public function setText($txt) {
	$this->_tok = preg_split($this->rx[0], $txt, -1, PREG_SPLIT_DELIM_CAPTURE);
	$this->_endpos = $this->_compute_endpos($this->_tok);
}


/**
 * Return true all tags (e.g. {:=TAG}) exist in $txt.
 * 
 * @throws
 * @param string|array $txt
 * @param array $tags
 * @return bool
 */
public function hasReplaceTags($txt, $tags) {

	if (empty($txt) || mb_strpos($txt, $this->rx[2].'=') === false) {
		return false;
	}

	$found = 0;

	for ($i = 0; $i < count($tags); $i++) {
		$tag = $tags[$i];

		if (empty($tag)) {
			throw new Exception('invalid replace tag', "tag=[$tag]");
		}

		$tag = $this->rx[1].$this->rx[2].'='.$tag.$this->rx[3];
		if (mb_strpos($txt, $tag) !== false) {
			$found++;
		}
	}

	return $found > 0 && $found == $i;
}


/**
 * Return true if tag {$name:...} exists.
 * 
 * @param string $name
 * @return bool
 */
public function hasTag($name) {

	if (count($this->_tok) == 0) {
		throw new Exception('call setText() or load() first');
	}

	$tag = $name.':';
	$tl = mb_strlen($tag);

	for ($i = 1; $i < count($this->_tok); $i = $i + 2) {
		if (substr($this->_tok[$i], 0, $tl) == $tag) {
			return true;
		}
	}

	return false;
}


/**
 * Register plugins.
 * 
 * Plugin provider object must have method getPlugins(), e.g. handler.getPlugins() = { a: 2, b: 0 }.
 * Callback is handler.tok_a(param, body). Parse modes are:
 *
 *   0 = tokenized body (TokPlugin::PARSE)
 *   2 = untokenized body (TokPlugin::TEXT)
 *   4 = re-parse result (TokPlugin::REDO)
 *   8 = use tokCall(name, param, body) instead of tok_name(param, body) (TokPlugin::TOKCALL)
 *  16 = parameter is required (TokPlugin::REQUIRE_PARAM)
 *  32 = no parameter (TokPlugin::NO_PARAM)
 *  64 = body is required (TokPlugin::REQUIRE_BODY)
 * 128 = no body (TokPlugin::NO_BODY)
 * 256 = map string body (key=value|#|...) @see lib\conf2kv()
 * 512 = JSON body
 * 1024 = parameter is colon separated list e.g. {action:p1:p2:...} escape : with \:
 * 2048 = parameter is comma separated list e.g. {action:p1,p2,...} escape , with \,
 * 4096 = body is comma sparated list
 * 8192 = body is array string e.g. {action:}p1|#|p2|#| ... {:action} escape |#| with \|#|
 * 16384 = body is xml
 *
 * 6 = untokenized body + re-parse result (TokPlugin::TEXT | TokPlugin::REDO)
 *
 * If plugin parameter or argument needs parsing use handler.getPlugins() = { "name": { "parse": 2, "param": required }}.
 * 
 * @param object $handler
 */
public function register($handler) {

	$plugins = $handler->getPlugins($this);

	foreach ($plugins as $name => $opt) {
  	$this->_plugin[$name] = [ $handler, $opt ];
	}
}


/**
 * Old style plugin registration. These plugins use tokCall() callback.
 * 
 * @param string $name
 * @param object $handler
 */
public function setPlugin($name, $obj) {
	$this->_plugin[$name] = [ $handler, TokPlugin::TOKCALL ];
}


/**
 * Apply Tokenizer.
 *
 * @return string 
 */
public function toString() {
	$out = $this->_join_tok(0, count($this->_tok));

	for ($i = 0; $i < count($this->_postprocess); $i++) {
		$px = $this->_postprocess[$i];

		if (($px[3] & TokPlugin::REQUIRE_PARAM) && ($px[3] & TokPlugin::REQUIRE_BODY)) {
			$out = call_user_func($px[0], $px[1], $px[2], $out);
		}
		else if (($px[3] & TokPlugin::REQUIRE_PARAM) && ($px[3] & TokPlugin::NO_BODY)) {
			$out = call_user_func($px[0], $px[1], $out);
		}
		else if (($px[3] & TokPlugin::NO_PARAM) && ($px[3] & TokPlugin::REQUIRE_BODY)) {
			$out = call_user_func($px[0], $px[2], $out);
		}
		else if (($px[3] & TokPlugin::NO_PARAM) && ($px[3] & TokPlugin::NO_BODY)) {
			$out = call_user_func($px[0], $out);
		}
  }

	return $out;
}


/**
 * Recursive $_tok parser.
 * 
 * @throws rkphplib\Exception 'invalid plugin'
 * @param int $start
 * @param int $end
 * @return string
 */
private function _join_tok($start, $end) {
	array_push($this->_callstack, []);

	if (count($this->_tok) < 1 || count($this->_endpos) != count($this->_tok)) {
		throw new Exception('invalid status - call setText() first');
	}

	// \rkphplib\lib\log_debug("enter _join_tok - start=$start end=$end\ntok: ".print_r($this->_tok, true)."\nendpos: ".print_r($this->_endpos, true));
	$tok_out = array();

	for ($i = $start; $i < $end; $i++) {
		$tok = $this->_tok[$i];
		$ep = $this->_endpos[$i];
		$out = '';

		if ($i % 2 == 0) {
			$out = $tok;
		}
		else if ($ep == 0 || $ep < -3) {
			throw new Exception('invalid plugin', "parse error: i=$i ep=".$ep[$i].' tok='.$tok[$i]);
		}
		else if ($ep == -2) {
			$out = $this->rx[1].$tok.$this->rx[3]; // ignore
    }
		else if ($ep == -3) {
			// drop plugin end ...
		}
		else {
			// i position will change if $ep > 0
			$out = $this->_join_tok_plugin($i);
		}

		array_push($tok_out, $out);
	}

	$res = join('', $tok_out);
	array_pop($this->_callstack);

	// \rkphplib\lib\log_debug("end _join_tok (i=$i) - return:\n[$res]\n");
	return $res;
}


/**
 * Compute plugin output.
 * Loop position $i will change.
 *
 * @param int-reference $i
 * @return string
 */
private function _join_tok_plugin(&$i) {
	$tok = $this->_tok[$i];

	// \rkphplib\lib\log_debug("_join_tok_plugin($i): tok=".mb_substr($tok, 0, 60));

	// call plugin if registered ...
	$d  = $this->rx[2];
	$dl = mb_strlen($d);
	$pos = mb_strpos($tok, $d);
	$name = trim(mb_substr($tok, 0, $pos));
	$param = trim(mb_substr($tok, $pos + $dl));
	$buildin = '';
	$check_np = 0;
	$tp = 0;

	if (!isset($this->_plugin[$name])) {
		$this->tryPluginMap($name);
	}

	if (isset($this->_plugin[$name])) {
		$tp = $this->_plugin[$name][1];

		if (isset($this->_plugin['any'])) {
			$param = $tok;
			$name = 'any';
		}
		else if ($tp & TokPlugin::TOKCALL) {
			if (!method_exists($this->_plugin[$name][0], 'tokCall')) {
				throw new Exception('invalid plugin', "$name has no tokCall() method");
			}
		}
		else if (!method_exists($this->_plugin[$name][0], 'tok_'.$name)) {
			$check_np = 1;
		}
		else {
			$check_np = 2;
		}

		if ($check_np) {
			if (isset($this->_plugin[$name.$d.$param]) && method_exists($this->_plugin[$name.$d.$param][0], 'tok_'.$name.'_'.$param)) {
				// allow name:param -> tok_name_param() if tok_name does not exist
				$name = $name.$d.$param;
				$tp = $this->_plugin[$name][1];  // update plugin flag
				$param = '';
			}
			else if (($pos = mb_strpos($param, $d)) > 0) {
				$n2 = $name.$d.mb_substr($param, 0, $pos);
				$p2 = mb_substr($param, $pos + 1);

				if (isset($this->_plugin[$n2]) && method_exists($this->_plugin[$n2][0], 'tok_'.$name.'_'.mb_substr($param, 0, $pos))) {
					// allow name:param1:param2 -> tok_name_param1(param2) even if tok_name exists
					$name = $n2;
					$param = $p2;
					$tp = $this->_plugin[$name][1]; // update plugin flag
				}
				else if ($check_np === 1) {
					throw new Exception('invalid plugin', "no tok_$name() callback method");
				}
			}
			else if ($check_np === 1) {
				throw new Exception('invalid plugin', "no tok_$name() callback method");
			}
		}
	}
	else {
		// no such plugin - check for buildin ignore mode
		if ($this->_config & self::TOK_IGNORE) {
			$buildin = 'ignore';
		}
		else if ($this->_config & self::TOK_KEEP) {
			$buildin = 'keep';
		}
		else if ($this->_config & self::TOK_DEBUG) {
			$buildin = 'debug';
		}
		else {
			throw new Exception('invalid plugin '.$name, "$name:$param is undefined - config=".$this->_config);
		}
	}

	// plugin detection done ... now compute plugin output
	$ep = $this->_endpos[$i];
	$out = '';

	if ($buildin) {
		if ($ep == -1) {
			$out = $this->_buildin($buildin, $name, $param);
		}
		else if ($ep > $i) {
			$out = $this->_buildin($buildin, $name, $param, $this->_merge_txt($i+1, $ep-1));
			$i = $ep; // modify loop position
		}
		else {
			throw new Exception('invalid endpos', "i=$i ep=$ep");
		}
	}
	else {
		if ($ep == -1) {
			// \rkphplib\lib\log_debug("no arg: name=$name param=[$param] i=$i ep=$ep");
			$out = $this->_call_plugin($name, $param);
			// \rkphplib\lib\log_debug("out:\n[$out]\n");
		}
		else if ($ep > $i) {
			if ($tp & TokPlugin::TEXT) {
				// do not parse argument ...
				$arg = $this->_merge_txt($i + 1, $ep - 1);
			}
			else {
				// parse argument with recursive _join_tok call ...
				// \rkphplib\lib\log_debug("compute arg of $name with recursion: start=$i+1 end=$ep\n");
				$arg = $this->_join_tok($i + 1, $ep);
			}
 
			// \rkphplib\lib\log_debug("arg: name=$name param=[$param] arg=[$arg] i=$i ep=$ep");
			$out = $this->_call_plugin($name, $param, $arg);
 			// \rkphplib\lib\log_debug("set i=$ep - out:\n[$out]\n");

			$i = $ep; // modify loop position
		}
		else {
			throw new Exception('invalid endpos', "i=$i ep=$ep");
		}
	}

	if ($tp & TokPlugin::REDO) {
		$old_tok = $this->_tok;
		$old_endpos = $this->_endpos;

		// \rkphplib\lib\log_debug("REDO:\n---\n$out\n---\n");
		$this->setText($out);
		$out = $this->_join_tok(0, count($this->_tok));
		
		$this->_tok = $old_tok;
		$this->_endpos = $old_endpos;
	}

	return $out;
}


/**
 * Return tokenizer dump (tok, endpos). Flag:
 * 
 * 1 = _tok
 * 2 = _endpos
 *
 * @param int $flag (default = 3 = 1 | 2 = _endpos + _tok)
 * @return string
 */
public function dump($flag = 3) {
	$res = '';

	if ($flag & 2) {
		$res .= "\n_endpos:\n";
		for ($i = 1; $i < count($this->_endpos); $i = $i + 2) {
			$res .= sprintf("%3d:%d\n", $i, $this->_endpos[$i]);
		}
	}

	if ($flag & 1) {
		$res .= "\n_tok:\n";
		for ($i = 0; $i < count($this->_tok); $i++) {
			$t = preg_replace("/\r?\n/", "\\n", trim($this->_tok[$i]));

			if (mb_strlen($t) > 60) {
				$res .= sprintf("%3d: %s ... %s\n", $i, mb_substr($t, 0, 20), mb_substr($t, -20));
			}
			else {
				$res .= sprintf("%3d: %s\n", $i, $t);
			}
		}
	}

	return $res;
}


/**
 * Return {PLUGIN:PARAM}$arg{:PLUGIN}.
 *
 * @param string $tok (PLUGIN:PARAM)
 * @param string $arg (default = null = no argument)
 * @return string
 */
public function getPluginTxt($tok, $arg = null) {
	list ($name, $param) = mb_split($this->rx[2], $tok, 2);
	$res = '';

	if (is_null($arg)) {
		$res = $this->rx[1].$tok.$this->rx[3];
	}
	else {
		$res  = $this->rx[1].$name.$this->rx[2].$param.$this->rx[3].$arg.$this->rx[1].$this->rx[2].$name.$this->rx[3];
	}

	return $res;
}


/**
 * Return unparsed merged _tok from n to m.
 *
 * @param int n
 * @param int m
 * @return string
 */
private function _merge_txt($n, $m) {
	$res = '';

	for ($i = $n; $i <= $m; $i++) {
		if ($i % 2 == 0) {
			$res .= $this->_tok[$i];
		}
		else {
			$res .= $this->rx[1].$this->_tok[$i].$this->rx[3];
		}
	}

	return $res;
}


/**
 * Return result of buildin action (ignore, keep and debug) .
 * If action is ignore return empty. 
 *
 * @param string $action (ignore, keep and debug)
 * @param string $name
 * @param string $param
 * @param string $arg (default = null = no argument)
 * @return string
 */
private function _buildin($action, $name, $param, $arg = null) {	
	$res = '';

	if ($action == 'ignore') {
		// do nothing ...
	}
	else if ($action == 'keep') {
		$tok = $name.$this->rx[2].$param;
		$res = is_null($arg) ? $this->rx[1].$tok.$this->rx[3] : $this->rx[1].$tok.$this->rx[3].$arg.$this->rx[1].$this->rx[2].$name.$this->rx[3];
  }
	else if ($action == 'debug') {
		$res = is_null($arg) ? "{debug:$name:$param}": "{debug:$name:$param}$arg{:debug}";
	}

	return $res;
}


/**
 * Return plugin result = $plugin->tok_NAME($param, $arg).
 * If callback mode is not TokPlugin::TOKCALL preprocess param and arg. Example:
 *
 * Convert param into vector and arg into map if plugin $name is configured with
 * TokPlugin::KV_BODY | TokPlugin::PARAM_CSLIST | TokPlugin::REQUIRE_BODY | TokPlugin::REQUIRE_PARAM 
 *
 * @throws rkphplib\Exception 'plugin parameter missing', 'invalid plugin parameter', 'plugin body missing', 'invalid plugin body'
 * @param string $name
 * @param string $param
 * @param string $arg (default = null = no argument)
 * @return string
 */
private function _call_plugin($name, $param, $arg = null) {	

	$csl = count($this->_callstack);
	array_push($this->_callstack[$csl - 1], [ $name, null ]);

	if ($this->_plugin[$name][1] & TokPlugin::TOKCALL) {
		return call_user_func(array($this->_plugin[$name][0], 'tokCall'), $name, $param, $arg);
	}

	if ($this->_plugin[$name][1] & TokPlugin::POSTPROCESS) {
		array_push($this->_postprocess, [ array($this->_plugin[$name][0], 'tok_'.str_replace(':', '_', $name)), 
			$param, $arg, $this->_plugin[$name][1] ]);
		return '';
	}

	$func = 'tok_'.$name;
	$pconf = $this->_plugin[$name][1];
	$plen = strlen($param);
	$alen = strlen($arg);

	if (($pos = mb_strpos($name, $this->rx[2])) > 0) {
		// tok_name_param callback !
		$func = 'tok_'.str_replace($this->rx[2], '_', $name);
	}

	if (($pconf & TokPlugin::REQUIRE_PARAM) && $plen == 0) {
		throw new Exception('plugin parameter missing', "plugin=$name pconf=[$pconf]");
	}
	else if (($pconf & TokPlugin::NO_PARAM) && $plen > 0) {
		throw new Exception('invalid plugin parameter', "plugin=$name pconf=[$pconf] param=$param");
	}

	if (($pconf & TokPlugin::REQUIRE_BODY) && $alen == 0) {
		throw new Exception('plugin body missing', "plugin=$name pconf=[$pconf] param=[$param] arg=[$arg]\n".$this->dump());
	}
	else if (($pconf & TokPlugin::NO_BODY) && $alen > 0) {
		throw new Exception('invalid plugin body', "plugin=$name pconf=[$pconf] arg=[$arg]");
	}

	$src_dir = dirname(__DIR__);

	if (($pconf & TokPlugin::PARAM_LIST) || ($pconf & TokPlugin::PARAM_CSLIST)) {
		require_once($src_dir.'/lib/split_str.php');
		$delim = ($pconf & TokPlugin::PARAM_LIST) ? ':' : ',';
		$param = \rkphplib\lib\split_str($delim, $param);
	}

	if ($pconf & TokPlugin::KV_BODY) {
		require_once($src_dir.'/lib/conf2kv.php');
		$arg = \rkphplib\lib\conf2kv($arg);
	}
	else if ($pconf & TokPlugin::JSON_BODY) {
		require_once($src_dir.'/JSON.class.php');
		$arg = \rkphplib\JSON::decode($arg);
	}
	else if (($pconf & TokPlugin::CSLIST_BODY) || ($pconf & TokPlugin::LIST_BODY)) {
		require_once($src_dir.'/lib/split_str.php');
		$delim = ($pconf & TokPlugin::CSLIST_BODY) ? ',' : HASH_DELIMITER;
		$arg = \rkphplib\lib\split_str($delim, $arg);
	}
	else if ($pconf & TokPlugin::XML_BODY) {
		require_once($src_dir.'/XML.class.php');	
		$arg = \rkphplib\XML::toMap($arg);
	}

	$res = '';

	if ($pconf & TokPlugin::NO_PARAM) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $arg);
	}
	else if ($pconf & TokPlugin::NO_BODY) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $param);
	}
	else {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $param, $arg);
	}

	return $res;
}


/**
 * Return endpos list for $tok. Values of _endpos[n]:
 * 
 *   0: unknown
 * > 0: position of plugin end 
 *  -1: param only plugin {xxx:yyyy}
 *  -2: ignore
 *  -3: plugin end ({:xxxx})
 * 
 * @throws rkphplib\Exception 'invalid plugin'
 * @param array $tok
 * @return array
 */
private function _compute_endpos($tok) {

	$endpos = array();

	for ($i = 0; $i < count($tok); $i++) {
		$endpos[$i] = 0;
	}

	$d = $this->rx[2];
	$dl = mb_strlen($d);
  
	for ($i = 1; $i < count($tok); $i = $i + 2) {
		$plugin = $tok[$i];
		$start = '';

		if (mb_substr($plugin, 0, $dl) == $d) {
			// ignore plugin ... unless start is found ...
			$endpos[$i] = -2;
			$end = mb_substr($plugin, $dl);

			// {=:}x{:=} is forbidden ...
			if (mb_substr($end, 0, 1) != '=') {
				$start = empty($end) ? $d : $end.$d;
			}
		}

		if ($start) {
			// find plugin start ...
			$found = false;

			for ($j = $i - 2; !$found && $j > 0; $j = $j - 2) {
				$prev_plugin = $tok[$j];

				if ($endpos[$j] == -1 && ($xpos = mb_strpos($prev_plugin, $start)) !== false && ($start == $d || $xpos == 0)) {
					$found = true;
					$endpos[$j] = $i;
					$endpos[$i] = -3;
				}
			}
		}
		else if ($endpos[$i] == 0) {
			// parameter only plugin ...
			$endpos[$i] = -1;
		}
	}

	// Check endpos sanity, e.g. {a:}{b:}{:a}{:b} is forbidden
	$max_ep = array(count($endpos) - 2);
	$max = 0;

	for ($i = 1; $i < count($endpos) - 1; $i = $i + 2) {
		$max = end($max_ep);

		if ($max < $i) {
			array_pop($max_ep);
			$max = end($max_ep);
		}

		if ($endpos[$i] > 0) {
			if ($endpos[$i] > $max) {
				throw new Exception('invalid plugin', "Plugin [".$tok[$i]."] must end before [".$tok[$max]." i=[$i] ep=[".$endpos[$i]."] max=[$max]");
			}
			else {
				array_push($max_ep, $endpos[$i]);
			}
		}
	}

	return $endpos;
}


/**
 * Return escaped string. Replace $rx[1..3] with rx[4..6].
 * Example: {action:param} = &#123;action&#58;param&#125;
 *
 * @param string $txt
 * @param vector<string> $rx (default = null = use $this->rx)
 * @return string
 */
public function escape($txt, $rx = null) {

	if (is_null($rx)) {
		$rx = $this->rx;
	}

	if (($p1 = mb_strpos($txt, $rx[1])) === false || 
			($p2 = mb_strpos($txt, $rx[2], $p1 + 1)) === false ||
			($p3 = mb_strpos($txt, $rx[3], $p2 + 1)) === false) {
		return $txt;
	}

	$tok = preg_split($rx[0], $txt, -1, PREG_SPLIT_DELIM_CAPTURE);

	if (count($tok) === 1) {
		return $txt;
	}

	for ($i = 1; $i < count($tok); $i = $i + 2) {
		$tok[$i] = $rx[4].str_replace($rx[2], $rx[5], $tok[$i]).$rx[6];
	}

  return join('', $tok);
}


/**
 * Return unescaped string. Replace $rx[4..6] with rx[1..3].
 * Example: &#123;action&#58;param&#125; = {action:param}
 *
 * @param string $txt
 * @return string
 */
public function unescape($txt, $rx = null) {
	$rx = [ '', $this->rx[4], $this->rx[5], $this->rx[6], $this->rx[1], $this->rx[2], $this->rx[3] ];
	$rx[0] = '/'.preg_quote($this->rx[4]).'([a-zA-Z0-9_]*'.preg_quote($this->rx[5]).'.*?)'.preg_quote($this->rx[6]).'/s';
	return $this->escape($txt, $rx);
}


/**
 * Return $tpl with {:=key} (rx[1].$rx[2].'='.$key.$rx[3]) replaced by replace[key].
 *
 * @throws if tag not found
 * @param string $tpl
 * @param map $replace
 * @param string $prefix
 * @return string
 */
public function replaceTags($tpl, $replace, $prefix = '') {
	foreach ($replace as $key => $value) {
		if (!is_array($value)) {
			$tag = $this->rx[1].$this->rx[2].'='.$prefix.$key.$this->rx[3];
			$tpl = str_replace($tag, $value, $tpl);
		}
	}

	return $tpl;
}


/**
 * Return {:=$name}. Use $name = 'TAG:PREFIX' for "{:=" and $name = 'TAG:SUFFIX' for "}".
 *
 * @param string $name
 * @return string
 */
public function getTag($name) {
	$res = $this->rx[1].$this->rx[2].'='.$name.$this->rx[3];

	if ($name == 'TAG:PREFIX') {
		$res = $this->rx[1].$this->rx[2].'=';
	}
	else if ($name == 'TAG:SUFFIX') {
		$res = $this->rx[3];
	}

	return $res;
}


/** AUTO CREATED BY bin/plugin_map */
private function tryPluginMap($name) {
	static $map = [
		'TArray' => [ 'array', 'array:set', 'array:get', 'array:shift', 'array:unshift', 'array:pop', 'array:push', 'array:join', 'array:length' ],
		'TBase' => [ 'tf', 't', 'true', 'f', 'false', 'find', 'plugin', 'escape', 'unescape', 'encode', 'decode', 'get', 'include', 'include_if', 'ignore', 'if', 'switch', 'keep', 'load', 'link', 'toupper', 'tolower', 'join', 'set_default', 'redirect', 'var', 'esc' ],
		'TDate' => [ 'date' ],
		'TEval' => [ 'eval:math', 'eval:logic', 'eval' ],
		'TFormValidator' => [ 'fv', 'fv:init', 'fv:conf', 'fv:check', 'fv:in', 'fv:error', 'fv:error_message', 'fv:set_error_message' ],
		'THtml' => [ 'html:inner', 'html:tidy', 'html:xml', 'html:uglify', 'html' ],
		'TLanguage' => [ 'language:init', 'language:get', 'language', 'txt', 'ptxt' ],
		'TLogin' => [ 'login', 'login_account', 'login_check', 'login_auth', 'login_update', 'login_clear' ],
		'TLoop' => [ 'loop:var', 'loop:list', 'loop:hash', 'loop:show', 'loop:join', 'loop:count', 'loop' ],
		'TMath' => [ 'nf', 'number_format' ],
		'TMenu' => [ 'menu', 'menu:add', 'menu:conf', 'menu:privileges' ],
		'TOutput' => [ 'output:set', 'output:get', 'output:conf', 'output:init', 'output:loop', 'output:header', 'output:footer', 'output:empty', 'output' ],
		'TPicture' => [ 'picture:init', 'picture:src', 'picture' ],
		'TSQL' => [ 'sql:query', 'sql:dsn', 'sql:name', 'sql:qkey', 'sql:json', 'sql:col', 'sql:getId', 'sql:nextId', 'sql:in', 'sql:password', 'sql', 'null' ],
		'TTwig' => [ 'autoescape', 'block', 'do', 'embed', 'extends', 'filter', 'flush', 'for', 'from', 'if', 'import', 'include', 'macro', 'sandbox', 'set', 'spaceless', 'use', 'verbatim', 'v' ]
	];

	foreach ($map as $cname => $list) {
		if (in_array($name, $list)) {
			require_once(__DIR__.'/'.$cname.'.class.php');
			$cname = '\\rkphplib\\tok\\'.$cname;
			$obj = new $cname();
			$this->register($obj);
			return;
		}
	}
}

}
