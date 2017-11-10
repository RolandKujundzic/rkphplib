<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');


/**
 * JSON wrapper. 
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class JSON {


/**
 * Return last error message for encode/decode.
 *
 * @param int $err_no
 * @return string
 */
private static function _error_msg($err_no) {
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
 * Return json encoded $obj. 
 *
 * Throw error if failed. Wrapper of json_encode().
 *
 * @throws
 * @param object $obj
 * @param int $options default is 448 = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
 * @param int $depth
 * @return string
 */
public static function encode($obj, $options = 448, $depth = 512) {
	$res = json_encode($obj, $options, $depth);

	if (($err_no = json_last_error())) {
		throw new Exception("JSON.encode failed", self::_error_msg($err_no));
	}

	return $res;
}


/**
 * Return json decoded $txt as map.
 *
 * @throws 
 * @param string $txt
 * @param bool $assoc
 * @return array[string]string|object
 */
public static function decode($txt, $assoc = true) {
	$res = json_decode($txt, $assoc);

	if (($err_no = json_last_error())) {
		$txt = (mb_strlen($txt) > 80) ? substr($txt, 0, 30).' ... '.substr($txt, -30) : $txt;
		throw new Exception("JSON.decode failed", self::_error_msg($err_no)."\nJSON=[".$txt."]");
	}

	return $res;
}


/**
 * Return pretty printed json.
 *
 * @param string $json
 * @return string
 */
public static function pretty_print($json) {
    return json_encode(json_decode($json), 320|JSON_PRETTY_PRINT);
}


/**
 * Print JSON object and exit. Use for ajax script output.
 * If code >= 400 and $o is string or Exception return { error_message: "..." }.
 *
 * @param Object $o
 * @param int $code
 */
public static function output($o, $code = 200) {
	
	if ($code >= 400) {
		if ($o instanceof \Exception) {
			$o = [ 'error_message' => $o->getMessage(), 'error_code' => $o->getCode() ];
		}
		else if (is_string($o)) {
			$o = [ 'error_message' => $o ];
		}
	}

	http_response_code($code);
	header('Content-Type: application/json');
	$output = JSON::encode($o);
	header('Content-Length: '.mb_strlen($output));
	print $output;
	exit(0);
}


}

