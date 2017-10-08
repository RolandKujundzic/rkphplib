<?php

namespace rkphplib;

require_once(__DIR__.'/DateCalc.class.php');
require_once(__DIR__.'/lib/split_str.php');


/**
 * All checks are static methods and return true|false.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class ValueCheck {



/**
 * Run check if value is not empty.
 *
 * @param string $key
 * @param string|callable $value
 * @param string $check
 * @return bool
 */
public static function run($key, $value, $check) {
	$condition = '';

	if (($start = mb_strpos($key, '[')) > 0 && ($end = mb_strrpos($key, ']')) > $start + 1) {
		// e.g. column[table=test] -> key=column condition=[table=test]
		$condition = mb_substr($key, $start + 1, $end - $start - 1);
		$key = mb_substr($key, 0, $start);

		if (preg_match('/^([a-zA-Z0-9\_]+)\=([a-zA-Z0-9\_]+)$/', $condition, $match)) {
			$key2 = $match[1];
			$key2_value = is_callable($value) ? $value($key2) : (isset($_REQUEST[$key2]) ? $_REQUEST[$key2] : '');

			if ($key2_value == $match[2]) {
				$value = is_callable($value) ? $value : (isset($_REQUEST[$key]) ? $_REQUEST[$key] : '');
				return self::run($key, $value, $check);
			}
			else {
				return true;
			}
		}
	}

	if (($start = mb_strpos($key, '.')) > 0) {
		// e.g. email.1, email.2 
		$key = mb_substr(0, $key);
	}

	if (is_callable($value)) {
		$value = $value($key);
	}

	if (strlen($value) == 0) {
		return true;
	}

	if (!is_array($check)) {
		$check = \rkphplib\lib\split_str(':', $check);
	}

	$method = array_shift($check);

	if (!method_exists(__CLASS__, $method)) {
		if (count($check) == 0 && mb_substr($method, 0, 2) == 'is') {
			array_push($check, mb_substr($method, 2)); 
			$method = 'isMatch';
		}
		else {
			throw new Exception('no such check: '.$method, "key=$key value=[$value] check=[".join (':', $check)."]");
		}
	}

	$pn = count($check);
	$res = false;

	if ($pn > 3) {
		$res = self::$method($value, $check);
	}
	else if ($pn == 3) {
		$res = self::$method($value, $check[0], $check[1], $check[2]);
	}
	else if ($pn == 2) {
		$res = self::$method($value, $check[0], $check[1]);
	}
	else if ($pn == 1) {
		$res = self::$method($value, $check[0]);
	}
	else {
		$res = self::$method($value);
	}

	\rkphplib\lib\log_debug("key=[$key] value=[$value] check=[".join(':', $check)."] method=[$method] res=[$res]");

	return $res;
}


/**
 * Return match pattern. Names:
 *
 * Required, Int, UInt, Integer (=UInt), Real, UReal, Email, EmailPrefix, URL, URLPrefix, URLPath, HTTP, HTTPS, 
 * Phone, PhoneNumber (=Phone), Variable, PLZ
 * 
 * @param string
 * @return string (empty if not found)
 */
public static function getMatch($name) {
	$rx = array(
		'Required' => '/^.+$/',
		'Bool' => '/^(1|0|)$/',
		'Boolean' => '/^(1|0|)$/',
		'Int' => '/^\-?[1-9][0-9]*$/',
		'UInt' => '/^[1-9][0-9]*$/',
		'Integer' => '/^[1-9][0-9]*$/',
		'Real' => '/^\-?([0-9]+|[0-9]+\.[0-9]+)$/',
		'UReal' => '/^([0-9]+|[0-9]+\.[0-9]+)$/',
		'Email' => '/^[a-z0-9_\.\-]+@[a-z0-9\.\-]+$/i',
		'EmailPrefix' => '/^[a-z0-9_\.\-]+$/i',
		'URL' => '/^[a-z0-9\-]+\.[a-z0-9\.\-]+$/i',
		'URLPrefix' => '/^[a-z0-9\-\.]+$/i',
		'URLPath' => '/^[a-z0-9\-\.\%\+\_\,\/]+$/i',
		'HTTP' => '/^http\:\/\//i',
		'HTTPS' => '/^https\:\/\//i',
		'Phone' => '/^[\+0-9\(\)\/ \.\-]+$/i',
		'PhoneNumber' => '/^[\+0-9\(\)\/ \.\-]+$/i',
		'Variable' => '/^[0-9A-Z_]+$/i',
		'PLZ' => '/^[0-9]{5}$/');

  return isset($rx[$name]) ? $rx[$name] : '';
}


/**
 * True if value matches regular expression (e.g. /[0-9]/).
 * Slash at start and end of regular expression are optional.
 * @param string $value
 * @param string $rx
 * @return boolean
 */
public static function isMatch($value, $rx) {
  $res = '';

  if (mb_substr($rx, 0, 1) != '/' && mb_substr($rx, -1) != '/') {
		$rx = self::getMatch($rx);
	}
	else {
		$rx = '/'.$rx.'/';
	}

  return preg_match($rx, $value);
}


/**
 * True if CasNr is valid.
 * @param string $value
 * @return boolean
 */
public static function isCasNr($value) {

  if (!preg_match('/^([0-9]+)\-([0-9][0-9])\-([0-9])$/', $value, $match)) {
		return false;
  }

  $z = $match[1].$match[2];
  for ($i = mb_strlen($z) - 1, $j = 1, $sum = 0; $i >= 0; $i--, $j++) {
    $sum += $j * intval($z[$i]);
  }

  if ($sum % 10 != intval($match[3])) {
		return false;
  }

  return true;
}


/**
 * True if UStIdNr is valid.
 * @ToDo http://www.pruefziffernberechnung.de/U/USt-IdNr.shtml (nur de hat momentan pruefziffer check)
 * @param string $value
 * @param string $cc (country code iso2, e.g. de)
 * @return boolean
 */
public static function isUStIdNr($value, $cc) {
	$cc = mb_strtolower(mb_substr($cc, 0, 2));

	$rx = array();
	$rx['at'] = '/^ATU[0-9]{8}$/';
	$rx['be'] = '/^BE0[0-9]{9}$/';
	$rx['bg'] = '/^BG[0-9]{9}[0-9]?$/';
	$rx['cz'] = '/^CZ[0-9]{8,10}+$/';
	$rx['cy'] = '/^CY[0-9A-Z]{9}$/';
	$rx['dk'] = '/^DK[0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2}$/';
	$rx['ee'] = '/^EE[0-9]{9}$/';
	$rx['el'] = '/^EL[0-9]{9}$/';
	$rx['fi'] = '/^FI[0-9]{8}$/';
	$rx['fr'] = '/^FR[A-Z]{2} [0-9]{9}$/';
	$rx['gb'] = '/^GB(\d{3} \d{4} \$d{2}|\d{3} \d{4} \$d{2} \d{3}|GD\d{3}|HA\d{3})$/';
	$rx['hu'] = '/^HU[0-9]{8}$/';
	$rx['ie'] = '/^IE[0-9]S[0-9]{5}L$/';
	$rx['it'] = '/^IT[0-9]{11}$/';
	$rx['lt'] = '/^LT(\d{9}|\d{12})$/';
	$rx['lu'] = '/^LU[0-9]{8}$/';
	$rx['lv'] = '/^LV[0-9]{11}$/';
	$rx['mt'] = '/^MT[0-9]{8}$/';
	$rx['nl'] = '/^NL[0-9]{9}B[0-9]{2}$/';
	$rx['pl'] = '/^PL[0-9]{10}$/';
	$rx['pt'] = '/^PT[0-9]{9}$/';
	$rx['ro'] = '/^RO[0-9]{2,10}$/';
	$rx['se'] = '/^SE[0-9]{12}$/';
	$rx['si'] = '/^SI[0-9]{8}$/';
	$rx['sk'] = '/^SK[0-9]{10}$/';

	if (isset($rx[$cc])) {
		return preg_match($rx[$cc], $value);
	}

	if ($cc == 'es') {
		return preg_match('/^ES([0-9A-Z])[0-9]{7}([0-9A-Z])$/', $value, $match) && !ctype_digit($match[1]) && !ctype_digit($match[1]);
	}
	else if ($cc == 'de') {

  	if (!preg_match('/^DE[0-9]{9}$/', $value)) {
    	return false;
		}

		$p = 10;
		$s = 0;

  	for ($i = 0; $i < 8; $i++) {
    	$z = intval(mb_substr($value, 2 + $i, 1));
    	$s = ($z + $p) % 10;

    	if ($s == 0) {
      	$s = 10;
    	}

    	$p = (2 * $s) % 11;
  	}

  	$pruefziffer = 11 - $p;

  	if ($pruefziffer == 10) {
    	$pruefziffer = 0;
  	}

  	return (intval(mb_substr($value, -1)) == $pruefziffer);
	}

	// apply minimal check
	return preg_match('/'.mb_strtoupper($cc).'.+[0-9].+/$', $value);
}


/**
 * Return parameter array.
 * 
 * @param string $p1
 * @param string $p1
 * @param string $p1
 * @return array
 */
public static function getParameterArray($p1 = null, $p2 = null, $p3 = null) {
	$arr = [];

	if (is_array($p1)) {
		$arr = $p1;
	}
	else if ($p2 === null && $p3 === null) {
		$arr = [ $p1 ];
	}
	else if ($p3 === null) {
		$arr = [ $p1, $p2 ];
	}
	else {
		$arr = [ $p1, $p2, $p3 ];
	}

	return $arr;
}


/**
 * True if value is in self::getParameterArray(p1, p2, p3).
 *
 * @param string $value
 * @param string $p1
 * @param string $p2
 * @param string $p3
 * @return boolean
 */
public static function isEnum($value, $p1 = null, $p2 = null, $p3 = null) {
	return in_array($value, self::getParameterArray($p1, $p2, $p3));
}


/**
 * True if value is yyyy-mm-dd.
 * @param string $value
 * @param int $min_year (default = 1900)
 * @param int $max_year (default = 2150)
 * @return boolean
 */
public static function isSQLDate($value, $min_year = 1900, $max_year = 2150) {
	$res = true;

	if (!preg_match("/^([0-9]{4})\-([0-9]{2})\-([0-9]{2})$/", $value, $date) ||
		$date[3] < 1 || $date[3] > 31 || $date[2] < 1 || $date[2] > 12 || 
		($min_year && $date[1] < $min_year) || ($max_year && $date[1] > $max_year)) {
			$res = false;
	}

 	return $res;
}


/**
 * True if value is dd.mm.yyyy.
 * @param string $value
 * @param int $min_year (default = 1900)
 * @param int $max_year (default = 2150)
 * @return boolean
 */
public static function isDate($value, $min_year = 1900, $max_year = 2150) {
	$res = true;

	if (!preg_match("/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/", $value, $date) ||
		$date[1] < 1 || $date[1] > 31 || $date[2] < 1 || $date[2] > 12 || 
		($min_year && $date[3] < $min_year) || ($max_year && $date[3] > $max_year)) {
			$res = false;
	}

	return $res;
}


/**
 * True if value is dd.mm.yyyy[ hh:mm[:ss]].
 * @param string $value
 * @param int $min_year (default = 1900)
 * @param int $max_year (default = 2150)
 * @return boolean
 */
public static function isDateTime($value, $min_year = 1900, $max_year = 2150) {
	$time = mb_substr($value, 11);
	return self::isDate(mb_substr($value, 0, 10), $min_year, $max_year) && (!$time || self::isTime($time));
}


/**
 * True if value is between min and max.
 * @param string $value
 * @param float $min
 * @param float $max
 */
public static function isRange($value, $min, $max) {
	return $value >= $min && $value <= $max;
}


/**
 * True if length($value) == $len.
 * @param string $value
 * @param int $len
 * @return boolean
 */
public static function isLength($value, $len) {
	return $len > 0 && mb_strlen($value) == $len;
}


/**
 * True if number of lines == $lnum
 * @param string $value
 * @param int $lnum
 * @return boolean
 */
public static function maxLines($value, $lnum) {
	$lines = preg_split("/\r?\n/", trim($value));
	return $lnum > 0 && count($lines) <= $lnum;
}


/**
 * True if length($value) <= $len.
 * @param string $value
 * @param int $len
 * @return boolean
 */
public static function maxLength($value, $len) {
	return $len > 0 && mb_strlen($value) <= $len; 
}


/**
 * True if length($value) >= $len.
 * @param string $value
 * @param int $len
 * @return boolean
 */
public static function minLength($value, $len) {
	return $len > 0 && mb_strlen($value) >= $len; 
}


/**
 * True if value > now() - days.
 * @param date-string $value 
 * @param int $days
 * @return boolean
 */
public static function maxDaysOld($value, $days) {
	$res = true;

	try {
		$ts1 = DateCalc::dmy2unix(DateCalc::date2dmy($value));
		$ts2 = time();
		$day_diff = intval(($ts2 - $ts1) / 86400);

		if ($day_diff > $days) {
    	$res = false;
		}
	}
	catch (\Exception $e) {
		$res = false;
	}

	return $res;
}


/**
 * Return error message if value < max_date (date comparison).
 *
 * @param date-string $value
 * @param string $max_date
 * @param boolean $hms_compare
 * @return boolean
 */
public static function date_greater($value, $max_date, $hms_compare = false) {
	$res = true;

	try {
		$res = $hms_compare ? DateCalc::date2unix($value) > DateCalc::date2unix($max_date) : 
			DateCalc::sql_date($value, '') > DateCalc::sql_date($max_date, '');
	}
	catch (\Exception $e) {
		$res = false;
	}

	return $res;
}


/**
 * Return value op compare_with. Operator is:
 *  
 * ge (greater_equal), le (lower_equal), lower, greater, equal
 * 
 * @param float $value
 * @param string $op 
 * @param float $compare_with
 * @return boolean
 */
public static function compare($value, $op, $compare_with) {
	$res = false;

	if ($op == 'le' || $op == 'lower_equal') {
		$res = $value <= $compare_with;
	}
	else if ($op == 'ge' || $op == 'greater_equal') {
		$res = $value >= $compare_with;
	}
	else if ($op == 'lower') {
		$res = $value < $compare_with;
	}
	else if ($op == 'greater') {
		$res = $value > $compare_with;
	}
	else if ($op == 'equal') {
		$res = $value == $compare_with;
	}

	return $res;
}


/**
 * True if !empty(value)
 */
public static function not_empty($value) {
	return !empty($value);
}


/**
 * True if there is no html tag within value ( < ... > ).
 * @param string $value
 * @return boolean
 */
public static function noHTML($value) {
	$has_html_tag = ($pos = mb_strpos($value, '<')) !== false && mb_strpos($value, '>', $pos + 1) !== false;
	return !$has_html_tag;
}


/**
 * True if value has suffix.
 * @param string $value
 * @param string $suffix
 * @return boolean
 */
public static function hasSuffix($value, $suffix) {
	return mb_substr($value, -1 * mb_strlen($suffix)) == $suffix;
}


/**
 * True if value has prefix.
 * @param string $value
 * @param string $prefix
 * @return boolean
 */
public static function hasPrefix($value, $prefix) {
	return mb_substr($value, 0, mb_strlen($prefix)) == $prefix;
}


}
