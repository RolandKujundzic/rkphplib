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
 *
 */
class TArray implements TokPlugin {

// @var hash $env 
protected $env = [ 'name' => null, 'isHash' => false, 'isVector' => false ];

// @var array $array 
protected $array = [];



/**
 * @plugin array:set|get|shift|unshift|pop|push|join|length
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
	$plugin['array:split'] = TokPlugin::REQUIRE_BODY;
	return $plugin;
}


/**
 * Return array[name]. Abort if array is not activated or if $type (hash|vector) mismatch. 
 */
private function &getArray(string $name = '', string $type = '') : array {
	if ('' == $name && strlen($this->env['name']) > 0) {
		if ($this->env['isHash']) {
			$name = '#'.$this->env['name'];
		}
		else {
			$name = $this->env['name'];
		}
	}

	if ('' == $type) {
		$type = 'vector';
	}
	else if ('hash' != $type && 'vector' != $type) {
		throw new Exception('invalid array type '.$type);
	}
	else if ('hash' == $type && !$this->env['isHash']) {
		throw new Exception('array '.$name.' is not hash');
	}
	else if ('vector' == $type && !$this->env['isVector']) {
		throw new Exception('array '.$name.' is not vector');
	}

	if ('#' == substr($name, 0, 1)) {
		$name = substr($name, 1);
		$type = 'hash';
	}

	// \rkphplib\lib\log_debug("TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> TArray.&getArray:92> name=[$name] type=[$type] env.name=[".$this->env['name']."]");
	if ($this->env['name'] != $name || !array_key_exists($name, $this->array)) {
		$prefix = $this->env['isHash'] ? '#' : '';
		throw new Exception("no such array '$prefix$name' - call [array:$prefix$name] or [array:]$prefix$name".'[:array] first');
	}

	return $this->array[$name];
}


/**
 * Use array $name. Create empty array $name if necessary. 
 * If $name is empty use trim($arg). If $name has leading '#' create hash.
 *
 * @tok {array:a4} = use|create vector a4
 * @tok {array:}b{:array} = use|create array b
 * @tok {array:vx}a|#|b|#|c{:array} - create vx = [ 'a', 'b', 'c' ]
 * @tok {array:s}Hello{:array} - create s = [ 'Hello' ]
 * @tok {array:r}[]{:array} - reset to r = []
 * @tok {array:#h} = use|create hash h
 * @tok {array:#hx}a=x|#|b=y{:array} - create hx = [ 'a' => 'x', 'b' => 'y' ]
 */
public function tok_array(string $name, ?string $arg) : void {
	$data = null;

	if (empty($name)) {
		$name = trim($arg);
	}
	else if (!empty($arg)) {
		$data = conf2kv($arg);

		if (is_string($data)) {
			if ('[]' == $data) {
				$data = [];
			}
			else {
				$data = [ $data ];
			}
		}
	}

	if (empty($name)) {
		throw new Exception('empty array name');
	}

	$type = 'isVector';
	if ('#' == substr($name, 0, 1)) {
		$name = substr($name, 1);
		$type = 'isHash';
	}

	$fix_name = preg_replace('/[a-zA-Z0-9_]/', '', $name);
	if ('' != $fix_name) {
		throw new Exception("no special chars allowed use [a-zA-Z0-9_] instead of '$name'");
	}

	$this->env['name'] = $name;
	$this->env['isVector'] = false;
	$this->env['isHash'] = false;
	$this->env[$type] = true;

	if (!is_null($data)) {
		// \rkphplib\lib\log_debug("TArray.tok_array:154> create new array $name ($type) = ".print_r($data, true));
		$this->array[$name] = $data;
	}
	else if (!isset($this->array[$name])) {
		// \rkphplib\lib\log_debug("TArray.tok_array:158> create new empty array $name ($type)");
		$this->array[$name] = [];
	}
	else {
		// \rkphplib\lib\log_debug("TArray.tok_array:162> use array $name ($type)");
	}
}


/**
 * Set array = split_str($delimiter, $arg).
 *
 * @tok {array:split::}a:b:c{:array} = [a, b, c]
 * @tok {array:split:,}a, b\,c{:array} = ['a', 'b,c']
 */
public function tok_array_split(string $delimiter, string $arg) : void {
	// \rkphplib\lib\log_debug("TArray.tok_array_split:174> delimiter=[$delimiter] arg=[$arg]");
	$a = &$this->getArray('', 'vector');
	$a = split_str($delimiter, $arg);
	// \rkphplib\lib\log_debug('TArray.tok_array_split:177> '.$this->env['name'].': '.print_r($this->array[$this->env['name']], true));
}


/**
 * Set array[$key] = value.
 *
 * @tok {array:set:0}abc{:array} - array[0] = 'abc'
 * @tok {array:set:label}Hello{:array} - array['label'] = 'Hello'
 * @tok {array:set}a=x1|#|b=x2{:array} - array = array_merge(array, [ 'a' => 'x1', 'b' => 'x2' ])
 */
public function tok_array_set(string $key, string $value) : void {
	$pos = intval($key);
	$type = ("$pos" == $key) ? 'vector' : 'hash';

	// \rkphplib\lib\log_debug('TArray.tok_array_set:192> '.$this->env['name']." ($type) - set [$key]=[$value]");
	$a = &$this->getArray('', $type);

	if ('' == $key) {
		$a = array_merge($a, conf2kv($value));
		// \rkphplib\lib\log_debug('TArray.tok_array_set:197> merge: '.print_r($this->array[$this->env['name']], true));
	}
	else {
		$a[$key] = $value;
		// \rkphplib\lib\log_debug('TArray.tok_array_set:201> set key: '.print_r($this->array[$this->env['name']], true));
	}
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
	$a = &$this->getArray();

	if ('' == $key) {
		return kv2conf($a);
	}

	if (!array_key_exists($key, $a)) {
		throw new Exception("no such key $key", $this->env['name'].': '.print_r($a, true));
	}

	return $a[$key];
}


/**
 * Remove and return first array value. Return empty string if array is empty.
 *
 * @tok {array:shift} = a ([a] to []) 
 */
public function tok_array_shift() : string {
	$a = &$this->getArray('', 'vector');
	return array_shift($a);
}


/**
 * Insert element at first position of array.
 *
 * @tok {array:unshift}b{:array} ([a] to [b,a])
 */
public function tok_array_unshift(string $value) : void {
	$a = &$this->getArray('', 'vector');
	array_unshift($a, $value);
}


/**
 * Remove and return last array value. Return empty string if array is empty.
 *
 * @tok {array:pop} = a ([a] to []) 
 */
public function tok_array_pop() : string {
	$a = &$this->getArray('', 'vector');
	return array_pop($a);
}


/**
 * Insert element at last position of array.
 *
 * @tok {array:push}b{:array} ([a] to [a,b])
 */
public function tok_array_push(string $value) : void {
	$a = &$this->getArray('', 'vector');
	array_push($a, $value);
}


/**
 * Return joined array. Default delimiter is '|'.
 *
 * @tok {array:test}a|#|b{:array} {array:join:|} {array:join},{:array} = "a|b" "a,b"
 */
public function tok_array_join(?string $param_delimiter, ?string $arg_delimiter) : string {
	$delimiter = '|';

	if (!empty($param_delimiter)) {
		$delimiter = $param_delimiter;
	}
	else if (!empty($arg_delimiter)) {
		$delimiter = $arg_delimiter;
	}
	
	$a = &$this->getArray('', 'vector');
	return join($delimiter, $a);
}


/**
 * Return array length.
 *
 * @tok {array:}a{:array}{array:length} = 1 ([a])
 */
public function tok_array_length() : int {
	$a = &$this->getArray();
	return count($a);
}


}
