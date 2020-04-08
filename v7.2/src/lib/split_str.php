<?php

namespace rkphplib\lib;

require_once dirname(__DIR__).'/Exception.class.php';

use rkphplib\Exception;


/**
 * Split string $txt at delimiter. Remove quotes from parts, trim parts, allow backslash escaped delimiter char.
 * If $txt is vector return $txt (with trimmed elements, apply ignore_empty and limit).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function split_str(string $delim, $txt, bool $ignore_empty = false, int $limit = -1) : array {

	if (is_array($txt)) {
		$arr = [];
		$n = 0;

		foreach ($txt as $key => $value) {
			$value = trim($value);

			if ($ignore_empty && strlen($value) == 0) {
				continue;
			}

			if ($limit > 0 && $n > $limit) {
				$last = array_pop($arr);
				$value = $last.$delim.$value;
			}

			array_push($arr, $value);
			$n++;
		}

		return $arr;
	}

	if (strpos($txt, $delim) === false && $limit > 1) {
		throw new Exception("text has no delimiter [$delim]", $txt);
	}

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
	else if ($pos == $len && !$ignore_empty && ($len === 0 || substr($txt, -1 * $dl) === $delim)) {
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

