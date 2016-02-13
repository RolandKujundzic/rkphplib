<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;


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

	/** @var string $secret secret key */
	protected $secret = '';

	/** @var string $enc = 'xor' - encoding/crypt method */
	protected $enc = 'xor';

	/** @var string $wrap = urlenc_base64 = urlencode(base64_encode(...)) */
	protected $wrap = 'urlenc_base64';


	/**
	 * Constructor. Options:
	 * 
	 *  secret: set secret key
	 *  enc: xor|ord|aes256cbc|mcrypt_r256 
	 *  wrap: urlenc_base64|safe64|no 
	 */
	public function __construct($opt = array()) {

		if (!empty($opt['secret'])) {
			$this->secret = $opt['secret'];
		}

		if (!empty($opt['enc'])) {
			$this->enc = $opt['enc'];
		}

		if (!empty($opt['wrap'])) {
			$this->wrap = $opt['wrap'];
		}

		if (strlen($this->secret) < 4) {
			throw new Exception('Secret key is too short (use at lease 4 characters)');
		}

		$method_list = [ 'enc_'.$this->enc, 'dec_'.$this->enc, 'wrap_'.$this->wrap, 'unwrap_'.$this->wrap ];
		foreach ($method_list as $m) {
			if (!method_exists($this, $m)) {
				throw new Exception('Invalid configuration', "no such method: $m");
			}
		}
	}


	/**
	 * Return encrypted (@see $this->enc and $this->secret) and wrapped (@see $this->wrap) data.
	 * If $data is map and keys is vector use map2str($data, $keys).
	 *
	 * @param string|map $data
	 * @param vector $keys (default = null)
	 * @return string
	 */
	public function encode($data, $keys = null) {

		if (is_array($data) && count($keys) > 1) {
			$data = $this->map2str($data, $keys);
		}

		$method = 'enc_'.$this->enc;
		$wrap = 'wrap_'.$this->wrap;
		return self::$wrap(self::$method($data, $this->secret));
	}


	/**
	 * Return urlencode(base64_encode($str)).
	 *
	 * @param string $str
	 * @return string
	 */
	public static function wrap_urlenc_base64($str) {
		return urlencode(base64_encode($str));
	}


	/**
	 * Return url safe base64 encoding.
	 *
	 * @param string $str
	 * @return string 
	 */
	public static function wrap_safe64($str) {
		$str = base64_encode($str);
		$str = str_replace(array('+','/','='), array('-','_',''), $str);
		return trim($str);
	}


	/**
	 * Return unmodified string.
	 *
	 * @param string $str
	 * @return string
	 */
	public static function wrap_no($str) {
		return $str;
	}


	/**
	 * Return decoded safe64.
	 *
	 * @param string $str
	 * @return string 
	 */
	public static function unwrap_safe64($str) {
		$str = str_replace(array('-','_'), array('+','/'), $str);
		$mod4 = strlen($str) % 4;

		if ($mod4) {
			$str .= substr('====', $mod4);
		}

		return base64_decode($str);
	}
	

	/**
	 * Return unmodified string.
	 *
	 * @param string $str
	 * @return string
	 */
	public static function unwrap_no($str) {
		return $str;
	}


	/**
	 * Return base64_decode(urldecode($str)).
	 *
	 * @param string $str
	 * @return string
	 */
	public static function unwrap_urlenc_base64($str) {
		return base64_decode(urldecode($str));
	}


	/**
	 * Return mcrypted text.
	 * 
	 * @param string $text
	 * @param string $secret
	 * @return string
	 */
	public static function enc_mcrypt_r256($text, $secret) { 
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		return mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $secret, $text, MCRYPT_MODE_ECB, $iv);
	}
 

	/**
	 * Return multiple xor encoded string.
	 *
	 * @param string $text
	 * @param string $secret
	 * @return string
	 */
	public static function enc_xor($text, $secret) {
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
	 *
	 * @param string $text
	 * @param string $secret
	 * @return string
	 */
	public static function enc_ord($text, $secret) {
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
	 * Decode data.
	 *
	 * @param string $data
	 * @param array $keys (default = null)
	 * @return string|map
	 */
	public function decode($data, $keys = null) {
		$method = 'dec_'.$this->enc;
		$unwrap = 'unwrap_'.$this->wrap;

		$res = self::$method(self::$unwrap($data), $this->secret);

		if (count($keys) > 0) {
			$res = self::str2map($res, $keys);
		}

		return $res;
	}


	/**
	 * Decode self::enc_mcrypt_r256 text.
	 * 
	 * @param string $text
	 * @param string $secret
	 * @return string
	 */
	public static function dec_mcrypt_r256($text, $secret) {
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $secret, $text, MCRYPT_MODE_ECB, $iv));
	}


	/**
	 * Decode self::enc_xor text.
	 *
	 * @param string $text
	 * @param string $secret
	 * @return string
	 */
	public static function dec_xor($text, $secret) {
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
	 * 
	 * @param string $text
	 * @param string $secret
	 * @return string
	 */
	public static function dec_ord($text, $secret) {
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
	 *
	 * @param map $hash
	 * @param vector $keys
	 * @return string
	 */
	public static function map2str($hash, $keys) {
		$tmp = array();	

		foreach($keys as $key) {
			$val = empty($hash[$key]) ? '' : $hash[$key];
			array_push($tmp, $val);
		}

		return join('|', $tmp);
	}


	/**
	 * Split self::map2str text back to map.
	 * 
	 * @param string $text
	 * @param vector $keys
	 * @return map
	 */
	public static function str2map($text, $keys) {
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
	 *
	 * @param string $secret
	 * @return vector[2] enc+auth keys
	 */
	public static function splitSecret($secret) {
		return [
			hash_hmac('sha256', 'encryption', $masterKey, true),
			hash_hmac('sha256', 'authentication', $masterKey, true)
		];
	}


	/**
	 * Encrypt with Openssl AES (aes-256-cbc).
	 * 
	 * @param string $text
	 * @param string $secret
	 * @return string 
	 */
	public static function enc_aes256cbc($text, $secret) {
		list($encKey, $authKey) = self::splitSecret($secret);

		$ivsize = openssl_cipher_iv_length('aes-256-cbc');
		$iv = openssl_random_pseudo_bytes($ivsize);

		$ciphertext = openssl_encrypt($text, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
		$mac = hash_hmac('sha256', $iv.$ciphertext, $authkey, true);
		return $mac.$iv.$ciphertext;
	}


	/**
	 * Decrypt self::enc_aes256cbc.
	 *
	 * @param string $text
	 * @param string $secret
	 */
	public static function dec_aes256cbc($text, $secret) {
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
        
		return openssl_decrypt($ciphertext, 'aes-256-cbc', $secret, OPENSSL_RAW_DATA, $iv);
	}


}


