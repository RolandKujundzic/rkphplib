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
 * Return pretty printed json. Use native JSON_PRETTY_PRINT as default.
 *
 * @see http://stackoverflow.com/questions/6054033/pretty-printing-json-with-php
 * @param string $json
 * @param bool $custom 
 * @return string
 */
public static function pretty_print($json, $custom = false) {

	if (!$custom && ($json_object = json_decode($json))) {
		return json_encode($json_object, 320|JSON_PRETTY_PRINT);
	}

	$result = '';
	$level = 0;
	$in_quotes = false;
	$in_escape = false;
	$ends_line_level = NULL;
	$json_length = mb_strlen($json);

	for ($i = 0; $i < $json_length; $i++) {
		$char = $json[$i];
		$new_line_level = NULL;
		$post = "";

		if ($ends_line_level !== NULL) {
			$new_line_level = $ends_line_level;
			$ends_line_level = NULL;
		}

		if ($in_escape) {
			$in_escape = false;
		}
		else if ($char === '"') {
			$in_quotes = !$in_quotes;
		}
		else if (!$in_quotes) {
			switch ($char) {
				case '}': case ']':
					$level--;
					$ends_line_level = NULL;
					$new_line_level = $level;
					break;

				case '{': case '[':
					$level++;
				case ',':
					$ends_line_level = $level;
					break;

				case ':':
					$post = " ";
					break;

				case " ": case "\t": case "\n": case "\r":
					$char = "";
					$ends_line_level = $new_line_level;
					$new_line_level = NULL;
					break;
			}
		}
		else if ($char === '\\') {
			$in_escape = true;
		}

		if ($new_line_level !== NULL) {
			$result .= "\n".str_repeat( "\t", $new_line_level );
		}

		$result .= $char.$post;
	}

	return $result;
}


}

