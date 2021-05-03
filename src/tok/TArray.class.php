<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once dirname(__DIR__).'/lib/conf2kv.php';
require_once dirname(__DIR__).'/lib/kv2conf.php';
require_once dirname(__DIR__).'/lib/split_str.php';

use rkphplib\Exception;

use function rkphplib\lib\conf2kv;
use function rkphplib\lib\kv2conf;
use function rkphplib\lib\split_str;


/**
 * Array plugin. Array is either vector or hash (use #name). Example:
 *
 * @tok {array:test}
 * @tok {array:push}3{:array}
 * @tok {array:push}{:array}
 * @tok {array:join} = {array:join}","{:array}
 *
 * @tok {array:#hash}
 * @tok {array:set:a}xyz{:array}
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TArray implements TokPlugin {

// @var string $name
private $name = '';

// @var array $array 
private $array = [];


/**
 *
 */
public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];
	$plugin['array'] = 0;
	$plugin['array:set'] = TokPlugin::REQUIRE_BODY;
	$plugin['array:get'] = 0;
	$plugin['array:shift'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['array:unshift'] = TokPlugin::NO_PARAM;
	$plugin['array:pop'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['array:push'] = TokPlugin::NO_PARAM;
	$plugin['array:join'] = 0;
	$plugin['array:length'] = TokPlugin::NO_PARAM;
	$plugin['array:split'] = 0;
	return $plugin;
}


/**
 * Use|Create array $name. Use '=' to switch to trim($arg) as name.
 *
 * @tok {array:}a=5{:array} - create [ 'a' => 5 ]
 * @tok {array:}a|#|b{:array} - create [ 'a', 'b' ]
 * @tok {array:a4} = use|create a4
 * @tok {array:=}b{:array} = use|create b
 * @tok {array:vx}a|#|b|#|c{:array} - create vx = [ 'a', 'b', 'c' ]
 * @tok {array:s}Hello{:array} - create s = [ 'Hello' ]
 * @tok {array:r}[]{:array} - (re)set r = []
 * @tok {array:hx}a=x|#|b=y{:array} - create hx = [ 'a' => 'x', 'b' => 'y' ]
 */
public function tok_array(string $name, ?string $arg) : void {
	if ($name === '=') {
		$name = trim($arg);
		$arg = null;
	}

	if (strlen(preg_replace('/[a-zA-Z0-9_]/', '', $name)) > 0) {
		throw new Exception("no special chars allowed use [a-zA-Z0-9_]+ instead of '$name'");
	}

	$this->name = $name;

	if (is_null($arg) || $arg === '' || $arg === '[]') {
		if (!isset($this->array[$name]) || $arg === '[]') {
			$this->array[$name] = [];
		}

		return;
	}

	$this->array[$name] = conf2kv($arg);
	// \rkphplib\lib\log_debug([ "TArray.tok_array:92> create '$name' = <1>", $this->array[$name] ]);
}


/**
 * Set array = split_str($delimiter, $arg).
 *
 * @tok {array:split::}a:b:c{:array} = [a, b, c]
 * @tok {array:split:,}a, b\,c{:array} = ['a', 'b,c']
 */
public function tok_array_split(string $delimiter, ?string $arg) : void {
	$this->array[$this->name] = split_str($delimiter, $arg);
	// \rkphplib\lib\log_debug([ "TArray.tok_array_split:104> {$this->name} = <1>", $this->array[$this->name] ]);
}


/**
 * Set array[$key] = value.
 *
 * @tok {array:set:0}abc{:array} - array[0] = 'abc'
 * @tok {array:set:label}Hello{:array} - array['label'] = 'Hello'
 * @tok {array:set}a=x1|#|b=x2{:array} - array = array_merge(array, [ 'a' => 'x1', 'b' => 'x2' ])
 */
public function tok_array_set(string $key, string $value) : void {
	if ($key === '') {
		$this->array[$this->name] = array_merge($this->array[$this->name], conf2kv($value));
		// \rkphplib\lib\log_debug([ "TArray.tok_array_set:118> {$this->name} = <1>", $this->array[$this->name] ]);
		return;
	}

	if (is_integer($key)) {
		$key = intval($key);
	}

	// \rkphplib\lib\log_debug("TArray.tok_array_set:126> set {$this->name}[$key] = '$value'");
	$this->array[$this->name][$key] = $value;
}


/**
 * Return array[$key]. Paramter $key is either string, int (return a[(int) $key]) or empty (return kv2conf(array)).
 * Throw exception if array key is not set.
 * 
 * @tok {array:get} - "a|#|b|#|c" if array is [a, b, c]
 * @tok {array:get} - "a=x|#|b=y" if array is [ 'a' => 'x', 'b' => 'y' ]
 * @tok {array:get:0} - array[0]
 * @tok {array:get:abc} - array['abc']
 */
public function tok_array_get(string $key) : string {
	if ('' == $key) {
		return kv2conf($this->array[$this->name]);
	}

	if (!array_key_exists($key, $this->array[$this->name])) {
		throw new Exception("missing {$this->name}[$key]", print_r($this->array[$this->name], true));
	}

	return $this->array[$this->name][$key];
}


/**
 * Remove and return first array value. Return empty string if array is empty.
 *
 * @tok {array:shift} = a ([a] to []) 
 */
public function tok_array_shift() : string {
	return array_shift($this->array[$this->name]);
}


/**
 * Insert element at first position of array.
 *
 * @tok {array:unshift}b{:array} ([a] to [b,a])
 */
public function tok_array_unshift(string $value) : void {
	array_unshift($this->array[$this->name], $value);
}


/**
 * Remove and return last array value. Return empty string if array is empty.
 *
 * @tok {array:pop} = a ([a] to []) 
 */
public function tok_array_pop() : string {
	return array_pop($this->array[$this->name]);
}


/**
 * Insert element at last position of array.
 *
 * @tok {array:push}b{:array} ([a] to [a,b])
 */
public function tok_array_push(string $value) : void {
	array_push($this->array[$this->name], $value);
}


/**
 * Return joined array. Default delimiter is '|'.
 *
 * @tok {array:}a|#|b{:array} {array:join:|} {array:join},{:array} = "a|b" "a,b"
 */
public function tok_array_join(?string $param_delimiter, ?string $arg_delimiter) : string {
	$delimiter = '|';

	if (!empty($param_delimiter)) {
		$delimiter = $param_delimiter;
	}
	else if (!empty($arg_delimiter)) {
		$delimiter = $arg_delimiter;
	}
	
	return join($delimiter, $this->array[$this->name]);
}


/**
 * Return array length.
 *
 * @tok {array:}a{:array}{array:length} = 1 ([a])
 */
public function tok_array_length() : int {
	return count($this->array[$this->name]);
}

}
