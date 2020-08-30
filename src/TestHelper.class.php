<?php declare(strict_types=1);

namespace rkphplib;

defined('SETTINGS_LOG_DEBUG') || define('SETTINGS_LOG_DEBUG', '/dev/stderr');
defined('PATH_RKPHPLIB') || define('PATH_RKPHPLIB', __DIR__.'/');

if (!defined('DOCROOT')) {
	if (is_dir(dirname(__DIR__).'/data')) {
		define('DOCROOT', dirname(__DIR__));
	}
	else if (substr(PATH_RKPHPLIB, -18) == '/php/rkphplib/src/' && is_dir(dirname(dirname(dirname(__DIR__)))).'/data') {
		define('DOCROOT', dirname(dirname(dirname(__DIR__))));
	}
}

define('TEST_MYSQL', 'mysqli://unit_test:magic123@tcp+localhost/unit_test');
define('TEST_SQLITE', 'sqlite://magic123@./unit_test.sqlite');

require_once __DIR__.'/lib/config.php';
require_once __DIR__.'/tok/Tokenizer.class.php';
require_once __DIR__.'/FSEntry.class.php';
require_once __DIR__.'/PhpCode.class.php';
require_once __DIR__.'/Profiler.class.php';
require_once __DIR__.'/PhpCode.class.php';
require_once __DIR__.'/JSON.class.php';
require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Dir.class.php';



/**
 * Test suite class.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TestHelper {

// @var hash $_tc test counter hash
private $_tc = [];

// @var string $output
public $output = '';

// @var Profiler $profiler
public $profiler = null;



/**
 * Constructor. Initialize test counter hash.
 */
public function __construct() {
	$this->_tc['path'] = '';
	$this->_tc['overview'] = [];

	// counter for current test
	$this->_tc['num'] = 0;
	$this->_tc['ok'] = 0;
	$this->_tc['error'] = 0;

	// overall counter
	$this->_tc['t_ok'] = 0;
	$this->_tc['t_error'] = 0;
	$this->_tc['t_num'] = 0;
	$this->_tc['t_pass'] = 0;
	$this->_tc['t_fail'] = 0;
}


/**
 * Initialize and start profiler.
 * @example:
 * $this->profilerStart();
 * $this->profiler->log($message);
 * $this->profilerEnd();
 * @:example
 */
public function profilerStart() : Profiler {
	$this->profiler = new Profiler();
	$this->profiler->startXDTrace();
	$this->profiler->log('start test');
	return $this->profiler;
}


/**
 * Stop profiler and print report.
 * @see profilerStart()
 */
public function profilerStop() : void {
	$this->profiler->log('done.');
	$this->profiler->stopXDTrace();
	print "\nProfiler Log:\n";
	$this->profiler->writeLog();
	print "\n\n";
}


/**
 * Print error message.
 * 
 * @param mixed $out
 * @param mixed $ok
 */
private function _error_cmp(string $msg, $out, $ok) : void {
	$m_out = '';
	$m_ok = '';

	if (is_string($out)) {
		$m_out = (strlen($out) < 40) ? "out=[$out]" : "\nout=[$out]\n"; 
	}
	else if (is_numeric($out) || is_bool($out)) {
		$m_out = "out=[$out]";
	}
	else {
		$m_out = "\nout: ".print_r($out, true)."\n";
	}

	if (is_string($ok)) {
		$m_ok = (strlen($ok) < 40) ? "ok=[$ok]" : "\nok=[$ok]\n"; 
	}
	else if (is_numeric($ok) || is_bool($ok)) {
		$m_ok = "ok=[$ok]";
	}
	else {
		$m_ok = "\nok: ".print_r($ok, true)."\n";
	}

	$diff = '';
	if (is_string($out) && is_string($ok)) {
		$lout = strlen($out);
		$lok = strlen($ok);

		if ($lout != $lok) {
			$diff = "\nlenght(out) = ".$lout.' != '.$lok. ' = length(ok)';
		}

		for ($i = 0, $done = false; !$done && $i < $lout && $i < $lok; $i++) {
			if ($out[$i] != $ok[$i]) {
				$diff .= "\nout[$i] = chr(".ord($out[$i]).') != chr('.ord($ok[$i]).") = ok[$i]";
				$done = true;
			}
		}
	}

	print "\nERROR: $m_out $m_ok$diff\n\n";
}


/**
 * Print log message. Values of cn (2^n):
 *
 * 0: print only $msg
 * 1: add trailing linebreak to msg (default)
 * 2: add leading linebreak
 * 4: print delimiter line before $msg
 * 8: print delimiter line after $msg
 * 16: add linebreak to output
 */
private function _log(string $msg, int $cn = 1) : void {

	if ($cn & 2) {
		print "\n";
	}

	if ($cn & 4) {
		print "---------------------------------------------------------------------------------------\n";
	}

	print $msg;

	if ($cn & 1) {
		print "\n";
	}

	if ($cn & 8) {
		print "---------------------------------------------------------------------------------------\n";
	}
}


/**
 * Include (once) php file $file (relative path to rkphplib/ directory) from rkphplib/ directory.
 */
public function load(string $file) : void {
	$rkphplib = '';

	if (!empty($_SERVER['PWD']) && ($pos = mb_strpos($_SERVER['PWD'], '/rkphplib/')) !== false) {
		$rkphplib = mb_substr($_SERVER['PWD'], 0, $pos).'/rkphplib';
	}

	FSEntry::isDir($rkphplib);

	$path_prefix = [ '', 'src', 'src/tok', 'src/lib', 'src/doc' ];
	$php_file = '';

	foreach ($path_prefix as $prefix) {
		$php_file = str_replace('//', '/', $rkphplib.'/'.$prefix.'/'.$file);
		if (FSEntry::isFile($php_file, false)) {
			break;
		}
	}

	FSEntry::isFile($php_file);
	require_once $php_file;
}


/**
 * Print overall result.
 */
public function result() : void {

	if (count($this->_tc['overview']) == 0) {
		return;
	}

	$overall = "Overall result of ".count($this->_tc['overview'])." Class/Function Tests:";
	$msg = sprintf("%s\n%'=".mb_strlen($overall)."s\n", $overall, '');
	$this->_log($msg, 2);
	$this->_log(join("\n", $this->_tc['overview'])."\n\n");
}


/**
 * Run test script.
 */
public function runTest(string $run_php) : void {

	FSEntry::isFile($run_php);
	$script_dir = dirname($run_php);

	$this->_tc['num'] = 0;
	$this->_tc['ok'] = 0;
	$this->_tc['error'] = 0;
	$this->_tc['path'] = $script_dir;

	$this->_log('START: '.$script_dir.' Tests', 15);

	// execute tests ...
	include $run_php;

	$result = '';
	$this->_tc['t_num'] += $this->_tc['num'];

	if ($this->_tc['error'] == 0 && $this->_tc['ok'] > 0) {
		$this->_tc['t_ok'] += $this->_tc['ok'];
		$this->_tc['t_pass']++;
		$result = 'PASS';
	}
 	else {
		$this->_tc['t_error'] += $this->_tc['error'];
		$this->_tc['t_fail']++;
		$result = 'FAIL';
	}

	$this->_log('RESULT: '.$this->_tc['ok'].'/'.$this->_tc['num'].' OK - '.$this->_tc['error']." ERROR \t".$result, 31);

	$overview = sprintf("%16s: %3d/%-3d ok - %3d errors", dirname($run_php), $this->_tc['ok'], $this->_tc['num'], $this->_tc['error']);
	array_push($this->_tc['overview'], $overview);
}


/**
 * Call function and compare result. Load $test (list of function calls - parameterlist + result) and $func (function name) from $path.fc.php.
 */
public function runFuncTest(string $path) : void {
	// execute test
	$php_file = empty($this->_tc['path']) ? $path.'.fc.php' : $this->_tc['path'].'/'.$path.'.fc.php';
	$this->_log('runFuncTest: loading '.$php_file.' ... ', 2);
	require_once $php_file;

	if (!isset($func)) {
		throw new Exception('$func is undefined in '.$php_file); 
	}

	if (!isset($test)) {
		throw new Exception('$test is undefined in '.$php_file); 
	}

	$csm = false;
	if (is_string($func) && ($pos = strpos($func, '::')) !== false) {
		$class = substr($func, 0, $pos);
		$method = substr($func, $pos + 2);
		$csm = true;
	}

	$this->_tc['num']++;

	$n_err = 0;
	$n_ok = 0;

	$this->_log('executing '.count($test).' tests', 9);
	foreach ($test as $x) {
		$ok = array_pop($x);
		$res = null;

		if ($csm) {
			$res = $this->_fc_static_method($class, $method, $x);
		}
		else if (is_string($func)) {
			$res = $this->_fc_function($func, $x);
		}
		else {
			if (empty($this->output)) {
				$res = $func();
			}
			else {
				File::remove($this->output, false);
				$func();
				$res = File::load($this->output);
			}
		}

		if ($ok === '@file') {
			$ok = File::load(dirname($php_file).'/'.File::basename($php_file, true).'.ok');
		}

		$res = self::res2str($res);
		$msg = 'ok';

		if ($res !== $ok) {
			$msg = ' != '.$ok.' - ERROR!';
	
			if (empty($ok) || strlen($res) > 40) {
				$save_ok = sys_get_temp_dir().'/res.out';
				File::save($save_ok, $res);
				$msg .= ' (see: '.$save_ok.')';
			}

			$n_err++;
		}
		else if (!empty($this->output)) {
			File::remove($this->output, false);
		}

		if (is_numeric($res) || is_bool($res)) {
			$this->_log("$res ... $msg");
		}
		else {
			$this->_log("'$res' ... $msg");
		}
	}

	if (!$n_err) {
		 $this->_tc['ok']++;
	}
	else {
	  $this->_tc['error']++;
		throw new Exception("Test failed: runFuncTest($path)");
	}
}


/**
 * Call test function $func($arg). Return vector [ NAME, 1, 1, 0, 1, ... ] with 1 = OK and 0 = ERR.
 * 
 * @param mixed $arg
 */
public function callTest(callable $func, $arg, array $result) : void {
  $this->_tc['num']++;

  $this->_log(array_shift($result).": ", 0);

	// execute test
  $r = $func($arg);

	$n = count($r);
	$ok = 0;
	$err = 0;

	for ($i = 0; $i < count($result); $i++) {
 		if ($r[$i] != $result[$i]) {
			$err++;
		}
		else {
			$ok++;
		}
	}

  if ($err == 0 && $ok == $n) {
    $this->_log("$n/$n OK");
  	$this->_tc['ok']++;
  }
  else {
    $this->_log("$ok/$n OK and $err ERROR");
  	$this->_tc['error']++;
  }
}


/**
 * Compare output $out_list with expected result $ok_list. Result vector may contain less keys than output (e.g. ignore date values).
 */
public function compare(string $msg, array $out_list, array $ok_list) : void {
	$this->_log($msg.": ", 0);
	$this->_tc['num']++;

	if (is_string($ok_list)) {
		$ok_list = $this->getResult($ok_list);
	}

	$n = count($ok_list);
	$err = 0;
	$ok = 0;

	for ($i = 0; $i < $n; $i++) {
		$t_out = $out_list[$i];
		$t_ok = $this->getResult($ok_list[$i]);
		$cmp = true;

		if ((is_string($t_out) && is_string($t_ok)) || (is_numeric($t_out) && is_numeric($t_ok)) || (is_bool($t_out) && is_bool($t_ok))) {
			if ($t_out !== $t_ok) {
				$this->_error_cmp("(Test $i)", $t_out, $t_ok);
				$cmp = false;
			}
		}
		else if (is_array($t_out) && is_array($t_ok) && count($t_out) >= count($t_ok)) {
			foreach ($t_ok as $key => $value) {
				if (!array_key_exists($key, $t_out) || $value != $t_out[$key]) {
					$this->_error_cmp("(Test $i) key=$key", $t_out[$key], $value);
					$cmp = false;
					break;
				}
			}
		}
		else {
			$this->_error_cmp("(Test $i)", $t_out, $t_ok);
			$cmp = false;
		}

		if ($cmp) {
  		$ok++;
		}
		else {
  		$err++;
		}
	}

	if ($err == 0 && $ok == $n) {
		$this->_log("$n/$n OK");
		$this->_tc['ok']++;
	}
	else {
		$this->_log("$ok/$n OK and $err ERROR");
		$this->_tc['error']++;
	}
}


/**
 * Compare hash output with expected result. Result hash may contain lass keys than output (e.g. ignore date values).
 */
public function compareHash(string $msg, array $out, array $ok) : void {
	$this->_log($msg.": ", 0);
	$this->_tc['num']++;
	$err = 0;

	if (is_string($ok)) {
		$ok = $this->getResult($ok);
	}

	foreach ($ok as $key => $ok_val) {
		if (!isset($out[$key])) {
			$this->_error_cmp("(Missing $key)", '', $ok_val);
			$err++;
		}
		else {
			$out_val = $out[$key];
			$ok_val = $this->getResult($ok_val);

			if ($out_val !== $ok_val) {
				$this->_error_cmp("(Compare $key)", $out_val, $ok_val);
				$err++;
			}
		}
	}

	$n = count($ok);

	if ($err) {
		$this->_log(($n - $err)."/$n OK and $err ERROR");
  		$this->_tc['error']++;
	}
	else {
		$this->_log("$n/$n OK");
		$this->_tc['ok']++;
	}
}


/**
 * If $value (any) is string with "@" prefix and [.json|.ser|.txt] suffix return file content. Otherwise return value.
 * 
 * @param mixed $value
 * @return mixed
 */
private function getResult($value) {

	if (!is_string($value) || mb_substr($value, 0, 1) !== '@') {
		return $value;
	}

	$file = mb_substr($value, 1);

	if (mb_substr($file, -5) === '.json') {
		$value = JSON::decode(File::load($file));
	}
	else if (mb_substr($file, -4) === '.ser') {
		$value = File::unserialize($file);
	}
	else if (mb_substr($file, -4) === '.txt') {
		$value = File::load($file);
	}
	else {
		throw new Exception('invalid file suffix use [json|ser|txt]', "file=$file"); 
	}

	return $value;
}


/**
 * Return _fc_function|_fc_static_method call.
 *
 * @param mixed $x
 */
private function _fc_log(string $call, $x) : string {
	$y = array();
	$prefix = '';
	$suffix = '';

	if (is_array($x)) {
		foreach ($x as $key => $value) {
			if (is_numeric($key)) {
				array_push($y, $this->_fc_log('', $value));
			}
			else {
				array_push($y, $key.' => '.$this->_fc_log('', $value));
			}
		}

		$prefix = '[';
		$suffix = ']';
	}
	else if (is_string($x)) {
		array_push($y, $x);
		$prefix = "'";
		$suffix = "'";
	}
	else {
		array_push($y, $x);
	}

	return $call ? $call.'('.join(', ', $y).') = ' : $prefix.join(', ', $y).$suffix;
}


/**
 * Return result (any) of $func($x[0], $x[1], ...) callback.
 *
 * @return mixed
 */
private function _fc_function(string $func, array $x) {

	$this->_log($this->_fc_log("$func", $x), 0);
	$pnum = count($x);

	if ($pnum == 1) {
		$res = $func($x[0]);
	}
	else if ($pnum == 2) {
		$res = $func($x[0], $x[1]);
	}
	else if ($pnum == 3) {
		$res = $func($x[0], $x[1], $x[2]);
	}
	else if ($pnum == 4) {
		$res = $func($x[0], $x[1], $x[2], $x[3]);
	}

	return $res;
}


/**
 * Return result (any) of $class::$method($x[0], $x[1], ...) callback.
 * 
 * @return mixed
 */
private function _fc_static_method(string $class, string $method, array $x) {

	$this->_log($this->_fc_log("$class::$method", $x), 0);
	$pnum = count($x);

	if ($pnum == 1) {
		$res = $class::$method($x[0]);
	}
	else if ($pnum == 2) {
		$res = $class::$method($x[0], $x[1]);
	}
	else if ($pnum == 3) {
		$res = $class::$method($x[0], $x[1], $x[2]);
	}
	else if ($pnum == 4) {
		$res = $class::$method($x[0], $x[1], $x[2], $x[3]);
	}

	return $res;
}


/**
 * Convert $res (any) to string if object or array (json_encode).
 * 
 * @param mixed $res
 * @return mixed number|bool|string
 */
public static function res2str($res) {
	if (is_float($res) || is_int($res) || is_bool($res)) {
		return $res;
	}
	else if (is_string($res)) {
		return $res;
	}
	else {
		// is_object(), is_array()
		return json_encode($res, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_AMP);
	}
}


/**
 * Return test dir ([abs-path, rel-path]).
 */
private function _test_dir() : array {
	$cwd = getcwd();
	$tdir = '';

	if (basename(dirname($cwd)) == 'test') {
		$tdir = $cwd;
	}
	else if (!empty($this->_tc['path'])) {
		$tdir = $cwd.'/'.$this->_tc['path'];
	}

	if (empty($tdir) || ($pos = mb_strpos($tdir, '/test/')) === false) {
		throw new Exception('could not determine test directory');
	}

	$rel_dir = substr($tdir, $pos + 6);

	return [ $tdir, $rel_dir ];
}


/**
 * Execute (include) php file in/$n.php, save output as out/$n.txt
 * and compare with ok/$n.txt. Log _tc.num|err.ok and print message.
 */
public function execPHP(int $n) : void {
	$dir = getcwd();

	if (!Dir::exists($dir.'/out')) {
		Dir::create($dir.'/out');
	}

	ob_start();
	include "$dir/in/t$n.php";
	$out = ob_get_contents();
	ob_end_clean();
	File::save("$dir/out/t$n.txt", $out);
	$ok = File::load("$dir/ok/t$n.txt");
	$this->compare(basename($dir)."/in/t$n.php", [ $out ], [ $ok ]);
}


/**
 * Load php source file and run all '@tok ...' examples.
 */
public function tokCheck_new(string $php_source) : void {
	$php = new PhpCode();
	$php->load($php_source);
	$pclass = $php->getClass('path');
	define('CLI_NO_EXIT', 1);

	$this->_log("\nrun @tok ... tests in $pclass");
	exit(1);

	for (; $i < count($code_lines); $i++) {
		$line = trim($code_lines[$i]);
		$linebreak = false;
		$result_file = '';

		if (strlen($line) == 0 || $line[0] != '*' || mb_substr($line, 2, 4) != '@tok') {
			continue;
		}

		$line = trim(mb_substr($line, 7));

		if (substr($line, 0, 9) == 'request {' && substr($line, -1) == '}') {
			$_REQUEST = array_merge($_REQUEST, json_decode(substr($line, 8), true));
			continue;
		}

		$tok = new \rkphplib\tok\Tokenizer();
		$tok->register(new $pclass());

		if (substr($line, 0, 1) == '"' && substr($line, -1) == '"') {
			$plugin = substr($line, 1, -1);
			$linebreak = true;
		}
		else if (($pos = mb_strrpos($line, '=')) !== false) {
			$plugin = trim(mb_substr($line, 0, $pos));
			$result = trim(mb_substr($line, $pos + 1));
		}
		else {
			$plugin = $line;
			$linebreak = true;
		}
	
		if ($linebreak) {
			$result = trim(mb_substr($code_lines[$i + 1], 3));
			$i++;

			if (mb_substr($result, 0, 12) == '@tok:result ') {
				$result_file = trim(mb_substr($result, 12));
				$result = File::load($result_file.'.ok');
			}
		}

		$this->_tc['num']++;
		if ($linebreak) {
			$this->_log("$plugin\n\t$result\n\t... ", 0);
		}
		else {
			$this->_log("$plugin ? $result ... ", 0);
		}

		$tok->setText($plugin);

    ob_start();
		$res = $tok->toString();
    $out = ob_get_contents();
    ob_end_clean();

		if (!empty($result) && empty($res) && !empty($out)) {
			$res = $out;
		}

		if (!empty($result_file)) {
			File::save($result_file.'.out', $res);
		}

		if ($res === $result) {
			$this->_log('ok');
			$this->_tc['ok']++;				
		}
		else {
			$this->_log(" != $res - ERROR!");
			$this->_tc['error']++;
		}
	}
	
	exit(1);
}


/**
 * Load php source file and run all '@tok ...' examples.
 */
public function tokCheck(string $php_source) : void {
	$code_lines = File::loadLines($php_source);
	$found_pclass = false;
	$pclass = '';

	define('CLI_NO_EXIT', 1);

	for ($i = 0; !$found_pclass && $i < count($code_lines); $i++) {
		$line = trim($code_lines[$i]);

		if (strlen($line) == 0) {
			continue;
		}
		else if (substr($line, 0, 10) == 'namespace ') {
			$pclass = '\\'.trim(substr($line, 10, -1));  
		}
		else if (preg_match('/^class ([a-zA-Z0-9_]+) implements TokPlugin/', $line, $match)) {
			$pclass .= '\\'.$match[1];
			$found_pclass = true;
		}
	}

	if (!$found_pclass) {
		throw new Exception('failed to find plugin class in '.$php_source);
	}

	$this->_log("\nrun @tok ... tests in $pclass");

	for (; $i < count($code_lines); $i++) {
		$line = trim($code_lines[$i]);
		$linebreak = false;
		$result_file = '';

		if (strlen($line) == 0 || $line[0] != '*' || mb_substr($line, 2, 4) != '@tok') {
			continue;
		}

		$line = trim(mb_substr($line, 7));

		if (substr($line, 0, 9) == 'request {' && substr($line, -1) == '}') {
			$_REQUEST = array_merge($_REQUEST, json_decode(substr($line, 8), true));
			continue;
		}

		$tok = new \rkphplib\tok\Tokenizer();
		$tok->register(new $pclass());

		if (substr($line, 0, 1) == '"' && substr($line, -1) == '"') {
			$plugin = substr($line, 1, -1);
			$linebreak = true;
		}
		else if (($pos = mb_strrpos($line, '=')) !== false) {
			$plugin = trim(mb_substr($line, 0, $pos));
			$result = trim(mb_substr($line, $pos + 1));
		}
		else {
			$plugin = $line;
			$linebreak = true;
		}
	
		if ($linebreak) {
			$result = trim(mb_substr($code_lines[$i + 1], 3));
			$i++;

			if (mb_substr($result, 0, 12) == '@tok:result ') {
				$result_file = trim(mb_substr($result, 12));
				$result = File::load($result_file.'.ok');
			}
		}

		$this->_tc['num']++;
		if ($linebreak) {
			$this->_log("$plugin\n\t$result\n\t... ", 0);
		}
		else {
			$this->_log("$plugin ? $result ... ", 0);
		}

		$tok->setText($plugin);

    ob_start();
		$res = $tok->toString();
    $out = ob_get_contents();
    ob_end_clean();

		if (!empty($result) && empty($res) && !empty($out)) {
			$res = $out;
		}

		if (!empty($result_file)) {
			File::save($result_file.'.out', $res);
		}

		if ($res === $result) {
			$this->_log('ok');
			$this->_tc['ok']++;				
		}
		else {
			$this->_log(" != $res - ERROR!");
			$this->_tc['error']++;
		}
	}
	
	exit(1);
}


/**
 * Run tokenizer tests t1.txt ... t$num.txt if $num is int (requires t1.ok.txt ... t$num.ok.txt).
 * If $num is string 'a.txt' run only this test. If $num is array [ 'a.inc.html', 'b.inc.html' ]
 * run a.inc.html (compare with a.inc.html.ok) and b.inc.html (compare with b.inc.html.ok).
 *
 * @param mixed $num int|string|array
 */
public function runTokenizer($num, array $plugin_list) : void {

	list ($tdir, $rel_tdir) = $this->_test_dir();

	$this->load('tok/Tokenizer.class.php');
	$tok = new \rkphplib\tok\Tokenizer(\rkphplib\tok\Tokenizer::TOK_DEBUG);

	for ($i = 0; $i < count($plugin_list); $i++) {
		$plugin = 'rkphplib\\tok\\'.$plugin_list[$i];
		$this->load($plugin_list[$i].'.class.php');
		$tok->register(new $plugin());
	}

	$test_files = array();

	if (is_string($num)) {
		$this->_log("runTokenizer: $rel_tdir/$num", 11);
		array_push($test_files, $tdir.'/'.$num);
	}
	else if (is_array($num)) {
		foreach ($num as $file) {
			array_push($test_files, $tdir.'/'.$file);
		}
	}
	else {
		if ($num > 1) {
			$this->_log("runTokenizer: $rel_tdir/t1.txt ... $rel_tdir/t$num.txt", 11);
		}
		else {
			$this->_log("runTokenizer: $rel_tdir/t$num.txt", 11);
		}

		for ($i = 1; $i <= $num; $i++) {
			array_push($test_files, $tdir.'/t'.$i.'.txt');
		}
	}

	$i = 0;
	foreach ($test_files as $f_txt) {
		$f_out = str_replace([ '.txt', '.inc.html' ], [ '.out.txt', '.out.html' ], $f_txt);
		$f_ok = str_replace([ '.txt', '.inc.html' ], [ '.ok.txt', '.ok.html' ], $f_txt);
		$i++;

		$tok->setText(File::load($f_txt));
		$ok = File::load($f_ok);
		$out = $tok->toString();

		$rkey = 't'.$i.'_test_';
		if (!empty($_REQUEST[$rkey.'out'])) {
			$out = File::load($_REQUEST[$rkey.'out']);
			$f_out = $_REQUEST[$rkey.'out'];
		}

		$this->_tc['num']++;
		$this->_log("Test $i ... ", 0);

		if ($out != $ok) {
			if (mb_strlen($out) > 40 || strpos($out, "\n") !== false) {
				$this->_log("ERROR! (see $f_out)");
				File::save($f_out, $out);
			}
			else {
				$this->_log("$out != $ok - ERROR!");
			}

			$this->_tc['error']++;
		}
		else {
			$this->_tc['ok']++;
			$this->_log("ok");
		}
	}
}


}

