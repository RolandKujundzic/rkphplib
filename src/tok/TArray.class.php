<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once dirname(__DIR__).'/lib/conf2kv.php';
require_once dirname(__DIR__).'/lib/split_str.php';

use rkphplib\Exception;

use function rkphplib\lib\conf2kv;
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

/** @var hash $env */
protected $env = [ 'name' => null, 'isHash' => false, 'isVector' => false ];

/** @var array $array */
protected $array = [];



/**
 * Return {array:set|get|shift|unshift|pop|push|join|length}
 */
public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];
	$plugin['array'] = 0;
	$plugin['array:set'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['array:get'] = 0;
	$plugin['array:shift'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['array:unshift'] = TokPlugin::NO_PARAM;
	$plugin['array:pop'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['array:push'] = TokPlugin::NO_PARAM;
	$plugin['array:join'] = TokPlugin::NO_PARAM;
	$plugin['array:length'] = TokPlugin::NO_PARAM;
	$plugin['array:split'] = TokPlugin::REQUIRE_BODY;
	return $plugin;
}


/**
 * Return array[name]. Abort if array is not activated or if $type (hash|vector) mismatch. 
 */
private function getArray(string $name = '', string $type = '') : array {

	if ($name = '' && strlen($this->env['name']) > 0) {
		if ($this->env['isHash']) {
			$name = '#'.$this->env['name'];
		}
		else {
			$name = $this->env['name'];
		}
	}

	if (substr($name, 0, 1) == '#') {
		$name = substr($name, 1);
		$type = 'hash';
	}

	if ($this->env['name'] != $name || !array_key_exists($name, $this->array)) {
		$prefix = $this->env['isHash'] ? '#' : '';
		throw new Exception("no such array '$prefix$name' - call [array:$prefix$name] or [array:]$prefix$name".'[:array] first');
	}

	if ($type == '') {
		$type = 'vector';
	}

	if ($type != 'hash' && $type != 'vector') {
		throw new Exception('invalid array type '.$type);
	}
	else if ($type == 'hash' && !$this->env['isHash']) {
		throw new Exception('array is not hash');
	}
	else if ($type == 'vector' && !$this->env['isVector']) {
		throw new Exception('array is not vector');
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
public function tok_array(string $name, string $arg) : void {
	$data = null;

	if (empty($name)) {
		$name = trim($arg);
	}
	else if (!empty($arg)) {
		$data = conf2kv($arg);

		if (is_string($data)) {
			if ($data == '[]') {
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

	$fix_name = preg_replace('/[a-zA-Z0-9_]/', '', $name);
	if ($fix_name != '') {
		throw new Exception("no special chars allowed use [a-zA-Z0-9_] instead of '$name'");
	}

	$type = 'isVector';
	if (substr($name, 0, 1) == '#') {
		$name = substr($name, 1);
		$type = 'isHash';
	}

	$this->env['name'] = $name;
	$this->env['isVector'] = false;
	$this->env['isHash'] = false;
	$this->env[$type] = true;

	if (!is_null($data)) {
		$this->array[$name] = $data;
	}
	else if (!isset($this->array[$name])) {
		$this->array[$name] = [];
	}
}


/**
 * Set array = split_str($delimiter, $arg).
 *
 * @tok {array:split:\:}a:b:c{:array} = [a, b, c]
 * @tok {array:split:,}a, b\,c{:array} = ['a', 'b,c']
 */
public function tok_array_split(string $delimiter, string $arg) : void {
	$a =& $this->getArray('', 'vector');
	$a = split_str($delimiter, $arg);
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

	$a =& $this->getArray('', $type);

	if ($key == '') {
		$a = array_merge($a, conf2kv($value));
	}
	else {
		$a[$key] = $value;
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
	$a =& $this->getArray();

	if ($key == '') {
		return kv2conf($a);
	}

	if (!array_key_exists($a, $key)) {
		throw new Exception("no such key $key");
	}

	return $a[$key];
}


/**
 * Remove and return first array value. Return empty string if array is empty.
 *
 * @tok {array:shift} = a ([a] to []) 
 * 
 * @throws
 * @return string
 */
public function tok_array_shift() {
	$a =& $this->getVector();
	return array_shift($a);
}


/**
 * Insert element at first position of array.
 *
 * @tok {array:unshift}b{:array} ([a] to [b,a])
 * 
 * @throws
 * @param string $value
 * @return ''
 */
public function tok_array_unshift($value) {
	$a =& $this->getVector();
	array_unshift($a, $value);
	return '';
}


/**
 * Remove and return last array value. Return empty string if array is empty.
 *
 * @tok {array:pop} = a ([a] to []) 
 * 
 * @throws
 * @return string
 */
public function tok_array_pop() {
	$a =& $this->getVector();
	return array_pop();
}


/**
 * Insert element at last position of array.
 *
 * @tok {array:push}b{:array} ([a] to [a,b])
 * 
 * @throws
 * @param string $value
 * @return ''
 */
public function tok_array_push($value) {
	$a =& $this->getVector();
	array_push($a, $value);
	return '';
}


/**
 * Return joined array.
 *
 * @tok {array:join}|{:array} ([a,b] to "a|b")
 * 
 * @throws
 * @param string $value
 * @return string
 */
public function tok_array_join($delimiter) {
	$a =& $this->getVector();
	array_push($a, $value);
	return '';
}


/**
 * Return array length.
 *
 * @tok {array:length} = 1 ([a])
 * 
 * @throws
 * @return int
 */
public function tok_array_length() {
	$a =& $this->getVector();
	return count($a);
}


}
