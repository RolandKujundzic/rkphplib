<?php

namespace rkphplib;

/**
 * Command Line Interface Helper. Parse Arguments, run Syntax Check in cli
 * Application.
 *
 * Define APP_HELP=1 to show help syntax and return false.
 * Use APP_HELP=quiet to disable output.
 *
 * @code
 * CLI::parse();
 * print_r($CLI::arg);
 * @eol
 *
 * @code
 * CLI::$desc = 'App Description';
 * if (!CLI::syntax([ '@file:path/to/config.json' ])) {
 *   return;
 * }
 * @eol
 *
 * @author Roland Kujundzic
 * @copyright 2019-2021 Roland Kujundzic
 */
class CLI {

public static $app = '';

public static $desc = '';

public static $arg = [];

private static $arg_list = [];

private static $arg_num = 0;

private static $is_error = false;

private static $example = [];


/**
 * Abort if not cli mode and APP_HELP is undefined.
 */
private static function init() : bool {
	if (defined('APP_HELP') && !isset($_SERVER['argv'])) {
		return false;
	}

	if (php_sapi_name() !== 'cli') {
		fwrite(STDERR, "\nERROR: run as cli\n\n\n");
		exit(1);
	}

	if (empty($_SERVER['argv'][0])) {
		fwrite(STDERR, "\nERROR: argv[0] missing\n\n\n");
		exit(1);
	}

	if (!self::$app) {
		self::$app = $_SERVER['argv'][0];
	}

	self::$is_error = false;
	self::$example = [];
}


/**
 * Abort with syntax error if count($argv_example) != count($_SERVER['argv']) or if 
 * argv_example check failed. Exit with syntax message if $_SERVER['argv'][1] == '?' or 'help'.
 * Show APP_DESC if defined. Use APP instead of $_SERVER['argv'][0] if defined. 
 * Use '@file:path/to/file' to enable file exists check for parameter.
 * Use '@dir:path/to/directory' to enable directory exists check for parameter.
 * Use '@?:optional' for optional parameter
 * Use '@example:...' for example of previous parameter
 * Use '@or:on|off' to ensure parameter is either 'on' or 'off'.
 * Use '@docroot' for getcwd == DOCROOT check.
 * Use --name=value for optional parameter name - define(PARAMETER_NAME, value).
 */
public static function check(array $arg_list = []) : bool {
	self::$arg_list = $arg_list;

	if (!self::init()) {
		return false;
	}

	self::$arg_num = (count(self::$arg_list) > 0) ? count(self::$arg_list) + 1 : 0;

	for ($i = 0; !self::$is_error && $i < count(self::$arg_list); $i++) {
		self::checkParam(self::arg_list[$i]);
	}

	$res = true;
	$app_desc = self::getAppDesc();

	if (defined('APP_HELP')) {
		if (APP_HELP != 'quiet') {
			fwrite(STDERR, "\nSYNTAX: $app ".join(' ', $argv_example)."\n$app_desc\n\n");
		}

		$res = false;
	}
	else if (!empty($_SERVER['argv'][1]) && ('?' == $_SERVER['argv'][1] || 'help' == $_SERVER['argv'][1])) {
		print "\nSYNTAX: $app ".join(' ', $argv_example)."\n$app_desc\n\n";
		exit(0);
	}
	else if ($is_error || ($arg_num > 0 && $arg_num != count($_SERVER['argv']))) {
		fwrite(STDERR, "\nSYNTAX: $app ".join(' ', $argv_example)."\n$app_desc\n$error_msg\n");
		exit(1);
	}

	return $res;
}


/**
 * 
 */
private static function getAppDesc() {
	$desc = self::$desc ? "\n".self::$desc."\n\n".self::$app : self::$app;

	if (count(self::$example) == 0) {
		return $desc;
	}

	for ($i = 0; $i < count(self::$example); $i++) {
		$pos = self::$example[$j];
		$desc .= " '".str_replace("'", "\\'", substr($argv_example[$pos], 9))."'";
			$arg_num--;
		}

		for ($j = count($example) - 1; $j >= 0; $j--) {
			array_splice($argv_example, $example[$j], 1);
		}

		$app_desc .= "\n\n"; 
	}



/**
 *
 */
private static function checkParam(string $param, int $i) : void {
	$arg = isset($_SERVER['argv'][$i + 1]) ? $_SERVER['argv'][$i + 1] : ''; 
	$error_msg = '';

	if (substr($param, 0, 1) == '@' && ($pos = strpos($param, ':')) > 2) {
		$do = substr($param, 1, $pos - 1);

		if ($do == '?') {
			$error_msg = self::optional(substr($param, $pos), $arg); 
		}
		else {
			$error_msg = self::$do(substr($param, $pos), $arg); 
		}

		if ($error_msg) {
			self::$is_error = true;
		}

		if ($error_msg == '1') {
			$error_msg = '';
		}
	}
	else {
		$pos = 0;
		$do = '';
	}

}


/**
 *
 */
private static function ToDo(string $param, string $arg) : string {
/*
		else if ($do == '@?') {
			// optional parameter
			$argv_example[$i] = '['.substr($param, 3).']';
			$arg_num--;
			$pos = 0;
		}
		else if ($do == 'req') {
			$req_keys = explode(',', substr($param, 5));
			$req_example='';
			$arg_num--;
			$pos = 0;

			foreach ($req_keys as $rkey) {
				if (!isset($_REQUEST[$rkey])) {
					$req_example .= ' req:'.$rkey.'=…';
					$is_error = true;
				}
				else {
					$arg_num++;
				}
			}

			$argv_example[$i] = ltrim($req_example);
		}
		else if ($do == 'srv') {
			$srv_keys = explode(',', substr($param, 5));
			$srv_example='';
			$arg_num--;
			$pos = 0;

			foreach ($srv_keys as $skey) {
				if (!isset($_SERVER[$skey])) {
					$srv_example .= ' srv:'.$skey.'=…';
					$is_error = true;
				}
				else {
					$arg_num++;
				}
			}

			$argv_example[$i] = ltrim($srv_example);
		}
		else if ($do == 'example') {
			array_push($example, $i);
			$arg_num--;
			$pos = 0;
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
*/
}


/**
 *
 */
private static function file(string $param, string $arg) : string {
	return ($arg && !file_exists($arg)) ? 'no such file '.$arg : '';
}


/**
 *
 */
private static function dir(string $param, string $arg) : string {
	return ($arg && !is_dir($arg)) ? 'no such directory '.$arg : '';
}


/**
 *
 */
private static function or(string $param, string $arg) : string {
	return (!$arg || !in_array($arg, explode('|', $param))) ? '1' : '';
}


/**
 * Parse $_SERVER[argv] or $arg_str if set. 
 * Detect --name=value or -name value.
 * Detect -ab or --a --b (a=1, b=1).
 * Use @file:=path/to/file to parse file into result (suffix .ser|.conf|.json).
 * Use @json:={…} to load json string.
 *
 * @code CLI::parse(); print_r(CLI::arg);

 * @code …
 * CLI::parse("run.php --name value --k2 v2"); json_encode(CLI::arg) == 
 *   '{"":["run.php","value","v2"],"name":1,"k2":1}';
 * CLI::parse("run.php -k -n abc"); json_encode(CLI::arg) ==
 *   '{"":["run.php","abc"],"k":1,"n":1}';
 * CLI::parse("run.php -uvw xyz test"); json_encode(CLI::arg) ==
 *   '{"":["run.php","xyz","test"],"u":1,"v":1,"w":1}'
 * CLI::parse("run.php --k1=K1 --k2=K2 --k2=K3 -a -b x -b y"); json_encode(CLI::arg) ==
 *   '{"":["run.php","x","y"],"k1":"K1","k2":["K2","K3"],"a":1,"b":1}';
 * CLI::parse("run.php k=v -f --g=arg"); json_encode(CLI::arg) ==
 *   '{"":["run.php","k=v"],"f":1,"g":"arg"}';
 *
 * CLI::parse("run.php '@file=test.json'"); json_encode(CLI::arg) ==
 *   '{"":["run.php"],"hash":{"a":"aa","b":"bbb"},"list":["a","b"]}';
 * CLI::parse('run.php @json={"k1":"v1","k2":["a","b"]}'); json_encode(CLI::arg) ==
 *   '{"":["run.php"],"k1":"v1","k2":["a","b"]}';
 * @eol
 */
public static function parse(?string $arg_str = null) : ?array {
	self::$arg = [ "" => [] ];

	if (!is_null($arg_str)) {
		$arg = preg_split('/\s+/', $arg_str);
	}
	else {
		if (php_sapi_name() !== 'cli') {
			return null;
		}
		
		$arg = $_SERVER['argv'];
	}

	$no_parse = false;
	for ($i = 0; $i < count($arg); $i++) {
		$value = $arg[$i];
		$key = null;
	
		if ($no_parse) {
			// \rkphplib\lib\log_debug("CLI.parse:332> push $value");
			array_push(self::$arg, $value);
		}
		else if ($value == '--') {
			$no_parse = true;
		}
		else if ($value[0] == '@' && ($pos = mb_strpos($value, '=')) > 2) {
			$do = mb_substr($value, 1, $pos - 1);	
			$value = mb_substr($value, $pos + 1);
			$json = '';

			if ($do == 'file' && file_exists($value)) {
				$json = trim(file_get_contents($value));
			}
			else if ($do == 'json') {
				$json = $value;
			}

			if ((substr($json, 0, 1) == '{' && substr($json, -1) == '}') ||
						(substr($json, 0, 1) == '[' && substr($json, -1) == ']')) {
				// \rkphplib\lib\log_debug([ "CLI.parse:349> merge <1>", $hash ]);
				$hash = json_decode($json, true);
				self::$arg = array_merge(self::$arg, $hash);
			}
		}
		else if ($value[0] == '-' && $value[1] == '-') {
			if (($pos = mb_strpos($value, '=')) > 2) {
				$key = mb_substr($value, 2, $pos - 2);
				$value = mb_substr($value, $pos + 1);
			}
			else {
				$akey = substr($value, 2);
				// \rkphplib\lib\log_debug("CLI.parse:359> $akey=1");
				self::$arg[$akey] = 1;
			}
		}
		else if ($value[0] == '-') {
			$plen = strlen($value);

			if ($plen == 2) {
				$akey = $value[1];
				// \rkphplib\lib\log_debug("CLI.parse:368> $akey=1");
				self::$arg[$akey] = 1;
			}
			else {
				for ($k = 1; $k < $plen; $k++) {
					$akey = $value[$k];
					if (ord($akey) < 97 || ord($akey) > 122) {
						throw new Exception('invalid flag '.$akey, $value);
					}

					// \rkphplib\lib\log_debug("CLI.parse:378> $akey=1");
					self::$arg[$akey] = 1;
				}
			}
		}
		else {
			$key = '';
		}

		if (!is_null($key)) {
			if (isset(self::$arg[$key])) {
				if (!is_array(self::$arg[$key])) {
					self::$arg[$key] = [ self::$arg[$key] ];
				}

				array_push(self::$arg[$key], $value);
			}
			else {
				// \rkphplib\lib\log_debug("CLI.parse:396> $key=$value");
				self::$arg[$key] = $value;
			}
		}
	}

	return self::$arg;
}


/**
 * Run cli_input and export output into $_REQUEST (prefix req:) and $_SERVER (prefix srv|server|http:).
 *
 * @example run2.php --req:name value --req:list=v1 req:list=v2
 * REQUEST={"name":"value","list":["v1","v2"]}
 * SERVER={}
 *
 * @example run2.php req:a=x --req:b http:host=domain.tld server:addr=1.2.3.4 --srv:request_method post
 * REQUEST={"a":"x","b":1}
 * SERVER={"HTTP_HOST":"domain.tld","SERVER_ADDR":"1.2.3.4","REQUEST_METHOD":"post"}
 */
function cli_http_input(?string $arg_str = null) : void {
	if (($data = cli_input($arg_str)) === null) {
		return;
	}

	foreach ($data as $key => $value) {
		if (empty($key)) {
			continue;
		}

		if (empty($value)) {
			$value = 1;
		}
		else if ($value[0] == '@') {
			$n = intval(substr($value, 1));
			$value = $data[''][$n];
		}

		$value_str = is_array($value) ? print_r($value, true) : $value;

		if (substr($key, 0, 4) == 'req:') {
			$key = substr($key, 4);
			// \rkphplib\lib\log_debug("cli_http_input:160> set _REQUEST[$key]=[$value_str]");
			$_REQUEST[$key] = $value;
		}
		else {
			if (substr($key, 0, 4) == 'srv:') {
				$key = strtoupper(substr($key, 4));
			}
			else if (substr($key, 0, 5) == 'http:') {
				$key = 'HTTP_'.strtoupper(substr($key, 5));
			}
			else if (substr($key, 0, 7) == 'server:') {
				$key = 'SERVER_'.strtoupper(substr($key, 7));
			}

			if (!isset($_SERVER[$key])) {
				// \rkphplib\lib\log_debug("cli_http_input:175> set _SERVER[$key]=[$value_str]");
				$_SERVER[$key] = $value;
			}
		}
	}
}


}

