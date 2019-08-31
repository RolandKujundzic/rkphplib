<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');

use rkphplib\Exception;


/**
 * Array plugin. Example:
 *
 * @tok {array:test}
 * @tok {array:push}3{:array}
 * @tok {array:push}{:array}
 * @tok {array:join} = {array:join}","{:array}
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TArray implements TokPlugin {

/** @var string $name */
protected $name = null;

/** @var array $vector */
protected $vector = [];



/**
 * Return {array:set|get|shift|unshift|pop|push|join|length}
 */
public function getPlugins(Tokenizer $tok) : array {
  $plugin = [];
	$plugin['array'] = 0;
  $plugin['array:set'] = TokPlugin::REQUIRE_PARAM;
  $plugin['array:get'] = TokPlugin::REQUIRE_PARAM;
  $plugin['array:shift'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
  $plugin['array:unshift'] = TokPlugin::NO_PARAM;
  $plugin['array:pop'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
  $plugin['array:push'] = TokPlugin::NO_PARAM;
	$plugin['array:join'] = TokPlugin::NO_PARAM;
	$plugin['array:length'] = TokPlugin::NO_PARAM;
  return $plugin;
}


/**
 * Return names vector.
 *
 * @throws if named vector does not exist
 * @return vector-reference
 */
private function getVector() {
	if (is_null($this->name)) {
		throw new Exception('no such array - call [array:]NAME[:array] first');
	}

	return $this->vector[$this->name];
}


/**
 * Set array name. Create empty array if name is unused.
 *
 * @tok {array:a4} = use array a4
 * @tok {array:}b{:array} = use array b
 *
 * @throws
 * @see tok_number_format
 * return ''
 */
public function tok_array($param, $arg) {

	$name = empty($param) ? trim($arg) : $param;
	$fix_name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

	if ($fix_name != $name) {
		throw new Exception("no special chars allowed use [$fix_name] instead of [$name]");
	}

	$this->name = $name;

	if (!isset($this->vector[$this->name])) {
		$this->vector[$this->name] = [];
	}
}


/**
 * Set array value at position.
 *
 * @tok {array:set:0}a{:array}
 *
 * @throws if pos is invalid
 * @param int $pos
 * @param string $value
 * @return ''
 */
public function tok_array_set($pos, $value) {
	$pos = intval($pos);
	$a =& $this->getVector();

	if ($pos < 0 || $pos >= count($a)) {
		throw new Exception("[$pos] is not in [0, ".count($a)."[");
	}
	
	$a[$pos] = $value;
}


/**
 * Return array value.
 *
 * @tok {array:get:0} = a ([a] to [a]) 
 * 
 * @throws if pos is invalid
 * @param int $pos
 * @return string
 */
public function tok_array_get($pos) {
	$pos = intval($pos);
	$a =& $this->getVector();

	if ($pos < 0 || $pos >= count($a)) {
		throw new Exception("[$pos] is not in [0, ".count($a)."[");
	}
	
	return $a[$pos];
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
