<?php

namespace rkphplib\lib;

require_once __DIR__.'/csv_explode.php';
require_once __DIR__.'/entity.php';

if (!defined('HASH_DELIMITER')) {
	/** @const HASH_DELIMITER = '|#|' if undefined */
	define('HASH_DELIMITER', '|#|');
}


/**
 * Split text into key value hash or string. 
 * 
 * Keys must not start with "@@" or "@N". Split text at $d2 (|#|) into lines. 
 * Split lines at first $d1 (=) into key value. If key is not found return $text or use array index (0, 1, 2, ... ).
 * If key already exists rename to key.N (N = 1, 2, ...). If value starts with "@N" use conf[@@N]="sd1","sd2" and 
 * set value = conf2kv(value, sd1, sd2). Default values are [@@1="",","], [@@2=$d1,$d2] and [@@3="=","|:|". 
 * All keys and values are trimmed. Use leading and traing quote character (") to preserve whitespace and delimiter.
 * If $d1 is empty return array with $d2 as delimiter. If text is empty return empty array. Unescape entity($d2).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function conf2kv(?string $text, string $d1 = '=', string $d2 = HASH_DELIMITER, array $ikv = []) {

	if (strlen(trim($text)) == 0) {
		return [];
	}

	$lines = explode($d2, $text);
	$e_d2 = entity($d2);
	$d1_len = mb_strlen($d1);
	$value = '';
	$keep = [];
	$key = null;
	$kv = [];
	$n = 0;

	foreach ($lines as $line) {
		$tmp = ($d1_len > 0) ? explode($d1, $line, 2) : [ $line ];
		$key = trim($tmp[0]);

		if (count($tmp) == 1) {
			$value = $tmp[0];
			$key = $n;
			$n++;
		}
		else if ($key === '') {
			$key = $n;
			$value = $line;
			$n++;
		}
		else if (isset($kv[$key])) {
			$i = 0;

			do {
				$i++;
			} while (isset($kv[$key.'.'.$i]));

			$key .= '.'.$i;
			$value = $tmp[1];
		}
		else {
			$value = $tmp[1];
		}

		if (!is_null($key) && !is_null($value)) {
			$value = trim($value);

			if (mb_strpos($value, $e_d2) !== false) {
				$value = str_replace($e_d2, $d2, $value); 
			}

			if (preg_match('/^"(@[0-9]+)\s(.+)"$/s', $value, $match) || preg_match('/^(@[0-9]+)\s(.+)$/s', $value, $match)) {
      	$sf = $match[1];

				if (!isset($ikv[$sf])) {
        	if ($sf == '@1') {
          	$ikv['@1'] = array('', ',');
					}
					else if ($sf == '@2') {
						$ikv['@2'] = array($d1, $d2);
					}
					else if ($sf == '@3') {
						$ikv['@3'] = array('=', '|:|');
					}
				}

				if (isset($ikv[$sf])) {
					$value = conf2kv(trim($match[2]), $ikv[$sf][0], $ikv[$sf][1], $ikv);
				}
			}
			else if (mb_substr($key, 0, 2) == '@@') {
				if (mb_substr($value, 0, 1) == '"' && mb_substr($value, -1) == '"') {
					$ikv[mb_substr($key, 1)] = explode('","', mb_substr($value, 1, -1));
				}

				$key = '';
			}
			else if (mb_substr($value, 0, 1) == '"' && mb_substr($value, -1) == '"') {
				$value = mb_substr($value, 1, -1);
				$keep[$n - 1] = 1;
			}

			if ($key !== '') {
				$kv[$key] = $value;
			}
		}
	}

	if ($n > 1 && !isset($keep[$n - 1]) && isset($kv[$n - 1]) && $kv[$n - 1] === '') {
		unset($kv[$n - 1]);
	}

	if (!isset($keep[0]) && isset($kv[0]) && $kv[0] === '') {
		$keys = array_keys($kv);

		if ($keys[0] === 0) {
			array_shift($kv);
		}
		else {
			// mixed content
			unset($kv[0]);
		}
	}

	return $kv;
}

