<?php

namespace rkphplib\lib;


/**
 * Prefix/Suffix free replace of $a in $txt with $b. Replace is only executed 
 * if previous/next character is start|end or in [ .,;-:!?§$€)(][}{><|"/\\&#`´+-*=~^%\'\r\n\t].
 * Use $a as search, $b as replace and $txt as text string.
 */
function replace_str(string $a, string $b, string $txt) : string {
	$len_txt = mb_strlen($txt);
	$len_a = mb_strlen($a);
	$len_b = mb_strlen($b);
	$split = ' .,;-:!?§$€)(][}{><|"/\\&#`´+-*=~^%'."'\r\n\t";
	$start = 0;

	while (($pos = mb_strpos($txt, $a, $start)) !== false) {
		$c_prev = ($pos > 0) ?  mb_substr($txt, $pos - 1, 1) : false;
		$c_next = ($pos + $len_a < $len_txt - 1) ?  mb_substr($txt, $pos + $len_a, 1) : false;

		if ($c_prev && mb_strpos($split, $c_prev) === false) {
			$start += $len_a;
		}
		else if ($c_next && mb_strpos($split, $c_next) === false) {
			$start += $len_a + 1;
		}
		else {
			$txt = mb_substr($txt, 0, $pos).$b.mb_substr($txt, $pos + $len_a);
			$start += $len_b;
		}
	}

	return $txt;
}

