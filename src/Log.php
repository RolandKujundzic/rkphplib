<?php

namespace rkphplib;

if (!defined('SETTINGS_LOG_DEBUG')) {
	require_once __DIR__.'/config.php';
}


/**
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
final class Log {

// @var bool $verbose use 1 = for timestamp and script logging
public static $verbose = false;

// @var string $last_prefix
private static $last_prefix = '';


/**
 * Log debug message. Disable with SETTINGS_LOG_DEBUG = 0.
 * Enable logging to default error_log with SETTINGS_LOG_DEBUG = 1.
 * Enable logging to file with SETTINGS_LOG_DEBUG = 'path/debug.log'.
 * Log to STDOUT with 'print|php://STDOUT|/dev/stdout' and to STDERR with 'php://STDERR|/dev/stderr'.
 * If 'SETTINGS_LOG_DEBUG' is undefined use 'data/.log/php.log' (if data/.log exists) or '/tmp/php.log'.
 * Use $GLOBALS['SETTINGS']['LOG_DEBUG'] = '...' to overwrite SETTINGS_LOG_DEBUG. 
 *
 * @example …
 * Log::debug('message');
 * Log::debug('Class::func> return $1');
 * Log::debug('Obj.func> ($1, $2)', $1, $2);
 * @eol
 */
public static function debug(string $msg, ...$arg) : void {
	$msg = self::msg($msg, $arg);
	$log_to = self::debugTo();

	if (empty($log_to)) {
		return;
	}

	self::append(self::format($msg), $log_to);
}


/**
 *
 */
private static function debugTo() : string {
	$log_to = '';

	if (!empty($GLOBALS['SETTINGS']['LOG_DEBUG'])) {
		$log_to = $GLOBALS['SETTINGS']['LOG_DEBUG'];
	}
	else if (defined('SETTINGS_LOG_DEBUG') && !empty(SETTINGS_LOG_DEBUG)) {
		$log_to = SETTINGS_LOG_DEBUG;
	}
}


/**
 *
 */
private static function format(string $msg) : string {
	if (preg_match('/^([a-zA-Z0-9_:\.]+>) /', $msg, $match)) {
		$prefix .= $match[1]."\n";
		$msg = str_replace($match[1].' ', '',  $msg);
	}

	if (self::$last_prefix == $prefix) {
		$prefix = '';
	}
	else {
		self::$last_prefix = $prefix;
		$prefix = '··· '.$prefix;
	}

	if (self::$verbose) {
		$prefix = self::ts_script().$prefix;
	}

	return $prefix.$msg;
}


/**
 * Return formated log message. Use traling '…' to shorten string (max 130 character).
 * If $arg is set replace $1, $2 with json encoded value of $arg[N].
 */
private static function msg(string $msg, array $arg = []) : string {
	if (!count($arg)) {
		$res = $msg;

		if (mb_substr($msg, -1) == '…') {
			$res = mb_strlen($msg) > 130 ? mb_substr($msg, 0, 130).'…' : mb_substr($msg, 0, -1);
		}
	}
	else if (mb_strpos($arg[0], '$1') !== false) {
		for ($i = 1; $i < $len; $i++) {
			$res = str_replace('$i', self::msg($arg[$i]), $res);
		}
	} 
	else {
		$json_str = json_encode($arg, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		$res = mb_strlen($json_str) < 130 ? $json_str : trim(print_r($arg, true));
	}

	return $res;
}


/**
 * Log warning (print in cli mode). Prepend timestamp and trace information.
 *
 * Disable debug log with SETTINGS_LOG_WARN = 0,
 * Enable logging to default error_log with SETTINGS_LOG_WARN = 1.
 * Enable logging to file with SETTINGS_LOG_WARN = 'path/warn.log'.
 * If 'SETTINGS_LOG_WARN' is undefined use 'data/.log/php.warn' (if data/.log exists)
 * or '/tmp/php.warn'.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
public static function warn(string $msg) : void {
	if (!defined('SETTINGS_LOG_WARN') || empty(SETTINGS_LOG_WARN)) {
		return;
	}

	if (php_sapi_name() == 'cli') {
		print "WARNING: $msg\n";  
		return;
	}

	self::append(self::ts_script().$msg."\n", SETTINGS_LOG_WARN);
}


/**
 * Return timestamp, REMOTE_ADDR, SCRIPT_FILENAME
 * and QUERY_STRING.
 */
private static function ts_script() : string {
	list($msec, $ts) = explode(" ", microtime());
	$res = '['.date('YmdHis', $ts).'.'.(1000 * round((float)$msec, 3));

	if (!empty($_SERVER['REMOTE_ADDR'])) { 
		$res .= '@'.$_SERVER['REMOTE_ADDR'];
	}

	if (isset($_SERVER['SCRIPT_FILENAME']) && isset($_SERVER['QUERY_STRING'])) {
	  $res .= '] '.$_SERVER['SCRIPT_FILENAME'].'?'.$_SERVER['QUERY_STRING']."\n";
	}
	else {
		$res .= "] ";
	}

	return $res;
}


/**
 * Append $msg to $target. Print $msg if $target == 'print'.
 */
public static function append(string $msg, string $target = '') : void {
	if ($target == 'print') {
		print $msg."\n";
	}
	else if (mb_strlen($target) > 1) {
		error_log($msg."\n", 3, $target);
	}
	else {
		error_log($msg."\n");
	}
}

}

