<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/Exception.class.php');

use rkphplib\Exception;



/**
 * Execute shell command (e.g. rm -f {:=file}). Optional parameter $flag is int|bool|string.
 * Command parameter {:=param} are replaced with escaped (\') hash values.
 * Use {:=_param} for unescaped replace. Append [ 2>&1] to command to catch stderr. 
 * Append [> /dev/null 2> /dev/null &] for background job.
 * Append [ && echo $! > /dev/null 2>&1 & ] for background job with pid output (= return value).
 * 
 * Flag is either 2^n, true|false or "test ..." string. Flag Options:
 *
 *  - true: same as 2^0
 *  - false: same as 0
 *  - 2^0: throw exception if error
 *  - 2^1: change command to "($cmd) 2>&1"
 *  - test -f 'some/file': change command to "if test -f 'some/file'; then ($cmd) 2>&1; fi"
 *
 * Return (string|false) last line of output or true, false if not [flag & 2^0] and error.
 */
function execute(string $cmd, ?array $parameter = null, $flag = 1) {

	if (empty($cmd) || !is_string($cmd)) {
		throw new Exception('invalid command', print_r($cmd, true));
	}

	if (is_array($parameter)) {
		foreach ($parameter as $key => $value) {
			if (substr($key, 0, 1) == '_') {
				$cmd = str_replace(TAG_PREFIX.$key.TAG_SUFFIX, $value, $cmd);
			}
			else {
				$cmd = str_replace(TAG_PREFIX.$key.TAG_SUFFIX, escapeshellarg($value), $cmd);
			}
		}
	}

	if (is_bool($flag)) {
		if ($flag) {
			$flag = 1;
		}
		else {
			$flag = 0;
		}
	}
	else if (is_string($flag)) {
		if (substr($flag, 0, 5) == 'test ') {
			$cmd = 'if '.$flag.'; then '.$cmd.' 2>&1; fi';
		}

		$flag = 1;
	}
  else if (is_integer($flag)) {
		if (($flag & 2) == 2) {
			$cmd = "($cmd) 2>&1";
		}
	}

	$output_msg = '';

	// \rkphplib\lib\log_debug("lib/execute($cmd, ...)> $cmd");
	if ($cmd) {
		$output = array();
		$retval = -1;
		$cmd_out = exec($cmd, $output, $retval);
    
		$output_msg = (is_array($output) && count($output) > 0) ? join("\r\n", $output) : $cmd_out;
	}

	if ($retval) {
		if (($flag & 1) == 1) {
			throw new Exception('external execution failed', "Command:\r\n".$cmd."\r\nOutput:\r\n".$output_msg);
		}
		else {
			$res = false;
		}
	}
	else {
		if (($flag & 1) != 1 && strlen($output_msg) == 0) {
			$res = true;
		}
		else {
			$res = $output_msg;
		}
	}

	return $res;
}

