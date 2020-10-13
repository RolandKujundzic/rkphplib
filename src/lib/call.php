<?php
  
namespace rkphplib\lib;

require_once dirname(__DIR__).'/Exception.class.php';

use rkphplib\Exception;


/**
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 * Return dynamic function|method call result.
 * If is either hash or vector (max length 4).
 *
 * @example call('print', [ 'abc' ])
 * @example call('rkphplib\File::size', [ '/etc/hosts' ])
 * @example call('Foo.bar')
 * 
 * @$res = any
 */
function call(string $name, array $arg = []) {
	$anum = count($arg);
	$res = null;

	if ($anum > 0 && !array_key_exists('0', $arg)) {
		$anum = -1;
	}

	if (empty($name)) {
		throw new Exception('empty class|function');
	}
	else if ($anum > 4) {
		throw new Exception('more than 4 arguments', "$name: ".print_r($arg, true));
	}

	// \rkphplib\lib\log_debug([ "call:37> anum=<1> call $name(<2>)", $anum, $arg ]);
	if (($pos = strpos($name, '.')) > 0) {
		$oname = substr($name, 0, $pos);
		$obj = new $oname();
		$func = substr($name, $pos + 1);

		if ($anum == 0) {
			$res = $obj->$func();
		}
		else if ($anum == -1) {
			$res = $obj->$func($arg);
		}
		else if ($anum == 1) {
			$res = $obj->$func($arg[0]);
		}
		else if ($anum == 2) {
			$res = $obj->$func($arg[0], $arg[1]);
		}
		else if ($anum == 3) {
			$res = $obj->$func($arg[0], $arg[1], $arg[2]);
		}
		else {
			$res = $obj->$func($arg[0], $arg[1], $arg[2], $arg[3]);
		}
	}
	else if (($pos = strpos($name, '::')) > 0) {
		$class = substr($name, 0, $pos);
		$func = substr($name, $pos + 2);

		if ($anum == 0) {
			$res = $class::$func();
		}
		else if ($anum == -1) {
			$res = $class::$func($arg);
		}
		else if ($anum == 1) {
			$res = $class::$func($arg[0]);
		}
		else if ($anum == 2) {
			$res = $class::$func($arg[0], $arg[1]);
		}
		else if ($anum == 3) {
			$res = $class::$func($arg[0], $arg[1], $arg[2]);
		}
		else {
			$res = $class::$func($arg[0], $arg[1], $arg[2], $arg[3]);
		}
	}
	else {
		if (!array_key_exists('0', $arg)) {
			$res = $name();
		}
		else if ($anum == -1) {
			$res = $name($arg);
		}
		else if ($anum == 1) {
			$res = $name($arg[0]);
		}
		else if ($anum == 2) {
			$res = $name($arg[0], $arg[1]);
		}
		else if ($anum == 3) {
			$res = $name($arg[0], $arg[1], $arg[2]);
		}
		else {
			$res = $name($arg[0], $arg[1], $arg[2], $arg[3]);
		}
	}

	// \rkphplib\lib\log_debug([ "call:106> $res = [<1>]", $res ]);
	return $res;
}

