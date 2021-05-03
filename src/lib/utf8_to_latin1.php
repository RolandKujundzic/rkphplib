<?php

namespace rkphplib\lib;


/**
 * Convert all strings within $data (any) from utf8 to latin1.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function utf8_to_latin1(&$data) : void {

	if (is_string($data)) {
		if (mb_detect_encoding($data, 'UTF-8,ISO-8859-1', true) == 'UTF-8') {
			$data = mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8');
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

