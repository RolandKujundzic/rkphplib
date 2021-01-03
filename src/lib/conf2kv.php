<?php

namespace rkphplib\lib;

require_once __DIR__.'/entity.php';

if (!defined('HASH_DELIMITER')) {
	// @const HASH_DELIMITER = '|#|' if undefined
	define('HASH_DELIMITER', '|#|');
}


/**
 * Split text into key value hash. Unescape entity($d2).
 * Split text at $d2 (|#|) into lines. Split lines at first $d1 (=) into key value. 
 * If $d1 is empty return array with $d2 as delimiter. If text == '' return empty array.
 * All keys and values are trimmed. Use quote (") to preserve whitespace.
 *
 * @code conf2kv('k1=v1|#|k2=v2') == [ 'k1' => 'v1', 'k2' => 'v2' ] 
 * @code conf2kv('a=1|#|a=" 2 "|#|a= 3 ') == [ 'a' => 1, 'a.1' => ' 2 ', 'a.2' => 3 ]
 * @code conf2kv('a|#|b|#|c') == [ 'a', 'b', 'c' ]
 * @code conf2kv('abc') == [ 'abc' ]
 *
 * Use [@@="=","\n\n"\n] to switch to $d1 = '=' and $d2 = "\n\n" (escape empty line with leading space).
 * Use .= to append value to last key. Use '@@N="d1","d2"' to defined value split '@N'.
 * If value starts with '@N' use '@@N' value split (value = conf2kv(value, d1, d2)).
 * Predefined value split: '@@1="",","', '@@2=$d1,$d2' and '@@3="=","|:|"'.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function conf2kv(?string $text, string $d1 = '=', string $d2 = HASH_DELIMITER, array $env = []) : array {

	if (strlen(trim($text)) == 0) {
		return [];
	}

	if (preg_match('/^@@="(.*)","(.*)"\n/', $text, $match)) {
		$text = substr($text, strlen($match[0]));
		$d1 = $match[1];
		$d2 = str_replace('\\n', "\n", $match[2]);
	}

	$lines = explode($d2, $text);
	$e_d2 = entity($d2);
	$d1_len = mb_strlen($d1);
	$value = '';
	$keep = [];
	$last = '';
	$key = null;
	$kv = [];
	$n = 0;

	foreach ($lines as $line) {
		$tmp = ($d1_len > 0) ? explode($d1, $line, 2) : [ $line ];
		$key = trim($tmp[0]);

		if (count($tmp) == 1) {
			if ($key !== '') {
				$value = $tmp[0];
				$key = $n;
				$n++;
			}
		}
		else if ($key === '') {
			if (substr($tmp[1], 0, 2) === ' @') {
				list ($ekey, $value) = explode(' ', substr($tmp[1], 2), 2);
				$env[$ekey] = trim($value);
			}
			else {
				$value = $line;
				$key = $n;
				$n++;
			}
		}
		else if (substr($key, 0, 2) == '@@' && ($value = trim($tmp[1])) &&
							substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
			$value = explode('","', substr($value, 1, -1));
			$env[substr($key, 1)] = str_replace([ '\\n', $e_d2 ], [ "\n", $d2 ], $value);
			$key = '';
		}
		else if ($key === '.' && isset($kv[$last])) {
			$kv[$last] .= $d2.trim($tmp[1]);
			$key = '';
		}
		else {
			$value = $tmp[1];

			if (!empty($env['prefix'])) {
				$key = $env['prefix'].$key;
			}

			if (isset($kv[$key])) {
				$i = 0;

				do {
					$i++;
				} while (isset($kv[$key.'.'.$i]));

				$key .= '.'.$i;
			}
		}

		if ($key === '') {
			continue;
		}

		$value = trim($value);
		if (mb_strpos($value, $e_d2) !== false) {
			$value = str_replace($e_d2, $d2, $value); 
		}

		if (preg_match('/^"(@[0-9]+)\s(.+)"$/s', $value, $match) || preg_match('/^(@[0-9]+)\s(.+)$/s', $value, $match)) {
      $sf = $match[1];

			if (!isset($env[$sf])) {
       	if ($sf == '@1') {
         	$env['@1'] = array('', ',');
				}
				else if ($sf == '@2') {
					$env['@2'] = array($d1, $d2);
				}
				else if ($sf == '@3') {
					$env['@3'] = array('=', '|:|');
				}
			}

			if (isset($env[$sf])) {
				$value = conf2kv(trim($match[2]), $env[$sf][0], $env[$sf][1], $env);
			}
		}
		else if (mb_substr($value, 0, 1) == '"' && mb_substr($value, -1) == '"') {
			$value = mb_substr($value, 1, -1);
			$keep[$n - 1] = 1;
		}

		$kv[$key] = $value;
		$last = $key;
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

