<?php

namespace rkphplib\lib;

if (!defined('SETTINGS_LOG_ERROR')) {
  /** @define string SETTINGS_LOG_ERROR = '/tmp/php.fatal' */
  define('SETTINGS_LOG_ERROR', '/tmp/php.fatal');
}

if (!defined('SETTINGS_TIMEZONE')) {
  /** @define string SETTINGS_TIMEZONE = Auto-Detect */
  date_default_timezone_set(@date_default_timezone_get());
  define('SETTINGS_TIMEZONE', date_default_timezone_get());
}
else {
  date_default_timezone_set(SETTINGS_TIMEZONE);
}


/**
 * Log error message (add timestamp and trace information).
 *
 * Disable logging with SETTINGS_LOG_ERROR = 0,
 * Enable logging to default with SETTINGS_LOG_ERROR = 1 (default).
 * Enable logging to file with SETTINGS_LOG_ERROR = 'path/error.log'.
 * Overwrite default define('SETTINGS_LOG_ERROR', '/tmp/php.fatal')
 * before inclusion of this file.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string $msg
 */
function log_error($msg) {

	if (!defined('SETTINGS_LOG_ERROR') || empty(SETTINGS_LOG_ERROR)) {
		return;
	}

	list($msec, $ts) = explode(" ", microtime());
	$log = '['.date('YmdHis', $ts).'.'.(1000 * round((float)$msec, 3));

	if (!empty($_SERVER['REMOTE_ADDR'])) { 
		$log .= $_SERVER['REMOTE_ADDR'];
	}

	if (isset($_SERVER['SCRIPT_FILENAME']) && isset($_SERVER['QUERY_STRING'])) {
	  $log .= '] '.$_SERVER['SCRIPT_FILENAME'].$_SERVER['QUERY_STRING']."\n$msg";
	}
	else {
		$log .= "] $msg";
	}

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

	if (mb_strlen(SETTINGS_LOG_ERROR) > 1) {
		error_log($log."\n", 3, SETTINGS_LOG_ERROR);
	}
	else {
		error_log($log);
	}
}

