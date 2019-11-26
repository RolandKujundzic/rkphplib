<?php

namespace rkphplib\lib;

if (!defined('SETTINGS_LOG_WARN')) {
	// @define string SETTINGS_LOG_WARN = '[DOCROOT/data/.log|/tmp]/php.warn'
	if (defined('DOCROOT') && is_dir(DOCROOT.'/data/.log')) {
		define('SETTINGS_LOG_WARN', DOCROOT.'/data/.log/php.warn');
	}
	else {
		define('SETTINGS_LOG_WARN', '/tmp/php.warn');
	}
}


/**
 * Log warning. Prepend timestamp and trace information.
 *
 * Disable debug log with SETTINGS_LOG_WARN = 0,
 * Enable logging to default error_log with SETTINGS_LOG_WARN = 1.
 * Enable logging to file with SETTINGS_LOG_WARN = 'path/warn.log'.
 * If 'SETTINGS_LOG_WARN' is undefined use 'data/.log/php.warn' (if data/.log exists)
 * or '/tmp/php.warn'.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function log_warn(string $msg) : void {

	if (!defined('SETTINGS_LOG_WARN') || empty(SETTINGS_LOG_WARN)) {
		return;
	}

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

	if (mb_strlen(SETTINGS_LOG_WARN) > 1) {
		error_log($log."\n", 3, SETTINGS_LOG_WARN);
	}
	else {
		error_log($log);
	}
}

