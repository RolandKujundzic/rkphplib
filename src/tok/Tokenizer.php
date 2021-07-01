<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Exception.php';
require_once __DIR__.'/../File.php';
require_once __DIR__.'/../lib/kv2conf.php';

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

// @var Tokenizer $site Tokenizer Object for Website (first tokenizer object created)
public static $site = null;

// @var vector $rx Token expression (regular expression for start+end token, prefix, delimiter, suffix, esc-prefix, esc-delimiter, esc-suffix)
public $rx = [ "/\{([a-zA-Z0-9_\.]*\:.*?)\}/s", '{', ':', '}', '&#123;', '&#58;', '&#125;' ];

// @var string $file Token data from $file - defined if load($file) was used
public $file = '';


// @const TOK_IGNORE remove unkown plugin
const TOK_IGNORE = 2;

// @const TOK_KEEP keep unknown plugin
const TOK_KEEP = 4;

// @const TOK_DEBUG debug unknown plugin
const TOK_DEBUG = 8;

// @const TOK_AUTOLOAD autoload unknown plugin
const TOK_AUTOLOAD = 16;


// @const VAR_MUST_NOT_EXIST throw exception in setVar() if key already exists
const VAR_MUST_NOT_EXIST = 1;

// @const VAR_APPEND append value to vector key in setVar()
const VAR_APPEND = 2;


// @var $last plugin call stack
public $last = [];

// @var Profiler $prof
public $prof = null;

// @var map $vmap plugin variable interchange
private $vmap = [];

// @var map<string:map<object:int>> $_plugin
private $_plugin = [];

// @var vector<string> $_tok
private $_tok = [];

// @var map<int:int> $_endpos
private $_endpos = [];

// @var table<string:any> $callstack
private $_callstack = [];

// @var int $_config constructor config flag
private $_config = 0;

// @var array $_postprocess
private $_postprocess = [];



/**
 * Constructor. Set behavior for unknown plugin (TOK_[IGNORE|KEEP|DEBUG).
 * Default $flag (=16) is to abort if unknown plugin is found. Values are
 * 2^n: Tokenizer::TOK_[IGNORE|KEEP|DEBUG|AUTOLOAD])
 */
public function __construct(int $flag = 16) {
	if (is_null(self::$site)) {
		self::$site =& $this;
	}

	$this->_config = $flag;
}


/**
 * Call before setText to remove var and postprocess settings.
 */
public function reset() {
	$this->last = [];
	$this->vmap = [];
	$this->_tok = [];
	$this->_endpos = [];
	$this->_callstack = [];
	$this->_postprocess = [];
}


/**
 * Load Profiler.
 */
public function useProfiler() {
	require_once dirname(__DIR__).'/Profiler.php';
	$this->prof = new \rkphplib\Profiler();
}


/**
 * Return this.vmap[$name] (any). If $name is "a.b.c" return this.vmap[a][b][c].
 * If variable does not exist return false. If variable ends with ! throw 
 * exception if it does not exist.
 */
public function getVar(string $name) {
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
public static function log($message, string $to) : void {

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
public function setVar(string $name, $value, int $flags = 0) : void {
	if (empty($name)) {
		throw new Exception('empty vmap name');
	}

	$path = explode('.', $name);
	$map =& $this->vmap;

	while (count($path) > 0) {
		$key = array_shift($path);

		if (count($path) == 0) {
			if (isset($map[$key]) && ($flags & self::VAR_MUST_NOT_EXIST)) {
				throw new Exception('setVar('.$name.', ...) ({var:='.$name.'}) already exists', print_r($value, true));
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
public function printCallStack() : array {
	$cs_rownum = count($this->_callstack);
	$res = '';

	for ($i = 0; $i < $cs_rownum; $i++) {
		$cs_row = $this->_callstack[$i];
		$res .= $i.': ';

		for ($j = 0; $j < count($cs_row); $j++) {
			$res .= ($j > 0) ? ', '.$cs_row[$j][0] : $cs_row[$j][0];
	
			if ($cs_row[$j][1] !== null) {
				$res .= ':'.json_encode($cs_row[$j][1], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
			}
		}

		$res .= "\n";
	}

	return trim($res);
}


/**
 * Set value (any) to first found name from end of callstack. 
 */
public function setCallStack(string $name, $value) : void {
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
public function getCallStack(string $name) {
	$cs_rownum = count($this->_callstack);

	for ($i = $cs_rownum - 1; $i >= 0; $i--) {
		$cs_row = $this->_callstack[$i];
		for ($j = count($cs_row) - 1; $j >= 0; $j--) {
			if ($cs_row[$j][0] === $name) {
				return $cs_row[$j][1];
			}
		}
	}

	throw new Exception('plugin missing in callstack', "name=$name stack: ".print_r($this->_callstack));
}


/**
 * Tokenize $file content according to this.$rx.
 */
public function load(string $file) : void {
	$this->setText(File::load($file));
	$this->file = $file;
}


/**
 * Tokenize $text according to this.$rx.
 */
public function setText(string $txt) : void {
	$this->_tok = preg_split($this->rx[0], $txt, -1, PREG_SPLIT_DELIM_CAPTURE);
	$this->_compute_endpos();

	// Check endpos sanity, e.g. {a:}{b:}{:a}{:b} is forbidden
	$max_ep = array(count($this->_endpos) - 2);
	$max = 0;

	for ($i = 1; $i < count($this->_endpos) - 1; $i = $i + 2) {
		$max = end($max_ep);

		if ($max < $i) {
			array_pop($max_ep);
			$max = end($max_ep);
		}

		if ($this->_endpos[$i] > 0) {
			if ($this->_endpos[$i] > $max) {
				throw new Exception('invalid plugin', "Plugin [".$this->_tok[$i]."] must end before [".
					$this->_tok[$max]." i=[$i] ep=[".$this->_endpos[$i]."] max=[$max]");
			}
			else {
				array_push($max_ep, $this->_endpos[$i]);
			}
		}
	}
}


/**
 * Return true if all $tags (e.g. {:=TAG}) exist in $txt.
 */
public function hasReplaceTags(string $txt, array $tags) : bool {

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
public function hasTag(string $name) : bool {

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
public function register(TokPlugin $handler) : void {

	$plugins = $handler->getPlugins($this);

	foreach ($plugins as $name => $opt) {
		$this->_plugin[$name] = [ $handler, $opt ];
	}
}


/**
 * Old style plugin registration. These plugins use tokCall() callback.
 */
public function setPlugin(string $name, TokPlugin $handler) : void {
	$this->_plugin[$name] = [ $handler, TokPlugin::TOKCALL ];
}


/**
 * Apply Tokenizer.
 */
public function toString() : string {
	if (!is_null($this->prof)) {
		$this->prof->log('enter Tokenizer::toString');
	}

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

	if (!is_null($this->prof)) {
		$this->prof->log('exit Tokenizer::toString');
		$out = str_replace('</body>', $this->prof->log2json('profiler')."\n</body>", $out);
	}

	return $out;
}


/**
 * Return tokenizer status. Flag (default=3): 
 *   2^0 = 1: count info
 *   2^1 = 2: _plugin keys
 */
public function getStatus($flag = 3) : string {
	$plugin_list = join(', ', array_keys($this->_plugin));
	$res = '';

	$count = [ 'last:'.count($this->last),
		'vmap:'.count($this->vmap),
		'_plugin:'.count($this->_plugin),
		'_tok:'.count($this->_tok),
		'_endpos:'.count($this->_endpos),
		'_callstack:'.count($this->_callstack),
		'_config:'.$this->_config,
		'_postprocess:'.count($this->_postprocess) 
	];

	if ($flag & 1) {
		$res .= 'Tokenizer.count: '.join(', ', $count)."\n";
	}

	if ($flag & 2) {
		$res .= "Tokenizer._plugins: $plugin_list\n";
	}

	return $res;
}


/**
 * Recursive $_tok parser.
 */
private function _join_tok(int $start, int $end) : string {
	array_push($this->_callstack, []);

	if (count($this->_tok) < 1 || count($this->_endpos) != count($this->_tok)) {
		throw new Exception('invalid status - call setText() first');
	}

	// \rkphplib\lib\log_debug([ "Tokenizer._join_tok:526> start=$start end=$end\ntok: <1>\nendpos: <2>", $this->_tok, $this->_endpos ]);
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

	// \rkphplib\lib\log_debug("Tokenizer._join_tok:561> i=[$i] return: [$res]");
	return $res;
}


/**
 * Return current plugin name. Call only once before executing callPlugin().
 * If flag == 1 return name:param.
 */
public function getCurrentPlugin(int $flag = 0) : string {
	if (count($this->last) < 1) {
		throw new Exception('last is not set');
	}

	list ($name, $param) = array_pop($this->last);
	$res = $name;

	if ($flag == 1) {
		$res = $name.$this->rx[2].$param;
	}

	return $res;
}


/**
 * Return plugin features (e.g. TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY). 
 * If plugin is build return null. If plugin is not loaded try autoload.
 * If plugin is not found throw exception.
 */
public function getPluginFeatures(string $name) : ?int {
	if (in_array($name, [ 'ignore', 'keep', 'debug' ])) {
		return null;
	}

	if (!isset($this->_plugin[$name])) {
		$this->tryPluginMap($name);

		if (!isset($this->_plugin[$name])) {
			throw new Exception('no such plugin '.$name, join('|', array_keys($this->_plugin)));
		}
	}

	return $this->_plugin[$name][1];
}


/**
 * Compute plugin output. Loop position $i will change.
 */
private function _join_tok_plugin(int &$i) : ?string {
	$tok = $this->_tok[$i];

	// \rkphplib\lib\log_debug([ "Tokenizer._join_tok_plugin:614> tok.$i=<1>", $tok ]);
	$d  = $this->rx[2];
	$dl = mb_strlen($d);
	$pos = mb_strpos($tok, $d);
	$name = trim(mb_substr($tok, 0, $pos));
	$param = trim(mb_substr($tok, $pos + $dl));
	$buildin = '';
	$tp = 0;

	array_push($this->last, [ $name, $param ]);

	if (isset($this->_plugin['catchall'])) {
		// if [catchall] was registered as plugin run everything through this handler ...
		$name = 'catchall';
	}

	if (!isset($this->_plugin[$name]) && ($this->_config & self::TOK_AUTOLOAD)) {
		$this->tryPluginMap($name);
	}

	if (!isset($this->_plugin[$name])) {
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
	else {
		$tp = $this->_plugin[$name][1];
		$tmp = explode($d, $param);
		$sub = $name.$d.$tmp[0];

		if ($tp & TokPlugin::TOKCALL) {
			if (!method_exists($this->_plugin[$name][0], 'tokCall')) {
				throw new Exception('invalid plugin', "$name has no tokCall() method");
			}
		}
		else if (isset($this->_plugin[$sub]) && method_exists($this->_plugin[$sub][0], 'tok_'.$name.'_'.$tmp[0])) {
			// allow name:param -> tok_name_param()
			$tp = $this->_plugin[$sub][1];
			$name = $sub;
			array_shift($tmp);
			$param = join($d, $tmp);
		}
		else if (!method_exists($this->_plugin[$name][0], 'tok_'.$name)) {
			throw new Exception('invalid plugin '.$this->getPluginTxt("$name:$param"), "no tok_$name() callback method");
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
			// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:689> no arg: name=$name param=[$param] i=$i ep=$ep");
			$out = $this->_call_plugin($name, $param);
			// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:691> out: [$out]");
		}
		else if ($ep > $i) {
			if ($tp & TokPlugin::TEXT) {
				// do not parse argument ...
				$arg = $this->_merge_txt($i + 1, $ep - 1);
			}
			else if ($tp & TokPlugin::ASK) {
				$arg = $this->_call_plugin($name, '?') ? $this->_join_tok($i + 1, $ep) : '';
			}
			else {
				// parse argument with recursive _join_tok call ...
				// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:703> compute arg of $name with recursion: start=$i+1 end=$ep\n");
				$arg = $this->_join_tok($i + 1, $ep);
			}
 
			// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:707> arg: name=$name param=[$param] arg=[$arg] i=$i ep=$ep");
			$out = $this->_call_plugin($name, $param, $arg);
 			// \rkphplib\lib\log_debug("Tokenizer._join_tok_plugin:709> set i=$ep - out: [$out]");

			$i = $ep; // modify loop position
		}
		else {
			throw new Exception('invalid endpos', "i=$i ep=$ep");
		}
	}

/*
	if ($tp & TokPlugin::REDO) {
		$out = $this->redo($out);
	}
*/

	return $out;
}


/**
 * Return tokenizer dump (tok, endpos). Flag: 1 = 2^0 = _tok, 2 = 2^1 = _endpos
 */
public function dump(int $flag = 3) : string {
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
public function getPluginTxt($tok, ?string $arg = null) : string {
	$name = ''; 
	$param = '';

	if (is_array($tok)) {
		list ($name, $param) = (count($tok) >= 2) ? $tok : explode(':', $tok[0]);
		$tok = $name.$this->rx[2].$param;
	}
	else if (mb_strpos($tok, $this->rx[2]) > 0) {
		list ($name, $param) = mb_split($this->rx[2], $tok, 2);
	}
	else if (mb_strpos($tok, ':') > 0) {
		list ($name, $param) = mb_split(':', $tok, 2);
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
private function _merge_txt(int $n, int $m) : string {
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
private function _buildin(string $action, string $name, string $param, ?string $arg = null) : string {
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
public function hasPlugin(string $name) : bool {
	return !empty($this->_plugin[$name]);
}


/**
 * Return result (any) of plugin $name function $func. Parameter $args (default = []) is mixed:
 *
 * - vector (max length 3) use call_user_func(PLUGIN, $func[, args[0], args[1], args[2]]).
 * - hash use call_user_func(PLUGIN, $func, args).
 * - string assume $func=$param and use this._call_plugin($name, $func, $args).
 * 
 * @example …
 * callPlugin('login', 'id?')
 * callPlugin('login', 'tok_login', [ 'name' ]) = callPlugin('login', 'name')
 * callPlugin('row', 'init', 'mode=material');
 * callPlugin('row', '2,3', 'a|#|b');
 * @EOF
 */
public function callPlugin(string $name, string $func, $args = []) {
	if (!isset($this->_plugin[$name])) {
		$this->tryPluginMap($name);

		if (!isset($this->_plugin[$name])) {
			throw new Exception('no such plugin '.$name, join('|', array_keys($this->_plugin)));
		}
	}

	if (strpos($func, 'tok_') !== 0 && !method_exists($this->_plugin[$name][0], $func)) {
		if (is_null($args) || (is_array($args) && count($args) == 0)) {
			$args = '';
		}

		if (isset($this->_plugin[$name.':'.$func])) {
			$name = $name.':'.$func;
			$func = '';
		}

		$flag = is_array($args) ? 3 : 0;
		// \rkphplib\lib\log_debug("Tokenizer.callPlugin:873> return this._call_plugin($name, $func, $args, $flag)");
		return $this->_call_plugin($name, $func, $args, $flag);
	}

	if (!method_exists($this->_plugin[$name][0], $func)) {
		throw new Exception("no such plugin method $name.".$func);
	}

	// \rkphplib\lib\log_debug([ "Tokenizer.callPlugin:881> name=$name, func=$func, args: [<1>]", $args ]);
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
 * 
 * @param null|string|array $arg
 * @param int $flag (1 = no postprocess, 2 = keep arg
 */
private function _call_plugin(string $name, string $param, $arg = null, int $flag = 0) : ?string {

	$csl = count($this->_callstack);
	if ($csl > 0) {
		array_push($this->_callstack[$csl - 1], [ $name, null ]);
	}

	if ($this->_plugin[$name][1] & TokPlugin::TOKCALL) {
		return call_user_func(array($this->_plugin[$name][0], 'tokCall'), $name, $param, $arg);
	}

	if ($this->_plugin[$name][1] & TokPlugin::POSTPROCESS) {
		if ($flag & 1) {
			throw new Exception('no postprocess plugins allowed in '.$name);
		}

		array_push($this->_postprocess, [ array($this->_plugin[$name][0], 'tok_'.str_replace(':', '_', $name)), 
			$param, $arg, $this->_plugin[$name][1] ]);
		return '';
	}

	if (is_array($arg)) {
		if (($flag & 2) != 2) {
			throw new Exception("call plugin [$name:$param] argument is array", print_r($arg, true));
		}
	}
	else {
		$this->_prepare_call($name, $param, $arg);
	}

	$func = 'tok_'.$name;
	if (($pos = mb_strpos($name, $this->rx[2])) > 0) {
		// tok_name_param callback !
		$func = 'tok_'.str_replace($this->rx[2], '_', $name);
	}

	$pconf = $this->_plugin[$name][1];
	$res = '';

	if ($pconf & TokPlugin::NO_PARAM) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $arg);
	}
	else if (($pconf & TokPlugin::NO_BODY) || ($pconf & TokPlugin::ONE_PARAM)) {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $param);
	}
	else {
		$res = call_user_func(array($this->_plugin[$name][0], $func), $param, $arg);
	}

	if ($this->_plugin[$name][1] & TokPlugin::REDO) {
		$res = $this->redo($res);
	}

	return $res;
}


/**
 *
 */
public function redo(string $txt) : string {
	// \rkphplib\Log::debug("Tokenizer.redo> (<1>)", $txt);
	if (strpos($txt, $this->rx[1]) === false) {
		return $res;
	}

	$old_endpos = $this->_endpos;
	$old_tok = $this->_tok;

	$this->setText($txt);
	$res = $this->_join_tok(0, count($this->_tok));

	$this->_endpos = $old_endpos;
	$this->_tok = $old_tok;
	return $res;
}


/**
 *
 */
private function _prepare_call(string $name, string &$param, ?string &$arg) : void {
	$pconf = $this->_plugin[$name][1];
	$plen = strlen($param);
	$alen = strlen($arg);

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
		require_once $src_dir.'/JSON.php';
		$arg = \rkphplib\JSON::decode($arg);
	}
	else if (($pconf & TokPlugin::CSLIST_BODY) || ($pconf & TokPlugin::LIST_BODY)) {
		require_once $src_dir.'/lib/split_str.php';
		$delim = ($pconf & TokPlugin::CSLIST_BODY) ? ',' : HASH_DELIMITER;
		$arg = \rkphplib\lib\split_str($delim, $arg);
	}
	else if ($pconf & TokPlugin::XML_BODY) {
		require_once $src_dir.'/XML.php';	
		$arg = \rkphplib\XML::toMap($arg);
	}
}


/**
 * Return endpos list for $tok. Values of _endpos[n]:
 * Allow name.sub:param instead of name:sub:param.
 * 
 *   0: unknown
 * > 0: position of plugin end 
 *  -1: param only plugin {xxx:yyyy}
 *  -2: ignore
 *  -3: plugin end ({:xxxx})
 */
private function _compute_endpos() : void {
	$d = $this->rx[2];
	$dl = mb_strlen($d);
	$ep = array();
  
	for ($i = 0; $i < count($this->_tok); $i++) {
		$ep[$i] = 0;
	}

	for ($i = 1; $i < count($this->_tok); $i = $i + 2) {
		$plugin = $this->_tok[$i];
		$start = '';

		if (mb_substr($plugin, 0, $dl) == $d) {
			// ignore plugin ... unless start is found ...
			$ep[$i] = -2;
			$end = mb_substr($plugin, $dl);

			// {=:}x{:=} is forbidden ...
			if (mb_substr($end, 0, 1) != '=') {
				$start = empty($end) ? $d : $end.$d;
			}
		}
		else if (($dot = strpos($plugin, '.')) > 0 && strpos($plugin, $d) > $dot + 1) {
			// allow name.sub:param instead of name:sub:param
			$plugin = substr($plugin, 0, $dot).$d.substr($plugin, $dot + 1);
			// \rkphplib\lib\log_debug("Tokenizer._compute_endpos:1090> change {$this->_tok[$i]} into $plugin");
			$this->_tok[$i] = $plugin;
		}

		if ($start) {
			// find plugin start ...
			$found = false;

			for ($j = $i - 2; !$found && $j > 0; $j = $j - 2) {
				$prev_plugin = $this->_tok[$j];

				if ($ep[$j] == -1 && ($xpos = mb_strpos($prev_plugin, $start)) !== false && ($start == $d || $xpos == 0)) {
					$found = true;
					$ep[$j] = $i;
					$ep[$i] = -3;
				}
			}
		}
		else if ($ep[$i] == 0) {
			// parameter only plugin ...
			$ep[$i] = -1;
		}
	}

	$this->_endpos = $ep;
}


/**
 * Return escaped string. Replace $rx[1..3] with rx[4..6]. If $rx is null (default) use this.$rx.
 * Example: {action:param} = &#123;action&#58;param&#125;
 */
public function escape(string $txt, ?array $rx = null) : string {

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
public function unescape(string $txt, ?string $rx = null) : string {
	$rx = [ '', $this->rx[4], $this->rx[5], $this->rx[6], $this->rx[1], $this->rx[2], $this->rx[3] ];
	$rx[0] = '/'.preg_quote($this->rx[4]).'([a-zA-Z0-9_]*'.preg_quote($this->rx[5]).'.*?)'.preg_quote($this->rx[6]).'/s';
	return $this->escape($txt, $rx);
}


/**
 * Return $tpl with {:=key} (rx[1].$rx[2].'='.$key.$rx[3]) replaced by replace[key].
 * If $tpl has {:=_hash} replace with $replace string hash.
 */
public function replaceTags(string $tpl, array $replace, string $prefix = '') : string {
	if (is_string($replace) && strlen(trim($replace)) == 0) {
		throw new Exception('replaceTags hash is string', "replace=[$replace] tpl=[$tpl]");
	}

	$hash_tag = $this->rx[1].$this->rx[2].'=_hash'.$this->rx[3];
	if (false !== mb_strpos($tpl, $hash_tag)) {
		$tpl = str_replace($hash_tag, \rkphplib\lib\kv2conf($replace), $tpl);
		if (false === mb_strpos($tpl, $this->rx[1].$this->rx[2].'=')) {
			return $tpl;
		}
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
public function removeTags(string $txt, string $replace_with = '') : string {
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
public function getTagList(string $txt, bool $as_name = false) : array {
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
public function getTag(string $name) : string {
	$res = $this->rx[1].$this->rx[2].'='.$name.$this->rx[3];

	if ($name == 'TAG:PREFIX') {
		$res = $this->rx[1].$this->rx[2].'=';
	}
	else if ($name == 'TAG:SUFFIX') {
		$res = $this->rx[3];
	}

	return $res;
}


// AUTO CREATED
private function tryPluginMap(string $name) : void {
	static $map = [
		'Conf' => [ 'conf', 'conf:append', 'conf:get', 'conf:get_path', 'conf:id', 'conf:load', 'conf:save', 'conf:set', 'conf:set_path', 'conf:set_default', 'conf:var' ],
		'FileSystem' => [ 'directory', 'directory:copy', 'directory:move', 'directory:create', 'directory:exists', 'directory:entries', 'directory:is', 'file', 'file:info', 'file:size', 'file:copy', 'file:download', 'file:exists', 'csv_file', 'csv_file:conf', 'csv_file:append', 'csv_file:open', 'csv_file:close', 'dirname', 'basename' ],
		'Html' => [ 'html:tag', 'html:inner', 'html:append', 'html:meta', 'html:meta_og', 'html:tidy', 'html:xml', 'html:uglify', 'html:nobr', 'html', 'google', 'text2html', 'input:checkbox', 'input:radio', 'input:select', 'input:xcrypt', 'input', 'user_agent' ],
		'Job' => [ 'job' ],
		'Menu' => [ 'menu', 'menu:add', 'menu:conf' ],
		'TArray' => [ 'array', 'array:set', 'array:get', 'array:shift', 'array:unshift', 'array:pop', 'array:push', 'array:join', 'array:length', 'array:split' ],
		'TBase' => [ 'clear', 'const', 'decode', 'encode', 'esc', 'escape', 'escape:tok', 'f', 'false', 'filter', 'find', 'get', 'hidden', 'if', 'if:get', 'ignore', 'include', 'inc_html', 'include_if', 'join', 'json', 'json:exit', 'keep', 'li', 'link', 'load', 'loadJSON', 'log', 'log_debug', 'plugin', 'redirect', 'redo', 'row', 'row:init', 'set', 'set_default', 'shorten', 'skin', 'strlen', 'switch', 't', 'tf', 'tolower', 'toupper', 'tpl', 'tpl_set', 'trim', 'true', 'unescape', 'var', 'view' ],
		'TDate' => [ 'date' ],
		'TEval' => [ 'eval:math', 'eval:logic', 'eval:call', 'eval' ],
		'TFormValidator' => [ 'fv', 'fv:appendjs', 'fv:check', 'fv:conf', 'fv:emsg', 'fv:error', 'fv:error_message', 'fv:get', 'fv:get_conf', 'fv:hidden', 'fv:in', 'fv:init', 'fv:preset', 'fv:set_error_message', 'fv:tpl' ],
		'TGDLib' => [ 'gdlib:print', 'gdlib:font', 'gdlib:init', 'gdlib:load', 'gdlib:new', 'gdlib' ],
		'THighlight' => [ 'source:php', 'source:html' ],
		'THttp' => [ 'http:get', 'url', 'http', 'domain:idn', 'domain:utf8', 'cookie', 'domain' ],
		'TLanguage' => [ 'language:init', 'language:get', 'language:script', 'language', 'txt:js', 'txt', 't', 'ptxt' ],
		'TLogin' => [ 'login', 'login_account', 'login_check', 'login_auth', 'login_auth:basic', 'login_auth:digest', 'login_access', 'login_update', 'login_clear' ],
		'TLoop' => [ 'loop:var', 'loop:list', 'loop:json', 'loop:hash', 'loop:show', 'loop:join', 'loop:count', 'loop' ],
		'TMailer' => [ 'mail:init', 'mail:html', 'mail:txt', 'mail:send', 'mail:attach', 'mail' ],
		'TMath' => [ 'nf', 'number_format', 'intval', 'floatval', 'rand', 'math', 'md5' ],
		'TMisc' => [ 'sleep' ],
		'TOutput' => [ 'output:set', 'output:get', 'output:conf', 'output:init', 'output:loop', 'output:json', 'output:header', 'output:footer', 'output:empty', 'output', 'sort', 'search' ],
		'TPicture' => [ 'picture:init', 'picture:src', 'picture:list', 'picture:tpl', 'picture:tbn', 'picture' ],
		'TSQL' => [ 'sql', 'sql:col', 'sql:change', 'sql:dsn', 'sql:getId', 'sql:hasTable', 'sql:import', 'sql:in', 'sql:json', 'sql:loop', 'sql:name', 'sql:nextId', 'sql:options', 'sql:password', 'sql:qkey', 'sql:query', 'null' ],
		'TSetup' => [ 'setup:database', 'setup:table', 'setup:install', 'setup' ],
		'TSitemap' => [ 'sitemap' ],
		'TString' => [ 'string2url' ],
		'TTwig' => [ 'autoescape', 'block', 'do', 'embed', 'extends', 'filter', 'flush', 'for', 'from', 'if', 'import', 'include', 'macro', 'sandbox', 'set', 'spaceless', 'use', 'verbatim', 'v' ],
		'TUpload' => [ 'upload:save', 'upload:init', 'upload:conf', 'upload:formData', 'upload:exists', 'upload:scan', 'upload' ]
	];

	foreach ($map as $cname => $list) {
		if (in_array($name, $list)) {
			require_once __DIR__."/$cname.php";
			$cname = '\\rkphplib\\tok\\'.$cname;
			$obj = new $cname();
			$this->register($obj);
			return;
		}
	}
}

}
