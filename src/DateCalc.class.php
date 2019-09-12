<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';

use rkphplib\Exception;


if (!defined('SETTINGS_TIMEZONE')) {
  /** @const string SETTINGS_TIMEZONE = Auto-Detect */
	date_default_timezone_set(@date_default_timezone_get());
  define('SETTINGS_TIMEZONE', date_default_timezone_get());
}
else {
  date_default_timezone_set(SETTINGS_TIMEZONE);
}

if (!defined('SETTINGS_LANGUAGE')) {
  /** @const string SETTINGS_LANGUAGE = 'de' */
  define('SETTINGS_LANGUAGE', 'de');
}


/**
 * Date calculation helper class. All methods are static.
 * Define SETTINGS_TIMEZONE = CET and SETTINGS_LANGUAGE = de if unset.
 * Set date_default_timezone_set(SETTINGS_TIMEZONE) if unset.
 *
 * @author Roland Kujundzic <roland@inkoeln.com>
 */
class DateCalc {


/**
 * Return localized month names (SETTINGS_LANGUAGE = en|de|hr, default = de). Month is from [1,12].
 */
public static function monthName(int $month) : string {

  $month = ($month > 100000) ? intval(mb_substr($month, -2)) : intval($month);

  if ($month < 1 || $month > 12) {
    throw new Exception('invalid month', $month);
  }

  $lang = defined('SETTINGS_LANGUAGE') && mb_strlen(SETTINGS_LANGUAGE) === 2 ? SETTINGS_LANGUAGE : 'de';

  $month_names = array();

  $month_names['de'] = array('Januar', 'Februar', 'März', 'April', 'Mai',
    'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');

  $month_names['en'] = array('January', 'February', 'March', 'April', 'Mai',
    'June', 'July', 'August', 'September', 'October', 'November', 'December');

  $month_names['hr'] = array('Sije&#269;anj', 'Velj&#269;a', 'O&#382;ujak', 'Travanj', 'Svibanj',
    'Lipanj', 'Srpanj', 'Kolovoz', 'Rujan', 'Listopad', 'Studeni', 'Prosinac');

  if (!isset($month_names[$lang])) {
    throw new Exception("no month names for [$lang]");
  }

  return $month_names[$lang][$month - 1];
}


/**
 * Convert sql date(time) "yyyy-mm-dd[ hh:ii:ss]" to number "yyyymmdd[.hhiiss]".
 * Use day > 0 to force day instead of dd. Use day = -1 to cut off hh:ii:ss.
 *
 * @return mixed int|float
 */
public static function sql2num(string $sql_date, int $day = 0) {
	$res = null;

	if (empty($sql_date) || mb_substr($sql_date, 0, 10) == '0000-00-00' || $sql_date == '0000-00-00 00:00:00') {
		$res = 0;
	}
	else if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $sql_date, $m)) {
		$day = ($day > 0 && $day < 32) ? sprintf("%02d", $day) : $m[3];
		$res = intval($m[1].$m[2].$day);
	}
	else if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-5][0-9])\:([0-5][0-9])\:([0-5][0-9])$/', $sql_date, $m)) {
		if ($day > 0 && $day < 32) {
			$res = intval($m[1].$m[2].sprintf("%02d", $day));
		}
		else if ($day == -1) {
			$res = intval($m[1].$m[2].$m[3]);
		}
		else {
			$res = floatval($m[1].$m[2].$m[3].'.'.$m[4].$m[5].$m[6]);
		}
	}
	else {
		throw new Exception('invalid sql date|datetime', $sql_date);
	}

	return $res;
}


/**
 * Return max sql date|datetime (sql format).
 */
public static function max(string $d1, string $d2, bool $force_date = false) : string {
	$day = $force_date ? -1 : 0;
    $nd1 = self::sql2num($d1, $day);
    $nd2 = self::sql2num($d2, $day);
    return ($nd1 < $nd2) ? $d2 : $d1;
}


/**
 * Return timestamp of sql date|datetime. If day is not set use current day. If day == -1 cut of hh:ii:ss.
 */
public static function sqlTS(string $date, int $day = 0) : int {

	if (empty($date) || mb_substr($date, 0, 10) == '0000-00-00' || $date == '0000-00-00 00:00:00') {
		return 0;
	}

	if ($day == -1) {
		$date = mb_substr($date, 0, 10);	
	}

	if ($day > 0 && $day < 32) {
		$day = intval($day);
	}
	else {
		$day = intval(mb_substr($date, 8, 2));
	}

	$m = intval(mb_substr($date, 5, 2));
	$y = mb_substr($date, 0, 4);

	if (mb_strlen($date) == 10) {
		$res = mktime(0, 0, 0, $m, $day, $y);
	}
	else if (mb_strlen($date) == 19) {
		$h = intval(mb_substr($date, 11, 2));
		$i = intval(mb_substr($date, 14, 2));
		$s = intval(mb_substr($date, 17, 2));
		$res = mktime($h, $i, $s, $m, $day, $y);
	}

	return $res;
}


/**
 * Return sql date|datetime with mnum (number of month added).
 */
public static function sqlAddMonth(string $date, int $mnum) : string {

	if ($mnum < -120 || $mnum > 120) {
		throw new Exception('invalid mnum use [-120,120]', $mnum);
	}

	$ymd = mb_substr($date, 0, 10);

	if ($mnum > 0) {
		while ($mnum > 0) {
			$ymd = date('Y-m', self::sqlTS($ymd, 25) + 3600 * 24 * 8)."-".mb_substr($ymd, 8, 2);
			$mnum--;
		}
	}
	else if ($mnum < 0) {
		while ($mnum < 0) {
			$ymd = date('Y-m', self::sqlTS($ymd, 1) - 3600 * 24 * 2)."-".mb_substr($ymd, 8, 2);
			$mnum++;
		}
	}

	if (mb_strlen($date) == 19) {
		$ymd .= mb_substr($date, 10);
	}

	return $ymd;
}


/**
 * Convert date string (default datetime) into unix timestamp.
 * See self::date2dmyhis for valid date format.
 */
public static function date2unix(string $date, bool $allow_dmy = false) : int {
  return self::dmy2unix(self::date2dmyhis($date, $allow_dmy, false));
}


/**
 * Convert $dmy = array(d,m,y) into unix timestamp. Year is in [1970,2200] or 0.
 */
public static function dmy2unix(array $dmy) : int {

	if (count($dmy) != 3 && count($dmy) != 6) {
		throw new Exception('invalid dmy array', join('|', $dmy));
	} 

	$d = intval($dmy[0]);
	$m = intval($dmy[1]);
	$y = intval($dmy[2]);

	if ($d + $m + $y == 0) {
		return 0;
	}

	if ($d < 1 || $d > 31 || $m < 1 || $m > 12 || $dmy[2] < 1970 || $dmy[2] > 2200) {
		throw new Exception('invalid value in dmy array', join('|', $dmy));
	}

	if (count($dmy) == 6) {
    	$res = mktime(intval($dmy[3]), intval($dmy[4]), intval($dmy[5]), $m, $d, $y);
	}
	else {
    	$res = mktime(0, 0, 0, $m, $d, $y);
	}

	return $res;
}


/**
 * Convert string date(time) into dmyhis array. See self::date2dmy() for recognised dates. Return array (d,m,y,h,i,s).
 */
public static function date2dmyhis(string $date, bool $allow_dmy = false, bool $use_curr_time = false) : array {
	$dmyhis = array();
	$dl = mb_strlen($date);

	if ($dl > 11) {

		if ($dl == 12 || $dl == 14) {
			// yyyymmddhhii[ss]
			$dmyhis = self::date2dmy(mb_substr($date, 0, 8));
			$his = array(mb_substr($date, 8, 2), mb_substr($date, 10, 2));
			$his[2] = ($dl == 14) ? mb_substr($date, 12, 2) : 0;
		}
		else if ($dl > 14) {
			// dd.mm.yyyy hh:ii[:ss] or yyyy-mm-dd hh:ii[:ss]
			$dmyhis = self::date2dmy(mb_substr($date, 0, 10));
			$his = explode(':', mb_substr($date, 11));
		}

		if (count($his) == 3) {
			array_push($dmyhis, intval($his[0]), intval($his[1]), intval($his[2]));
		}
		else if (count($his) == 2) {
			array_push($dmyhis, intval($his[0]), intval($his[1]), 0);
		}
		else {
			throw new Exception('invalid date|datetime', $date);
		}
	}
	else if ($allow_dmy) {
		$dmyhis = self::date2dmy($date);

		if ($use_curr_time) {
			$his = date('His', time());
			$h = intval(mb_substr($his, 0, 2));
			$m = intval(mb_substr($his, 2, 2));
			$s = intval(mb_substr($his, 4, 2));
			array_push($dmyhis, $h, $m, $s);
		}
		else {
			array_push($dmyhis, 0, 0, 0);
		}
	}
	else {
		throw new Exception('invalid date|datetime', $date);
	}

	return $dmyhis;
}


/**
 * Convert string date into dmy array. Cut datetime to date. Return array (d,m,y).
 * 
 * Recognised formats are "yyyymmddhhii[ss]", "dd.mm.yyyy hh:ii[:ss]",
 * "200708", "20070813", "26.01.2007", "11.2003|11/2003|2003-11" and "2007-01-26".
 */
public static function date2dmy(string $date, bool $abort = true) : array {

	$dmy = array();
	$dl = mb_strlen($date);

	if ($dl > 11) {
		if ($dl == 12 || $dl == 14) {
			// yyyymmddhhii[ss]
			$date = mb_substr($date, 0, 8);
		}
		else if ($dl > 14) {
			// dd.mm.yyyy hh:ii[:ss]
			$date = mb_substr($date, 0, 10);
		}

		$dl = mb_strlen($date);
	}

	if ($dl == 6) {
		// e.g. 200708 (= 01.08.2007)
		$dmy = array(1, intval(mb_substr($date, 4, 2)), intval(mb_substr($date, 0, 4)));
	}
	else if ($dl == 7) {
		// e.g. 11.2007 = (01.11.2007) = 2007-11 = 11/2007
		if (($pos = mb_strpos($date, '-')) !== false && $pos == 4) {
			$dmy = array(1, intval(mb_substr($date, 5, 2)), intval(mb_substr($date, 0, 4)));
		}
		else {
			if (intval(mb_substr($date, 4, 1)) > 0) {
				throw new Exception('invalid date', $date);
			}

			$dmy = array(1, intval(mb_substr($date, 0, 2)), intval(mb_substr($date, 3, 4)));
		}
	}
	else if ($dl == 8) {
		// e.g. 20070813
		$dmy = array(intval(mb_substr($date, 6, 2)), intval(mb_substr($date, 4, 2)), intval(mb_substr($date, 0, 4)));
	}
	else if ($dl == 10) {
		if (mb_strpos($date, '.') !== false) {
			// e.g. 26.01.2007
			$tmp = explode('.', $date);
			$dmy = array(intval($tmp[0]), intval($tmp[1]), intval($tmp[2]));
		}
		else if (mb_strpos($date, '-') !== false) {
			// e.g. 2007-01-26
			$tmp = explode('-', $date);
			$dmy = array(intval($tmp[2]), intval($tmp[1]), intval($tmp[0]));
		}
	}

	if (count($dmy) != 3 && $abort) {
		throw new Exception('invalid date', $date);
	}

	return $dmy;
}


/**
 * Re-format date string into yyyy-mm-dd.
 */
public static function sql_date(string $date, string $delimiter = '-') : string {
  $dmy = self::date2dmy($date);
  $res = $dmy[2].$delimiter.sprintf("%02d", $dmy[1]).$delimiter.sprintf("%02d", $dmy[0]);
  return $res;
}


/**
 * Return number of seconds formated as "LL h NN min KK sec". Use h:m:s if param == hms).
 */
public static function min_sec(int $sec, string $param = '') : string {

	$sec = intval($sec);
	$res = '';
	$h = -1;
	$m = -1;
	$s = -1;

	if ($sec < 0) {
		throw new Exception('invalid sec', $sec);
	}
	else if ($sec < 1) {
		// return nothing ...
	}
	else if ($sec < 60) {
    	$s = $sec;
	}
	else if ($sec < 3600) {
    	$m = intval($sec / 60);
		$s = $sec % 60;
	}
	else {
		$h = intval($sec / 3600);
		$sec = $sec % 3600;
		$m = intval($sec / 60);
		$s = $sec % 60;
	}

	if ($param == 'hms')  {
		if ($h > -1) { $res .= $h.':'; }
		if ($m > -1) { $res .= $m.':'; }
		if ($s > -1) { $res .= $s; }
	}
	else {
		if ($h > -1) { $res .= $h.' h '; }
		if ($m > -1) { $res .= $m.' min '; }
		if ($s > -1) { $res .= $s.' sec'; }
	}

	return $res;
}


/**
 * Return kw of year (MONDAY, SUNDAY). If kw is empty return (MONDAY_1, SUNDAY_2, MONDAY_2, SUNDAY_2, ... , MONDAY_53, SUNDAY_53).
 * If you retrieve kw of whole year use kw[(n-1)*2, (n-1)*2 + 1] for n'th entry.
 */
public static function kw(int $year, int $kw = 0) : array {
	$res = array();
  
	if ($kw > 0) {
		$kw = sprintf("%02d", $kw);
		$res[0] = date("d.m.Y", strtotime($year.'-W'.$kw));
		$res[1] = date("d.m.Y", strtotime($year.'-W'.$kw.'-7'));
	}
	else {
		for ($i = 1; $i < 54; $i++) {
			$kw = sprintf("%02d", $i);
			array_push($res, date("d.m.Y", strtotime($year.'-W'.$kw)));
			array_push($res, date("d.m.Y", strtotime($year.'-W'.$kw.'-7')));
		}
	}

	return $res;
}


/**
 * Convert hh, hh:mm and hh:mm:ss into number of second (h * 3600 + m * 60 + s).
 */
public static function hms2sec(string $hms) : int {

	if (preg_match('/^([0-5][0-9])\:([0-5][0-9])\:([0-5][0-9])$/', $hms, $m)) {
		$res = intval($m[1]) * 3600 + intval($m[2]) * 60 + intval($m[3]);
	}
	else if (preg_match('/^([0-5][0-9])\:([0-5][0-9])$/', $hms, $m)) {
		$res = intval($m[1]) * 60 + intval($m[2]);
	}
	else {
		throw new Exception('invalid hms', $hms);
	}

	return $res;
}


/**
 * Convert seconds into [hh:]mm:ss.
 */
public static function sec2hms(int $sec) : string {

	$h = '';
	if ($sec >= 3600) {
		$h = sprintf("%02d", ($sec / 3600));
		$sec = $sec % 3600;
	}

	$m = '00';
	if ($sec >= 60) {
		$m = sprintf("%02d", ($sec / 60));
		$sec = $sec % 60;
	}

	$s = sprintf("%02d", $sec);
	$res = '';

	if (!empty($h)) {
		$res = $h.':';
	}

	$res .= $m.':'.$s;

	return $res;
}


/**
 * Compute unix timestamp from now string "now(+/-offset)". Example of $str are
 * now(), now(+3600) or now(-60) or now(+2day|month).
 */
public static function nowstr2time(string $str, bool $abort = true) : int {

	$str = trim($str);

	if (mb_substr($str, 0, 4) !== 'now(' || mb_substr($str, -1) !== ')') {
		if ($abort) {
			throw new Exception('invalid - use now(...)', $str);
		}

		return 0;
	}

	$res = time();

	if (mb_strlen($str) > 5) {
		$expr = mb_substr($str, 4, -1);

		if (preg_match('/^([\+\-]?)([0-9]+)$/', $expr, $match)) {
			$num = intval($match[2]);
			$res = ($match[1] == '-') ? $res - $num : $res + $num;
		}
		else if (preg_match('/^([\+\-]?)([0-9]+)(.+)$/', $expr, $match)) {
			$res = strtotime($match[1].$match[2].' '.$match[3], $res);
		}
		else if ($abort) {
			throw new Exception('failed to parse now() expression', "expr=[$expr]");
		}
	}

	return $res;
}


/**
 * Return next month as yyyymm. If year = 0 (default) return only next month.
 */
public static function nextMonth(int $month, int $year = 0) : int {

	if ($year > 1000) {
		$res = ($year * 100) + $month;
		$res = ($month == 12) ? $res + 89 : $res + 1;
	}
	else {
		$res = ($month % 12) + 1;
	}

	return $res;
}


/**
 * Return previous month as yyyymm. If year = 0 (default) return only prev month.
 */
public static function prevMonth(int $month, int $year = 0) : int {

	if ($year > 1089) {
		$res = ($year * 100) + $month;
    	$res = ($month == 1) ? $res - 89 : $res - 1;
	}
	else {
		$res = ($month + 10) % 12 + 1;
	}

	return $res;
}


/**
 * Return last day of month. If year or month is empty use current.
 */
public static function lastDay(int $month = 0, int $year = 0) : int {

	if (empty($month)) {
		$month = date('n');
	}

 	if (empty($year)) {
		$year = date('y');
	}

	$res = date("t", mktime(0, 0, 0, $month, 1, $year));
	return intval($res);
}


/**
 * Return Day of month of sql date string yyyy-mm-dd. If since_year (> 1000) is given add days from since_year - sql_date.year.
 */
public static function sqlDayOfYear(string $sql_date, int $since_year = 0) : int {
	$dmy = self::date2dmy($sql_date);
	$res = date("z", mktime(0, 0, 0, $dmy[1], $dmy[0], $dmy[2])) + 1;

	if ($since_year > 1000) {
		for ($i = $since_year; $i < $dmy[2]; $i++) {
			$res += date("z", mktime(0, 0, 0, 12, 31, $i)) + 1;
		}
	}

	return $res;
}


/**
 * Re-format date string. See self::formatDateTimeStr. Format in/out:
 * 
 * de = d.m.Y H:i:s
 * sql = Y-m-d H:i:s
 * % map = any combination of %d, %e, %m, %y, %Y, %H, %i, %s, %B
 * default map = any combination of d,m,Y,y,H,i,s
 */
public static function formatDateStr(string $format_out, string $date_str, string $format_in = '') : string {

	if (preg_match('/^[0\-\.\:]+$/', $date_str) || (!$date_str && $format_in != 'now')) {
		return '';
	}

	if (empty($format_out)) {
		throw new Exception('no output format defined');
	}

	$format_map = array('de' => 'd.m.Y H:i:s', 'sql' => 'Y-m-d H:i:s', 'en' => 'j M Y H:i:s');

	if (!empty($format_map[$format_out])) {
		$format_out = $format_map[$format_out];
	}

	if (!empty($format_map[$format_in])) {
		$format_in = $format_map[$format_in];
	}

	$regroup = array('d.m.Y', 'd.m.Y H:i:s', 'YmdHis', 'Ymd', 'Ym', 'Y-m-d', 'Y-m-d H:i:s');

	if (in_array($format_in, $regroup) && (in_array($format_out, $regroup) || mb_strpos($format_out, '%') !== false)) {
		$dmyhis = self::date2dmyhis($date_str, true, true);

		$Xd = sprintf('%02d', $dmyhis[0]);
		$Xm = sprintf('%02d', $dmyhis[1]);
		$Xyyyy = $dmyhis[2];
		$Xyy = mb_substr($dmyhis[2], -2);
		$Xh = sprintf('%02d', $dmyhis[3]);
		$Xi = sprintf('%02d', $dmyhis[4]);
		$Xs = sprintf('%02d', $dmyhis[5]);

		if (mb_strpos($format_out, '%') !== false) {
			$map = array('%d' => $Xd, '%e' => $Xd, '%m' => $Xm, '%y' => $Xyy, '%Y' => $Xyyyy, '%H' => $Xh, '%i' => $Xi, '%s' => $Xs, 
				'%B' => self::monthName($Xm));
		}
		else {
			$map = array('d' => $Xd, 'm' => $Xm, 'y' => $Xyy, 'Y' => $Xyyyy, 'H' => $Xh, 'i' => $Xi, 's' => $Xs);
		}

		$res = $format_out;
		foreach ($map as $tag => $value) {
			$res = str_replace($tag, $value, $res);
		}
	}
	else {
		$res = self::formatDateTimeStr($format_out, $date_str, $format_in);
	}

	return $res;
}


/**
 * Re-format datetime string. See self::nowstr2time(), self::date2dmyhis() and self::dmy2unix(). Format in/out:
 * 
 * unix = unix timestamp
 * date = unix timestamp
 * strtotime = use strtotime($date_str)
 * mm/yyyy = use d=1, his=00:00:00
 * now = see nowstr2time($date_str)
 * strftime = use strftime($format_out, time)
 */
public static function formatDateTimeStr(string $format_out, string $date_str, string $format_in = '') : string {

	if ($format_in == 'unix' && mb_substr($date_str, 0, 4) == 'now(') {
		$format_in = 'now';
	}

	$format_map = array('de' => 'd.m.Y H:i:s', 'sql' => 'Y-m-d H:i:s');

	if (!empty($format_map[$format_out])) {
		$format_out = $format_map[$format_out];
	}

	if (!empty($format_map[$format_in])) {
		$format_in = $format_map[$format_in];
	}

	if ($format_in == 'unix' || $format_in == 'date') {
		$time = intval($date_str);
	}
	else if ($format_in == 'strtotime') {
		$time = strtotime($date_str);
	}
	else if (preg_match("/^([0-9]{2})\/([0-9]{4})$/", $date_str, $m)) {
		$time = mktime(0, 0, 0, $m[1], 1, $m[2]) - 1;
	}
	else if ($format_in == 'now' || mb_substr($date_str, 0, 4) == 'now(') {
		$time = empty($date_str) ? time() : self::nowstr2time($date_str);
	}
	else {
		$date = self::date2dmyhis($date_str, true, true);
		$time = self::dmy2unix($date);
	}

	if ($format_in == 'strftime') {
		$res = strftime($format_out, $time);
	}
	else if (mb_strpos($format_out, '%') !== false) {
		// replace %e=day, %m=month, %y, %H=hour, %M=minute, %S=second, %B=month name 
		$tmp = explode('-', date('d-m-Y-H-i-s', $time));
		$map['%d'] = $tmp[0];
		$map['%e'] = intval($tmp[0]);
		$map['%m'] = $tmp[1];
		$map['%Y'] = $tmp[2];
		$map['%H'] = $tmp[3];
		$map['%i'] = $tmp[4];
		$map['%s'] = $tmp[5];
		$map['%B'] = self::monthName(intval($tmp[1]));

		$res = $format_out;
		foreach ($map as $tag => $value) {
			$res = str_replace($tag, $value, $res);
		}
	}
	else if ($format_out == 'unix') {
		$res = $time;
	}
	else if ($time > 0) {
		$res = date($format_out, $time);
	}
	else {
		throw new Exception('invalid time', $time);
	}

	return $res;
}


}

