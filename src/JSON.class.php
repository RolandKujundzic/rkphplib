<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/lib/http_code.php';

use function rkphplib\lib\http_code;


/**
 * JSON wrapper. 
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class JSON {


/**
 * Convert json to vector|hash table.
 *
 * @example …
 * $table = [
 *   [ "1", "John", "32", "male" ],
 *   { "id": "2", "age": 18, "name": "Frank" }
 * ];
 *
 * JSON::toTable($table, [ 'id', 'name', 'age', 'gender:male' ])) == …
 * [
 *   [ "1", "John", "32", "male" ],
 *   [ "2", "Frank", "18", "male" ]
 * ]
 * @EOL
 *
 * @param string|array $table
 */
public static function toTable($table, array $cols = [], $hash_table = false) : array {
	if (is_string($table)) {
		$table = JSON::decode($table);
	}

	if (!is_array($table)) {
		throw new Exception('invalid table use either array or json string', "$hash_table=[$hash_table], cols: ".
			print_r($cols, true)."table: ".print_r($table, true));
	}

	if (!count($cols)) {
		return $table;
	}

	for ($i = 0; $i < count($table); $i++) {
		$new_row = [];

		for ($j = 0; !$hash_table && !isset($table[$i][0]) && $j < count($cols); $j++) {
			$tmp = explode(':', $cols[$j]);
			$default = count($tmp) == 2 ? $tmp[1] : null;
			$col = $tmp[0];

			if (isset($table[$i][$col])) {
				array_push($new_row, $table[$i][$col]);
			}
			else {
				array_push($new_row, $default);
			}
		}

		if (count($new_row)) {
			$table[$i] = $new_row;
		}
		else if (!$hash_table && !count($cols) && !isset($table[$i][0])) {
			throw new Exception('invalid row '.$i.' (parameter 2 = column name list missing)', print_r($table[$i], true));
		}
	}

	return $table;
}


/**
 * Return last error message for encode/decode.
 */
private static function _error_msg(int $err_no) : string {
	static $errors = array(
		JSON_ERROR_NONE => 'No error',
		JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
		JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)',
		JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
		JSON_ERROR_SYNTAX => 'Syntax error',
		JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
	);

	$res = 'Unknown error';

	if (function_exists('json_last_error_msg')) {
		$res = json_last_error_msg();
	}
	else if (isset($errors[$err_no])) {
		$res = $errors[$err_no];
	}

	return $res;
}


/**
 * Return json encoded $any. Wrapper of json_encode() with sane options.
 * $options = 448 = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT
 */
public static function encode($any, int $options = 448, int $depth = 512) : string {
	$res = json_encode($any, $options, $depth);

	if (($err_no = json_last_error())) {
		throw new Exception("JSON.encode failed", self::_error_msg($err_no));
	}

	return $res;
}


/**
 * Return json decoded $txt as hash ($flag = 1) or object ($flag = 0). Flags: 
 * - 2^0 = return assoc
 * - 2^1 = html_entity_decode result 
 * - 2^2 = return empty array if error
 *
 * @param int|bool flag (default 2^0 = 1 = return assoc)
 * @return object|array
 */
public static function decode(string $txt, $flag = 1) {
	$assoc = is_bool($flag) ? $flag : $flag & 1;
	$res = json_decode($txt, $assoc);

	if (($err_no = json_last_error())) {
		if ($flag & 4) {
			return [];
		}

		$txt = (mb_strlen($txt) > 80) ? substr($txt, 0, 30).' ... '.substr($txt, -30) : $txt;
		throw new Exception("JSON.decode failed", self::_error_msg($err_no)."\nJSON=[".$txt."]");
	}

	if ($flag & 2) {
		self::html_entity_decode($res);
	}

	return $res;
}


/**
 * Return pretty printed json.
 */
public static function pretty_print(string $json) : string {
    return json_encode(json_decode($json), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
}


/**
 * Print JSON encoded $o and exit. Use for ajax script output.
 * If code >= 400 and $o is string or Exception return { error: 1, error_message: "..." }.
 */
public static function output($o, int $code = 200) : void {
	
	if ($code >= 400) {
		if ($o instanceof \Exception) {
			$o = [ 'error' => 1, 'error_message' => $o->getMessage(), 'error_code' => $o->getCode() ];
		}
		else if (is_string($o)) {
			$o = [ 'error' => 1, 'error_message' => $o ];
		}
	}

	http_code($code, [ 'Content-Type' => 'application/json', '@output' => JSON::encode($o) ]);
}


/**
 * Apply html_entity_decode to all (value) strings within $data.
 * @param &any $data
 */
public static function html_entity_decode(&$data) : void {
	if (is_string($data)) {
		$data = html_entity_decode($data);
	}
	else if (is_array($data)) {
		foreach ($data as &$value) {
			self::html_entity_decode($value);
		}

		unset($value);
	}
	else if (is_object($data)) {
		$vars = array_keys(get_object_vars($data));

		foreach ($vars as $var) {
			html_entity_decode($data->$var);
		}
	}
}


}

