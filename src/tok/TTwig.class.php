<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Exception.class.php';

use rkphplib\Exception;


/**
 * Tokenizer to twig converter.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TTwig implements TokPlugin {


/**
 * Return Plugin list: autoescape, block, do, embed, extends, filter, flush, for, from, if, import, include, macro, sandbox, set, 
 * spaceless, use, verbatim, v.
 */
public function getPlugins(Tokenizer $tok) : array {

	$plugin = [
		'autoescape' => TokPlugin::TOKCALL,
		'block' => TokPlugin::TOKCALL,
		'do' => TokPlugin::TOKCALL,
		'embed' => TokPlugin::TOKCALL,
		'extends' => TokPlugin::TOKCALL,
		'filter' => TokPlugin::TOKCALL,
		'flush' => TokPlugin::TOKCALL,
		'for' => TokPlugin::TOKCALL,
		'from' => TokPlugin::TOKCALL,
		'if' => TokPlugin::TOKCALL,
		'import' => TokPlugin::TOKCALL,
		'include' => TokPlugin::TOKCALL,
		'macro' => TokPlugin::TOKCALL,
		'sandbox' => TokPlugin::TOKCALL,
		'set' => TokPlugin::TOKCALL,
		'spaceless' => TokPlugin::TOKCALL,
		'use' => TokPlugin::TOKCALL,
		'verbatim' => TokPlugin::TOKCALL,
		'v' => TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY
	];

	return $plugin;
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
