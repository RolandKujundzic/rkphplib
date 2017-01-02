<?php

namespace rkphplib;

require_once(__DIR__.'/TokPlugin.iface.php');
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
 * not required. Escaped Tag is: &#123;action&#58;param&#125; (html-utf8-escape).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Tokenizer {

/** @var vector $rx Token expression (regular expression for start+end token, prefix, delimiter, suffix, esc-prefix, esc-delimiter, esc-suffix) */
public $rx = array("/\{([a-zA-Z0-9_]*\:.*?)\}/s", '{', ':', '}', '&#123;', '&#58;', '&#125;');

/** @var string $file Token data from $file - defined if load($file) was used */
public $file = '';

/** @var map $vmap plugin variable interchange */
public $vmap = array();


/** @const TOK_IGNORE remove unkown plugin */
const TOK_IGNORE = 2;

/** @const TOK_KEEP keep unknown plugin */
const TOK_KEEP = 4;

/** @const TOK_DEBUG debug unknown plugin */
const TOK_DEBUG = 8;


/** @var map<string:map<object:int>> */
private $_plugin = array();

private $_endpos = array();

private $_tok = array();

private $_redo = array();

private $_config = 0;

private $_level = array(); // [ [ tok_startpos, tok_endpos, level, redo_count ], ... ]

private $_last_level_pos = -1;



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
	$this->_compute_level(false);
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
	$this->_redo = array();
	$out = $this->_join_tok(0, count($this->_tok));

	while (count($this->_redo) == 2) {
		// parse redo_text + rest_text	
		$this->_tok = preg_split($this->rx[0], $this->_redo[1].$this->_merge_txt($this->_redo[0] + 1, count($this->_tok) - 1), -1, PREG_SPLIT_DELIM_CAPTURE);
		$this->_endpos = $this->_compute_endpos($this->_tok);
		$this->_compute_level(true);
		$this->_redo = array();
		$out .= $this->_join_tok(0, count($this->_tok));
	}

	return $out;
}


/**
 * Recursive $_tok parser. If redo return parsed text before redo.
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
			$check_np = 0;
			$tp = null;

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
						// allow name:param -> tok_name_param() 
						$name = $name.$d.$param;
						$tp = $this->_plugin[$name][1];
						$param = '';
					}
					else if (($pos = mb_strpos($param, $d)) > 0) {
						$n2 = $name.$d.mb_substr($param, 0, $pos);
						$p2 = mb_substr($param, $pos + 1);

						if (isset($this->_plugin[$n2]) && method_exists($this->_plugin[$n2][0], 'tok_'.$name.'_'.mb_substr($param, 0, $pos))) {
							// allow name:param1:param2 -> tok_name_param1(param2) 
							$name = $n2;
							$param = $p2;
							$tp = $this->_plugin[$name][1];
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
				else if ($ep > $i) {
					$out = $this->_buildin($buildin, $name, $param, $this->_merge_txt($i+1, $ep-1));
					$i = $ep;
				}
				else {
					throw new Exception('invalid endpos', "i=$i ep=$ep");
				}
			}
			else {
				$this->_set_level($i, $ep);

				if ($ep == -1) {
					$out = $this->_call_plugin($name, $param);
				}
				else if ($ep > $i) {
					if ($tp & TokPlugin::TEXT) {
						$out = $this->_call_plugin($name, $param, $this->_merge_txt($i + 1, $ep - 1));
					}
					else { 
						$out = $this->_call_plugin($name, $param, $this->_join_tok($i + 1, $ep));
					}

					$i = $ep;
				}
				else {
					throw new Exception('invalid endpos', "i=$i ep=$ep");
				}

				if ($tp & TokPlugin::REDO) {
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
 * @param string $plugin
 * @return int
 */
public function getLevel($plugin) {
	$level = 0;

	for ($i = $this->_last_level_pos; !$level && $i > 0; $i--) {
		$end = $this->_level[$i][1];
		if ($this->_tok[$end] === ':'.$plugin) {
			$level = $this->_level[$i][2];
		}
	}

	if (!$level) {
		throw new Exception('tokenizer error', "getLevel($plugin) failed - _last_level_pos=".$this->_last_level_pos."\n".$this->dump());
	}

	return $level;
}


/**
 * Return tokenizer dump (level, endpos, tok). Flag:
 * 
 * 1 = _level
 * 2 = _endpos
 * 4 = _tok
 *
 * @param int $flag (default = 7 = 1 | 2 | 4 = _level + _endpos + _tok)
 * @return string
 */
public function dump($flag = 7) {
	$res = '';

	if ($flag & 1) {
		$res .= "\n_level:\n";
		for ($i = 0; $i < count($this->_level); $i++) {
			$l = $this->_level[$i];
			$res .= sprintf("%3d: [%3d,%3d,%3d,%3d]\n", $i, $l[0], $l[1], $l[2], $l[3]);
		}
	}

	if ($flag & 2) {
		$res .= "\n_endpos:\n";
		for ($i = 1; $i < count($this->_endpos); $i = $i + 2) {
			$res .= sprintf("%3d:%d\n", $i, $this->_endpos[$i]);
		}
	}

	if ($flag & 4) {
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
 * Set _last_level_pos.
 *
 * @param int $start
 * @param int $end
 */
private function _set_level($start, $end) {
	$this->_last_level_pos = -1;

	for ($i = count($this->_level) - 1; $this->_last_level_pos === -1 && $i > -1; $i--) {
		if ($this->_level[$i][0] === $start && ($this->_level[$i][1] === $end || ($end === -1 && $this->_level[$i][1] === $start))) {
			$this->_last_level_pos = $i;
		}
	}

	if ($this->_last_level_pos === -1) {
		throw new Exception('tokenizer error', "_set_level failed: start=$start end=$end\n".$this->dump());
	}
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

	if ($this->_plugin[$name][1] & TokPlugin::TOKCALL) {
		return call_user_func(array($this->_plugin[$name][0], 'tokCall'), $name, $param, $arg);
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
		throw new Exception('plugin parameter missing', "plugin=$name");
	}
	else if (($pconf & TokPlugin::NO_PARAM) && $plen > 0) {
		throw new Exception('invalid plugin parameter', "plugin=$name param=$param");
	}

	if (($pconf & TokPlugin::REQUIRE_BODY) && $alen == 0) {
		throw new Exception('plugin body missing', "plugin=$name");
	}
	else if (($pconf & TokPlugin::NO_BODY) && $alen > 0) {
		throw new Exception('invalid plugin body', "plugin=$name arg=$arg");
	}

	if (($pconf & TokPlugin::PARAM_LIST) || ($pconf & TokPlugin::PARAM_CSLIST)) {
		require_once(__DIR__.'/lib/split_str.php');
		$delim = ($pconf & TokPlugin::PARAM_LIST) ? ':' : ',';
		$param = lib\split_str($delim, $param);
	}

	if ($pconf & TokPlugin::KV_BODY) {
		require_once(__DIR__.'/lib/conf2kv.php');
		$arg = lib\conf2kv($arg);
	}
	else if ($pconf & TokPlugin::JSON_BODY) {
		require_once(__DIR__.'/JSON.class.php');
		$arg = JSON::decode($arg);
	}
	else if (($pconf & TokPlugin::CSLIST_BODY) || ($pconf & TokPlugin::LIST_BODY)) {
		require_once(__DIR__.'/lib/split_str.php');
		$delim = ($pconf & TokPlugin::CSLIST_BODY) ? ',' : '|#|';
		$arg = lib\split_str($delim, $arg);
	}
	else if ($pconf & TokPlugin::XML_BODY) {
		require_once(__DIR__.'/XML.class.php');	
		$arg = XML::toJSON($arg);
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
 * Compute level. If update is true keep "done" part of level.
 *
 * @param bool $update 
 */
private function _compute_level($update = false) {

	if ($update) {
		$level = $this->_update_level();
	}
	else {
		$this->_level = array();
		$level = 1;
	}

	$this->_last_level_pos = -1;
	$e = array();	

	for ($i = 1; $i < count($this->_endpos); $i = $i + 2) {
		if (count($e) > 0 && $i === end($e)) {
			array_pop($e);
			$level--;
		}

		// ignore ep == -3
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
 * Update _level. Keep only "done" part and update redo count. Return last level.
 * 
 * @return int 
 */
private function _update_level() {

	for ($i = 0, $found = -1; $found === -1 && $i < count($this->_level); $i++) {
		if ($this->_redo[0] === $this->_level[$i][1]) {
			$found = $i;
		}
	}

	if ($found === -1) {
		throw new Exception('tokenizer error', "endpos ".$this->_redo[0]." not found in _level\n".$this->dump());
	}

	// update redo count
	$this->_level[$found][3]++;

	// keep only done levels
	$this->_level = array_slice($this->_level, 0, $found + 1);

	// prevent interference with level search 
	for ($i = 0; $i < count($this->_level); $i++) {
		$this->_level[$i][0] = 0;
		$this->_level[$i][1] = 0;
	}

	return $this->_level[$found][2];
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


}

