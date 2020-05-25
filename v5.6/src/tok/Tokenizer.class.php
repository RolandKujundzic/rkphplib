<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Exception.class.php';
require_once __DIR__.'/../File.class.php';

use rkphplib\Exception;
use rkphplib\File;


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

/** @var Tokenizer $site Tokenizer Object for Website (first tokenizer object created) */
public static $site = null;

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

/** @const TOK_AUTOLOAD autoload unknown plugin */
const TOK_AUTOLOAD = 16;


/** @const VAR_MUST_NOT_EXIST throw exception in setVar() if key already exists */
const VAR_MUST_NOT_EXIST = 1;

/** @const VAR_APPEND append value to vector key in setVar() */
const VAR_APPEND = 2;


/** @var $last plugin call stack */
public $last = [];

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
 * Default $flag (=16) is to abort if unknown plugin is found. Values are
 * 2^n: Tokenizer::TOK_[IGNORE|KEEP|DEBUG|AUTOLOAD])
 */
public function __construct($flag = 16) {
	if (is_null(self::$site)) {
		self::$site =& $this;
	}

	$this->_config = $flag;
}


/**
 * Return this.vmap[$name] (any). If $name is "a.b.c" return this.vmap[a][b][c].
 * If variable does not exist return false. If variable ends with ! throw 
 * exception if it does not exist.
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
				return false;
			}
			
			throw new Exception('missing vmap.'.join('.', $done)." (vmap.$name)");
		}
	}
}


/**
 * Log to self::$site.vmap[$to] in append (<br>\n) mode.
 * Retrieve log via {var:$to}. Log only in cms directory.
 * Log only if SETTINGS_TOKENIZER_LOG is set. Message is
 * either string or [ 'message' => '...', 'label' => '...' ].
 */
public static function log($message, $to) {

	if (!defined('SETTINGS_TOKENIZER_LOG') || !SETTINGS_TOKENIZER_LOG) {
		return;
	}

	if (substr($to, 0, 4) != 'log.') {
		throw new Exception("missing log prefix [log.] in [$to]");
	}

	if (is_array($message)) {
		$m = '';

		if (isset($message['label'])) {
			$m .= '<span style="opacity: 0.7">'.$message['label'].'</span> ';
		}

		if (isset($message['message'])) {
			$m .= $message['message'];
		}

		$message = $m;
	}

	if (defined('DOCROOT')) {
		$message = str_replace(DOCROOT, '..', $message); 
	}

	self::$site->setVar($to, $message."<br>\n", self::VAR_APPEND);
}


/**
 * Set this.vmap[$name] = $value ($value type = any). If $name is "a.b.c" set this.vmap[a][b][c] = $value.
 * Concatenate with existing value in append mode ($flag = VAR_MUST_NOT_EXIST | VAR_APPEND). 
 */
public function setVar($name, $value, $flags = 0) {

	if (empty($name)) {
		if (is_array($value)) {
			$this->vmap = $value;
		}
		else {
			throw new Exception('empty vmap name');
		}
	}

	$path = explode('.', $name);
	$map =& $this->vmap;

	while (count($path) > 0) {
		$key = array_shift($path);

		if (count($path) == 0) {
			if (isset($map[$key]) && ($flags & self::VAR_MUST_NOT_EXIST)) {
				throw new Exception('setVar('.$name.', ...) ({var:='.$name.'}) already exists', $value);
			}

			if ($flags & self::VAR_APPEND) {
				if (!isset($map[$key])) {
					$map[$key] = $value;
				}
				else {
					$map[$key] .= $value;
				}
			}
			else {
				$map[$key] = $value;
			}
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
 * Set value (any) to first found name from end of callstack. 
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
 * Get value (any) of first found name from end of callstack. 
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
 * Tokenize $file content according to this.$rx.
 */
public function load($file) {
	$this->setText(File::load($file));
	$this->file = $file;
}


/**
 * Tokenize $text according to this.$rx.
 */
public function setText($txt) {
	$this->_tok = preg_split($this->rx[0], $txt, -1, PREG_SPLIT_DELIM_CAPTURE);
	$this->_endpos = $this->_compute_endpos($this->_tok);
}


/**
 * Return true if all $tags (e.g. {:=TAG}) exist in $txt.
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
 */
public function register($handler) {

	$plugins = $handler->getPlugins($this);

	foreach ($plugins as $name => $opt) {
  	$this->_plugin[$name] = [ $handler, $opt ];
	}
}


/**
 * Old style plugin registration. These plugins use tokCall() callback.
 */
public function setPlugin($name, $obj) {
	$this->_plugin[$name] = [ $handler, TokPlugin::TOKCALL ];
}


/**
 * Apply Tokenizer.
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
 */
private function _join_tok($start, $end) {
	array_push($this->_callstack, []);

	if (count($this->_tok) < 1 || count($this->_endpos) != count($this->_tok)) {
		throw new Exception('invalid status - call setText() first');
	}

	// \rkphplib\lib\log_debug("Tokenizer._join_tok:443> start=$start end=$end\ntok: ".print_r($this->_tok, true)."\nendpos: ".print_r($this->_endpos, true));
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

		if (is_array($out)) {
			throw new Exception('Output of plugin ['.$tok.'] is not string');
		}

		array_push($tok_out, $out);
	}

	$res = join('', $tok_out);
	array_pop($this->_callstack);

	// \rkphplib\lib\log_debug("Tokenizer._join_tok:478> i=[$i] return:\n[$res]\n");
	return $res;
}


/**
 * Return current plugin name. Call only once before executing callPlugin().
 */
public function getCurrentPlugin() {
	if (count($this->last) < 1) {
		throw new Exception('last is not set');
	}

	return array_pop($this->last);
}


/**
 * Compute plugin output. Loop position $i will change.
 */
private function _join_tok_plugin(&$i) {
	$tok = $this->_tok[$i];

	// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:501> i=$i, tok=".mb_substr($tok, 0, 60));

	// call plugin if registered ...
	$d  = $this->rx[2];
	$dl = mb_strlen($d);
	$pos = mb_strpos($tok, $d);
	$name = trim(mb_substr($tok, 0, $pos));
	$param = trim(mb_substr($tok, $pos + $dl));
	$buildin = '';
	$check_np = 0;
	$tp = 0;

	array_push($this->last, $name);

	if (isset($this->_plugin['catchall'])) {
		// if [catchall] was registered as plugin run everything through this handler ...
		$name = 'catchall';
	}

	if (!isset($this->_plugin[$name]) && ($this->_config & self::TOK_AUTOLOAD)) {
		$this->tryPluginMap($name);
	}

	if (isset($this->_plugin[$name])) {
		$tp = $this->_plugin[$name][1];

		if ($tp & TokPlugin::TOKCALL) {
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
					throw new Exception('invalid plugin '.$this->getPluginTxt("$name:$param"), "no tok_$name() callback method");
				}
			}
			else if ($check_np === 1) {
				throw new Exception('invalid plugin '.$this->getPluginTxt("$name:$param"), "no tok_$name() callback method");
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
			// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:599> no arg: name=$name param=[$param] i=$i ep=$ep");
			$out = $this->_call_plugin($name, $param);
			// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:601> out:\n[$out]\n");
		}
		else if ($ep > $i) {
			if ($tp & TokPlugin::TEXT) {
				// do not parse argument ...
				$arg = $this->_merge_txt($i + 1, $ep - 1);
			}
			else {
				// parse argument with recursive _join_tok call ...
				// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:610> compute arg of $name with recursion: start=$i+1 end=$ep\n");
				$arg = $this->_join_tok($i + 1, $ep);
			}
 
			// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:614> arg: name=$name param=[$param] arg=[$arg] i=$i ep=$ep");
			$out = $this->_call_plugin($name, $param, $arg);
 			// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:616> set i=$ep - out:\n[$out]\n");

			$i = $ep; // modify loop position
		}
		else {
			throw new Exception('invalid endpos', "i=$i ep=$ep");
		}
	}

/*
	if ($tp & TokPlugin::REDO) {
		$old_tok = $this->_tok;
		$old_endpos = $this->_endpos;

		\rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:680> REDO:\n---\n$out\n---\n");
		$this->setText($out);
		$out = $this->_join_tok(0, count($this->_tok));
		
		$this->_tok = $old_tok;
		$this->_endpos = $old_endpos;
	}
*/

	return $out;
}


/**
 * Return tokenizer dump (tok, endpos). Flag: 1 = 2^0 = _tok, 2 = 2^1 = _endpos
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
 * Return {PLUGIN:PARAM}$arg{:PLUGIN}. Parameter $tok (string|array[2]) is either 'name:param' or [ name, param ].
 * No argument if $arg is null.
 */
public function getPluginTxt($tok, $arg = null) {

	if (is_array($tok) && count($tok) == 2) {
		list ($name, $param) = $tok;
		$tok = $name.$this->rx[2].$param;
	}
	else {
		list ($name, $param) = mb_split($this->rx[2], $tok, 2);
	}

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
 * Return result of buildin $action (ignore|keep|debug). If action is ignore return empty. 
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
 * Return true if plugin $name exists.
 */
public function hasPlugin($name) {
	return !empty($this->_plugin[$name]);
}


/**
 * Return result (any) of plugin $name function $func. Parameter $args (default = []) is mixed:
 *
 * - vector (max length 3) use call_user_func(PLUGIN, $func[, args[0], args[1], args[2]]).
 * - hash use call_user_func(PLUGIN, $func, args).
 * - string assume $func=$param and use this._call_plugin($name, $func, $args).
 * 
 * Example:
 *  callPlugin('login', 'tok_login', [ 'id' ]) = callPlugin('login', 'id')
 *  callPlugin('row', 'init', 'mode=material');
 *  callPlugin('row', '2,3', 'a|#|b');
 */
public function callPlugin($name, $func, $args = []) {

	if (empty($this->_plugin[$name])) {
		throw new Exception('no such plugin '.$name, join('|', array_keys($this->_plugin)));
	}

	if (strpos($func, 'tok_') !== 0 && !method_exists($this->_plugin[$name][0], $func)) {
		if (is_null($args) || (is_array($args) && count($args) == 0)) {
			$args = '';
		}
		else if (!is_string($args)) {
			throw new Exception('invalid args string', "name=$name func=$func args: ".print_r($args, true));
		}

		if (isset($this->_plugin[$name.':'.$func])) {
			$name = $name.':'.$func;
			$func = '';
		}

		// \rkphplib\lib\log_debug("Tokenizer.callPlugin:780> return this._call_plugin($name, $func, $args)");
		return $this->_call_plugin($name, $func, $args);
	}

	if (!method_exists($this->_plugin[$name][0], $func)) {
		throw new Exception("no such plugin method $name.".$func);
	}

	// \rkphplib\lib\log_debug("Tokenizer.callPlugin:788> name=$name, funct=$func, args: ".print_r($args, true));
	if (count($args) == 0) {
		$res = call_user_func(array($this->_plugin[$name][0], $func));
	}
	else if (!isset($args[0]) || count($args) > 3) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $args);
	}
	else if (count($args) == 1) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $args[0]);
	}
	else if (count($args) == 2) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $args[0], $args[1]);
	}
	else if (count($args) == 3) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $args[0], $args[1], $args[2]);
	}

	return $res;
}


/**
 * Return plugin result = $plugin->tok_NAME($param, $arg). If callback mode is not TokPlugin::TOKCALL 
 * preprocess $param and $arg (default = null = no argument). Example:
 *
 * Convert param into vector and arg into map if plugin $name is configured with
 * TokPlugin::KV_BODY | TokPlugin::PARAM_CSLIST | TokPlugin::REQUIRE_BODY | TokPlugin::REQUIRE_PARAM 
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

	if (is_array($arg)) {
		throw new Exception("call plugin [$name:$param] argument is array", print_r($arg, true));
	}

	$alen = strlen($arg);

	if (($pos = mb_strpos($name, $this->rx[2])) > 0) {
		// tok_name_param callback !
		$func = 'tok_'.str_replace($this->rx[2], '_', $name);
	}

	if (($pconf & TokPlugin::REQUIRE_PARAM) && $plen == 0) {
		throw new Exception('missing parameter in plugin '.$this->getPluginTxt("$name:$param"), 
			"plugin=$name pconf=[$pconf]");
	}
	else if (($pconf & TokPlugin::NO_PARAM) && $plen > 0) {
		throw new Exception('unexpected parameter in plugin '.$this->getPluginTxt("$name:$param"), 
			"plugin=$name pconf=[$pconf] param=$param");
	}

	if (($pconf & TokPlugin::REQUIRE_BODY) && $alen == 0) {
		throw new Exception('missing plugin body in '.$this->getPluginTxt("$name:$param"), 
			"plugin=$name pconf=[$pconf] param=[$param] arg=[$arg]\n".$this->dump());
	}
	else if (($pconf & TokPlugin::NO_BODY) && $alen > 0) {
		throw new Exception('unexpected plugin body in '.$this->getPluginTxt("$name:$param"), 
			"plugin=$name pconf=[$pconf] arg=[$arg]");
	}

	if ($pconf & TokPlugin::ONE_PARAM) {
		if ($alen > 0) {
			$param = $arg;
		}
		else if ($plen == 0) {
			throw new Exception('empty plugin parameter and body in '.$this->getPluginTxt("$name:$param"),
				"plugin=$name pconf=[$pconf] param=[$param] arg=[$arg]");
		}
	}

	$src_dir = dirname(__DIR__);

	if (($pconf & TokPlugin::PARAM_LIST) || ($pconf & TokPlugin::PARAM_CSLIST)) {
		require_once $src_dir.'/lib/split_str.php';
		$delim = ($pconf & TokPlugin::PARAM_LIST) ? ':' : ',';
		$param = \rkphplib\lib\split_str($delim, $param);
	}

	if ($pconf & TokPlugin::KV_BODY) {
		require_once $src_dir.'/lib/conf2kv.php';
		$arg = \rkphplib\lib\conf2kv($arg);
	}
	else if ($pconf & TokPlugin::JSON_BODY) {
		require_once $src_dir.'/JSON.class.php';
		$arg = \rkphplib\JSON::decode($arg);
	}
	else if (($pconf & TokPlugin::CSLIST_BODY) || ($pconf & TokPlugin::LIST_BODY)) {
		require_once $src_dir.'/lib/split_str.php';
		$delim = ($pconf & TokPlugin::CSLIST_BODY) ? ',' : HASH_DELIMITER;
		$arg = \rkphplib\lib\split_str($delim, $arg);
	}
	else if ($pconf & TokPlugin::XML_BODY) {
		require_once $src_dir.'/XML.class.php';	
		$arg = \rkphplib\XML::toMap($arg);
	}

	$res = '';

	if ($pconf & TokPlugin::NO_PARAM) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $arg);
	}
	else if ($pconf & TokPlugin::NO_BODY || $pconf & TokPlugin::ONE_PARAM) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $param);
	}
	else {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $param, $arg);
	}

  if ($this->_plugin[$name][1] & TokPlugin::REDO) {
    $old_tok = $this->_tok;
    $old_endpos = $this->_endpos;

    // \rkphplib\lib\log_debug("Tokenizer._call_plugin:916> REDO:\n---\n$res\n---\n");
    $this->setText($res);
    $res = $this->_join_tok(0, count($this->_tok));

    $this->_tok = $old_tok;
    $this->_endpos = $old_endpos;
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
 * Return escaped string. Replace $rx[1..3] with rx[4..6]. If $rx is null (default) use this.$rx.
 * Example: {action:param} = &#123;action&#58;param&#125;
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
 * Return unescaped string. Replace $rx[4..6] with rx[1..3]. If $rx is null (default) use this.$rx.
 * Example: &#123;action&#58;param&#125; = {action:param}
 */
public function unescape($txt, $rx = null) {
	$rx = [ '', $this->rx[4], $this->rx[5], $this->rx[6], $this->rx[1], $this->rx[2], $this->rx[3] ];
	$rx[0] = '/'.preg_quote($this->rx[4]).'([a-zA-Z0-9_]*'.preg_quote($this->rx[5]).'.*?)'.preg_quote($this->rx[6]).'/s';
	return $this->escape($txt, $rx);
}


/**
 * Return $tpl with {:=key} (rx[1].$rx[2].'='.$key.$rx[3]) replaced by replace[key].
 */
public function replaceTags($tpl, $replace, $prefix = '') {
	if (is_string($replace) && strlen(trim($replace)) == 0) {
		throw new Exception('replaceTags hash is string', "replace=[$replace] tpl=[$tpl]");
	}
		
	foreach ($replace as $key => $value) {
		if (!is_array($value)) {
			$tag = $this->rx[1].$this->rx[2].'='.$prefix.$key.$this->rx[3];
			$tpl = str_replace($tag, $value, $tpl);
		}
		else {
			foreach ($value as $akey => $aval) {
				$tag = $this->rx[1].$this->rx[2].'='.$prefix.$key.'.'.$akey.$this->rx[3];
				$tpl = str_replace($tag, $aval, $tpl);
			}
		}
	}

	return $tpl;
}


/**
 * Replace all tags with $replace_with (default = ''). 
 */
public function removeTags($txt, $replace_with = '') {
	$prefix = $this->rx[1].$this->rx[2].'=';
	$suffix = $this->rx[3];

  if (mb_strpos($txt, $prefix) !== false) {
    $txt = preg_replace('/'.preg_quote($prefix).'.+?'.preg_quote($suffix).'/', $replace_with, $txt);
  }

  return $txt;
}


/**
 * Return tag list vector.
 */
public function getTagList($txt, $as_name = false) {
	$prefix = $this->rx[1].$this->rx[2].'=';
	$suffix = $this->rx[3];
  $res = [];

  while (preg_match('/'.preg_quote($prefix).'(.+?)'.preg_quote($suffix).'/', $txt, $match)) {
		$list_value = $as_name ? $match[1] : $prefix.$match[1].$suffix;
    array_push($res, $list_value);
    $txt = preg_replace('/'.preg_quote($prefix).preg_quote($match[1]).preg_quote($suffix).'/', '', $txt);
  }

  return $res;
}


/**
 * Return {:=$name}. Use $name = 'TAG:PREFIX' for "{:=" and $name = 'TAG:SUFFIX' for "}".
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
		'TArray' => [ 'array', 'array:set', 'array:get', 'array:shift', 'array:unshift', 'array:pop', 'array:push', 'array:join', 'array:length', 'array:split' ],
		'TBase' => [ 'row:init', 'row', 'tpl_set', 'tpl', 'tf', 't', 'true', 'f', 'false', 'find', 'filter', 'plugin', 'escape:tok', 'escape', 'unescape', 'encode', 'decode', 'get', 'const', 'include', 'include_if', 'view', 'clear', 'ignore', 'if', 'switch', 'keep', 'load', 'link', 'redo', 'toupper', 'tolower', 'hidden', 'trim', 'join', 'set_default', 'set', 'redirect', 'var', 'esc', 'log', 'shorten', 'strlen', 'json:exit', 'json' ],
		'TConf' => [ 'conf', 'conf:id', 'conf:var', 'conf:get', 'conf:get_path', 'conf:set', 'conf:set_path', 'conf:set_default', 'conf:append' ],
		'TDate' => [ 'date' ],
		'TEval' => [ 'eval:math', 'eval:logic', 'eval' ],
		'TFileSystem' => [ 'directory:copy', 'directory:move', 'directory:create', 'directory:exists', 'directory:entries', 'directory:is', 'directory', 'file:size', 'file:copy', 'file:exists', 'file', 'dirname', 'basename' ],
		'TFormValidator' => [ 'fv', 'fv:init', 'fv:conf', 'fv:get', 'fv:get_conf', 'fv:check', 'fv:in', 'fv:tpl', 'fv:hidden', 'fv:preset', 'fv:error', 'fv:appendjs', 'fv:error_message', 'fv:emsg', 'fv:set_error_message' ],
		'THtml' => [ 'html:tag', 'html:inner', 'html:append', 'html:meta', 'html:meta_og', 'html:tidy', 'html:xml', 'html:uglify', 'html', 'text2html', 'input:checkbox', 'input:radio', 'input', 'user_agent' ],
		'THttp' => [ 'http:get', 'http', 'domain:idn', 'domain:utf8', 'domain' ],
		'TJob' => [ 'job' ],
		'TLanguage' => [ 'language:init', 'language:get', 'language:script', 'language', 'txt:js', 'txt', 't', 'ptxt' ],
		'TLogin' => [ 'login', 'login_account', 'login_check', 'login_auth', 'login_access', 'login_update', 'login_clear' ],
		'TLoop' => [ 'loop:var', 'loop:list', 'loop:hash', 'loop:show', 'loop:join', 'loop:count', 'loop' ],
		'TMailer' => [ 'mail:init', 'mail:html', 'mail:txt', 'mail:send', 'mail:attach', 'mail' ],
		'TMath' => [ 'nf', 'number_format', 'intval', 'floatval', 'rand', 'math', 'md5' ],
		'TMenu' => [ 'menu', 'menu:add', 'menu:conf' ],
		'TOutput' => [ 'output:set', 'output:get', 'output:conf', 'output:init', 'output:loop', 'output:json', 'output:header', 'output:footer', 'output:empty', 'output', 'sort', 'search' ],
		'TPicture' => [ 'picture:init', 'picture:src', 'picture:list', 'picture' ],
		'TSQL' => [ 'sql:query', 'sql:change', 'sql:dsn', 'sql:name', 'sql:qkey', 'sql:json', 'sql:col', 'sql:options', 'sql:getId', 'sql:nextId', 'sql:in', 'sql:hasTable', 'sql:password', 'sql:import', 'sql', 'null' ],
		'TSetup' => [ 'setup:database', 'setup:table', 'setup:install', 'setup' ],
		'TSitemap' => [ 'sitemap' ],
		'TTwig' => [ 'autoescape', 'block', 'do', 'embed', 'extends', 'filter', 'flush', 'for', 'from', 'if', 'import', 'include', 'macro', 'sandbox', 'set', 'spaceless', 'use', 'verbatim', 'v' ],
		'TUpload' => [ 'upload:init', 'upload:conf', 'upload:formData', 'upload:exists', 'upload:scan', 'upload' ]
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