<?php

namespace rkphplib;


/**
 * Command Line Interface Helper. Parse Arguments, run Syntax Check in cli
 * Application.
 *
 * @code
 * CLI::parse();
 * print_r($CLI::arg);
 * print_r($CLI::argv);
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

public static $abort = true;

public static $log = STDERR;

public static $app = '';

public static $desc = '';

public static $arg = [];

public static $argv = [];


/**
 * Abort with syntax error if count($args) != count(self::$argv).
 * Use [@CHECK:][?]PARAMETER as $args entry. Optional argument is ?name.
 * Customize error message with self::$arg (=self::$argv[0]) and self::$desc.
 * Avoid abort if CLI::$abort = false. Define APP_HELP=quiet or
 * set CLI::$abort=false and CLI::$log = null to skip check.
 *
 * @code CLI::syntax([ '@dir:path/to/docroot', '@file:config.json' ]);
 * @code CLI::syntax([ 'fontname', '?parameter' ], [ '@docroot', '@1:Poppins', '@2:'300,300i' ]);
 * @code CLI::syntax([ '@or:on|off' ]);
 * @code
 * CLI::abort = false;
 * if (!CLI::syntax([ '@file:path/to/config.json' ])) {
 *   return;
 * }
 * @eol
 */
public static function syntax(array $args = [], array $opts = []) : bool {
	if (defined('APP_HELP')) {
		if (count(self::$argv) < 2) {
			return false;
		}

		self::$abort = false;
		self::$log = null;
	}

	if (count(self::$arg) == 0) {
		self::parse();
	}

	if (php_sapi_name() !== 'cli') {
		self::syntaxError('run as cli');
	}

	if (!self::$app) {
		self::$app = self::$argv[0];
	}

	$error = [];
	$plist = [];

	for ($i = count($args) - 1; $i > -1; $i--) {
		$param = isset(self::$argv[$i + 1]) ? self::$argv[$i + 1] : '';
		list ($pname, $error_msg) = self::syntaxCheck($args[$i], $param);
		array_push($plist, $pname);
		if ($error_msg) {
			array_push($error, $error_msg);
		}
	}

	for ($i = 0; $i < count($opts); $i++) {
		if ($opts[$i] == '@docroot') {
			if (!defined('DOCROOT')) {
				array_push($error, 'DOCROOT is undefined');
			}
			else if (getcwd() != DOCROOT) {
				array_push($error, 'run in '.DOCROOT);
			}
		}
	}

	$res = true;

	if (count($error)) {
		self::syntaxError(self::$app.' '.join(' ', $plist), $error, $opts);
		$res = false;
	}

	return $res;
}


/**
 * Return [ parameter, '' ] or [ parameter, error ]
 */
private static function syntaxCheck(string $name, string $value) : array {
	$optional = false;
	$syntax = '';
	$error = '';
	$check = '';

	if (substr($name, 0, 1) == '@' && ($pos = strpos($name, ':')) > 2) {
		$check = substr($name, 1, $pos);
		$name = substr($name, $pos + 1);
	}

	if (substr($name, 0, 1) == '?') {
		$name = substr($name, 1);
		$optional = true;
	}
	else if ($value === '') {
		$error = 'missing '.$name;
	}

	$syntax = $optional ? '['.$name.']' : $name;

	if ($check && $value !== '') {
		if ($check == 'file' && !file_exists($value)) {
			$error = 'no such file '.$value;
		}
		else if ($check == 'dir' && !is_dir($value)) {
			$error = 'no such directory '.$value;
		}
		else if ($check == 'or' && !in_array($name, explode('|', $value))) {
			$error = 'invalid '.$value;
		}
	}

	return [ $syntax, $error ];
}


/**
 * 
 */
private static function syntaxError(string $syntax, array $error = [], array $opts = []) : void {
	$msg = self::$desc ? "\nSYNTAX: $syntax\n\n" : "\nSYNTAX: $syntax\n\n".self::$desc."\n\n";

	$plist = [];
	for ($i = 0; $i < count($opts); $i++) {
		if (preg_match('/^@[1-9]:(.+)$/', $opts[$i], $match)) {
			array_push($plist, $match[1]);
		}
	}

	if (count($plist)) {
		$msg .= $syntax.' '.join(' ', $plist)."\n\n";
	}

	if (count($error)) {
		$msg .= join("\n", $error)."\n\n";
	}

	if (is_null(self::$log)) {
		// no error output
	}
	else if (is_string(self::$log)) {
		self::$log = $msg;
	}
	else {
		fwrite(self::$log, $msg);
	}

	if (self::$abort) {
		exit(1);
	}
}


/**
 * Parse $_SERVER[argv] or $arg_str if set. 
 * Detect --name=value or -name value.
 * Detect -ab or --a --b (a=1, b=1).
 * Use @file:=path/to/file to parse file into result (suffix .ser|.conf|.json).
 * Use @json:={…} to load json string.
 * Use self::$argv (= $result['']) and self::$arg for later access.
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
 * CLI::parse('run.php @req:name=value @req:list=v1 @req:list=v2
 * 	 // set $_REQUEST = [ 'name' => 'value', 'list' => [ 'v1', 'v2'] ];
 * CLI::parse('run.php @http:host=domain.tld @server:addr=1.2.3.4 @srv:request_method=post
 *   // set $_SERVER = [ 'HTTP_HOST' => 'domain.tld', 'SERVER_ADDR' => '1.2.3.4', 'REQUEST_METHOD' => 'post' ];
 * @eol
 */
public static function parse(?string $arg_str = null) : ?array {
	self::$arg = [ "" => [] ];
	self::$argv = [];

	if (!is_null($arg_str)) {
		$arg = preg_split('/\s+/', $arg_str);
	}
	else if (php_sapi_name() !== 'cli') {
		return null;
	}
	else {	
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
			self::parseAction(mb_substr($value, 1, $pos - 1), mb_substr($value, $pos + 1));
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
						throw new \Exception('invalid flag '.$akey, $value);
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

	$res = self::$arg;
	self::$argv = self::$arg[''];
	unset(self::$arg['']);

	return $res;
}


/**
 * Action $do is file, json, req:NAME, srv, http or server.
 */
private static function parseAction(string $do, string $value) : void {
	$json = '';

	if ($do == 'file' && file_exists($value)) {
		$json = trim(file_get_contents($value));
	}
	else if ($do == 'json') {
		$json = $value;
	}
	else if (substr($do, 0, 4) == 'req:') {
		$key = substr($do, 4);
		if (isset($_REQUEST[$key])) {
			if (!is_array($_REQUEST[$key])) {
				$_REQUEST[$key] = [ $_REQUEST[$key] ];
			}

			// \rkphplib\lib\log_debug("CLI.parseAction:427> push '$value' to _REQUEST[$key]");
			array_push($_REQUEST[$key], $value);
		}
		else {
			// \rkphplib\lib\log_debug("CLI.parseAction:420> set _REQUEST[$key]='$value'");
			$_REQUEST[$key] = $value;
		}
	}
	else if (preg_match('/^(srv|http|server):(.+)$/', $do, $match)) {
		if ($match[1] == 'srv') {
			$key = strtoupper($match[2]);
		}
		else if ($match[1] == 'http') {
			$key = 'HTTP_'.strtoupper($match[2]);
		}
		else if ($match[1] == 'server') {
			$key = 'SERVER_'.strtoupper($match[2]);
		}

		if (!isset($_SERVER[$key])) {
			// \rkphplib\lib\log_debug("CLI.parseAction:435> set _SERVER[$key]='".$match[2].'"");
			$_SERVER[$key] = $value;
		}
	}

	if ($json && ((substr($json, 0, 1) == '{' && substr($json, -1) == '}') ||
			(substr($json, 0, 1) == '[' && substr($json, -1) == ']'))) {
		// \rkphplib\lib\log_debug([ "CLI.parseAction:419> merge self::arg with <1>", $hash ]);
		$hash = json_decode($json, true);
		self::$arg = array_merge(self::$arg, $hash);
	}
}


}

