<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');


/**
 * En/Decryption helper class.
 * 
 * If mcrypt_r256 is not supported install php5-mcrypt:
 * sudo apt-get install -y php5-mcrypt; sudo php5enmod mcrypt; sudo service apache2 restart
 *
 * Method aes256cbc only works if PHP version >= 5.6.
 *
 * @author Roland Kujundzic <roland@kujundzic.de> 
 *
 */
class CryptoHelper {

/** @var map $_opt default options */
private $_opt = array('secret' => '', 'enc' => 'xor', 'wrap' => 'urlenc_base64', 'xor_secret' => '');


/**
 * Constructor. Options:
 * 
 *  secret: set secret key (required)
 *  enc: en/decrypt method - xor (default) | ord | aes256cbc | mcrypt_r256  
 *  wrap: urlenc_base64 (default) | safe64 | no 
 *  xor_secret: obfuscate with xor first if set
 */
public function __construct(array $opt = array()) {

	foreach ($opt as $key => $value) {
		$this->_opt[$key] = $value;
	}

	if (strlen($this->_opt['secret']) < 4) {
		throw new Exception('Secret key is too short (use at lease 4 characters)');
	}

	$method_list = [ 'enc_'.$this->_opt['enc'], 'dec_'.$this->_opt['enc'], 'wrap_'.$this->_opt['wrap'], 'unwrap_'.$this->_opt['wrap'] ];
	foreach ($method_list as $m) {
		if (!method_exists($this, $m)) {
			throw new Exception('Invalid configuration', "no such method: $m");
		}
	}

	throw new Exception('yalla', 'yuck');
}


/**
 * Return encrypted (@see $this->_opt[enc] and $this->_opt[secret]) and wrapped (@see $this->_opt[wrap]) data.
 * If $data (string|map) is map and keys is vector use map2str($data, $keys).
 */
public function encode($data, ?array $keys = null) : string {

	if (is_array($data) && count($keys) > 1) {
		$data = $this->map2str($data, $keys);
	}

	$method = 'enc_'.$this->_opt['enc'];
	$wrap = 'wrap_'.$this->_opt['wrap'];

	if ($this->_opt['xor_secret']) {
		$data = self::enc_sxor($data, $this->_opt['xor_secret']);
	}

	return self::$wrap(self::$method($data, $this->_opt['secret']));
}


/**
 * Return rawurlencode(base64_encode($str)).
 */
public static function wrap_urlenc_base64(string $str) : string {
	return rawurlencode(base64_encode($str));
}


/**
 * Return url safe base64 encoding.
 */
public static function wrap_safe64(string $str) : string {
	$str = base64_encode($str);
	$str = str_replace(array('+','/','='), array('-','_',''), $str);
	return trim($str);
}


/**
 * Return unmodified string.
 */
public static function wrap_no(string $str) : string {
	return $str;
}


/**
 * Return decoded safe64.
 */
public static function unwrap_safe64(string $str) : string {
	$str = str_replace(array('-','_'), array('+','/'), $str);
	$mod4 = strlen($str) % 4;
	if ($mod4) {
		$str .= substr('====', $mod4);
	}

	return base64_decode($str);
}
	

/**
 * Return unmodified string.
 */
public static function unwrap_no(string $str) : string {
	return $str;
}


/**
 * Return base64_decode(rawurldecode($str)).
 */
public static function unwrap_urlenc_base64(string $str) : string {
	return base64_decode(rawurldecode($str));
}


/**
 * Return mcrypted text.
 */
public static function enc_mcrypt_r256(string $text, string $secret) : string {
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	return mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $secret, $text, MCRYPT_MODE_ECB, $iv);
}

 
/**
 * Return simple xor de/encoded string.
 */
public static function enc_sxor(string $text, string $secret) : string {
	$tlen = strlen($text);
	$slen = strlen($secret);

	for ($i = 0, $k = 0; $i < $tlen; $i++) {
		if ($k === $slen - 1) {
			$k = 0;
		}

		$text[$i] = chr(ord($text[$i]) ^ ord($secret[$k]));
	}

	return $text;
}


/**
 * Return multiple xor encoded string.
 */
public static function enc_xor(string $text, string $secret) : string {
	$slen = strlen($secret);

	for ($i = 0; $i < strlen($text); $i++) {
		for ($j = 0; $j < $slen; $j++) {
			$text[$i] = chr(ord($text[$i]) ^ ord($secret[$j]));
		}
	}

	return $text;
}


/**
 * Return ord(char) + ord(secret) encoded string.
 */
public static function enc_ord(string $text, string $secret) : string {
	$result = '';
	$sl = strlen($secret);

	for ($i = 0; $i < strlen($text); $i++) {
		$char = $text[$i];
		$keychar = substr($secret, ($i % $sl) - 1, 1);
		$result .= chr(ord($char) + ord($keychar));
	}

	return $result;
}


/**
 * Return (string|hash) decoded data.
 */
public function decode(string $data, ?array $keys = null) {
	$method = 'dec_'.$this->_opt['enc'];
	$unwrap = 'unwrap_'.$this->_opt['wrap'];

	$res = self::$method(self::$unwrap($data), $this->_opt['secret']);

	if ($this->_opt['xor_secret']) {
		$res = self::enc_sxor($res, $this->_opt['xor_secret']);
	}

	if (count($keys) > 0) {
		$res = self::str2map($res, $keys);
	}

	return $res;
}


/**
 * Decode self::enc_mcrypt_r256 text.
 */
public static function dec_mcrypt_r256(string $text, string $secret) : string {
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $secret, $text, MCRYPT_MODE_ECB, $iv));
}


/**
 * Decode self::enc_xor text.
 */
public static function dec_xor(string $text, string $secret) : string {
	$slen = strlen($secret);
	for ($i = 0; $i < strlen($text); $i++) {
		for ($j = 0; $j < $slen; $j++) {
			$text[$i] = chr(ord($text[$i]) ^ ord($secret[$j]));
		}
	}

	return $text;
}


/**
 * Decode self::enc_ord string.
 */
public static function dec_ord(string $text, string $secret) : string {
	$sl = strlen($secret);
	$result = '';

	for ($i = 0; $i < strlen($text); $i++) {
		$char = $text[$i];
		$keychar = substr($secret, ($i % $sl) - 1, 1);
		$char = chr(ord($char) - ord($keychar));
		$result .= $char;
	}

	return $result;
}


/**
 * Join values from $hash according to $keys with "|".
 *
 * Example: "hash[keys[0]]|hash[keys[0]]|..."
 */
public static function map2str(array $hash, array $keys) : string {
	$tmp = array();	

	foreach($keys as $key) {
		$val = empty($hash[$key]) ? '' : $hash[$key];
		array_push($tmp, $val);
	}

	return join('|', $tmp);
}


/**
 * Split self::map2str text back to map.
 */
public static function str2map(string $text, array $keys) : array {
	$tmp = explode('|', $text);
	$result = array();
	$n = 0;

	foreach($keys as $key) {
		$result[$key] = $tmp[$n];
		$n++;
	}

	return $result;
}


/**
 * Split secret master key into encryption and authentication keys.
 * Return vector[2] enc+auth keys.
 */
public static function splitSecret(string $secret) : array {
	return [
		hash_hmac('sha256', 'encryption', $secret, true),
		hash_hmac('sha256', 'authentication', $secret, true)
	];
}


/**
 * Encrypt with Openssl AES (aes-256-cbc).
 */
public static function enc_aes256cbc(string $text, string $secret) : string {
	list($encKey, $authKey) = self::splitSecret($secret);

	$ivsize = openssl_cipher_iv_length('aes-256-cbc');
	$iv = openssl_random_pseudo_bytes($ivsize);

	$ciphertext = openssl_encrypt($text, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
	$mac = hash_hmac('sha256', $iv.$ciphertext, $authKey, true);
	return $mac.$iv.$ciphertext;
}


/**
 * Decrypt self::enc_aes256cbc.
 */
public static function dec_aes256cbc(string $text, string $secret) : string {
	list($encKey, $authKey) = self::splitSecret($secret);

	$ivsize = openssl_cipher_iv_length('aes-256-cbc');
	$mac = mb_substr($text, 0, 32, '8bit');
	$iv = mb_substr($text, 32, $ivsize, '8bit');
	$ciphertext = mb_substr($text, 32 + $ivsize, null, '8bit');

	// Very important: Verify MAC before decrypting
	$calc = hash_hmac('sha256', $iv.$ciphertext, $authKey, true);
	if (!hash_equals($mac, $calc)) {
		throw new Exception('MAC Validation failed');
	}
        
	return openssl_decrypt($ciphertext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
}


}


