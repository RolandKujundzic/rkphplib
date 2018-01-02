<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/Exception.class.php');

use rkphplib\Exception;

if (!defined('HASH_DELIMITER')) {
	/** @const HASH_DELIMITER = '|#|' if undefined */
	define('HASH_DELIMITER', '|#|');
}


/**
 * Convert map to string. 
 *
 * Reverse version of conf2kv().
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @see conf2kv
 * @param map $kv
 * @param string $d1 (default is "=")
 * @param string $d2 (default is "|#|")
 * @param bool $ikv (default = false, if true prepend [@@1="",","|#|@@2="$d1","$d2"|#|])
 * @param int $level (recursive call level - default = 1) 
 * @return string
 */
function kv2conf($kv, $d1 = '=', $d2 = HASH_DELIMITER, $ikv = false, $level = 1) {

	$conf = $ikv ? '@@1="",","'.$d2."\n".'@@2="'.$d1.'","'.$d2.'"'.$d2."\n" : '';
	
	foreach ($kv as $key => $value) {
		$conf .= $key.$d1;

		if (is_numeric($value)) {
			$conf .= $value.$d2."\n";
		}
		else if (is_bool($value)) {
			$conf .= intval($value).$d2."\n";
		}
		else if (is_string($value)) {
			if (strpos($value, $d2) !== false || trim($value) != $value) {
				$value = '"'.$value.'"'; 
			}

			$conf .= $value.$d2."\n";
		}
		else if (is_array($value)) {
			if (count(array_filter(array_keys($value), 'is_string')) == 0) {
				$q = str_pad("", $level, '"');
				$conf .= $q.'@2 '.kv2conf($value, $d1, $d2, false, $level + 1).$q.$d2."\n";
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
						throw new Exception("invalid array value", "$key: ".print_r($obj, true));
					}

					array_push($arr, $val);
				}

				$conf .= '@1 '.join(',', $arr).$d2."\n";
			}
		}
		else {
			throw new Exception("invalid value", "$key: ".print_r($obj, true));
		}
	}

	$res = trim($conf);
	$ld2 = mb_strlen($d2);

	if (substr($res, -1 * $ld2) == $d2) {
		$res = substr($res, 0, -1 * $ld2); 
	}

	return $res;
}

