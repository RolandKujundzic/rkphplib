<?php

namespace rkphplib;

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

	$default_log_dir = 'data/log/exception';

	if (defined('SETTINGS_LOG_EXCEPTION')) {
		if (!empty(SETTINGS_LOG_EXCEPTION)) {
			$stack = debug_backtrace();
			self::logTrace($stack);
		}
	}
	else if (is_dir($default_log_dir) && is_readable($default_log_dir)) {
		define('SETTINGS_LOG_EXCEPTION', $default_log_dir);
		$stack = debug_backtrace();
		self::logTrace($stack);
	}
}


/**
 * Log debug_backtrace to SETTINGS_LOG_EXCEPTION/NAME.json.
 * Abort was in stack[1].
 *
 * @param array $stack
 */
private static function logTrace($stack) {
	require_once(__DIR__.'/JSON.class.php');
	require_once(__DIR__.'/File.class.php');

	if (!defined('SETTINGS_TIMEZONE')) {
		/** @define string SETTINGS_TIMEZONE = Auto-Detect */
		date_default_timezone_set(@date_default_timezone_get());
		define('SETTINGS_TIMEZONE', date_default_timezone_get());
	}
	else {
		date_default_timezone_set(SETTINGS_TIMEZONE);
	}

	$last = $stack[1];
	list($msec, $ts) = explode(" ", microtime());
	$last['TIME'] = date('YmdHis', $ts).'.'.(1000 * round((float)$msec, 3));
	
	$add_server = [ 'REMOTE_ADDR', 'SCRIPT_FILENAME', 'QUERY_STRING' ];
	foreach ($add_server as $key) {
		if (!empty($_SERVER[$key])) { 
			$last[$key] = $_SERVER[$key];
		}
	}

	$save_as = md5($last['file'].':'.$last['line']).'.'.$last['TIME'];
	File::save(SETTINGS_LOG_EXCEPTION.'/'.$save_as.'.last.json', JSON::encode($last));
	File::save(SETTINGS_LOG_EXCEPTION.'/'.$save_as.'.stack.json', JSON::encode($stack));
}


}

