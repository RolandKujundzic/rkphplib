<?php

namespace rkphplib\lib;


/**
 * Convert all strings within $data (any) from utf8 to latin1.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 */
function utf8_to_latin1(&$data) {

	if (is_string($data)) {
		if (mb_detect_encoding($data, 'UTF-8', true)) {
			$data = utf8_decode($data);
		}
	}
	else if (is_array($data)) {
		foreach ($data as &$value) {
			utf8_to_latin1($value);
		}

		unset($value);
	}
	else if (is_object($data)) {
		$vars = array_keys(get_object_vars($data));

		foreach ($vars as $var) {
			utf8_to_latin1($data->$var);
		}
	}
}
