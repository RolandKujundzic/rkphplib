<?php

namespace rkphplib\lib;

/**
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */


/**
 * Custom exception with two parameter constructor.
 */
class Exception extends \Exception {

public $internal_message = '';

public function __construct($message, $interal_message = '') {
	$this->internal_message = $interal_message;
	parent::__construct($message);
}

}


/**
 * Return rkphplib version.
 * @return string
 */
function version() {
	return '1.0';
}


// E_ERROR | E_WARNING | E_PARSE | E_NOTICE or E_ALL or E_ALL ^ E_NOTICE
// error_reporting(E_ALL);


// set default timezone
if (isset($settings_TIMEZONE)) {
	date_default_timezone_set($settings_TIMEZONE);
}
else {
	date_default_timezone_set('GMT');
}

// set default language (=de)
if (!isset($settings_LANGUAGE)) {
	$settings_LANGUAGE = 'de';
}


/**
 * Default Exception catch.
 */
function exception_handler($e) {
	$msg = "\n\nABORT: ".$e->getMessage();
	$trace = $e->getFile()." on line ".$e->getLine()."\n".$e->getTraceAsString();
	$internal = empty($e->internal_message) ? '' : "INFO: ".$e->internal_message;

	if (php_sapi_name() !== 'cli') {
		error_log("$msg\n$internal\n\n$trace\n\n", 3, '/tmp/php.fatal');
	  die("<h3 style='color:red'>$msg</h3>");
	}

	die("$msg\n$internal\n\n$trace\n\n");
}

set_exception_handler('\rkphplib\lib\exception_handler');


/**
 * Custom error handler. Convert any php error into Exception.
 */
function error_handler($errNo, $errStr, $errFile, $errLine) {

	if (error_reporting() == 0) {
		// @ suppression used, ignore it
		return;
	}

	throw new \ErrorException($errStr, 0, $errNo, $errFile, $errLine);
}

set_error_handler('\rkphplib\lib\error_handler');


// Force UTF-8 encoding
mb_internal_encoding('UTF-8');

// global define
define('IGNORE_EMPTY', 1);
define('PRESERVE_QUOTE', 2);
define('TRIM_LINES', 4);
define('FIX_DQUOTE', 8);



/**
 * Explode csv string into array. Escape delim with quite enclosure. Escape quote with double quote.
 * @param string $text
 * @param string $delim (default = ",")
 * @param string $quote (default = '"')
 * @param int $mode (default = 0 - 1=IGNORE_EMPTY, 2=PRESERVE_QUOTE, 4=TRIM_LINES, 8=FIX_DQUOTE)
 * @return array
 */
function csv_explode($text, $delim=',', $quote = '"', $mode = 0) {
	$res = array();
	$n = 0;

	$ignore_empty = $mode & 1;
	$keep_quote = $mode & 2;
	$trim_lines = $mode & 4;
	$fix_dquote = $mode & 8;

	$tmp = explode($quote, $text);
	$tl = count($tmp);

	foreach($tmp as $x) {

		if ($n++ % 2) {
			if (!$keep_quote && $n < $tl - 1 && mb_strlen($tmp[$n]) == 0) {
				$x .= $quote;
			}

			$pq = ($mode & 2) ? $quote : '';
			array_push($res, array_pop($res).$pq.$x.$pq);
		}
		else {
			$tmp2 = explode($delim, $x);
			array_push($res, array_pop($res) . array_shift($tmp2));
			$res = array_merge($res, $tmp2);
		}
	}

	if (!$ignore_empty && !$trim_lines && !$fix_dquote) {
		return $res;
	}

	$out = array();
	for ($i = 0; $i < count($res); $i++) {
		$line = trim($res[$i]);

		if ($fix_dquote && mb_substr($line, 0, 1) == '"' && mb_substr($line, -1) == '"') {
			$res[$i] = str_replace('""', '"', $res[$i]);
			$line = trim($res[$i]);
		}

		if ($ignore_empty && mb_strlen($line) == 0) {
			continue;
		}

		if ($trim_lines) {
			array_push($out, $line);
		}
		else {
			array_push($out, $res[$i]);
		}
	}

	return $out;
}


/**
 * Split text into key value hash. Keys must not start with "@@" or "@_". 
 * Split text at $d2 (|#|) into lines. Split lines at first $d1 (=) into key value.
 * If key is not found return $text or use "@_N" as key (N is autoincrement 1, 2, ...) if mulitple keys are missing.
 * If key already exists rename to key.N (N is autoincrement 1, 2, ...).
 * If value starts with "@N" use conf[@@N]="sd1","sd2" and set value = conf2kv(value, sd1, sd2).
 * All keys and values are trimmed. Use Quote character ["] to preserve whitespace and delimiter.
 * Use double quote [""] to escape ["]. If $d1 is empty return array with $d2 as delimiter.
 *
 * @param string $text
 * @param string $d1 (default is "=")
 * @param string $d2 (default is "|#|")
 * @param hash ikv (config hash - default [ ])
 * @return string|array|hash
 */
function conf2kv($text, $d1 = '=', $d2 = '|#|', $ikv = array()) {
	$ld1 = mb_strlen($d1);

	if ($ld1 == 0 || mb_strpos($text, $d1) === false) {
		$res = $text;

		if (mb_strlen($d2) > 0 && mb_strpos($text, $d2) !== false) { 
			$res = csv_explode($text, $d2, '"', 4);
		}
		else if (mb_substr($res, 0, 1) == '"' && mb_substr($res, -1) == '"'){
			$res = mb_substr($res, 1, -1);
		}

		return $res;
	}

	$tmp = csv_explode($text, $d2, '"', 15);
	$kv = array();
	$kn = array();
	$n = 1;

	foreach ($tmp as $line) {
		if (($pos = mb_strpos($line, $d1)) > 0) {
			$key = trim(mb_substr($line, 0, $pos));
			$value = trim(mb_substr($line, $pos + $ld1));

			if (isset($kv[$key]) && mb_substr($key, 0, 2) != '@') {
				if (!isset($kn[$key])) {
					$kn[$key] = 0;
				}

				$kn[$key]++;
				$key .= '.'.$kn[$key];
			}
		}
		else {
			$key = '@_'.$n;
			$value = $line;
			$n++;
		}

		if (preg_match('/^(@[0-9]+)\s(.+)$/s', $value, $match)) {
			$sf = $match[1];
			if (isset($ikv[$sf])) {
				$kv[$key] = conf2kv(trim($match[2]), $ikv[$sf][0], $ikv[$sf][1], $ikv);
			}
		}
		else if (mb_substr($key, 0, 2) == '@@') {
			if (mb_substr($value, 0, 1) == '"' && mb_substr($value, -1) == '"') {
				$ikv[mb_substr($key, 1)] = explode('","', mb_substr($value, 1, -1));
			}
		}
		else {
			if (mb_substr($value, 0, 1) == '"' && mb_substr($value, -1) == '"') {
				$value = mb_substr($value, 1, -1);
			}

			$kv[$key] = $value;
		}
	}

	if ($n == 2 && count($kv) == 1 && isset($kv['@_1'])) {
		return $kv['@_1'];
	}

	return $kv;
}


/**
 * Return month name. Month is from [1,12].
 *
 * @param int $month
 * @return string
 */
function monthName($month) {
	global $settings_LANGUAGE;

	$month = ($month > 100000) ? intval(mb_substr($month, -2)) : intval($month);

	if ($month < 1 || $month > 12) {
		throw new Exception('invalid month', $month);
	}

	$lang = $settings_LANGUAGE;

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

