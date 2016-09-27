<?php

namespace rkphplib;

require_once(__DIR__.'/iTokPlugin.iface.php');
require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;


/**
 * Tokenizer to twig converter.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TTwig implements iTokPlugin {


/**
 * Return Tokenizer Plugin list:
 *
 * - autoescape, block, do, embed, extends, filter, flush, for, from, if, import, include, macro, sandbox, set, 
 *   spaceless, use, verbatim, v
 *
 * @param Tokenizer &$tok
 * @return map<string:int>
 */
public function getPlugins(&$tok) {

	$plugin = [
		'autoescape' => iTokPlugin::TOKCALL,
		'block' => iTokPlugin::TOKCALL,
		'do' => iTokPlugin::TOKCALL,
		'embed' => iTokPlugin::TOKCALL,
		'extends' => iTokPlugin::TOKCALL,
		'filter' => iTokPlugin::TOKCALL,
		'flush' => iTokPlugin::TOKCALL,
		'for' => iTokPlugin::TOKCALL,
		'from' => iTokPlugin::TOKCALL,
		'if' => iTokPlugin::TOKCALL,
		'import' => iTokPlugin::TOKCALL,
		'include' => iTokPlugin::TOKCALL,
		'macro' => iTokPlugin::TOKCALL,
		'sandbox' => iTokPlugin::TOKCALL,
		'set' => iTokPlugin::TOKCALL,
		'spaceless' => iTokPlugin::TOKCALL,
		'use' => iTokPlugin::TOKCALL,
		'verbatim' => iTokPlugin::TOKCALL,
		'v' => iTokPlugin::REQUIRE_PARAM | iTokPlugin::NO_BODY
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
