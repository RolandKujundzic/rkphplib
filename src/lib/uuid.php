<?php

namespace rkphplib\lib;

/**
 * Create uuid. Default ($type = '') is hhhh-hhhh-hhhh-hhhh (h = [0,f]). 
 * Use $type = 'rfc4122' to create rfc4122 compliant uuid.
 * Use $type = '16|20|32' to create hhh...h (16|20|32 hex digits).
 * Use o20|o32 for ordered uuid (first 15 hex char = microtime) with 20 or 32 hex characters.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @see https://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
 */
function uuid($type = '') {

	if (function_exists('random_bytes')) {
		$data = random_bytes(16);
	}
	else if (function_exists('openssl_random_pseudo_bytes')) {
		$data = openssl_random_pseudo_bytes(16);
	}
	else {
		$data = file_get_contents('/dev/urandom', NULL, NULL, 0, 16);
	}

	if (substr($type, 0, 1) == 'o') {
		list($usec, $sec) = explode(' ', microtime());
		$data = dechex($sec).dechex(substr($usec, 2)).$data;
		$type = substr($type, 1);
	}

	if ($type == 'rfc4122') {
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
		$res = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	else if ($type == '16') {
		$res = substr(bin2hex($data), 0, 16);
	}
	else if ($type == '20') {
		$res = substr(bin2hex($data), 0, 20);
	}
	else if ($type == '32') {
		$res = substr(bin2hex($data), 0, 32);
	}
	else {
		$res = vsprintf('%s-%s-%s-%s', str_split(bin2hex($data), 4));
	}

	return $res;
}

