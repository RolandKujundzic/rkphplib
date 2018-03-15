<?php

namespace rkphplib\lib;

if (!defined('SETTINGS_LOG_DEBUG')) {
  /** @define string SETTINGS_LOG_DEBUG = '/tmp/php.log' */
	define('SETTINGS_LOG_DEBUG', '/tmp/php.log');
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
 * Log debug message.
 *
 * Disable debug log with SETTINGS_LOG_DEBUG = 0,
 * Enable logging to default with SETTINGS_LOG_DEBUG = 1 (default).
 * Enable logging to file with SETTINGS_LOG_DEBUG = 'path/debug.log'.
 * Overwrite default define('SETTINGS_LOG_DEBUG', '/tmp/php.warn')
 * before inclusion of this file.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string|vector $msg
 * @param bool $prepend_info (default = false, prepend timestamp and trace information)
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

