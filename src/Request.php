<?php

namespace rkphplib;

/**
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Request {

/**
 * Flags (default = 3 = trim + escape):
 * 2^0: trim
 * 2^1: escape
 * 2^2: Exception if error (otherwise return null)
 * 2^4: no tag
 * 2^5: no bracket
 */
public static function get(string $key, int $flag = 3) : ?string {
	if (!isset($_REQUEST[$key]) || $_REQUEST[$key] == '') {
		return '';
	}

	$value = $_REQUEST[$key];

	if ($flag & 1) {
		$value = trim($value);
	}

	if (($flag & 8) && preg_match('/<.+>/s', $value)) {
		$value = null;	
	}
	else if (($flag & 16) && preg_match('/\{.+\}/s', $value)) {
		$value = null;
	}

	if (($flag & 4) && is_null($value)) {
		throw new \Exception('invalid request value in '.$key);
	}

	if (!is_null($value) && ($flag & 2)) {
		$value = htmlspecialchars(strip_tags($_REQUEST[$key]));
	}

	return $value;
}


/**
 *
 */
public static function checkEmail(string $email) : bool {
	if (preg_match('/^[a-z0-9_\.\-]+@([a-z0-9\-]+\.)+[a-z]{2,}$/i', $email)) {
		return true;
	}

	$ip4 = '\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\]';
	$rx = '/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@(('.$ip4.
		')|(([A-Z\-0-9]+\.)+[A-Z]{2,}))$/i';

	if (preg_match($rx, $email)) {
		return true;
	}

	if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
		return false;
	}

	$tmp = explode('@', $email);
	$ascii_email = idn_to_ascii($tmp[0]).'@'.idn_to_ascii($tmp[1]);
	return preg_match($rx, $ascii_email);
}


/**
 *
 */
public static function checkEmailPrefix(string $email) : bool {
	return self::checkEmail($email.'@test.de');
}


/**
 * @code Request::check([ 'firstname', 'email:email', 'phone?:phone', 'az:/[a-z]/' ]);
 */
public static function check(array $checklist, int $get_flag = 3) : bool {
	foreach ($checklist as $key) {
		$required = true;
		$error = false;
		$rx = '';

		if (strpos($key, ':')) {
			list ($key, $rx) = explode(':', $key, 2);

			if (substr($key, -1) == '?') {
				$key = substr($key, 0, -1);
				$required = false;
			}

		  if (mb_substr($rx, 0, 1) != '/' && mb_substr($rx, -1) != '/') {
				$rx = self::getMatch($rx);
			}
		}

		$value = self::get($key, $get_flag);

		if (is_null($value) || ($required && $value == '')) {
			$error = true;
		}
		else if ($rx && substr($rx, 0, 5) == 'check') {
			$error = self::$rx($value);
		}
		else if ($rx) {
			$error = !preg_match($rx, $value);
		}

		if ($error) {
			return false;
		}
	}

	return true;
}


/**
 * Return match pattern or 'check$name' if self::check$name($value)
 * should be used. Names:
 *
 * Required, Int(eger), UInt, PInt(>0), Real, UReal, Email, EmailPrefix, HTTP, HTTPS, 
 * Phone, PhoneNumber (=Phone), Variable, PLZ
 */
public static function getMatch(string $name) : string {
	$rx = array(
		'Required' => '/^.+$/',
		'Bool' => '/^(1|0|)$/',
		'Boolean' => '/^(1|0|)$/',
		'Int' => '/^\-?[0-9]*$/',
		'UInt' => '/^[0-9]*$/',
		'PInt' => '/^[1-9][0-9]*$/',
		'Integer' => '/^\-?[0-9]*$/',
		'Real' => '/^\-?([0-9]+|[0-9]+\.[0-9]+)$/',
		'UReal' => '/^([0-9]+|[0-9]+\.[0-9]+)$/',
		'Email' => 'checkEmail',
		'EmailPrefix' => 'checkEmailPrefix',
		'HTTP' => '/^http\:\/\//i',
		'HTTPS' => '/^https\:\/\//i',
		'Mobile' => '/^\+[0-9]+$/',
		'Phone' => '/^[\+0-9\(\)\/ \.\-]+$/i',
		'PhoneNumber' => '/^[\+0-9\(\)\/ \.\-]+$/i',
		'Variable' => '/^[0-9A-Z_]+$/i',
		'PLZ' => '/^[0-9]{4,5}$/', // ch,at,be=4stellig
		'dePLZ' => '/^[0-9]{5}$/',
		'deDate' => '/^[0-9]{2}\.[0-9]{2}\.[12][0-9]{3}$/',
		'deDateTime' => '/^[0-9]{2}\.[0-9]{2}\.[12][0-9]{3} [0-9]{2}:[0-9]{2}(:[0-9]{2})?$/',
		'sqlDate' => '/^[1-2][0-9]{3}\-[0-9]{2}\-[0-9]{2}$/',
		'sqlDateTime' => '/^[1-2][0-9]{3}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}(:[0-9]{2})?$/',
		'BIC' => '/^[A-Z]{6}[0-9A-Z]{2}([0-9A-Z]{3})?$/i',
		'IBAN' => '/^[A-Z]{2}[0-9]{2}(?:[ ]?[0-9]{4}){4}(?!(?:[ ]?[0-9]){3})(?:[ ]?[0-9]{1,2})?/'
	);

	if (!isset($rx[$name])) {
		throw new \Exception('invalid regular expression check '.$name);
	}
 
	return $rx[$name];
}

}

