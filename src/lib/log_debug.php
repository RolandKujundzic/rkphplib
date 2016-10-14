<?php

namespace rkphplib\lib;

require_once(__DIR__.'/config.php');


/**
 * Log debug message.
 *
 * Disable debug log with SETTINGS_LOG_DEBUG = 0,
 * Enable logging to default with SETTINGS_LOG_DEBUG = 1 (default).
 * Enable logging to file with SETTINGS_LOG_DEBUG = 'path/debug.log'.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string $msg
 * @param bool $prepend_info (default = false, prepend timestamp and trace information)
 */
function log_debug($msg, $prepend_info = false) {

	if (!defined(SETTINGS_LOG_DEBUG) || empty(SETTINGS_LOG_DEBUG)) {
		return;
	}

	if ($prepend_info) {
		list($msec, $ts) = explode(" ", microtime());
		$log = '['.date('YdmHis', $ts).'.'.(1000 * round((float)$msec, 3));

		if (!empty($_SERVER['REMOTE_ADDR'])) { 
			$log .= $_SERVER['REMOTE_ADDR'];
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

