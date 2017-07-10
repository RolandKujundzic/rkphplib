<?php

namespace rkphplib;

require_once(__DIR__.'/lib/config.php');
require_once(__DIR__.'/lib/log_debug.php');
require_once(__DIR__.'/FSEntry.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/JSON.class.php');



/**
 * Test suite class.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TestHelper {

/** @var hash $_tc test counter hash */
private $_tc = [];


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
 * Print error message.
 *
 * @param string $msg
 * @param string|array $out
 * @param string|array $ok
 */
private function _error_cmp($msg, $out, $ok) {
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

	print "\nERROR: $m_out $m_ok\n\n";
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
 *
 * @param string $msg
 * @param int $cn
 */
private function _log($msg, $cn = 1) {

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
 * Load php file from rkphplib/ directory.
 * 
 * @param string $file (relative path to rkphplib/ directory) 
 */
public function load($file) {
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
	require_once($php_file);
}


/**
 * Print overall result.
 */
public function result() {

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
 *
 * @param string $run_php
 */
public function runTest($run_php) {

	FSEntry::isFile($run_php);
	$script_dir = dirname($run_php);

	$this->_tc['num'] = 0;
	$this->_tc['ok'] = 0;
	$this->_tc['error'] = 0;
	$this->_tc['path'] = $script_dir;

	$this->_log('START: '.$script_dir.' Tests', 15);

	// execute tests ...
	include($run_php);

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
 * Call function and compare result. Load $test (list of function calls - parameterlist + result) and 
 * $func (function name) from $path.fc.php.
 *
 * @param string $path
 */
public function runFuncTest($path) {

	// execute test
	$php_file = empty($this->_tc['path']) ? $path.'.fc.php' : $this->_tc['path'].'/'.$path.'.fc.php';
	$this->_log('runFuncTest: loading '.$php_file.' ... ', 2);
	require_once($php_file);

	if (!isset($func)) {
		throw new Exception('$func is undefined in '.$php_file); 
	}

	if (!isset($test)) {
		throw new Exception('$test is undefined in '.$php_file); 
	}

	$csm = false;
	if (($pos = strpos($func, '::')) !== false) {
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

		if ($csm) {
			$res = $this->_fc_static_method($class, $method, $x);
		}
		else {
			$res = $this->_fc_function($func, $x);
		}

		$res = self::res2str($res);
		$msg = 'ok';

		if ($res !== $ok) {
			$msg = ' != '.$ok.' - ERROR!';

			if (strlen($res) > 40) {
				$save_ok = sys_get_temp_dir().'/res.out';
				File::save($save_ok, $res);
				$msg .= ' (see: '.$save_ok.')';
			}

			$n_err++;
		}

		if (is_string($res)) {
			$this->_log("'$res' ... $msg");
		}
		else {
			$this->_log("$res ... $msg");
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
 * Call test function $func($arg).
 * @param string $func
 * @param any $arg
 * @param array $result ( NAME, 1, 1, 0, 1, ... ) - 1 = OK, 0 = ERR
 */
public function callTest($func, $arg, $result) {
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
 * Compare output with expected result. Result vector may contain less keys than output (e.g. ignore date values).
 *
 * @param string $msg
 * @param vector<any> $out_list
 * @param vector<any> $ok_list ("@file[.json|.ser|.txt]" entry = load file)
 */
public function compare($msg, $out_list, $ok_list) {
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
 *
 * @param string $msg
 * @param map<string:any> $out
 * @param map<string:any> $ok
 */
public function compareHash($msg, $out, $ok) {
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
 * If value is string with "@" prefix and [.json|.ser|.txt] suffix
 * return file content. Otherwise return value.
 *
 * @param any
 * @return any
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
 * @param string $call
 * @param any $x
 * @return string
 */
private function _fc_log($call, $x) {
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
 * Return result of $func($x[0], $x[1], ...) callback.
 *
 * @string $func
 * @array $x
 * @return any
 */
private function _fc_function($func, $x) {

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
 * Return result of $class::$method($x[0], $x[1], ...) callback.
 *
 * @string $class
 * @string $method
 * @array $x
 * @return any
 */
private function _fc_static_method($class, $method, $x) {

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
 * Convert function result to string.
 *
 * @param any $res
 * @return string
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
		return json_encode($res, 322);
	}
}


/**
 * Return test dir.
 *
 * @return array (abs-path, rel-path)
 */
private function _test_dir() {
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
 * Run tokenizer tests t1.txt ... t$num.txt.
 * Requires t1.ok.txt ... t$num.ok.txt
 *
 * @param any $num (5 = run t1.txt ... t5.txt, 'a.txt' run only this test)
 * @param vector $plugin_list
 */
public function runTokenizer($num, $plugin_list) {

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
		$f_out = str_replace('.txt', '.out.txt', $f_txt);
		$f_ok = str_replace('.txt', '.ok.txt', $f_txt);
		$i++;

		$tok->setText(File::load($f_txt));
		$ok = File::load($f_ok);
		$out = $tok->toString();

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

