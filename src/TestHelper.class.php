<?php

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

require_once __DIR__.'/lib/call.php';
require_once __DIR__.'/lib/config.php';
require_once __DIR__.'/tok/Tokenizer.class.php';
require_once __DIR__.'/tok/TokPlugin.iface.php';
require_once __DIR__.'/FSEntry.class.php';
require_once __DIR__.'/PhpCode.class.php';
require_once __DIR__.'/Profiler.class.php';
require_once __DIR__.'/PhpCode.class.php';
require_once __DIR__.'/JSON.class.php';
require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Dir.class.php';

use rkphplib\tok\Tokenizer;
use rkphplib\tok\TokPlugin;


/**
 * Test suite class.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TestHelper implements TokPlugin {

// @var Tokenizer $tok
private $tok = null;

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
	// overall counter
	$this->_tc['t_ok'] = 0;
	$this->_tc['t_error'] = 0;
	$this->_tc['t_num'] = 0;
	$this->_tc['t_pass'] = 0;
	$this->_tc['t_fail'] = 0;

	$this->_tc['path'] = '';
	$this->_tc['overview'] = [];

	$this->reset();
}


/**
 * Reset local test counter.
 */
public function reset() {
	$this->_tc['num'] = 0;
	$this->_tc['ok'] = 0;
	$this->_tc['error'] = 0;
	$this->_tc['vim'] = [];
}


/**
 * Register {tok_helper:show_value}
 */
public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];
	$plugin['test_helper:show_value'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['test_helper'] = 0;
	return $plugin;
}


/**
 * @tok {test_helper:show_value}{math:}5+3{:math}{:test_helper} …
 * &#123;math&#58;t&#125;5+3&#123;&#58;math&#125;=[8] 
 * @EOL
 */
public function tok_test_helper_show_value(string $arg) : string {
	$lines = explode("\n", $arg);
	$res = '';

	foreach ($lines as $line) {
		$line = trim($line);

		if (substr($line, 0, 1) == '{' && substr($line, -1) == '}') {
			$res .= $this->tok->escape($line).' = ['.$line."]\n";
		}
		else if (strlen($line) > 0) {
			throw new Exception("invalid line [$line]");
		}
	}

	return $res;
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
 * Print log message on 80 char width. If $msg is array
 * print $msg[0] left and $msg[1] right aligned in same line.
 * Flag $cn (2^n):
 *
 * 0: print only $msg
 * 1: add trailing linebreak to msg (default)
 * 2: add leading linebreak
 * 4: print delimiter line before $msg
 * 8: print delimiter line after $msg
 * 16: add linebreak to output
 *
 * @param string|array $msg
 */
private function _log($msg, int $cn = 1) : void {
	if ($cn & 2) {
		print "\n";
	}

	if ($cn & 4) {
		print "\x1b[0;2m".str_pad('', 80, '-', STR_PAD_LEFT)."\x1b[0m\n";
	}

	if (is_array($msg) && count($msg) == 2) {
		if (preg_match("/^(.*?)\x1b\[.+?m(.*)\x1b\[0m$/", $msg[1], $match)) {
			$len = strlen($match[1]) + strlen($match[2]);
		}
		else {
			$len = strlen($msg[1]);
		}

		printf('%-'.(80 - $len)."s%s", $msg[0], $msg[1]);
	}
	else {
		print $msg;
	}

	if ($cn & 1) {
		print "\n";
	}

	if ($cn & 8) {
		print "\x1b[0;2m".str_pad('', 80, '-', STR_PAD_LEFT)."\x1b[0m\n";
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
	else if (!empty($_SERVER['SCRIPT_FILENAME']) && ($pos = mb_strpos($_SERVER['SCRIPT_FILENAME'], '/rkphplib/')) !== false) {
		$rkphplib = mb_substr($_SERVER['SCRIPT_FILENAME'], 0, $pos).'/rkphplib';
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
 * Execute $dir/run.php.
 */
public function test(string $dir) : void {
	File::exists($dir.'/run.php');

	if (!chdir($dir)) {
		throw new Exception("chdir $dir");
	}

	include 'run.php';
	chdir('..');
}


/**
 * Run test script.
 */
public function runTest(string $run_php) : void {

	FSEntry::isFile($run_php);
	$script_dir = dirname($run_php);

	$this->reset();
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
 * Initialize Tokenizer with plugin list.
 * 
 * @example useTokPlugin([ 'TBase', 'TMath' ]);
 */
public function useTokPlugin(array $plugin_list, int $tok_flag = Tokenizer::TOK_DEBUG) : void {
	list ($tdir, $rel_tdir) = $this->_test_dir();

	$this->load_src('tok/Tokenizer');
	$this->tok = new Tokenizer($tok_flag);
	$this->tok->register($this);

	for ($i = 0; $i < count($plugin_list); $i++) {
		if (is_string($plugin_list[$i])) {
			$this->load_src('tok/'.$plugin_list[$i]);
			$plugin = 'rkphplib\\tok\\'.$plugin_list[$i];
			$this->tok->register(new $plugin());
		}
		else {
			$this->tok->register($plugin_list[$i]);
		}
	}
}


/**
 * @example load_src('tok/Tokenizer')
 * @example load_src(''); // in tok.TBase
 */
private function load_src(string $cpath = '') : void {
	if (empty($cpath)) {
		$cpath = str_replace('.', '/', basename(getcwd()));
	}

	if (File::exists(PATH_RKPHPLIB.$cpath.'.php')) {
		require_once PATH_RKPHPLIB.$cpath.'.php';
	}
	else if (File::exists(PATH_RKPHPLIB.$cpath.'.class.php')) {
		require_once PATH_RKPHPLIB.$cpath.'.class.php';
	}
	else {
		throw new Exception("missing $cpath.[php|class.php]");
	}
}


/**
 * Initialize run(). Calculate Test number.
 */
private function prepareRun(int $first, int $last) : int {
	$tnum = $last - $first + 1;
	$cname = basename(getcwd());

	if ($tnum < 0) {
		throw new Exception("invalid call run($first, $last)");
	}
	else if ($first == 0) {
		$this->_log($cname.':  no tests', 3);
		return 0;
	}

	$this->load_src();

	if (!Dir::exists('out')) {
		Dir::create('out');
	}

	$tnum = $this->getTestNumber($first, $last);

	if ($tnum == 1) {
		$this->_log([ $cname.':', '1 test' ], 11);
	}
	else {
		$this->_log([ $cname.':', $tnum.' tests' ], 11);
	}

	$this->reset();

	return $tnum;
}


/**
 * Return test number.
 */
private function getTestNumber(int $first, int $last) : int {
	$in_out = false;
	$tnum = 0;

	for ($i = $first; $i > 0 && $i <= $last; $i++) {
		$base = 't'.$i;
		$prefix = 'in/'.$base;

		if (File::exists($prefix.'.php') || File::exists($prefix.'.tok')) {
			$tnum++;
			$in_out = true;
		}
		else if (File::exists($prefix.'.json.php')) {
			include($prefix.'.json.php');
			$tnum += count($test) - 1;
		}
		else if (File::exists($prefix.'.json')) {
			$tnum += count(File::loadJSON($prefix.'.json')) - 1;
		}
		else {
			throw new Exception("no such file $prefix.[php|txt|tok|json]");
		}
	}

	if ($in_out) {
		Dir::exists('in', true);
		Dir::exists('ok', true);
	}

	return $tnum;
}


/**
 * @example run(1, 6)
 */
public function run(int $first, int $last) : void {

	if (($tnum = $this->prepareRun($first, $last)) == 0) {
		return;
	}

	for ($i = $first; $i > 0 && $i <= $last; $i++) {
		$base = 't'.$i;
		$prefix = 'in/'.$base;
		$this->_tc['num']++;

		if (File::exists($prefix.'.php')) {
			$out = $this->execPHP($base);
			$file = 'in/'.$base.'.php';
		}
		else if (File::exists($prefix.'.tok')) {
			$out = $this->execTok($base);
			$file = 'in/'.$base.'.tok';
		}
		else if (File::exists($prefix.'.json')) {
			$this->execJSON($prefix.'.json', $tnum);
			continue;
		}
 		else if (File::exists($prefix.'.json.php')) {
			$this->execJSON($prefix.'.json.php', $tnum);
			continue;
		}
		else {
			throw new Exception("no such file $prefix.[php|txt|tok]");
		}

		$cmp = $out == File::load("ok/{$base}.txt");
		$this->logRun($file, $cmp, $tnum);

		if (!$cmp) {
			$vim = "out/e$i.vim";
			File::save($vim, "e $file\nsplit\ne out/t$i.txt\nvsplit\ne ok/t$i.txt");
			array_push($this->_tc['vim'], 'vim -S '.$vim);
		}
	}

	$this->logResult();
}


/**
 * Print ok or error message.
 */
private function logRun(string $label, bool $ok, int $tnum) : void {
	if ($ok) {
		$this->_log([ $label, "{$this->_tc['num']}/$tnum \x1b[0;32mOK\x1b[0m" ], 1);
		$this->_tc['ok']++;
	}
	else {
		$this->_log([ $label, "{$this->_tc['num']}/$tnum \x1b[0;31mERROR\x1b[0m" ], 1);
		$this->_tc['error']++;
	}
}


/**
 *
 */
private function logResult() {
	if ($this->_tc['error'] == 0) {
		$this->_log([ "RESULT: {$this->_tc['ok']}/{$this->_tc['num']} OK - 0 ERROR",  "\x1b[0;32mPASS\x1b[0m" ], 5);
	}
	else {
		$this->_log([ "RESULT: {$this->_tc['ok']}/{$this->_tc['num']} OK - {$this->_tc['error']} ERROR",  "\x1b[0;31mFAIL\x1b[0m" ], 5);
		$this->_log("VIEW ERROR: ".join('; ', $this->_tc['vim']).' (exit with :qa)');
	}
}


/**
 * Return result of $call($args) as string. IF Exception occurs
 * return 'EXCEPTION'.
 * 
 * @example call('rkphplib\DateCalc::sql2num', [ '2020-07-12' ])
 * @return any
 */
private function call(string $call, array $args) {
	try {
		$res = \rkphplib\lib\call($call, $args);
	}
	catch (\Exception $e) {
		return 'EXCEPTION';
	}

	return self::res2str($res);
}


/**
 * File is either *.json or *.json.php (with $test = [ ... ]).
 *
 * @example …
 * [ 
 *   "rkphplib\\Class::method",
 *   [ "parameter 1", "parameter 2", "expected result" ],
 *   [ [ 1, 2 ], 17.3, "EXCEPTION" ],
 *   [ "NOW()", "<?= time()" ]
 * ]
 * @EOF
 * 
 * @example …
 * <?php $text = [ 'lib\split_str', [ ',', '"a","b","c"', ['a','b','c']], ... ]
 * @EOF
 */
private function execJSON(string $file, int $tnum) : void {
	if (substr($file, -4) == '.php') {
		include $file;
	}
	else {
		$test = File::loadJSON($file);
	}

	$call = array_shift($test);
	$this->_tc['num']--;

	for ($i = 0; $i < count($test); $i++) {
		if (count($test[$i]) < 2) {
			throw new Exception("less than two arguments in $call $i", print_r($test[$i], true));
		}

		$ok = array_pop($test[$i]);
		$args = $test[$i];

		if (substr($ok, 0, 4) == '<?= ') {
			eval('$ok = '.substr($ok, 4).';');
		}

		$this->_tc['num']++;
		$label = '';

		if (substr($call, 0, 8) == 'compare,') {
			if (count($args) != 1) {
				throw new Exception("more than two arguments in $call $i", print_r($args, true));
			}

			$label = substr($call, 8).' '.($i + 1);
			$out = $args[0];
		}
		else {
			$out = $this->call($call, $args);
			$label = $call.' '.($i + 1);
		}

		$cmp = $ok === $out;

		$this->logRun($label, $cmp, $tnum);

		if (!$cmp) {
			$this->error_vim($call.'_'.($i + 1), $out, $ok, $args);
		}
	}
}


/**
 * Create out/$base.[out|ok|vim].
 */
private function error_vim($base, $out, $ok, $in = null) : void {
	$base = 'out/'.preg_replace('/[^a-zA-Z0-9_\-\.]/', '', basename($base));

	File::save($base.'.out', '['.print_r($out, true).']');
	File::save($base.'.ok', '['.print_r($ok, true).']');

	if (!is_null($in)) {
		File::save($base.'.in', '['.print_r($in, true).']');
		File::save($base.'.vim', "e $base.in\nsplit\ne $base.out\nvsplit\ne $base.ok");
	}
	else {
		File::save($base.'.vim', "e $base.out\nvsplit\ne $base.ok");
	}

	array_push($this->_tc['vim'], "vim -S $base.vim");
}


/**
 * Execute (include) $base.php, save output as out/$base.txt
 * and compare with ok/$base.txt. Log _tc.num|err.ok and print message.
 */
private function execPHP(string $base) : string {
	ob_start();
	include "in/$base.php";
	$out = ob_get_contents();
	ob_end_clean();
	File::save("out/$base.txt", $out);
	return $out;
}


/**
 * Parse in/$base.tok and save as out/$base.txt.
 */
private function execTok(string $base) : string {
	if (is_null($this->tok)) {
		throw new Exception('call useTokPlugin() first');
	}

	$in = File::load('in/'.$base.'.tok');
	$this->tok->setText($in);
	$out = $this->tok->toString();

	if (strpos($in, '{test_helper:show_value}') !== false) {
		$out = $this->tok->unescape($out);
	}

	File::save('out/'.$base.'.txt', $out);

	return $out;
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

		$tok = new Tokenizer();
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

		$tok = new Tokenizer();
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
 * @example
 *  Call useTokenizer() first
 *
 * @param mixed $num int|string|array
 */
public function runTokenizer($num, array $plugin_list) : void {

	list ($tdir, $rel_tdir) = $this->_test_dir();
	$this->useTokPlugin($plugin_list);

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

