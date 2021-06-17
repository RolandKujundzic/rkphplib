<?php

namespace rkphplib;

require_once __DIR__.'/Exception.php';
require_once __DIR__.'/lib/array_join.php';
require_once __DIR__.'/lib/split_str.php';

use function rkphplib\lib\array_join;
use function rkphplib\lib\split_str;


/**
 * XOR En/Decryption.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de> 
 */
class XCrypt {

// @var string $secret
private $secret = '';


/**
 * @global SETTINGS_XCRYPT_SECRET SETTINGS_XCRYPT_RKEY
 */
public static function updateRequest() : void {
	if (empty($_REQUEST[SETTINGS_XCRYPT_RKEY])) {
		// \rkphplib\lib\log_debug("XCrypt::updateRequest:29> empty _REQUEST.".SETTINGS_XCRYPT_RKEY);
		return;
	}

	$xcr = new XCrypt(SETTINGS_XCRYPT_SECRET);
	$req = $xcr->decodeArray($_REQUEST[SETTINGS_XCRYPT_RKEY]);
	// \rkphplib\lib\log_debug([ "XCrypt::updateRequest:35> <1>", $req ]);
	$_REQUEST = array_merge($_REQUEST, $req);
}


/**
 *
 */
public function __construct($secret = 'secret') {
	$this->secret = $secret;
}


/**
 * Return encoded string.
 */
public function encode(string $text) : string {
	return strtr(base64_encode(self::sxor($text, $this->secret)), '+/=', '._-');
}


/**
 * Return decoded string
 */
public function decode(string $text) : string {
	return self::sxor(base64_decode(strtr($text, '._-', '+/=')), $this->secret);
}


/**
 * Return encoded string (value1|value2|...). If $append_keys is true
 * encode value1|...|valueN|key1|...|keyN|=N.
 */
public function encodeArray(array $kv, bool $append_keys = false) : string {
	$text = array_join('|', $kv);

	if ($append_keys) {
		$keys = array_keys($kv);
		sort($keys);
		$text .= '|'.join('|', $keys).'|='.count($keys);
	}

	return strtr(base64_encode(self::sxor($text, $this->secret)), '+/=', '._-');
}


/**
 * Return decoded string.
 */
public function decodeArray(string $text, array $keys = []) : array {
	$atext = self::sxor(base64_decode(strtr($text, '._-', '+/=')), $this->secret);

	$tmp = split_str('|', $atext);
	$res = [];

	$tlen = count($tmp);
	$klen = ($tlen - 1) / 2;
	if (count($keys) == 0 && $tmp[$tlen-1] == '='.$klen) {
		$keys = array_slice($tmp, $klen, $klen);
		$tlen = $klen;
	}

	for ($i = 0; $i < $tlen; $i++) {
		$key = isset($keys[$i]) ? $keys[$i] : $i;
		$res[$key] = $tmp[$i];
	}

	return $res;
}


/**
 * Return simple xor encoded string.
 * @example enc_sxor(enc_sxor('test')) == 'test'
 */
public static function sxor(string $text, string $secret) : string {
	$tlen = strlen($text);
	$slen = strlen($secret);

	for ($i = 0, $k = 0; $i < $tlen; $i++, $k++) {
		$k = $k % $slen;
		$text[$i] = chr(ord($text[$i]) ^ ord($secret[$k]));
	}

	return $text;
}


}


