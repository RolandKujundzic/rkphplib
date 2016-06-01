<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/lib/config.php');
require_once(__DIR__.'/File.class.php');

use rkphplib\Exception;


/**
 * String Tokenizer.
 *
 * Token Structure: [prefix][name][delimiter][parameter][suffix][body][prefix][delimiter][name][suffix].
 * Default prefix = "{", delimiter = ":", suffix = "}". Parameter and body are optional. Parsing is bottom-up
 * (but can be changed by plugin). Tokens can be nested. Tokens are replaced with result of associated plugin.
 *
 * Tag {action:param}body{:action} will be replaced with result of Plugin->tok_action(param, body).
 * Tag parameter and body are optional, e.g. {action:} or {action:param}. If body is empty close tag {:action} is 
 * not required.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Tokenizer {

/** @var vector $rx Token expression (regular expression for start+end token, prefix, delimiter, suffix) */
public $rx = array("/\{([a-zA-Z0-9_]*\:.*?)\}/s", '{', ':', '}');

/** @var string $file Token data from $file - defined if load($file) was used */
public $file = '';

/** @var map $vmap plugin variable interchange */
public $vmap = array();

/** @const PARSE tokenize plugin body */
const PARSE = 0;

/** @const TEXT don't tokenize plugin body */
const TEXT = 2;

/** @const REDO re-parse plugin result */ 
const REDO = 4;

/** @const TOKCALL use plugin callback tokCall(name, param, body) instead of tok_name(param, body) */
const TOKCALL = 8;

/** @const REQUIRE_PARAM plugin parameter is required */
const REQUIRE_PARAM = 16;

/** @const NO_PARAM no plugin parameter */
const NO_PARAM = 32;

/** @const REQUIRE_BODY plugin body is required */
const REQUIRE_BODY = 64;

/** @const NO_BODY no plugin body */
const NO_BODY = 128;

/** @const KV_BODY parse body with conf2kv */
const KV_BODY = 256;

/** @const JSON_BODY body is json */
const JSON_BODY = 512;

/** @const PARAM_LIST example {action:p1:p2:...} escape : with \: */
const PARAM_LIST = 1024;

/** @const PARAM_CSLIST example {action:p1,p2,...} escape , with \, */
const PARAM_CSLIST = 2048;

/** @const CSLIST_BODY example {action:}p1, p2, ... {:action} escape , with \, */ 
const CSLIST_BODY = 4096;

/** @const LIST_BODY example {action:}p1|#|p2|#| ... {:action} escape |#| with \|#| */
const LIST_BODY = 8192;

/** @const XML_BODY body is xml */
const XML_BODY = 16384;


/** @const TOK_IGNORE remove unkown plugin */
const TOK_IGNORE = 2;

/** @const TOK_KEEP keep unknown plugin */
const TOK_KEEP = 4;

/** @const TOK_DEBUG debug unknown plugin */
const TOK_DEBUG = 8;


private $_plugin = array();
private $_endpos = array();
private $_tok = array();
private $_redo = array();
private $_config = 0;
private $_level = array(); // [ [ tok_startpos, tok_endpos, level, redo_count ], ... ]
private $_lc = array();



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
	$this->_compute_level();
}


/**
 * Register plugins.
 * 
 * Plugin provider object must have property tokPlugin (callback:mode map), e.g. handler.tokPlugin = { a: 2, b: 0 }.
 * Callback is handler.tok_a(param, body). Parse modes are:
 *
 *   0 = tokenized body (self::PARSE)
 *   2 = untokenized body (self::TEXT)
 *   4 = re-parse result (self::REDO)
 *   8 = use tokCall(name, param, body) instead of tok_name(param, body) (self::TOKCALL)
 *  16 = parameter is required (self::REQUIRE_PARAM)
 *  32 = no parameter (self::NO_PARAM)
 *  64 = body is required (self::REQUIRE_BODY)
 * 128 = no body (self::NO_BODY)
 * 256 = map string body (key=value|#|...) @see lib\kv2conf()
 * 512 = JSON body
 * 1024 = parameter is colon separated list e.g. {action:p1:p2:...} escape : with \:
 * 2048 = parameter is comma separated list e.g. {action:p1,p2,...} escape , with \,
 * 4096 = body is comma sparated list
 * 8192 = body is array string e.g. {action:}p1|#|p2|#| ... {:action} escape |#| with \|#|
 * 16384 = body is xml
 *
 * 6 = untokenized body + re-parse result (self::TEXT | self::REDO)
 *
 * If plugin parameter or argument needs parsing use handler.tokPlugin = { "name": { "parse": 2, "param": required }}.
 * 
 * Add this (Tokenizer) as handler->tokPlugin[_]. If tokPlugin[_] exists unset it.
 *
 * @param object $handler
 */
public function register(&$handler) {

	if (isset($handler->tokPlugin['_'])) {
		unset($handler->tokPlugin['_']);
	}

	foreach (array_keys($handler->tokPlugin) as $name) {
  	$this->_plugin[$name] =& $handler;
	}

	$handler->tokPlugin['_'] =& $this;
}


/**
 * Old style plugin registration. These plugins use tokCall() callback.
 * 
 * @param string $name
 * @param object $handler
 */
public function setPlugin($name, &$obj) {
	$this->_plugin[$name] =& $handler;
}


/**
 * Apply Tokenizer.
 *
 * @return string
 */
public function toString() {
	$this->_redo = array();
	$out = $this->_join_tok(0, count($this->_tok));

	while (count($this->_redo) == 2) {	
		$this->_tok = preg_split($this->rx[0], $this->_redo[1].$this->_merge_txt($this->_redo[0] + 1, count($this->_tok) - 1), -1, PREG_SPLIT_DELIM_CAPTURE);
		$this->_endpos = $this->_compute_endpos($this->_tok);
		$this->_update_level();
		$this->_redo = array();
		$out .= $this->_join_tok(0, count($this->_tok));
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

	$d  = $this->rx[2];
	$dl = mb_strlen($d);
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
			// call plugin if registered ...
			$pos = mb_strpos($tok, $d);
			$name = trim(mb_substr($tok, 0, $pos));
			$param = trim(mb_substr($tok, $pos + $dl));
			$buildin = '';
			$tp = null;

			if (isset($this->_plugin[$name])) {
				if (property_exists($this->_plugin[$name], 'tokPlugin')) {
					$tp = $this->_plugin[$name]->tokPlugin[$name];
				}

				if (isset($this->_plugin['any'])) {
					$param = $tok;
					$name = 'any';
				}
				else if (is_null($tp) || $tp & self::TOKCALL) {
					if (!method_exists($this->_plugin[$name], 'tokCall')) {
						throw new Exception('invalid plugin', "$name has no callback");
					}
				}
				else if (!method_exists($this->_plugin[$name], 'tok_'.$name)) {
					throw new Exception('invalid plugin', "$name missing or invalid");
				}
			}
			else {
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
					throw new Exception('invalid plugin', "$name:$param is undefined");
				}
			}

			if ($buildin) {
				if ($ep == -1) {
					$out = $this->_buildin($buildin, $name, $param);
				}
				else if ($ep > 0) {
					$out = $this->_buildin($buildin, $name, $param, $this->_merge_txt($i+1, $ep-1));
					$i = $ep;
				}
			}
			else {
				$this->_set_level($i, $ep);

				if ($ep == -1) {
					$out = $this->_call_plugin($name, $param);
				}
				else if ($ep > 0) {

					if ($tp & self::TEXT) {
						$out = $this->_call_plugin($name, $param, $this->_merge_txt($i+1, $ep-1));
					}
					else { 
						$out = $this->_call_plugin($name, $param, $this->_join_tok($i + 1, $ep));
					}

					$i = $ep;
				}

				if ($tp & self::REDO) {
					$this->_redo = array($i, $out);
					return join('', $tok_out);
				}
			}
		}

		array_push($tok_out, $out);
	}

	return join('', $tok_out);
}


/**
 * Return plugin level.
 *
 * @return int
 */
public function getLevel() {

	if (count($this->_lc) !== 4) {
		throw new Exception('tokenizer error', '_lc is not set');
	}

	return $this->_lc[2];
}


/**
 * Set level. Copy _level[i] to _lc where _level[i][0] = start and _level[i][1] = end.
 *
 * @param int $start
 * @param int $end
 */
public function _set_level($start, $end) {
	$this->_lc = array();

	for ($i = count($this->_level) - 1; count($this->_lc) === 0 && $i > -1; $i--) {
		if ($this->_level[$i][0] === $start && $this->_level[$i][1] === $end) {
			$this->_lc = $this->_level[$i];
		}
	}
}


/**
 * Return {PLUGIN:PARAM}$arg{:PLUGIN}
 * @param string $tok (PLUGIN:PARAM)
 *
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
 * Return result of buildin action.
 * If action is ignore return empty. 
 *
 * @param string $action (ignore, keep, buildin)
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
 * If callback mode is not Tokenizer::TOKCALL preprocess param and arg. Example:
 *
 * Convert param into vector and arg into map if plugin $name is configured with
 * Tokenizer::KV_BODY | Tokenizer::PARAM_CSLIST | Tokenizer::REQUIRE_BODY | Tokenizer::REQUIRE_PARAM 
 *
 * @throws rkphplib\Exception 'plugin parameter missing', 'invalid plugin parameter', 'plugin body missing', 'invalid plugin body'
 * @param string $name
 * @param string $param
 * @param string $arg (default = null = no argument)
 * @return string
 */
private function _call_plugin($name, $param, $arg = null) {	

	if (!property_exists($this->_plugin[$name], 'tokPlugin') || $this->_plugin[$name]->tokPlugin[$name] & self::TOKCALL) {
		return call_user_func(array(&$this->_plugin[$name], 'tokCall'), $name, $param, $arg);
	}

	$pconf = $this->_plugin[$name]->tokPlugin[$name];
	$plen = strlen($param);
	$alen = strlen($arg);

	if (($pconf & self::REQUIRE_PARAM) && $plen == 0) {
		throw new Exception('plugin parameter missing', "plugin=$name");
	}
	else if (($pconf & self::NO_PARAM) && $plen > 0) {
		throw new Exception('invalid plugin parameter', "plugin=$name param=$param");
	}

	if (($pconf & self::REQUIRE_BODY) && $alen == 0) {
		throw new Exception('plugin body missing', "plugin=$name");
	}
	else if (($pconf & self::NO_BODY) && $alen > 0) {
		throw new Exception('invalid plugin body', "plugin=$name arg=$arg");
	}

	if (($pconf & self::PARAM_LIST) || ($pconf & self::PARAM_CSLIST)) {
		require_once(__DIR__.'/lib/split_str.php');
		$delim = ($pconf & self::PARAM_LIST) ? ':' : ',';
		$param = lib\split_str($delim, $param);
	}

	if ($pconf & self::KV_BODY) {
		require_once(__DIR__.'/lib/kv2conf.php');
		$arg = lib\kv2conf($arg);
	}
	else if ($pconf & self::JSON_BODY) {
		require_once(__DIR__.'/JSON.class.php');
		$arg = JSON::decode($arg);
	}
	else if (($pconf & self::CSLIST_BODY) || ($pconf & self::LIST_BODY)) {
		require_once(__DIR__.'/lib/split_str.php');
		$delim = ($pconf & self::CSLIST_BODY) ? ',' : '|#|';
		$arg = lib\split_str($delim, $arg);
	}
	else if ($pconf & self::XML_BODY) {
		require_once(__DIR__.'/XML.class.php');	
		$arg = XML::toJSON($arg);
	}

	$res = '';

	if ($pconf & self::NO_PARAM) {
		$res = call_user_func(array(&$this->_plugin[$name], 'tok_'.$name), $arg);
	}
	else if ($pconf & self::NO_BODY) {
		$res = call_user_func(array(&$this->_plugin[$name], 'tok_'.$name), $param);
	}
	else {
		$res = call_user_func(array(&$this->_plugin[$name], 'tok_'.$name), $param, $arg);
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
 * Compute level. 
 */
private function _compute_level() {

	$this->_level = array();
	$level = 1;
	$e = array();	

	for ($i = 0; $i < count($this->_endpos); $i++) {
		if (count($e) > 0 && $i === end($e)) {
			array_pop($e);
			$level--;
		}

		$ep = $this->_endpos[$i];

		if ($ep == -1) {
			array_push($this->_level, array($i, $i, $level, 0));
		}
		else if ($ep > 0) {
			array_push($this->_level, array($i, $ep, $level, 0));
			array_push($e, $ep);
			$level++;
		}
	}
}


/**
 * Update tok-start/end and redo counter in _level.
 */
private function _update_level() {
	$r = $this->_level[count($this->_level) - 1][3];

	for ($i = 0, $found = false; !$found && $i < count($this->_level); $i++) {
		if ($this->_redo[0] === $this->_level[$i][1] && $r === $this->_level[$i][3]) {
			$found = $i;
		}
	}

	$k = $found + 1;

	for ($i = 0; $i < count($this->_endpos); $i++) {
		$ep = $this->_endpos[$i];

		if ($ep == -1 || $ep > 0) {
			$this->_level[$k][0] = $i;
			$this->_level[$k][1] = ($ep == -1) ? $i : $ep;  
			$this->_level[$k][3] = $r + 1;
      $k++;
    }
	}
}


}

