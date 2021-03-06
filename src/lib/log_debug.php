<?php

namespace rkphplib\lib;

if (!defined('SETTINGS_LOG_DEBUG')) {
	require_once __DIR__.'/config.php';
}


/**
 * Log debug message (string or vector). Disable with SETTINGS_LOG_DEBUG = 0.
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
 * @see log_debug_msg
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param any $msg
 */
function log_debug($msg, int $flag = 0) : void {
	$msg = log_debug_msg($msg);

	if (!empty($GLOBALS['SETTINGS']['LOG_DEBUG'])) {
		$log_to = $GLOBALS['SETTINGS']['LOG_DEBUG'];
	}
	else if (defined('SETTINGS_LOG_DEBUG') && !empty(SETTINGS_LOG_DEBUG)) {
		$log_to = SETTINGS_LOG_DEBUG;
	}

	if (empty($log_to)) {
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


/**
 * Return formated log message. Use traling '…' to shorten string (max 130 character).
 * If $msg is array and $msg[0] is '<1> ... <2> ... <n>' and #$msg=n+1
 * replace <k> with formatted $msg[k].
 *
 * @param any $msg
 */
function log_debug_msg($msg) : string {
	if (is_string($msg)) {
		$res = $msg;
		if (mb_substr($msg, -1) == '…') {
			$res = mb_strlen($msg) > 130 ? mb_substr($msg, 0, 130).'…' : mb_substr($msg, 0, -1);
		}
	}
	else if (is_array($msg) && !empty($msg[0]) && ($len = count($msg)) > 1 &&
						is_string($msg[0]) && mb_strpos($msg[0], '<'.($len - 1).'>') !== false) {
		$res = $msg[0];
		for ($i = 1; $i < $len; $i++) {
			$res = str_replace("<$i>", log_debug_msg($msg[$i]), $res);
		}
	} 
	else {
		$json_str = json_encode($msg, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		$res = mb_strlen($json_str) < 130 ? $json_str : trim(print_r($msg, true));
	}

	return $res;
}

