<?php

namespace rkphplib\lib;

require_once __DIR__.'/csv_explode.php';
require_once __DIR__.'/entity.php';

if (!defined('HASH_DELIMITER')) {
  // @const HASH_DELIMITER = '|#|' if undefined 
  define('HASH_DELIMITER', '|#|');
}



/**
 * Split text into key value hash or string. Use csv quote escape (broken if quotes are wrong).
 * Quoted delimiter $d2 is escaped. Keys must not start with "@@", "@N" or "@_". 
 * Split text with csv_explode($text, $d2, ...) into columns. Split column at first $d1 (=) into key value.
 * If key is not found use autoincrement counter (0, 1, ... ). If key already exists use key.N (N = 1, 2, ...).
 * If value starts with "@N" use conf[@@N]="sd1","sd2" and set value = csv2kv(value, sd1, sd2).
 * Default values are [@@1="",","], [@@2=$d1,$d2] and [@@3="=","|:|". All keys and values are trimmed. 
 * Use Quote character ["] to preserve whitespace and delimiter. Use double quote [""] to escape ["]. 
 * If $d1 is empty return array (use $d2 as delimiter). If text is empty return empty array. 
 * Unescape entity($d2).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function csv2kv(?string $text, string $d1 = '=', string $d2 = HASH_DELIMITER, array $ikv = []) : array {
	$ld1 = mb_strlen($d1);

	if (empty($text)) {
		return [];
	}

	$e_d2 = entity($d2);
	$has_entity = mb_strpos($text, $e_d2) !== false;

	if ($ld1 == 0 || mb_strpos($text, $d1) === false) {
		$text = trim($text);
		$res = [ $text ];

		if (mb_strlen($d2) > 0 && mb_strpos($text, $d2) !== false) { 
			$res = csv_explode($text, $d2, '"', 4);
		}
		else if (mb_substr($text, 0, 1) == '"' && mb_substr($text, -1) == '"') {
			$res = [ mb_substr($text, 1, -1) ];
		}

		if ($has_entity) {
			foreach ($res as $key => $value) {
				$res[$key] = str_replace($e_d2, $d2, $value);
			}
		}

		// \rkphplib\lib\log_debug("csv2kv:55> text=[$text] d1=[$d1] d2=[$d2] res: ".print_r($res, true));
		return $res;
	}

	$tmp = csv_explode($text, $d2, '"', 15);
	$kv = array();
	$kn = array();
	$n = 0;

	foreach ($tmp as $line) {
		if (($pos = mb_strpos($line, $d1)) > 0) {
			$key = trim(mb_substr($line, 0, $pos));
			$value = trim(mb_substr($line, $pos + $ld1));

			if (mb_substr($key, 0, 1) != '@') {
				$value = str_replace('""', '"', $value);

				if (isset($kv[$key])) {
					if (!isset($kn[$key])) {
						$kn[$key] = 0;
					}

					$kn[$key]++;
					$key .= '.'.$kn[$key];
				}
			}
		}
		else {
			$key = $n;
			$value = $line;
			$n++;
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
				$kv[$key] = csv2kv(trim($match[2]), $ikv[$sf][0], $ikv[$sf][1], $ikv);
			}
		}
		else if (mb_substr($key, 0, 2) == '@@') {
			if (mb_substr($value, 0, 1) == '"' && mb_substr($value, -1) == '"') {
				$ikv[mb_substr($key, 1)] = explode('","', mb_substr($value, 1, -1));
			}
		}
		else {
			if (mb_substr($value, 0, 1) == '"' && mb_substr($value, -1) == '"') {
				$value = mb_substr($value, 1, -1);
			}

			$kv[$key] = $value;
		}
	}

	if ($has_entity) {
		foreach ($kv as $key => $value) {
			$kv[$key] = str_replace($e_d2, $d2, $value);
		}
	}

	return $kv;
}

