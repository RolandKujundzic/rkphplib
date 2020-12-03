<?php

namespace rkphplib\lib;

require_once __DIR__.'/dec2n.php';


/**
 * Create base64 uuid instead of hex (len [16,36]).
 * Use ordered=2 to prepend hex62(date(YmdHis).usec[3]) and
 * Use valid_uuid64() to validate uuid64(N, 2).
 * Use ordered=1 to prepend microtime.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @see https://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
 */
function uuid64($len = 16, $ordered = 0) {
	if ($len < 16 || $len > 36) {
		throw new \Exception("invalid uuid64 length $len use [16,36]"); 
	}

	if (function_exists('random_bytes')) {
		$data = random_bytes(32);
	}
	else if (function_exists('openssl_random_pseudo_bytes')) {
		$data = openssl_random_pseudo_bytes(32);
	}
	else {
		$data = file_get_contents('/dev/urandom', NULL, NULL, 0, 32);
	}

	if ($ordered == 2) {
		$data = str_replace([ '+', '/', '=' ], [ '', '', '' ], base64_encode($data));
		list($usec, $sec) = explode(' ', microtime());
		$hex62_ymd = dec2n(date('Ymd'), 62);
		$hex62_hisu = dec2n(date('His').substr($usec, 2, 3), 62);
		$data = $hex62_ymd.$hex62_hisu.$data;
	}
	else {
		if ($ordered == 1) {
			$hts = dechex($sec).dechex(substr($usec, 2));

			if (strlen($hts) % 2 == 1) {
				$hts .= '0';
			}
	
			$hts = hex2bin($hts).$data;
		}

		$data = str_replace([ '+', '/', '=' ], [ '', '', '' ], base64_encode($data));
	}

	return substr($data, 0, $len);
}


/**
 * Return true if uuid64 is valid (created with uuid64(N, 2))
 */
function valid_uuid64(string $uuid64) : bool {
	$hex62_ymd = substr($uuid64, 0, 5);	
	return strpos(\rkphplib\lib\n2dec($hex62_ymd, 62), date('Ymd', time())) === 0;	
}

