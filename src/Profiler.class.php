<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';


/**
 * PHP Profiler.
 * 
 * function example() {
 *   $prof = new \rkphplib\Profiler();
 *   $prof->log('enter');
 *   ...
 *   $prof->log('before xyz');
 *   ...
 *   $prof->log('after xyz');
 *   ...
 *   $prof->log('exit');
 *   $prof->writeLog();
 * }
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Profiler {

private $_log = array();
private $_xlog = array('time' => 0, 'memory' => 0, 'maxmem' => 0);
private $_xdebug_on = false;


/**
 * Constructor.
 */
public function __construct() {
	$this->_xlog['time'] = microtime(true);
	$this->_xlog['memory'] = memory_get_usage();
	$this->_xlog['maxmem'] = $this->_xlog['memory'];
	$this->_xlog['minmem'] = $this->_xlog['memory'];

	$this->_xdebug_on = function_exists('\xdebug_get_profiler_filename') && !empty(\xdebug_get_profiler_filename());
}


/**
 * Start xdebug trace. Needs xdebug module (apt-get install php5-xdebug).
 */
public function startXDTrace(string $trace_file = '/tmp/php5-xdebug.trace') : void {
	xdebug_start_trace($trace_file); 

	if (!$this->_xdebug_on) {
		$this->_xdebug_on = !empty(xdebug_get_tracefile_name());
	}
}


/**
 * Return parsed debug_backtrace() output [ (file, line, call, args), ... ]. 
 * Use $num > 0 for specific trace. Use $num < 0 for max = ($num * -1) trace array length.
 * Return hash (file, line, call, args) or array of hash. Flag $mode is 2^0=1=file, 2^1=2=line, 
 * 4=call, 8=args (default=file|line|call=7).
 */
public static function trace(int $num = -6, int $mode = 7) : array {

	$trace = debug_backtrace();
	$tnum = count($trace);
	$start = 1;

	if ($tnum == 1) {
		$start = 0;
		$end = 1;
	}
	else if ($num == 0) {
		$end = $tnum;
	}
	else if ($num < 0) {
		$end = min($tnum, $num * -1 + 1);
	}
	else if ($num > 0) {
		$start = $num;
		$end = $num + 1;
	}

	if ($end > $tnum) {
		throw new Exception('only '.$tnum.' debug traces available', "num=$num start=$start end=$end");
	}

	$tlist = array();
	$tl = 0;

	// i = 1: ignore lib_trace entry
	for ($i = $start; $i < $end; $i++) {
		$res = array('file' => '', 'line' => '', 'call' => '', 'args' => '');
		$t = $trace[$i];

		if (($mode & 1) && isset($t['file'])) {
			$res['file'] = basename($t['file']);
		}

		if (($mode & 2) && isset($t['line'])) {
			$res['line'] = $t['line'];
		}

		array_push($tlist, $res);
		$tl = count($tlist) - 1;

		if (!($mode & 4)) {
			continue;
		}

		if ($t['function'] == 'include' || $t['function'] == 'include_once' || $t['function'] == 'require' || $t['function'] == 'require_once') {
			$tlist[$tl]['call'] = $t['function'];
			continue;
		}

		$func = $t['function'];

		if (isset($t['class'])) {
			$func = $t['class'].$t['type'].$func;
		}

		if (($mode & 8) && isset($t['args'][0])) {
			$comma = ' ';
			$args = '';

			foreach ($t['args'] as $val) {
				$args .= $comma.self::print_var($val);
				$comma = ', ';
			}

     	$tlist[$tl]['args'] = $args;
		}

		$tlist[$tl]['call'] = $func;
	}

	return (count($tlist) == 1) ? $tlist[0] : $tlist;
}


/**
 * Return mixed $var as string. Return boolean as 'true'|'false' string. 
 * Cut string to 80 characters, return object as $Classname, array as array(key => value, ...) 
 */
public static function print_var($var) : string {
  $res = '';

  if (is_string($var)) {
    $res = '"'.str_replace(array("x00", "x0a", "x0d", "x1a", "x09"), array('\0', '\n', '\r', '\Z', '\t'), $var ).'"';

    if (mb_strlen($res) > 80) {
      $res = mb_substr($res, 0, 80).' ..."';
    }
  }
  else if (is_bool($var)) {
    if ($var) {
      $res = 'true';
    }
    else {
      $res = 'false';
    }
  }
  else if (is_object($var)) {
    $res = '$'.get_class($var);
  }
  else if (is_array($var)) {
    $res = 'array( ';
    $comma = '';

    foreach ($var as $key => $val) {
      $res .= $comma.lib_print_var($key).' => '.lib_print_var($val);
      $comma = ', ';
    }

    $res .= ' )';
  }
  else {
    $res = var_export($var, true);
  }

  return $res;
}


/**
 * Add log message (time, memory, message, call, file_line) to _log. Use Profile::traceLast() for $bt.
 */
public function log(string $msg = '') : void {
	$ts = microtime(true);
	$mem = memory_get_usage();

	if ($mem > $this->_xlog['maxmem']) {
		$this->_xlog['maxmem'] = $mem;
	}

	if ($mem < $this->_xlog['minmem']) {
		$this->_xlog['minmem'] = $mem;
	}

	$log = array('call' => '', 'file_line' => '');
	$log['time'] = $ts - $this->_xlog['time'];
	$log['memory'] = $mem - $this->_xlog['memory'];
	$log['message'] = $msg;

	if ($this->_xdebug_on) {
		$class = xdebug_call_class();
		$func = xdebug_call_function();
		$log['call'] = $class ? $class.'::'.$func : $func;
		$log['file_line'] = basename(xdebug_call_file()).':'.xdebug_call_line();
	}

	array_push($this->_log, $log);

	$this->_xlog['time'] = $ts;
	$this->_xlog['memory'] = $mem;
}


/**
 * Return last backtrace information (call, file.line). Call is either function or class::method.
 */
public static function traceLast() : array {
	$bt = debug_backtrace();
	$res = array('', '');

	if (!isset($bt[1])) {
		return $res;
	}

  $res[0] = isset($bt[1]['class']) ? $bt[1]['class'].'::'.$bt[1]['function'] : $bt[1]['function'];
	$res[1] = basename($bt[1]['file']).'.'.$bt[1]['line'];

	return $res;
}


/**
 * Return log as json (javascript).
 */
public function log2json(string $js_var = '') : string {
	$elapsed_time = 0;
	$json = empty($js_var) ? "[\n" : "<script>\nvar $js_var = [\n";

	for ($i = 0; $i < count($this->_log); $i++) {
		$log = $this->_log[$i];

		$time = ($log['time'] > 1) ? round($log['time'], 2).' s' : round($log['time'] * 1000, 2).' ms';
		$mem = ($log['memory'] > 1024) ? round($log['memory'] / 1024, 2).' kb' : $log['memory'].' b';
		$elapsed_time += $log['time'];

		$json .= sprintf("\n\t".'[ "%s", "%s", "%s", "%s", "%s" ],', $time, $mem, $log['message'], $log['file_line'], $log['call']);
	}

	$minmem = round($this->_xlog['minmem'] / 1024, 2).' kb';
	$maxmem = round($this->_xlog['maxmem'] / 1024, 2).' kb';
	$json .= sprintf("\n\t".'[ "%s", "%s", "%s" ]'."\n]", round($elapsed_time, 2).' s', $minmem, $maxmem);

	if ($js_var) {
		$json .= ";\n</script>";
	}

	return $json;
}


/**
 * Write log as tab csv (default: print = write to php://output).
 */
public function writeLog(string $file = 'php://output') : void {
	$fd = fopen($file, 'a');
	$elapsed_time = 0;

	for ($i = 0; $i < count($this->_log); $i++) {
		$log = $this->_log[$i];

		$time = ($log['time'] > 0.01) ? round($log['time'], 2).' s' : round($log['time'] * 1000, 2).' ms';
		$mem = ($log['memory'] > 1024) ? round($log['memory'] / 1024, 2).' kb' : $log['memory'].' b';
		$elapsed_time += $log['time'];

		fprintf($fd, "%10s\t%10s\t%-40s\t%s\t%s\n", $time, $mem, $log['message'], $log['file_line'], $log['call']);
	}

	$minmem = round($this->_xlog['minmem'] / 1024, 2).' kb';
	$maxmem = round($this->_xlog['maxmem'] / 1024, 2).' kb';
	fprintf($fd, "%10s\t%10s\t%10s", round($elapsed_time, 2).' s', $minmem, $maxmem);

	fclose($fd);
}


/**
 * Stop xdebug trace. Call startXDTrace() first.
 */
public function stopXDTrace(string $file = 'php://STDOUT') : void {
	$trace_file = xdebug_get_tracefile_name();
	xdebug_stop_trace();

	if (!$trace_file || !$file) {
		return;
	}

	$fh = fopen($trace_file, 'r');
  fgets($fh); // ignore first line

  $trace_buildin = array(
    'microtime()',
    'memory_get_usage()',
    'xdebug_call_class()',
    'xdebug_call_function()',
    'xdebug_call_line()',
    'xdebug_call_file()',
    'xdebug_stop_trace()');

  $trace_func = array(
    'rkphplib\Profiler->log()');

  $buildin_list = array('time', 'in_array', 'is_object', 'is_array', 'is_null', 'array_push', 'join', 'preg_match', 'array_keys',
    'array_shift', 'array_pop', 'array_push', 'strpos', 'preg_split', 'count', 'substr', 'mb_substr', 'basename', 'str_replace',
    'mysqli->real_escape_string', 'mysqli->mysqli', 'mysqli->real_query', 'mysqli->set_charset',
    'mysqli->query', 'mysqli_result->fetch_assoc', 'mysqli_result->close', 'mysqli->prepare', 'mysqli_stmt->bind_param',
    'mysqli_stmt->execute', 'mysqli_stmt->close', 'ReflectionClass->__construct', 'ReflectionClass->getMethod',
    'ReflectionMethod->invokeArgs');

  $buildin = array();
  foreach ($buildin_list as $func) {
    $buildin[$func.'()'] = 0;
  }

	$custom = array();

  $last = array(0, 0, '', '');

  while (($line = fgets($fh))) {
    $col = preg_split("/ +/", trim($line));

    if (empty($col[4])) {
      continue;
    }

    if (in_array($col[3], $trace_buildin)) {
      $last = $col;
      continue;
    }

    if (in_array($col[3], $trace_func)) {
      // ToDo ... ignore following
      continue;
    }

    if (isset($buildin[$col[3]])) {
      $buildin[$col[3]]++;
    }
    else {
      if (!isset($custom[$col[3]])) {
        $custom[$col[3]] = array('call' => 0, 'time' => 0, 'mem' => 0);
      }

      $custom[$col[3]]['call']++;
      // ToDo ... collect following
      $custom[$col[3]]['time'] = 0; 
      $custom[$col[3]]['mem'] = 0; 
    }

    $last = $col;
  }

	fclose($fh);

	$fh = fopen($file, 'a');

  fwrite($fh, "\nBuildIn Functions:\n");
  foreach ($buildin as $func => $call) {
    if ($call > 10) {
      fprintf($fh, "%10s%50s\n", $call.'x ', $func);
    }
  }

	fwrite($fh, "\nCustom Functions:\n");
  foreach ($custom as $func => $info) {
    if ($info['call'] > 0) {
			$c = $info['call'];
      fprintf($fh, "%10s%50s%16s%16s\n", $c.'x ', $func, round($info['time'] / $c, 4).' s',round($info['mem'] / $c, 0).' b');
    }
  }

	fclose($fh);
}


}

