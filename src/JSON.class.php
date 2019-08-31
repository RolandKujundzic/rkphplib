<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';


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
 * Return json encoded $any. Wrapper of json_encode() default options 448 = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT.
 */
public static function encode($any, int $options = 448, int $depth = 512) : string {
	$res = json_encode($any, $options, $depth);

	if (($err_no = json_last_error())) {
		throw new Exception("JSON.encode failed", self::_error_msg($err_no));
	}

	return $res;
}


/**
 * Return json decoded $txt as hash ($assoc = true) or object ($assoc = false).
 */
public static function decode(string $txt, bool $assoc = true) {
	$res = json_decode($txt, $assoc);

	if (($err_no = json_last_error())) {
		$txt = (mb_strlen($txt) > 80) ? substr($txt, 0, 30).' ... '.substr($txt, -30) : $txt;
		throw new Exception("JSON.decode failed", self::_error_msg($err_no)."\nJSON=[".$txt."]");
	}

	return $res;
}


/**
 * Return pretty printed json.
 */
public static function pretty_print(string $json) : string {
    return json_encode(json_decode($json), 320|JSON_PRETTY_PRINT);
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

	http_response_code($code);
	header('Content-Type: application/json');
	$output = JSON::encode($o);
	header('Content-Length: '.mb_strlen($output));
	print $output;
	exit(0);
}


}

