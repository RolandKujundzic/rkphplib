<?php

namespace rkphplib\lib;

if (!defined('DOCROOT') && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT']) && is_dir($_SERVER['CONTEXT_DOCUMENT_ROOT'].'/data/.log')) {
	define('DOCROOT', $_SERVER['CONTEXT_DOCUMENT_ROOT']);
}

if (!defined('SETTINGS_LOG_DEBUG')) {
	// @define string SETTINGS_LOG_DEBUG = '[DOCROOT/data/.log|/tmp]/php.log'
	if (defined('DOCROOT') && is_dir(DOCROOT.'/data/.log')) {
		define('SETTINGS_LOG_DEBUG', DOCROOT.'/data/.log/php.log');
	}
	else {
		define('SETTINGS_LOG_DEBUG', '/tmp/php.log');
	}
}

if (!defined('SETTINGS_TIMEZONE')) {
  // @define string SETTINGS_TIMEZONE = Auto-Detect
  date_default_timezone_set(@date_default_timezone_get());
  define('SETTINGS_TIMEZONE', date_default_timezone_get());
}
else {
  date_default_timezone_set(SETTINGS_TIMEZONE);
}

if (!isset($GLOBALS['SETTINGS'])) {
	$GLOBALS['SETTINGS'] = [];
}


/**
 * Log debug message (string or vector). 
 * Disable debug log with SETTINGS_LOG_DEBUG = 0,
 * Enable logging to default error_log with SETTINGS_LOG_DEBUG = 1.
 * Enable logging to file with SETTINGS_LOG_DEBUG = 'path/debug.log'.
 * Log to STDOUT with 'print|php://STDOUT|/dev/stdout' and to STDERR with 'php://STDERR|/dev/stderr'.
 * If 'SETTINGS_LOG_DEBUG' is undefined use 'data/.log/php.log' (if data/.log exists) or '/tmp/php.log'.
 * Use $GLOBALS['SETTINGS']['LOG_DEBUG'] = '...' to overwrite SETTINGS_LOG_DEBUG. 
 * Use 2^N flag:
 *
 * 1: prepend timestamp and trace information
 * 2, 4, 8, 16: log level (lower = less important)
 * 
 * Use SETTINGS_LOG_DEBUG_LEVEL ($GLOBALS['SETTINGS']['LOG_DEBUG_LEVEL']) = 16|24|28|30 to
 * log messages with LEVEL & LOG_DEBUG_LEVEL > 0. Use 30 to log all and 16 to log only most important.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function log_debug(string $msg, int $flag = 0) : void {
	if (!empty($GLOBALS['SETTINGS']['LOG_DEBUG'])) {
		$log_to = $GLOBALS['SETTINGS']['LOG_DEBUG'];
	}
	else if (defined('SETTINGS_LOG_DEBUG') && !empty(SETTINGS_LOG_DEBUG)) {
		$log_to = SETTINGS_LOG_DEBUG;
	}
	else {
		return;
	}

	$log_level = 0;
	if (!empty($GLOBALS['SETTINGS']['LOG_DEBUG_LEVEL'])) {
		$log_level = $GLOBALS['SETTINGS']['LOG_DEBUG_LEVEL'];
	}
	else if (defined('SETTINGS_LOG_DEBUG_LEVEL') && !empty(SETTINGS_LOG_DEBUG_LEVEL)) {
		$log_level = SETTINGS_LOG_DEBUG_LEVEL;
	}

	if ($log_level > 0 && ($flag & $log_level) == 0) {
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

	static $last_prefix = '';
	$prefix = '';

	if ($flag & 1 == 1) {
		list($msec, $ts) = explode(" ", microtime());
		$prefix = '['.date('YmdHis', $ts).'.'.(1000 * round((float)$msec, 3));

		if (!empty($_SERVER['REMOTE_ADDR'])) { 
			$prefix .= '@'.$_SERVER['REMOTE_ADDR'];
		}

		$prefix .= (isset($_SERVER['SCRIPT_FILENAME']) && isset($_SERVER['QUERY_STRING'])) ?
			'] '.$_SERVER['SCRIPT_FILENAME'].'?'.$_SERVER['QUERY_STRING'].' - ' : '] ';
	}

	if (preg_match('/^([a-zA-Z0-9_\.]+\:[0-9]+>) /', $msg, $match)) {
		$prefix .= $match[1]."\n";
		$msg = str_replace($match[1].' ', '',  $msg);
	}

	if ($last_prefix == $prefix) {
		$prefix = '';
	}
	else {
		$last_prefix = $prefix;
		$prefix = '··· '.$prefix;
	}

	if ($log_to == 'print') {
		print $prefix.$msg."\n";
	}
	else if (mb_strlen($log_to) > 1) {
		error_log($prefix.$msg."\n", 3, $log_to);
	}
	else {
		error_log($prefix.$msg."\n");
	}
}


