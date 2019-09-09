<?php

namespace rkphplib\lib;


/**
 * Abort with syntax error if count($argv_example) != count($_SERVER['argv'] or if 
 * argv_example check failed. Exit with syntax message if $_SERVER['argv'][1] == '?' or 'help'.
 * Show APP_DESC if defined. Use APP instead of $_SERVER['argv'][0] if defined. 
 * Use '@file:path/to/file' to enable file exists check for parameter.
 * Use '@dir:path/to/directory' to enable directory exists check for parameter.
 * Use '@or:on|off' to ensure parameter is either 'on' or 'off'.
 * Use '@docroot' for getcwd == DOCROOT check.
 * Use --name=value for optional parameter name - define(PARAMETER_NAME, value).
 * Export APP_DESC if $desc is not empty (and APP_DESC undefined).
 * Define APP_HELP to show help syntax and return false (use APP_HELP=quiet to disable output).
 */
function syntax($argv_example = [], $desc = '') {

	if (!empty($desc) && !defined('APP_DESC')) {
		define('APP_DESC', $desc);
	}

	if (!defined('APP_HELP') && (empty($_SERVER['argv'][0]) || php_sapi_name() !== 'cli')) {
		print "\nERROR: run as cli\n\n\n";
		exit(1);
	}

	$app = defined('APP') ? APP : $_SERVER['argv'][0];

	$app_desc = '';
	if (defined('APP_DESC')) {
		$app_desc = "\n".APP_DESC."\n";
	}
	else if (count($argv_example) == 0) {
		$app_desc = "\nno arguments necessary\n";
	}
 
	$arg_num = (count($argv_example) > 0) ? count($argv_example) + 1 : 0;
	$is_error = false;
	$error_msg = '';

	for ($i = 0; !$is_error && $i < count($argv_example); $i++) {
		$param = $argv_example[$i];
		$pos = 0;

		if (substr($param, 0, 6) == '@file:') {
			if (!empty($_SERVER['argv'][$i + 1]) && !file_exists($_SERVER['argv'][$i + 1])) {
				$error_msg = 'no such file '.$_SERVER['argv'][$i + 1];
				$is_error = true;
			}

			$pos = 6;
		}
		else if (substr($param, 0, 5) == '@dir:') {
			if (!empty($_SERVER['argv'][$i + 1]) && !is_dir($_SERVER['argv'][$i + 1])) {
				$error_msg = 'no such directory '.$_SERVER['argv'][$i + 1];
				$is_error = true;
			}

			$pos = 5;
		}
		else if (substr($param, 0, 4) == '@or:') {
			if (empty($_SERVER['argv'][$i + 1]) || !in_array($_SERVER['argv'][$i + 1], explode('|', substr($param, 4)))) {
				$is_error = true;
			}

			$pos = 4;
		}
		else if ($param == '@docroot') {
			if (!defined('DOCROOT')) {
				$error_msg = "DOCROOT is undefined";
				$is_error = true;
			}
			else if (getcwd() != DOCROOT) {
				$error_msg = 'run in '.DOCROOT;
				$is_error = true;
			}

			$arg_num--;
		}
		else if (substr($param, 0, 2) == '--' && ($pos = strpos($param, '=')) > 2) {
			list ($key, $value) = explode('=', substr($param, 2), 2);
			define('PARAMETER_'.$key, $value);
			$arg_num--;
		}

		if ($pos > 0) {
			$argv_example[$i] = substr($argv_example[$i], $pos);
		}

		if (!empty($error_msg)) {
			$error_msg = "ERROR: $error_msg\n\n";
		}
	}

	$res = true;

	if (defined('APP_HELP')) {
		if (APP_HELP != 'quiet') {
			print "\nSYNTAX: $app ".join(' ', $argv_example)."\n$app_desc\n\n";
		}

		$res = false;
	}
	else if (!empty($_SERVER['argv'][1]) && ('?' == $_SERVER['argv'][1] || 'help' == $_SERVER['argv'][1])) {
		print "\nSYNTAX: $app ".join(' ', $argv_example)."\n$app_desc\n\n";
		exit(0);
	}
	else if ($is_error || ($arg_num > 0 && $arg_num != count($_SERVER['argv']))) {
		print "\nSYNTAX: $app ".join(' ', $argv_example)."\n$app_desc\n$error_msg\n";
		exit(1);
	}

	return $res;
}

