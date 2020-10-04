<?php

namespace rkphplib\lib;

/**
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 * dec2n(NUMBER, BASE) = TEXT
 * n2dec(TEXT, BASE) = NUMBER (inverse of dec2n)
 * smd5(TEXT) = TEXT (short md5(TEXT))
 * smd5(smd5(TEXT), true) = md5(TEXT)
 */

/**
 * Convert decimal to base len (default = 36, len in [2,65]).
 * If len=36 use 0-9a-zA-Z, if len=10 use 0-9 (decimal), if len=16 use 0-9a-f (hexadecimal).
 * If len=62 use 0-9a-zA-Z. Maximum len is 65 (add -_.).
 * @example dec2n(65535, 16) == 'ffff'
 */
function dec2n(int $n, int $len = 36) : string {
	$a = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b',
		'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
		'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C',
		'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
		'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '-', '_', '.' ];

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
 * Convert base len (default = 36, len in [2,65]) to decimal (inverse of dec2n).
 * @example n2dec('ffff', 16) == 65535
 * @beware ffff-ffff-ffff-ffff > PHP_INT_MAX
 */
function n2dec(string $s, int $len = 36) : int {
	$a = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b',
		'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
		'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C',
		'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
		'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '-', '_', '.' ];

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


/**
 * Short md5. Return 32-bit hex md5($txt) with 64-bit base (. delimited).
 * @example smd5(smd5('Hello'), true) == md5('Hello') 
 */
function smd5(string $txt, bool $reverse = false) : string {
	if ($reverse) {
		$x = explode('.', $txt);
		$pad = [ 12, 12, 8 ];
		$md5 = '';

		for ($i = 0; $i < count($x); $i++) {
			$md5 .= str_pad(dec2n(n2dec($x[$i], 64), 16), $pad[$i], '0', STR_PAD_LEFT);
		}

		return $md5;
	}

	$md5 = md5($txt);
	$res = dec2n(n2dec(substr($md5, 0, 12), 16), 64);
	$res .= '.'.dec2n(n2dec(substr($md5, 12, 12), 16), 64);
	return $res.'.'.dec2n(n2dec(substr($md5, 24), 16), 64);
}

