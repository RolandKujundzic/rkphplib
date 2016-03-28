<?php

$test_count = ['path' => '', 'num' => 0, 'ok' => 0, 'error' => 0, 't_ok' => 0, 't_error' => 0, 't_num' => 0, 't_pass' => 0, 't_fail' => 0,
	'overview' => [ ] ];


/**
 * Run test script.
 * @param string $run_php
 */
function run_test($run_php) {
	global $test_count;

	$test_count['num'] = 0;
	$test_count['ok'] = 0;
	$test_count['error'] = 0;

	print "\n---------------------------------------------------------------------------------------";
	print "\nSTART: ".dirname($run_php)." Tests\n";
	print "---------------------------------------------------------------------------------------\n";

	$test_count['path'] = dirname($run_php);

	// execute tests ...
	include($run_php);

	$result = '';
	$test_count['t_num'] += $test_count['num'];

	if ($test_count['error'] == 0 && $test_count['ok'] > 0) {
		$result = 'PASS';
		$test_count['t_ok'] += $test_count['ok'];
		$test_count['t_pass']++;
	}
 	else {
		$result = 'FAIL';
		$test_count['t_error'] += $test_count['error'];
		$test_count['t_fail']++;
	}

	print "---------------------------------------------------------------------------------------\n";
	print "RESULT: ".$test_count['ok'].'/'.$test_count['num']." OK - ".$test_count['error']." ERROR \t".$result."\n";
	print "---------------------------------------------------------------------------------------\n\n";

	$overview = sprintf("%16s: %3d/%-3d ok - %3d errors", dirname($run_php), $test_count['ok'], $test_count['num'], $test_count['error']);
	array_push($test_count['overview'], $overview);
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
 * Print _fc_function|_fc_static_method call.
 * @param string $call
 * @param any $x
 * @return string
 */
function _fc_log($call, $x) {
	$y = array();
	$prefix = '';
	$suffix = '';

	if (is_array($x)) {
		foreach ($x as $key => $value) {
			if (is_numeric($key)) {
				array_push($y, _fc_log('', $value));
			}
			else {
				array_push($y, $key.' => '._fc_log('', $value));
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
 * @string $func
 * @array $x
 * @return any
 */
function _fc_function($func, $x) {

	print _fc_log("$func", $x);
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
 * @string $class
 * @string $method
 * @array $x
 * @return any
 */
function _fc_static_method($class, $method, $x) {

	print _fc_log("$class::$method", $x);
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
 * Call function and compare result. Load $test (list of function calls - parameterlist + result) and 
 * $func (function name) from $path.fc.php.
 * @param string $path
 */
function fc($path) {
	global $test_count;

	// execute test
	$php_file = empty($test_count['path']) ? $path.'.fc.php' : $test_count['path'].'/'.$path.'.fc.php';
	// load $func and $test from php_file
	require_once($php_file);

	$csm = false;
    if (($pos = strpos($func, '::')) !== false) {
        $class = substr($func, 0, $pos);
        $method = substr($func, $pos + 2);
		$csm = true;
	}

	$test_count['num']++;
	$n_ok = 0;
	$n_err = 0;

	foreach ($test as $x) {
		$ok = array_pop($x);

		if ($csm) {
			$res = _fc_static_method($class, $method, $x);
		}
		else {
			$res = _fc_function($func, $x);
		}

		$res = _res2str($res);
		$msg = 'ok';

		if ($res !== $ok) {
			$msg = ' != '.$ok.' - ERROR!';

			if (strlen($res) > 40) {
				$save_ok = sys_get_temp_dir().'/res.out';
				file_put_contents($save_ok, $res);
				$msg .= ' (see: '.$save_ok.')';
			}

			$n_err++;
		}

		if (is_string($res)) {
			print "'$res' ... $msg\n";
		}
		else {
			print "$res ... $msg\n";
		}
	}

	if (!$n_err) {
		 $test_count['ok']++;
	}
	else {
	  $test_count['error']++;
	}
}


/**
 * Convert function result to string.
 * @param any $res
 * @return string
 */
function _res2str($res) {
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
 * @param int $num
 * @param vector $plugin_list
 */
function run_tokenizer($num, $plugin_list) {
	global $test_count;

	$src_dir = dirname(__DIR__).'/src';
	$tdir = dirname(getcwd().'/'.$_SERVER['SCRIPT_NAME']);

	include_once($src_dir.'/Tokenizer.class.php');
	include_once($src_dir.'/File.class.php');

	$tok = new rkphplib\Tokenizer(rkphplib\Tokenizer::TOK_DEBUG);

	for ($i = 0; $i < count($plugin_list); $i++) {
		$plugin = 'rkphplib\\'.$plugin_list[$i];
		include_once($src_dir.'/'.$plugin_list[$i].'.class.php');
		$tok->register(new $plugin());
	}

	for ($i = 1; $i <= $num; $i++) {
		$tok->setText(rkphplib\File::load($tdir.'/t'.$i.'.txt'));
		$ok = rkphplib\File::load($tdir.'/t'.$i.'.ok.txt');
		$out = $tok->toString();

		$test_count['num']++;
		print "Test $i ... ";

		if ($out != $ok) {
			if (mb_strlen($out) > 40 || strpos($out, "\n") !== false) {
				$out_file = $tdir.'/t'.$i.'.out.txt';
				print "ERROR! (see $out_file)\n";
				rkphplib\File::save($out_file, $out);
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


