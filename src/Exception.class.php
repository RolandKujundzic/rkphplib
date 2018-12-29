<?php

namespace rkphplib;


if (!defined('TAG_PREFIX')) {
  /** @define string TAG_PREFIX = '{:=' */
  define('TAG_PREFIX', '{:=');
}

if (!defined('TAG_SUFFIX')) {
  /** @define string TAG_SUFFIX = '}' */
  define('TAG_SUFFIX', '}');
}



/**
 * Custom exception with two parameter constructor. Log debug_backtrace if save path
 * SETTINGS_LOG_EXCEPTION is set. If directory data/log/exception exist and 
 * SETTINGS_LOG_EXCEPTION is not set use SETTINGS_LOG_EXCEPTION=data/log/exception.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class Exception extends \Exception {

/** @var string $internal_message error message detail you don't want to expose */
public $internal_message = '';


/**
 * Class constructor.
 *
 * @param string $message error message
 * @param string $internal_message error message detail
 */
public function __construct($message, $internal_message = '') {
	parent::__construct($message);
	$this->internal_message = $internal_message;

	if (!defined('DOCROOT')) {
		return;
	}

	$default_log_dir = DOCROOT.'/data/log/exception';

	if (defined('SETTINGS_LOG_EXCEPTION')) {
		if (!empty(SETTINGS_LOG_EXCEPTION)) {
			$stack = debug_backtrace();
			self::logTrace($stack);
		}
	}
	else if (is_dir($default_log_dir) && is_readable($default_log_dir) && is_writeable($default_log_dir)) {
		define('SETTINGS_LOG_EXCEPTION', $default_log_dir);
		$stack = debug_backtrace();
		self::logTrace($stack);
	}
}


/**
 * Log debug_backtrace to SETTINGS_LOG_EXCEPTION/NAME.json (or *.dump|.ser is json encode fails).
 * Abort was in stack[1].
 *
 * @param array $stack
 */
private static function logTrace($stack) {
	require_once(__DIR__.'/File.class.php');

	if (!defined('SETTINGS_TIMEZONE')) {
		/** @define string SETTINGS_TIMEZONE = Auto-Detect */
		date_default_timezone_set(@date_default_timezone_get());
		define('SETTINGS_TIMEZONE', date_default_timezone_get());
	}
	else {
		date_default_timezone_set(SETTINGS_TIMEZONE);
	}

	$last = isset($stack[1]) ? $stack[1] : $stack[0];
	list($msec, $ts) = explode(" ", microtime());
	$last['TIME'] = date('YmdHis', $ts).'.'.(1000 * round((float)$msec, 3));
	
	$add_server = [ 'REMOTE_ADDR', 'SCRIPT_FILENAME', 'QUERY_STRING' ];
	foreach ($add_server as $key) {
		if (!empty($_SERVER[$key])) { 
			$last[$key] = $_SERVER[$key];
		}
	}

	$save_as  = (!empty($last['file']) && !empty($last['line'])) ? md5($last['file'].':'.$last['line']) : md5($last['TIME']);
	$save_as .= '.'.$last['TIME'];

	File::saveJSON(SETTINGS_LOG_EXCEPTION.'/'.$save_as.'.last.json', $last);
	File::saveJSON(SETTINGS_LOG_EXCEPTION.'/'.$save_as.'.stack.json', $stack);
}


}

