<?php

namespace rkphplib\lib;

/**
 * Create base64 uuid instead of hex (len [16,36]). 
 * If ordered it true prepend microtime.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @see https://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
 */
function uuid64($len = 16, $ordered = false) {
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

	if ($ordered) {
		list($usec, $sec) = explode(' ', microtime());
		$hts = dechex($sec).dechex(substr($usec, 2));

		if (strlen($hts) % 2 == 1) {
			$hts .= '0';
		}

		$data = hex2bin($hts).$data;
	}

	$data = str_replace([ '+', '/', '=' ], [ '', '', '' ], base64_encode($data));
	return substr($data, 0, $len);
}

