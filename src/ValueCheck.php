<?php

namespace rkphplib;

require_once __DIR__.'/DateCalc.php';
require_once __DIR__.'/lib/split_str.php';


use function rkphplib\lib\split_str;



/**
 * All checks are static methods and return true|false.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class ValueCheck {


/**
 * Check _REQUEST
 * @example ValueCheck::REQUEST([ 'day' => 'isSQLDate' ])
 */
public static function REQUEST(array $param_check) : void {
	foreach ($param_check as $key => $check) {
		if (!empty($_REQUEST[$key]) && !self::$check($_REQUEST[$key])) {
			throw new Exception('invalid '.$key, $check);
		}
	}
}


/**
 * Run check if string $value is not empty. If $value is callable replace $value with $value() result.
 * Split $value at [:] into self::method and parameter list (e.g. value = isRange:5:8). If self::method
 * doesn't exist assume isMatch and self::method = name of regular expression (see isMatch).
 */
public static function run(string $key, $value, string $check) : bool {
	// \rkphplib\lib\log_debug("ValueCheck::run:40> key=$key value=$value check=$check");
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
				// \rkphplib\lib\log_debug("ValueCheck::run:57> return true");
				return true;
			}
		}
	}

	if (($start = mb_strpos($key, '.')) > 0) {
		// e.g. email.1, email.2 
		$key = mb_substr(0, $start);
	}

	if (is_callable($value)) {
		$value = $value($key);
	}

	if (strlen($value) == 0) {
		// \rkphplib\lib\log_debug("ValueCheck::run:73> empty value - return true");
		return true;
	}

	if (!is_array($check)) {
		$check = split_str(':', $check);
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

	// \rkphplib\lib\log_debug("ValueCheck::run:96> method=[$method] pn=[$pn] check: ".print_r($check, true));
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

	// \rkphplib\lib\log_debug("ValueCheck::run:113> check=[".join(':', $check)."] method=[$method] res=[$res]");
	return $res;
}


/**
 * Return match pattern. Names:
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
		'Email' => '/^[a-z0-9_\.\-]+@[a-z0-9\.\-]+$/i',
		'EmailPrefix' => '/^[a-z0-9_\.\-]+$/i',
		'HTTP' => '/^http\:\/\//i',
		'HTTPS' => '/^https\:\/\//i',
		'Phone' => '/^[\+0-9\(\)\/ \.\-]+$/i',
		'Mobile' => '/^\+[0-9]+$/',
		'PhoneNumber' => '/^[\+0-9\(\)\/ \.\-]+$/i',
		'Variable' => '/^[0-9A-Z_]+$/i',
		'PLZ' => '/^[0-9]{5}$/');

	if (!isset($rx[$name])) {
		throw new Exception("invalid regular expression check $name - try: ".join(', ', array_keys($rx)));
	}

  return $rx[$name];
}


/**
 * Execute database query to check if columnn values exist. Parameter $colnames is comma separated string.
 * If $colnames=col1, col2, ... is set append col1=$_REQUEST[col1] and col2=$_REQUEST[col2] ... to query.
 *
 * @example check.postcode= sqlQuery:email,postcode:SELECT 1 AS ok FROM shop_customer WHERE type='consumer' AND status='active'
 */
public static function sqlQuery(string $ignore, string $colnames, string $query) : bool {
  require_once __DIR__.'/Database.php';

  $db = \rkphplib\Database::getInstance();

  $columns = split_str(',', $colnames);
  foreach ($columns as $column) {
		if (isset($_REQUEST[$column])) {
			$query .= ' AND '.$db->escape_name($column)."='".$db->esc($_REQUEST[$column])."'";
		}
  }

  // \rkphplib\lib\log_debug("ValueCheck::sqlQuery:171> ignore=[$ignore] colnames=[$colnames] query: $query");
  $dbres = $db->select($query);
  return count($dbres) > 0;
}


/**
 * Same as isDomain but http[s]:// prefix is required
 * @code ValueCheck::isURL('HTTP://domain.tld') == false
 * @code ValueCheck::isURL('https://domain.tld') == true
 * @code ValueCheck::isURL('x.tld') == false
 */
public static function isURL(string $domain, int $min_level = 2, int $max_level = 9) : bool {
	if (!preg_match('/^https?:\/\/(.+)$/', $domain, $match)) {
		return false;
	}

	return self::isDomain($match[1], $min_level, $max_level);
}


/**
 * True if $value is url path. Split $value at first [/] and do isURL check
 * if prefix is http[s]://.
 * 
 * @code ValueCheck::isURLPath('/dir/index.php') == true
 * @code ValueCheck::isURLPath('/dir/index.php?x=5') == false
 * @code ValueCheck::isURLPath('/dir/index.php?x=5#7', 1) == true
 * @code ValueCheck::isURLPath('https://domain.tpl/index.html) == true
 */
public static function isURLPath(string $value, $is_query = 0) : bool {
	$is_domain = true;
	if (preg_match('/https?:\/\/(.+?)\/(.+)$/', $value, $match)) {
		$is_domain = self::isDomain($match[1]);
		$value = $match[2];
	}

	$srx = $is_query ? '&#%=\?\+' : '';
	$rx = '/^[a-z0-9_,\/\-\.'.$srx.']+$/i';
	return ($is_domain && preg_match($rx, $value));
}


/**
 * Return true if $domain is valid domain name. Max level allowed is 9 (a.b.c.d.e.f.g.h.tld).
 * Use min_level > 2 and max_level >= min_level + 1 for subdomain check.
 * If min_level=0 set min_level=2. If max_level=0 set max_level=9.
 * Export $_REQUEST[xn--$domain] if domain is valid utf8.
 * 
 * @code ValueCheck::isDomain('a.b') == true
 * @code ValueCheck::isDomain('sub.domain.tld', 2, 2) == false (domain check)
 * @code ValueCheck::isDomain('sub.domain.tld', 3, 3) == true (subdomain check)
 * @code ValueCheck::isDomain('sub.sub.domain.tld', 4, 4) == true (subsubdomain check)
 * @code ValueCheck::isDomain('01aa.b-b.cc') == true
 * @code ValueCheck::isDomain('http://a.b') == false
 */
public static function isDomain(string $domain, int $min_level = 2, int $max_level = 9) : bool {
	$_REQUEST['xn--'.$domain] = '';

	$domain_parts = explode('.', $domain);
	$level = count($domain_parts);

	if ($level > 9) {
		throw new Exception("$domain level is $level", 'only [sub]domains up to level 6 are supported (e.g. a.b.c.d.e.f.g.h.tld)');
	}

	if (0 == $min_level) {
		$min_level = 2;
	}

	if (0 == $max_level) {
		$max_level = 9;
	}

	if ($min_level > $level || $level > $max_level) {
		return false;
	}

	if (!preg_match('/^[a-z0-9\-\.]+$/i', $domain)) {
		// try convert to IDNA ASCII form
		$xn = idn_to_ascii($domain);
		if (false === $xn) {
			return false;
		}

		$_REQUEST['xn--'.$domain] = $xn;
		$domain = $xn;
	}

	$rx_sub = '([a-z0-9]{1,63}\.|[a-z0-9]+[a-z0-9\-]{0,61}[a-z0-9]{1}\.)+';
	$rx_top = '([a-z]{2,20}|xn--[a-z0-9\-]{1,40})$';
	return preg_match('/^'.$rx_sub.$rx_top.'$/i', $domain);
}


/**
 * Execute database query to check if value is unique (does not exist in database yet). Example: 
 *
 * self::run(login, 'Joe', '{login:@table}:login:{get:login}:id:{login:id}')
 * check.email = isUnique:shop_customer@3:email:{get:email}:id: ({get:id} missing because unknown)
 * check.name = isUnique:dyndns_dns:name:{get:name}:and domain=1 and typ in ('A', 'AAAA')
 *
 * Array p: 
 *  0: Table name or TableName@N (N: where owner=N)
 *  1: Column name
 *  2: Column value
 *  3: 1 or column name - if 1 return true if result.anz == 1
 *  4: If not empty and 3 not empty add to where: AND p[3] != p[4]
 */
public static function isUnique(string $value, array $p) : bool {
	require_once __DIR__.'/Database.php';

	$query = 'SELECT count(*) AS anz FROM ';

	if (strpos($p[0], '@') !== false) {
		list ($table, $owner) = explode('@', $p[0]);
		$query .= \rkphplib\Database::table($table).' WHERE owner='.intval($owner).' AND ';
	}
	else {
		$query .= \rkphplib\Database::table($p[0]).' WHERE ';
	}

	$query .= \rkphplib\Database::table($p[1]).' = {:=u_val}';
	$id_val = '';

	if (!empty($p[3])) {
		if (stripos($p[3], 'and ') === 0) {
			$query .= ' '.$p[3];
		}
		else if (!empty($p[4])) {
			$query .= ' AND '.\rkphplib\Database::table($p[3]).' != {:=id_val}';
			$id_val = $p[4];
		}
	}

	$db = \rkphplib\Database::getInstance('', [ 'select_unique' => $query ]);
	$query = $db->getQuery('select_unique', [ 'id_val' => $id_val, 'u_val' => $p[2] ]);
	// \rkphplib\lib\log_debug("ValueCheck::isUnique:308> value=[$value] p=".print_r($p, true)."\n$query");
	$dbres = $db->select($query);
	$anz = intval($dbres[0]['anz']);
	// \rkphplib\lib\log_debug("ValueCheck::isUnique:311> anz=$anz");
	return (!empty($p[3]) && $p[3] == '1') ? $anz == 1 : $anz == 0;
}


/**
 * True if value matches regular expression (e.g. /[0-9]/).
 * Slash at start and end of regular expression are optional.
 */
public static function isMatch(string $value, string $rx) : bool {
	// \rkphplib\lib\log_debug("ValueCheck::isMatch:321> value=[$value] rx=[$rx]");
	if (empty($rx)) {
		throw new Exception('empty regular expression', "value=[$value]");
	}

  if (mb_substr($rx, 0, 1) != '/' && mb_substr($rx, -1) != '/') {
		$rx = self::getMatch($rx);
	}
	else {
		$rx = '/'.$rx.'/';
	}

  $res = preg_match($rx, $value);
	// \rkphplib\lib\log_debug("ValueCheck::isMatch:334> value=[$value], rx=[$rx] return res=[".intval($res)."]");
  return $res;
}


/**
 * True if CasNr is valid.
 */
public static function isCasNr(string $value) : bool {

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
 * True if UStIdNr is valid. Parameter $cc is iso2 country code, e.g. "de".
 * @ToDo http://www.pruefziffernberechnung.de/U/USt-IdNr.shtml (nur de hat momentan pruefziffer check)
 */
public static function isUStIdNr(string $value, string $cc) : bool {
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
 */
public static function getParameterArray(?string $p1 = null, ?string $p2 = null, ?string $p3 = null) : array {
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
 * True if value is in self::getParameterArray(p1, p2, p3) or in array $p1.
 *
 * @param string|array|null $p1
 */
public static function isEnum(string $value, $p1 = null, ?string $p2 = null, ?string $p3 = null) : bool {
	if (is_array($p1)) {
		return in_array($value, $p1);
	}

	return in_array($value, self::getParameterArray($p1, $p2, $p3));
}


/**
 * True if value is yyyy-mm-dd.
 */
public static function isSQLDate(string $value, int $min_year = 1900, int $max_year = 2150) : bool {
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
 */
public static function isDate(string $value, int $min_year = 1900, int $max_year = 2150) : bool {
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
 */
public static function isDateTime(string $value, int $min_year = 1900, int $max_year = 2150) : bool {
	$time = mb_substr($value, 11);
	return self::isDate(mb_substr($value, 0, 10), $min_year, $max_year) && (!$time || self::isTime($time));
}


/**
 * True if value is hh:mm[:ss]
 */
public static function isTime(string $value) : bool {
	if (!preg_match('/^([0-9]{2}):([0-9]{2}):?([0-9]{2})?$/', $value, $time)) {
		return false;
	}

	$h = intval($time[1]);
	$m = intval($time[2]);
	$s = isset($time[3]) ? intval($time[3]) : 0;

	if ($h < 0 || $h > 60 || $m < 0 || $m > 60 || $s < 0 || $s > 60) {
		return false;
	}

	return true;
}


/**
 * True if value is between min and max.
 */
public static function isRange(string $value, float $min, float $max) : bool {
	return $value >= $min && $value <= $max;
}


/**
 * True if length($value) == $len.
 */
public static function isLength(string $value, int $len) : bool {
	return $len > 0 && mb_strlen($value) == $len;
}


/**
 * True if number of lines == $lnum
 */
public static function maxLines(string $value, int $lnum) : bool {
	$lines = preg_split("/\r?\n/", trim($value));
	return $lnum > 0 && count($lines) <= $lnum;
}


/**
 * True if length($value) <= $len.
 */
public static function maxLength(string $value, int $len) : bool {
	return $len > 0 && mb_strlen($value) <= $len; 
}


/**
 * True if length($value) >= $len.
 */
public static function minLength(string $value, int $len) : bool {
	return $len > 0 && mb_strlen($value) >= $len; 
}


/**
 * True if $value (date string) > now() - days.
 */
public static function maxDaysOld(string $value, int $days) : bool {
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
 * Return error message if $value (date string) < $max_date (date string). Compare dates.
 * If parameter $hms_compare is true (default = false) compare H:M:S too.
 */
public static function date_greater(string $value, string $max_date, bool $hms_compare = false) : bool {
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
 * Return comparision result "$value $op $compare_with". Operator $op is:
 * ge (greater_equal), le (lower_equal), lower (lt), greater (gt), equal (eq).
 */
public static function compare(float $value, string $op, float $compare_with) : bool {
	$res = false;

	if ($op == 'le' || $op == 'lower_equal') {
		$res = $value <= $compare_with;
	}
	else if ($op == 'ge' || $op == 'greater_equal') {
		$res = $value >= $compare_with;
	}
	else if ($op == 'lt' || $op == 'lower') {
		$res = $value < $compare_with;
	}
	else if ($op == 'gt' || $op == 'greater') {
		$res = $value > $compare_with;
	}
	else if ($op == 'eq' || $op == 'equal') {
		$res = $value == $compare_with;
	}

	return $res;
}


/**
 * True if !empty(value).
 *
 * @param mixed $value
 */
public static function not_empty($value) : bool {
	return !empty($value);
}


/**
 * True if there is no html tag within value ( < ... > ).
 */
public static function noHTML(string $value) : bool {
	$has_html_tag = ($pos = mb_strpos($value, '<')) !== false && mb_strpos($value, '>', $pos + 1) !== false;
	return !$has_html_tag;
}


/**
 * True if $value contains suffix $suffix.
 */
public static function hasSuffix(string $value, string $suffix) : bool {
	return mb_substr($value, -1 * mb_strlen($suffix)) == $suffix;
}


/**
 * True if $value contains prefix $prefix.
 */
public static function hasPrefix(string $value, string $prefix) : bool {
	return mb_substr($value, 0, mb_strlen($prefix)) == $prefix;
}


}
