<?php

namespace rkphplib;

/**
 * Custom exception with two parameter constructor.
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
}


/**
 * Log exception data for debugging.
 *
 * Default is SETTINGS_LOG_EXCEPTION = 'data/log/exception/class.method.dmyhis.json'.
 * Disable exception log with SETTINGS_LOG_EXCEPTION = 0.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string $msg
 * @param string $method
 * @param array $parameter
 */
public static function logCall($class, $method, $parameter = []) {

	if (defined('SETTINGS_LOG_EXCEPTION') && empty(SETTINGS_LOG_EXCEPTION)) {
		return;
	}

	require_once(__DIR__.'/JSON.class.php');
	require_once(__DIR__.'/File.class.php');
	require_once(__DIR__.'/Dir.class.php');

	if (!defined('SETTINGS_LOG_EXCEPTION')) {
		/** @define string SETTINGS_LOG_EXCEPTION = 'data/log/exception' */
		define('SETTINGS_LOG_EXCEPTION', 'data/log/exception');
	}

	if (!defined('SETTINGS_TIMEZONE')) {
		/** @define string SETTINGS_TIMEZONE = Auto-Detect */
		date_default_timezone_set(@date_default_timezone_get());
		define('SETTINGS_TIMEZONE', date_default_timezone_get());
	}
	else {
		date_default_timezone_set(SETTINGS_TIMEZONE);
	}

	Dir::create(SETTINGS_LOG_EXCEPTION, 0, true);
	$data = [ 'class' => $class, 'method' => $method, 'parameter' => $parameter ];

	list($msec, $ts) = explode(" ", microtime());
	$data['time'] = date('YmdHis', $ts).'.'.(1000 * round((float)$msec, 3));
	
	$add_server = [ 'REMOTE_ADDR', 'SCRIPT_FILENAME', 'QUERY_STRING' ];
	foreach ($add_server as $key) {
		if (!empty($_SERVER[$key])) { 
			$data[$key] = $_SERVER[$key];
		}
	}

	File::save(SETTINGS_LOG_EXCEPTION."/$class.$method.".$data['time'].'.json', JSON::encode($data));
}


}

