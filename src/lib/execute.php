<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/Exception.class.php');

use rkphplib\Exception;



/**
 * Execute shell command.
 * Command parameter {:=param} are replaced with escaped (\') hash values
 * Append [ 2>&1] to command to catch stderr. Append
 * [> /dev/null 2> /dev/null &] for background job.
 * Append [ && echo $! > /dev/null 2>&1 & ] for background job with pid output (= return value).
 * 
 * @param string $cmd e.g. "rm -f {:=file}"
 * @param hash|empty $parameter optional parameter hash (default = null)
 * @param boolean $abort if true throw exception if error (default = true)
 * @return string|false (false if not $abort and error)
 */
function execute($cmd, $parameter = null, $abort = true) {

	if (empty($cmd) || !is_string($cmd)) {
		throw new Exception('invalid command', print_r($cmd, true));
	}

	if (is_array($parameter)) {
		foreach ($parameter as $key => $value) {
			$cmd = str_replace(TAG_PREFIX.$key.TAG_SUFFIX, escapeshellarg($value), $cmd);
		}
	}

	$output_msg = '';

	if ($cmd) {
		$output = array();
		$retval = -1;
		$cmd_out = exec($cmd, $output, $retval);
    
		$output_msg = (is_array($output) && count($output) > 0) ? join("\r\n", $output) : $cmd_out;
	}

	if ($retval) {
		if ($abort) {
			throw new Exception('external execution failed', "Command:\r\n".$cmd."\r\nOutput:\r\n".$output_msg);
		}
		else {
			$res = false;
		}
	}
	else {
		$res = $output_msg;
	}

	return $res;
}


