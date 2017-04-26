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
 * Convert all strings within $data to latin1.
 *
 * If string are latin1 already no change should occur.
 *
 * @param mixed &$data
 */
public static function latin1(&$data) {

	if (is_string($data)) {
		if (mb_detect_encoding($data, 'UTF-8', true)) {
			$data = utf8_decode($data);
		}
	}
	else if (is_array($data)) {
		foreach ($data as &$value) {
			self::latin1($value);
		}

		unset($value);
	}
	else if (is_object($data)) {
		$vars = array_keys(get_object_vars($data));

		foreach ($vars as $var) {
			self::latin1($data->$var);
		}
	}
}


/**
 * Convert all strings within $data to utf8.
 *
 * If strings are utf8 Umlaute (öäüß ÖÄÜ) will be broken.
 *
 * @param mixed &$data
 */
public static function utf8(&$data) {

	if (is_string($data)) {
		if (mb_detect_encoding($data, 'ISO-8859-1', true)) {
			$data = utf8_encode($data);
		}
	}
	else if (is_array($data)) {
		foreach ($data as &$value) {
			self::utf8($value);
		}

		unset($value);
	}
	else if (is_object($data)) {
		$vars = array_keys(get_object_vars($data));

		foreach ($vars as $var) {
			self::utf8($data->$var);
		}
	}
}


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


}

