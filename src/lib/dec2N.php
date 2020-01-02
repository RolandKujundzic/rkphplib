<?php

namespace rkphplib\lib;


/**
 * Convert decimal to base len (default = 36, len in [2,62]).
 * If len=36 use 0-9a-zA-Z, if len=10 use 0-9 (decimal), if len=16 use 0-9a-f (hexadecimal).
 * If len=62 use 0-9a-zA-Z.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function dec2n(int $n, int $len = 36) : string {
	$a = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b',
		'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
		'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C',
		'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
		'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z' ];

	$s = '';
	$m = 0;

	while ($n > 0) {
		$m = $n % $len;
		$n = intval($n / $len);
		$s = $a[$m].$s;
	}

	return $s;
}                                                                                                                                                        


/**
 * Convert base len (default = 36, len in [2,62]) to decimal (inverse of dec2n).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function n2dec(string $s, int $len = 36) : int {
	$a = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b',
		'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
		'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C',
		'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
		'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z' ];

	$sl = strlen($s);
	$c = '';
	$r = 0;

	for ($i = 0; $i < $sl; $i++) {
		$c = $s[$sl - 1 - $i];
		for ($j = 0; $j < count($a); $j++) {
			if ($c == $a[$j]) {
				$r += $j * pow($len, $i);
				break;
			}
		}
	}

	return $r;
}

