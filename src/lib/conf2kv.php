<?php

namespace rkphplib\lib;

require_once(__DIR__.'/csv_explode.php');


/**
 * Split text into key value hash. 
 * 
 * Keys must not start with "@@" or "@_". 
 * Split text at $d2 (|#|) into lines. Split lines at first $d1 (=) into key value.
 * If key is not found return $text or use "@_N" as key (N is autoincrement 1, 2, ...) if mulitple keys are missing.
 * If key already exists rename to key.N (N is autoincrement 1, 2, ...).
 * If value starts with "@N" use conf[@@N]="sd1","sd2" and set value = conf2kv(value, sd1, sd2).
 * Default values are [@@1="",","] and [@@2=$d1,$d2]. 
 * All keys and values are trimmed. Use Quote character ["] to preserve whitespace and delimiter.
 * Use double quote [""] to escape ["]. If $d1 is empty return array with $d2 as delimiter.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string $text
 * @param string $d1 (default is "=")
 * @param string $d2 (default is "|#|")
 * @param map ikv (config hash - default [ ])
 * @return string|array|hash
 */
function conf2kv($text, $d1 = '=', $d2 = '|#|', $ikv = array()) {
	$ld1 = mb_strlen($d1);

	if ($ld1 == 0 || mb_strpos($text, $d1) === false) {
		$res = $text;

		if (mb_strlen($d2) > 0 && mb_strpos($text, $d2) !== false) { 
			$res = csv_explode($text, $d2, '"', 4);
		}
		else if (mb_substr($res, 0, 1) == '"' && mb_substr($res, -1) == '"'){
			$res = mb_substr($res, 1, -1);
		}

		return $res;
	}

	$tmp = csv_explode($text, $d2, '"', 15);
	$kv = array();
	$kn = array();
	$n = 1;

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
			$key = '@_'.$n;
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
			}

			if (isset($ikv[$sf])) {
				$kv[$key] = conf2kv(trim($match[2]), $ikv[$sf][0], $ikv[$sf][1], $ikv);
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

	if ($n == 2 && count($kv) == 1 && isset($kv['@_1'])) {
		return $kv['@_1'];
	}

	return $kv;
}

