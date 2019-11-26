<?php

namespace rkphplib\lib;

require_once dirname(__DIR__).'/Exception.class.php';
require_once __DIR__.'/entity.php';
require_once __DIR__.'/is_map.php';

use rkphplib\Exception;

if (!defined('HASH_DELIMITER')) {
	// @const HASH_DELIMITER = '|#|' if undefined 
	define('HASH_DELIMITER', '|#|');
}


/**
 * Convert hash to string. 
 *
 * Reverse version of conf2kv(). Use '' for null values. Escape entity($d2).
 * If $ikv is true (default = false) prepend [@@1="",","|#|@@2="$d1","$d2"|#|].
 * Level is internal variable for recursion level detection.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function kv2conf(array $kv, string $d1 = '=', string $d2 = HASH_DELIMITER, bool $ikv = false, int $level = 1) : string {

	$d3 = '|:|';
	$conf = $ikv ? '@@1="",","'.$d2."\n".'@@2="'.$d1.'","'.$d2.'"'.$d2."\n".'@@3="'.$d1.'","'.$d3.'"'.$d2."\n" : '';
	$e_d2 = entity($d2);
	
	foreach ($kv as $key => $value) {
		$conf .= $key.$d1;

		if (is_numeric($value)) {
			$conf .= $value.$d2."\n";
		}
		else if (is_bool($value)) {
			$conf .= intval($value).$d2."\n";
		}
		else if (is_null($value)) {
			$conf .= ''.$d2."\n";
		}
		else if (is_string($value)) {
			if (strpos($value, $d2) !== false) {
				$value = str_replace($d2, $e_d2, $value);
			}

			if (trim($value) != $value) {
				$value = '"'.$value.'"'; 
			}

			$conf .= $value.$d2."\n";
		}
		else if (is_array($value)) {
			if (is_map($value)) {
				$q = str_pad("", $level, '"');
				$conf .= $q.'@3 '.kv2conf($value, $d1, $d3, false, $level + 1).$q.$d2."\n";
			}
			else {
				$arr = array();

				foreach ($value as $val) {
					if (is_string($val)) {
						if (strpos($val, ',') !== false) {
							$val = '"'.str_replace('"', '""', $val).'"';
						}
					}
					else if (is_bool($val)) {
						$val = intval($val);
					}
					else if (!is_numeric($val)) {
						throw new Exception("invalid array value", "$key: ".print_r($value, true));
					}

					array_push($arr, $val);
				}

				$conf .= '@1 '.join(',', $arr).$d2."\n";
			}
		}
		else {
			throw new Exception("invalid value", "$key: ".print_r($value, true));
		}
	}

	$res = trim($conf);
	$ld2 = mb_strlen($d2);

	if (substr($res, -1 * $ld2) == $d2) {
		$res = substr($res, 0, -1 * $ld2); 
	}

	return $res;
}

