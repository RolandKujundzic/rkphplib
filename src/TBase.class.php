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

/** @var bool $_tf_nested true if inside true or false block */
private $_tf_nested = false;

/** */
public $tokPlugin = [
	'tf' => Tokenizer::PARAM_LIST, 
	't' => Tokenizer::REQUIRE_BODY | Tokenizer::TEXT | Tokenizer::REDO,
	'true' => Tokenizer::REQUIRE_BODY | Tokenizer::TEXT | Tokenizer::REDO, 
	'f' => Tokenizer::REQUIRE_BODY | Tokenizer::TEXT | Tokenizer::REDO, 
	'false' => Tokenizer::REQUIRE_BODY | Tokenizer::TEXT | Tokenizer::REDO 
];


/**
 * Evaluate condition. Use tf, t(rue) and f(alse) as control structure plugin. 
 * Evaluation result is saved in $_tf and reused in tok_t[true]() and tok_f[false]().
 * Parameter Evaluation:
 *
 * - p.length == 1 and p[0] is empty: true if !empty($arg) @test:t1
 * - p.length == 1 and p[0] == !: true if empty($arg) @test:t2
 * - p.length == 1 and p[0] == switch: compare true:param with arg later @test:t3
 * - p.length == 2 and p[0] == cmp: true if p[1] == $arg @test:t4
 * - p.length == 2 and p[0] == prev[:n]: modify result of previous evaluation @test:t5 
 * - p.length == 2 and p[0] in (eq, ne, lt, le, gt, ge): floatval($arg) p[0] floatval(p[1]) @test:t6
 * - p.length == 2 and p[0] in (and, or, cmp): 
 *
 * @tok {tf:eq:5}3{:tf} = false, {tf:lt:3}1{:tf} = true, {tf:}0{:tf} = false, {tf:}00{:tf} = true
 * @param array $p
 * @param string $arg
 * @return empty
 */
public function tok_tf($p, $arg) {
	$tf = false;

	if (count($p) == 1) {
		$arg = trim($arg);

		if (mb_strlen($p[0]) == 0) {
			$tf = !empty($arg);
		}
		else if ($p[0] == '!') {
			$tf = empty($arg);
		}
		else if ($p[0] == 'switch') {
			$tf = $arg;
		}
	}
	else if (count($p) == 2) {
		$fvp = floatval($p[1]);
		$fva = floatval($arg);

		if ($p[0] == 'eq') {
			$tf = ($fva == $fvp); 
		}
		else if ($p[0] == 'ne') {
			$tf = ($fva != $fvp); 
		}
		else if ($p[0] == 'lt') {
			$tf = ($fva < $fvp); 
		}
		else if ($p[0] == 'le') {
			$tf = ($fva <= $fvp); 
		}
		else if ($p[0] == 'gt') {
			$tf = ($fva > $fvp); 
		}
		else if ($p[0] == 'ge') {
			$tf = ($fva >= $fvp); 
		}
		else if ($p[0] == 'cmp') {
			$tf = ($p[1] == $arg);
		}
	}

	array_push($this->_tf, $tf);

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
	if (count($this->_tf) == 0) {
 		throw new Exception('call tf first', 'Plugin [true:]'.$out.'[:true]');
	}

	$tf = end($this->_tf);

	return ($tf === true || (is_string($tf) && $tf === $val)) ? $out : '';
}


/**
 * Same as tok_false().
 * @alias tok_false()
 */
public function tok_f($out) {
	return $this->tok_f($out);
}


/**
 * Return $out if top($_tf) = false.
 * @param string $out
 * @return $out|empty
 */
public function tok_false($out) {

	if (count($this->_tf) == 0) {
 		throw new Exception('call tf first', 'Plugin [false:]'.$out.'[:false]');
	}

	$tf = end($this->_tf);

	return $tf === false ? $out : '';
}


}

