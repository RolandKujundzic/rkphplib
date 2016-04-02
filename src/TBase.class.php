<?php

namespace rkphplib;

require_once(__DIR__.'/Tokenizer.class.php');
require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/File.class.php');

use rkphplib\Exception;


/**
 * Basic Tokenizer plugins.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TBase {

/** @var vector<bool> $_tf keep results of (nested) tok_tf evaluation */
private $_tf = [ ];

/** @var map $tokPlugin plugin definition @see __construct() */
public $tokPlugin = [ ];


/**
 * Constructor. Tokenizer plugin definition:
 *
 * - tf: PARAM_LIST
 * - t, true: REQUIRE_BODY, TEXT, REDO
 * - f, false: REQUIRE_BODY, TEXT, REDO, NO_PARAM
 *
 */
public function __construct() {
	$this->tokPlugin['tf'] = Tokenizer::PARAM_LIST; 
	$this->tokPlugin['t'] = Tokenizer::REQUIRE_BODY | Tokenizer::TEXT | Tokenizer::REDO;
	$this->tokPlugin['true'] = Tokenizer::REQUIRE_BODY | Tokenizer::TEXT | Tokenizer::REDO; 
	$this->tokPlugin['f'] = Tokenizer::REQUIRE_BODY | Tokenizer::TEXT | Tokenizer::REDO | Tokenizer::NO_PARAM; 
	$this->tokPlugin['false'] = Tokenizer::REQUIRE_BODY | Tokenizer::TEXT | Tokenizer::REDO | Tokenizer::NO_PARAM;
}


/**
 * Evaluate condition. Use tf, t(rue) and f(alse) as control structure plugin. 
 * Evaluation result is saved in $_tf and reused in tok_t[true]() and tok_f[false]().
 * Merge p with split('|#|', $arg).
 *
 * @test:t1 p.length == 0: true if !empty($arg)
 * @test:t2 p.length == 1 and p[0] == !: true if empty($arg)
 * @test:t3 p.length == 1 and p[0] == switch: compare true:param with arg later (if arg is empty use f:)
 * @test:t4 p.length 1|2 and p[0] == cmp: true if p[1] == $arg
 * @test:t5 p.length 1|2 and p[0] in (eq, ne, lt, le, gt, ge): floatval($arg) p[0] floatval(p[1])
 * @test:t6 p.length >= 1 and p[0] == in_arr: true if end(p) in p[]
 * @test:t7 p[0] == set: search true:param in p[1..n] later
 * @test:t8 p.length == 2 and p[0] == in: set is split(',', p[1]) true if p[0] in set
 * @test:t9 p.length == 2 and p[0] == in_set: set is split(',', p[0]) true if p[1] in set
 *
 * - p.length == 2 and p[0] in (and, or, cmp_and, cmp_or): 
 * - p.length == 2 and p[0] == prev[:n]: modify result of previous evaluation
 *
 * @tok {tf:eq:5}3{:tf} = false, {tf:lt:3}1{:tf} = true, {tf:}0{:tf} = false, {tf:}00{:tf} = true
 * @param array $p
 * @param string $arg
 * @return empty
 */
public function tok_tf($p, $arg) {
	$tf = false;

	$level = $this->tokPlugin['_']->getLevel(); 
	$ta = trim($arg);
	$do = '';

	if (count($p) == 1) {
		if ($p[0] === '') {
			$tf = !empty($ta);
		}
		else if ($p[0] === '!') {
			$tf = empty($ta);
		}
		else if ($p[0] === 'switch') {
			$tf = empty($ta) ? false : $ta;
		}
		else if ($p[0] === 'set') {
			$tf = lib\split_str('|#|', $arg);
		}
		else {
			$do = $p[0];
			$ap = lib\split_str('|#|', $arg);
		}
	}
	else if (count($p) > 1) {
		$do = array_shift($p);
		$ap = array_merge($p, lib\split_str('|#|', $arg));
	}

	if (empty($do)) {
		$this->_tf[$level] = $tf;
		return '';
	}

	if ($do == 'cmp') {
		if (count($ap) != 2) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		$tf = ($ap[0] === $ap[1]);
	}
	else if ($do == 'set') {
		$tf = $ap;
	}
	else if (in_array($do, array('eq', 'ne', 'lt', 'le', 'gt', 'ge'))) {
		if (count($ap) != 2) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		$fva = floatval($ap[0]);
		$fvb = floatval($ap[1]);

		if ($do == 'eq') {
			$tf = ($fva === $fvb); 
		}
		else if ($do == 'ne') {
			$tf = ($fva !== $fvb); 
		}
		else if ($do == 'lt') {
			$tf = ($fva < $fvb); 
		}
		else if ($do == 'le') {
			$tf = ($fva <= $fvb); 
		}
		else if ($do == 'gt') {
			$tf = ($fva > $fvb); 
		}
		else if ($do == 'ge') {
			$tf = ($fva >= $fvb); 
		}
	}
	else if ($do == 'in_arr') {
		if (count($ap) < 2) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		$x = array_pop($ap);
		$tf = in_array($x, $ap);
	}
	else if ($do == 'in' || $do == 'in_set') {
		if (count($ap) != 2) {
			throw new Exception("invalid tf:$do", 'ap=['.join('|', $ap).']');
		}

		if ($do == 'in') {
			$set = lib\split_str(',', $ap[0]);
			$tf = in_array($ap[1], $set);
		}
		else {
			$set = lib\split_str(',', $ap[1]);
			$tf = in_array($ap[0], $set);
		}
	}
	else if ($do == 'and' || $do == 'or' || $do == 'cmp_or' || $do == 'cmp_and') {
		// ToDo ...
		throw new Exception('ToDo ...');
	}

	$this->_tf[$level] = $tf;
	return '';
}


/**
 * Same as tok_true().
 * @alias tok_true()
 */
public function tok_t($param, $arg) {
	return $this->tok_true($param, $arg);
}


/**
 * Return $out if top($_tf) = true or (is_string(top($_tf)) && $val = top($_tf)).
 *
 * @param string $val
 * @param string $out
 * @return $out|empty
 */
public function tok_true($val, $out) {
	$level = $this->tokPlugin['_']->getLevel(); 

	if (count($this->_tf) == 0 || !isset($this->_tf[$level])) {
 		throw new Exception('call tf first', 'Plugin [true:]'.$out.'[:true]');
	}

	return ((is_bool($this->_tf[$level]) && $this->_tf[$level]) || 
		(is_string($this->_tf[$level]) && $this->_tf[$level] === $val) || 
		(is_array($this->_tf[$level]) && !empty($val) && in_array($val, $this->_tf[$level]))) ? $out : '';
}


/**
 * Same as tok_false().
 * @alias tok_false()
 */
public function tok_f($out) {
	return $this->tok_false($out);
}


/**
 * Return $out if top($_tf) = false.
 * @param string $out
 * @return $out|empty
 */
public function tok_false($out) {
	$level = $this->tokPlugin['_']->getLevel(); 

	if (count($this->_tf) == 0 || !isset($this->_tf[$level])) {
 		throw new Exception('call tf first', 'Plugin [false:]'.$out.'[:false]');
	}

	return (is_bool($this->_tf[$level]) && !$this->_tf[$level]) ? $out : '';
}


}

