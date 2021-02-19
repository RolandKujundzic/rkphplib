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
 * if (!CLI::syntax([ 'path/to/config.json' ], [ '@1:file' ])) {
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
 * Use ?name for optional parameter. Use @N:check[:param] to check Nth parameter.
 * Options list $opts entry is either check (@N:check[:arg] or @check[:arg]) 
 * or example (#N:example, #2:example value for parameter 2).
 * Checks are @N:file, @N:dir, @N:suffix[:.jpg:.jpeg], @N:int, @N:enum or @docroot. 
 *
 * Customize error message with self::$arg (=self::$argv[0]) and self::$desc.
 * Avoid abort if CLI::$abort = false. Define APP_HELP=quiet or
 * set CLI::$abort=false and CLI::$log = null to skip check.
 *
 * @code CLI::syntax([ 'path/to/docroot', 'config.json' ], [ '@1:dir', '@2:file' ]);
 * @code CLI::syntax([ 'fontname', '?parameter' ], [ '@docroot', '#1:Poppins', '#2:300,300i' ]);
 * @code CLI::syntax([ 'image.jpg' ], [ '@1:file', '@1:suffix:.jpg:.jpeg' ])
 * @code CLI::syntax([ 'on|off', 'gender' ], [ '@1:enum', '@1:enum:m:f' ]);
 * @code
 * CLI::abort = false;
 * if (!CLI::syntax([ 'path/to/config.json' ], [ '@1:file' ])) {
 *   return;
 * }
 * @eol
 */
public static function syntax(array $args = [], array $opts = []) : bool {
	if (count(self::$argv) == 0) {
		self::parse();
	}

	if (defined('APP_HELP')) {
		self::$abort = false;
		self::$log = null;
	}

	if (php_sapi_name() !== 'cli') {
		self::syntaxError('run as cli');
	}

	if (!self::$app) {
		self::$app = self::$argv[0];
	}

	$error = [];
	$plist = [];

	for ($i = 0; $i < count($args); $i++) {
		$name = substr($args[$i], 0, 1) == '?' ? '['.substr($args[$i], 1).']' : $args[$i];
		array_push($plist, $name);

		if (!isset(self::$argv[$i + 1]) && substr($args[$i], 0, 1) != '?') {
			array_push($error, 'missing parameter #'.($i + 1).' '.$args[$i]);
		}
	}

	for ($i = 0; $i < count($opts); $i++) {
		$error_msg = null;

		if (preg_match('/@([1-9][0-9]*):(.+)$/', $opts[$i], $match)) {
			$n = $match[1];
			$name = substr($args[$n - 1], 0, 1) == '?' ? substr($args[$n - 1], 1) : $args[$n - 1];
			$value = isset(self::$argv[$n]) ? self::$argv[$n] : '';

			if (isset(self::$argv[$n])) {
				$error_msg = self::checkParam($name, self::$argv[$n], $match[2]);
			}
		}
		else if ($opts[$i] == '@docroot') {
			if (!defined('DOCROOT')) {
				$error_msg = 'DOCROOT is undefined';
			}
			else if (getcwd() != DOCROOT) {
				$error_msg = 'run in '.DOCROOT;
			}
		}

		if ($error_msg) {
			array_push($error, $error_msg);
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
 *
 */
private static function checkParam(string $name, string $value, string $arg) : ?string {
	$tmp = explode(':', $arg);
	$check = array_shift($tmp);
	$error = null;

	if ($check == 'file') {
		if (!file_exists($value)) {
			$error = "no such file '$value'";
		}
	}
	else if ($check == 'dir') {
		if (!is_dir($value)) {
			$error = "no such directory '$value'";
		}
	}
	else if ($check == 'enum') {
		$elist = count($tmp) == 0 ? explode('|', $name) : $tmp;
		if (!in_array($value, $elist)) {
			$error = 'invalid enum '.$value.' use '.join('|', $elist);
		}
	}
	else if ($check == 'int') {
		if (!is_integer($value)) {
			$error = 'invalid int '.$value;
		}
	}
	else if ($check == 'suffix') {
		$ok = false;
		for ($i = 0; !$ok && $i < count($tmp); $i++) {
			$sl = -1 * strlen($tmp[$i]);
			if (substr($value, $sl) === $tmp[$i]) {
				$ok = true;
			}
		}

		if (!$ok) {
			$error = 'invalid suffix in '.$value.' use '.join('|', $tmp);
		}
	}
	else {
		$error = "invalid check '$check'";
	}

	return $error;
}


/**
 * 
 */
private static function syntaxError(string $syntax, array $error = [], array $opts = []) : void {
	$msg = self::$desc === '' ? "\nSYNTAX: $syntax\n\n" : "\nSYNTAX: $syntax\n\n".self::$desc."\n\n";

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

