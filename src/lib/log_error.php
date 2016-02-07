<?php

namespace rkphplib\lib;

require_once(__DIR__.'/config.php');


/**
 * Log error message (add timestamp and trace information).
 * If file is empty do nothing.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string $msg
 * @param string $file 0|1|filename 
 */
function log_error($msg, $file = 1) {

	if (!$file) {
		return;
	}

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

	$trace = debug_backtrace();
	unset($trace[0]); // Remove call to this function from stack trace
	$i = 1;

	foreach($trace as $t) {
		$log .= "\n#$i ".$t['file'] ."(" .$t['line']."): "; 

		if (!empty($t['class'])) {
			$log .= $t['class'] . "->"; 
		}

		$log .= $t['function']."()";
		$i++;
	}

	if (mb_strlen($file) > 1) {
		error_log($log."\n", 3, $file);
	}
	else {
		error_log($log);
	}
}

