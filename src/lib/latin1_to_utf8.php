<?php

namespace rkphplib\lib;


/**
 * Convert all strings within $data from latin1 to utf8.
 * Parameter $data (can be anything) will be modified if latin1 strings are detected.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
function latin1_to_utf8(&$data) {

	if (is_string($data)) {
		if (mb_detect_encoding($data, 'ISO-8859-1', true)) {
			$data = utf8_encode($data);
		}
	}
	else if (is_array($data)) {
		foreach ($data as &$value) {
			latin1_2_utf8($value);
		}

		unset($value);
	}
	else if (is_object($data)) {
		$vars = array_keys(get_object_vars($data));

		foreach ($vars as $var) {
			latin1_to_utf8($data->$var);
		}
	}
}

