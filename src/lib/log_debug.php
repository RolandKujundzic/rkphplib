<?php

namespace rkphplib\lib;

if (!defined('DOCROOT') && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT']) && is_dir($_SERVER['CONTEXT_DOCUMENT_ROOT'].'/data/.log')) {
	define('DOCROOT', $_SERVER['CONTEXT_DOCUMENT_ROOT']);
}

if (!defined('SETTINGS_LOG_DEBUG')) {
	/** @define string SETTINGS_LOG_DEBUG = '[DOCROOT/data/.log|/tmp]/php.log' */
	if (defined('DOCROOT') && is_dir(DOCROOT.'/data/.log')) {
		define('SETTINGS_LOG_DEBUG', DOCROOT.'/data/.log/php.log');
	}
	else {
		define('SETTINGS_LOG_DEBUG', '/tmp/php.log');
	}
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
 * Log debug message (string or vector). If $prepend_info = true (default = false)
 * prepend timestamp and trace information.
 *
 * Disable debug log with SETTINGS_LOG_DEBUG = 0,
 * Enable logging to default error_log with SETTINGS_LOG_DEBUG = 1.
 * Enable logging to file with SETTINGS_LOG_DEBUG = 'path/debug.log'.
 * If 'SETTINGS_LOG_DEBUG' is undefined use 'data/.log/php.log' (if data/.log exists)
 * or '/tmp/php.log'.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function log_debug($msg, $prepend_info = false) {

	if (!defined('SETTINGS_LOG_DEBUG') || empty(SETTINGS_LOG_DEBUG)) {
		return;
	}

	if (is_array($msg)) {
		$out = $msg[0].' ';

		for ($i = 1; $i < count($msg); $i++) {
			if (is_string($msg[$i])) {
				$out .= $i.'=['.$msg[$i];
			}
			else {
				$out .= $i.'=[';
				foreach ($msg[$i] as $key => $value) {
					$out .= '('.$key.'|'.$value.')';
				}
			}

			$out .= '] ';
		}

		$msg = $out;
	}

	if ($prepend_info) {
		list($msec, $ts) = explode(" ", microtime());
		$log = '['.date('YmdHis', $ts).'.'.(1000 * round((float)$msec, 3));

		if (!empty($_SERVER['REMOTE_ADDR'])) { 
			$log .= '@'.$_SERVER['REMOTE_ADDR'];
		}

		if (isset($_SERVER['SCRIPT_FILENAME']) && isset($_SERVER['QUERY_STRING'])) {
		  $log .= '] '.$_SERVER['SCRIPT_FILENAME'].$_SERVER['QUERY_STRING']."\n$msg";
		}
		else {
			$log .= "] $msg";
		}
	}
	else {
		$log = $msg;
	}

	if (mb_strlen(SETTINGS_LOG_DEBUG) > 1) {
		error_log($log."\n", 3, SETTINGS_LOG_DEBUG);
	}
	else {
		error_log($log);
	}
}

