<?php

namespace rkphplib;

require_once(__DIR__.'/Tokenizer.class.php');
require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;


/**
 * Tokenizer to twig converter.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TTwig {

/** @var map $tokPlugin plugin definition @see __construct() */
public $tokPlugin = [ ];


/**
 * Constructor. Tokenizer plugin definition:
 *
 * - autoescape, block, do, embed, extends, filter, flush, for, from, if, import, include, macro, sandbox, set, 
 *   spaceless, use, verbatim, v
 *
 */
public function __construct() {

	$default = [ 'autoescape', 'block', 'do', 'embed', 'extends', 'filter', 'flush', 'for', 'from',
		'if', 'import', 'include', 'macro', 'sandbox', 'set', 'spaceless', 'use', 'verbatim' ];

	foreach ($default as $key) {
		$this->tokPlugin[$key] = Tokenizer::TOKCALL;
	}

	$this->tokPlugin['v'] = Tokenizer::REQUIRE_PARAM | Tokenizer::NO_BODY;
}


/**
 * Convert {v:x} to {{ x }}.
 *
 * @param string $param
 * @return string
 */
public function tok_v($param) {
	return '{{ '.$param.' }}';
}


/**
 * Return {% $action $param %}$arg{% end$action %}.
 *
 * @param string $action
 * @param string $param
 * @param string $arg
 * @return string
 */
public function tokCall($action, $param, $arg) {
	$pa = mb_strlen($param) > 0 ? $param.' %}' : '%}';
	$res = '{% '.$action.' '.$pa.$arg.'{% end'.$action.' %}';
	return $res;
}

}
