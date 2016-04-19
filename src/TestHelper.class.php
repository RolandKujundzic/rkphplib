<?php

namespace rkphplib;

require_once(__DIR__.'/FSEntry.class.php');
require_once(__DIR__.'/File.class.php');



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
	FSEntry::isFile($rkphplib.'/'.$file);

	require_once($rkphplib.'/'.$file);
}


/**
 * Print overall result.
 */
public function result() {
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

		$res = self::_res2str($res);
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
	}
}


/**
 * Call test function $func($arg).
 * @param string $func
 * @param any $arg
 * @param array $result ( NAME, 1, 1, 0, 1, ... ) - 1 = OK, 0 = ERR
 */
function call_test($func, $arg, $result) {
  global $test_count;

  $test_count['num']++;

  print array_shift($result).": ";

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
    print "$n/$n OK\n";
  	$test_count['ok']++;
  }
  else {
    print "$ok/$n OK and $err ERROR\n";
  	$test_count['error']++;
  }
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
private static function _res2str($res) {
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
 * Run tokenizer tests t1.txt ... t$num.txt.
 * Requires t1.ok.txt ... t$num.ok.txt
 *
 * @param any $num (5 = run t1.txt ... t5.txt, 'a.txt' run only this test)
 * @param vector $plugin_list
 */
function run_tokenizer($num, $plugin_list) {
	global $test_count;

	$src_dir = dirname(__DIR__).'/src';
	$tdir = dirname(getcwd().'/'.$_SERVER['SCRIPT_NAME']);

	if (!empty($test_count['path'])) {
		$tdir .= '/'.$test_count['path'];
	}

	include_once($src_dir.'/Tokenizer.class.php');
	include_once($src_dir.'/File.class.php');

	$tok = new rkphplib\Tokenizer(rkphplib\Tokenizer::TOK_DEBUG);

	for ($i = 0; $i < count($plugin_list); $i++) {
		$plugin = 'rkphplib\\'.$plugin_list[$i];
		include_once($src_dir.'/'.$plugin_list[$i].'.class.php');
		$tok->register(new $plugin());
	}

	$test_files = array();

	if (is_string($num)) {
		array_push($test_files, $tdir.'/'.$num);
	}
	else {
		for ($i = 1; $i <= $num; $i++) {
			array_push($test_files, $tdir.'/t'.$i.'.txt');
		}
	}

	$i = 0;
	foreach ($test_files as $f_txt) {
		$f_out = str_replace('.txt', '.out.txt', $f_txt);
		$f_ok = str_replace('.txt', '.ok.txt', $f_txt);
		$i++;

		$tok->setText(rkphplib\File::load($f_txt));
		$ok = rkphplib\File::load($f_ok);
		$out = $tok->toString();

		$test_count['num']++;
		print "Test $i ... ";

		if ($out != $ok) {
			if (mb_strlen($out) > 40 || strpos($out, "\n") !== false) {
				print "ERROR! (see $f_out)\n";
				rkphplib\File::save($f_out, $out);
			}
			else {
				print "$out != $ok - ERROR!\n";
			}

			$test_count['error']++;
		}
		else {
			$test_count['ok']++;
			print "ok\n";
		}
	}
}

}

