<?php

namespace rkphplib\lib;


/**
 * Split string at delimiter.
 *
 * Remove quotes from parts, allow backslash escaped delimiter char.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string $delim
 * @param string $txt
 * @param boolean $ignore_empty (default = false)
 * @param int $limit (default = -1)
 * @return array
 */
function split_str($delim, $txt, $ignore_empty = false, $limit = -1) {

	$esc = '\\';
	$dl = strlen($delim);
	$len = strlen($txt);
	$is_esc = false;
	$parts = array();
	$lpos = 0;
	$pos = 0;

	while ($pos < $len && ($pos = strpos($txt, $delim, $pos)) !== false) {

		if ($esc && substr($txt, $pos - 1, 1) == $esc) {
			$is_esc = true;
			$pos += $dl;
		}
		else {
			$value = trim(substr($txt, $lpos, $pos - $lpos));

			if ($is_esc) {
				$value = str_replace($esc.$delim, $delim, $value);
			}

			if (substr($value, 0, 1) == '"' && substr($value, -1) == '"' && strlen($value) > 1) {
				$value = substr($value, 1, -1);
			}

			if (!$ignore_empty || strlen($value) > 0) {
				array_push($parts, $value);
			}

			$pos += $dl;
			$lpos = $pos;
			$is_esc = false;
		}
	}

	if ($lpos < $len) {
		$value = trim(substr($txt, $lpos));

		if ($is_esc) {
			$value = str_replace($esc.$delim, $delim, $value);
		}

		if (substr($value, 0, 1) == '"' && substr($value, -1) == '"' && strlen($value) > 1) {
			$value = substr($value, 1, -1);
		}

		if (!$ignore_empty || strlen($value) > 0) {
			array_push($parts, $value);
		}
	}
	else if ($lpos == $len && !$ignore_empty && substr($txt, -1) === $delim) {
		// a,b, = [a][b][]
		array_push($parts, '');
	}

	if ($limit > 0 && count($parts) > $limit) {
		$tmp = array();

		while ($limit > 1) {
			array_push($tmp, array_shift($parts));
			$limit--;
		}

		array_push($tmp, join($delim, $parts));
		return $tmp;
	}

	return $parts;
}

