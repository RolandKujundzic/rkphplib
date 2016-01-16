<?php

namespace rkphplib\lib;

define('CSV_IGNORE_EMPTY', 1);
define('CSV_PRESERVE_QUOTE', 2);
define('CSV_TRIM_LINES', 4);
define('CSV_FIX_DQUOTE', 8);


/**
 * Explode csv string into array. Escape delim with quite enclosure. Escape quote with double quote.
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 * @param string $text
 * @param string $delim (default = ",")
 * @param string $quote (default = '"')
 * @param int $mode (default = 0 - 1=CSV_IGNORE_EMPTY, 2=CSV_PRESERVE_QUOTE, 4=CSV_TRIM_LINES, 8=CSV_FIX_DQUOTE)
 * @return array
 */
function csv_explode($text, $delim=',', $quote = '"', $mode = 0) {
	$res = array();
	$n = 0;

	$ignore_empty = $mode & 1;
	$keep_quote = $mode & 2;
	$trim_lines = $mode & 4;
	$fix_dquote = $mode & 8;

	$tmp = explode($quote, $text);
	$tl = count($tmp);

	foreach($tmp as $x) {

		if ($n++ % 2) {
			if (!$keep_quote && $n < $tl - 1 && mb_strlen($tmp[$n]) == 0) {
				$x .= $quote;
			}

			$pq = ($mode & 2) ? $quote : '';
			array_push($res, array_pop($res).$pq.$x.$pq);
		}
		else {
			$tmp2 = explode($delim, $x);
			array_push($res, array_pop($res) . array_shift($tmp2));
			$res = array_merge($res, $tmp2);
		}
	}

	if (!$ignore_empty && !$trim_lines && !$fix_dquote) {
		return $res;
	}

	$out = array();
	for ($i = 0; $i < count($res); $i++) {
		$line = trim($res[$i]);

		if ($fix_dquote && mb_substr($line, 0, 1) == '"' && mb_substr($line, -1) == '"') {
			$res[$i] = str_replace('""', '"', $res[$i]);
			$line = trim($res[$i]);
		}

		if ($ignore_empty && mb_strlen($line) == 0) {
			continue;
		}

		if ($trim_lines) {
			array_push($out, $line);
		}
		else {
			array_push($out, $res[$i]);
		}
	}

	return $out;
}

