<?php

namespace rkphplib;


if (!defined('TAG_PREFIX')) {
  // @define string TAG_PREFIX = '{:='
  define('TAG_PREFIX', '{:=');
}

if (!defined('TAG_SUFFIX')) {
  // @define string TAG_SUFFIX = '}'
  define('TAG_SUFFIX', '}');
}

if (!defined('SETTINGS_LOG_ERROR')) {
	// @define string SETTINGS_LOG_ERROR = '[DOCROOT/data/.log|/tmp]/php.fatal'
	if (defined('DOCROOT') && is_dir(DOCROOT.'/data/.log')) {
		define('SETTINGS_LOG_ERROR', DOCROOT.'/data/.log/php.fatal');
	}
	else {
		define('SETTINGS_LOG_ERROR', '/tmp/php.fatal');
	}
}

if (!defined('SETTINGS_TIMEZONE')) {
  date_default_timezone_set(@date_default_timezone_get());
  // @define string SETTINGS_TIMEZONE = Auto-Detect
  define('SETTINGS_TIMEZONE', date_default_timezone_get());
}
else {
  date_default_timezone_set(SETTINGS_TIMEZONE);
}


/**
 * Custom exception with two parameter constructor. Log debug_backtrace if save path
 * SETTINGS_LOG_EXCEPTION is set. If directory data/.log/exception exist and 
 * SETTINGS_LOG_EXCEPTION is not set use SETTINGS_LOG_EXCEPTION=data/.log/exception.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class Exception extends \Exception {

// @var string $internal_message error message detail you don't want to expose
public $internal_message = '';


/**
 * Class constructor. Allow optional error details besides the error message.
 */
public function __construct(string $message, string $internal_message = '') {
	parent::__construct($message);
	$this->internal_message = $internal_message;

	if (!defined('DOCROOT')) {
		return;
	}

	$default_log_dir = DOCROOT.'/data/.log/exception';

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
 * Enhance error messages.
 */
public function append(string $message, string $internal = '') : void {
	$this->message = trim($message."\n".$this->message);
	$this->internal_message = trim($internal."\n".$this->internal_message); 
}


/**
 * Log debug_backtrace to SETTINGS_LOG_EXCEPTION/NAME.json.
 */
private static function logTrace(array $stack) : void {
	require_once __DIR__.'/File.class.php';

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


/**
 * Log error message (add timestamp and trace information).
 *
 * Disable logging with SETTINGS_LOG_ERROR = 0,
 * Enable logging to default error_log with SETTINGS_LOG_ERROR = 1.
 * Enable logging to file with SETTINGS_LOG_ERROR = 'path/error.log'.
 * If SETTINGS_LOG_ERROR is undefined use 'data/.log/php.fatal' 
 * if (data/.log exists) or /tmp/php.fatal.
 */
public static function logError(string $msg) : void {

	if (!defined('SETTINGS_LOG_ERROR') || empty(SETTINGS_LOG_ERROR)) {
		return;
	}

	list($msec, $ts) = explode(" ", microtime());
	$log = '['.date('YmdHis', $ts).'.'.(1000 * round((float)$msec, 3));

	if (!empty($_SERVER['REMOTE_ADDR'])) { 
		$log .= $_SERVER['REMOTE_ADDR'];
	}

	$e = null;

	if (method_exists($msg, 'getMessage')) {
		$e = $msg;
		$msg = "\n\nABORT: ".$e->getMessage();
		$trace = $e->getFile()." on line ".$e->getLine()."\n".$e->getTraceAsString();
		$internal = property_exists($e, 'internal_message') ? "INFO: ".$e->internal_message : '';
	}

	if (isset($_SERVER['SCRIPT_FILENAME']) && isset($_SERVER['QUERY_STRING'])) {
		$log .= '] '.$_SERVER['SCRIPT_FILENAME'].$_SERVER['QUERY_STRING']."\n$msg";
	}
	else {
		$log .= "] $msg";
	}

	if (is_null($e)) {
		$trace = debug_backtrace();
		unset($trace[0]); // Remove call to this function from stack trace
		$i = 1;

		foreach($trace as $t) {
			if (!empty($t['file'])) {
				$log .= "\n#$i ".$t['file'] ."(" .$t['line']."): "; 
			}
			else {
				$log .= '???'.print_r($t, true).'???';
			}
	
			if (!empty($t['class'])) {
				$log .= $t['class'] . "->"; 
			}

			$log .= $t['function']."()";
			$i++;
		}
	}
	else {
		$log .= "\n$internal\n$trace";
	}

	if (mb_strlen(SETTINGS_LOG_ERROR) > 1) {
		error_log($log."\n", 3, SETTINGS_LOG_ERROR);
	}
	else {
		error_log($log);
	}
}


/**
 * Send "HTTP/1.1 $code Error" header ($code = 400|401|404|444) and exit. 
 * If message starts with @ajax or ($flag & 2^0) log and print error message with prefix "ERROR:".
 */
public static function httpError(int $code = 400, string $msg = '', int $flag = 0) : void {
	$error = [ '400' => 'Bad Request', '401' => 'Unauthorized', '404' => 'Not Found', '444' => 'No Response' ];

	if (!isset($error[$code])) {
		$code = 400;
	}

	if (php_sapi_name() == 'cli') {
		print "\nABORT: HTTP/1.1 ".$code.' '.$error[$code]."\n\n";
	}
	else {
		header('HTTP/1.1 '.$code.' '.$error[$code]);
	}
	
	if (empty($msg)) {
		$msg = 'httpError '.$code;
	}

	if (mb_substr($msg, 0, 6) == '@ajax ') {
		$msg = mb_substr($msg, 6);
		print 'ERROR: '.$msg;
	}

	if ($flag & 1) {
		print 'ERROR: '.$msg;
	}

	self::logError($msg);

	exit(1);
}


}
