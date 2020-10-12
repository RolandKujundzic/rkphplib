<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/lib/array_join.php';
require_once __DIR__.'/lib/split_str.php';

use function rkphplib\lib\array_join;
use function rkphplib\lib\split_str;


/**
 * XOR En/Decryption.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de> 
 * @copyright 2020 Roland Kujundzic
 */
class XCrypt {

// @var string $secret
private $secret = '';


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
 * Return encoded string.
 */
public function encodeArray(array $kv) : string {
	$text = array_join('|', $kv);
	return strtr(base64_encode(self::sxor($text, $this->secret)), '+/=', '._-');
}


/**
 * Return decoded string.
 */
public function decodeArray(string $text, array $keys = []) : array {
	$atext = self::sxor(base64_decode(strtr($text, '._-', '+/=')), $this->secret);

	$tmp = split_str('|', $atext);
	$res = [];

	for ($i = 0; $i < count($tmp); $i++) {
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


