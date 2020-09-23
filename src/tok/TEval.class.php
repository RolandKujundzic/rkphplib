<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once dirname(__DIR__).'/lib/call.php';

use rkphplib\Exception;


/**
 * Evaluate mathematical and logic expressions.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TEval implements TokPlugin {


/**
 * @plugin eval:math|logic
 */
public function getPlugins(Tokenizer $tok) : array {
  $plugin = [];
  $plugin['eval:math'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
  $plugin['eval:logic'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
  $plugin['eval:call'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['eval'] = 0;
  return $plugin;
}


/**
 * Execute php function or method. Up to 4 arguments (arg1, ..., arg4).
 * @example {eval:call}function=session_start{:eval}
 * @example {eval:call}class=Test|#|[static_]method=sum|#|arg1=5|#|arg2=3{:eval)
 * @example {eval:call}class=Foo|#|method=bar|#|height=7|#|width=12{:eval}
 */
public static function tok_eval_call(array $p) : string {
	if (!empty($p['function'])) {
		$name = $p['function'];
		unset($p['function']);
	}
	else if (!empty($p['class']) && !empty($p['method'])) {
		$name = $p['class'].'.'.$p['method'];
		unset($p['class']);
		unset($p['method']);
	}
	else if (!empty($p['class']) && !empty($p['static_method'])) {
		$name = $p['class'].'::'.$p['static_method'];
		unset($p['class']);
		unset($p['static_method']);
	}
	else {
		throw new Exception('missing function or class and ([static_]method) parameter', print_r($p, true));
	}

	$arg = [];
	for ($i = 1; $i <= 4; $i++) {
		if (isset($p['arg'.$i])) {
			$arg[$i - 1] = $p['arg'.$i];
		}
	}

	if (count($arg) == 0) {
		$arg = $p;
	}

	return \rkphplib\lib\call($name, $arg);
}


/**
 * Return result of boolean expression evaluation (0|1).
 * Remove all characters not in "tf01&|x!)(". Operators:
 * 
 * & = and, | = or, x = xor and ! = not, 0 = f = false, 1 = t = true
 *
 * @tok {eval:logic}(1 & 0) | t{:eval} = 1 
 */
public static function tok_eval_logic(string $expr) : int {

	$expr = preg_replace("/[\r\n\t ]+/", '', $expr);
	$expr_check = strtr($expr, 'tf01)(x&|!', '          ');
	$res = '';

	if (trim($expr_check) == '') {
		$expr = str_replace([ 't', 'f', 'x', '&', '|' ], [ 1, 0, ' xor ', ' and ', ' or ' ], $expr);

		if (eval('$res = '.$expr.';') === false) {
			throw new Exception('evaluation of expression failed', "arg=[$arg] expr=[$expr]");
		}
	}
	else {
		throw new Exception('invalid expression', "arg=[$arg] expr=[$expr]");
	}

	return $res;
}


/**
 * Return result of mathematical expression evaluation.
 * Remove all characters not in ".0123456789*+-/&|)(".
 * Replace "," into ".".
 *
 * @tok {eval:math}5 * ((6 - 20) / 2 + 1){:eval} = -30 
 */
public static function tok_eval_math(string $expr) : float {

	$expr = preg_replace("/[\r\n\t ]+/", '', $expr);
	$expr = str_replace(',', '.', $expr);
	$expr_check = strtr($expr, '.0123456789*+-/)(&|', '                   ');
	$res = '';

	if (trim($expr_check) == '' && preg_match('/[0-9]+/', $expr)) {
		if (strpos($expr, '/0') !== false && preg_match('/\/([0-9\.]+)/', $expr, $match)) {
			if (floatval($match[1]) == 0) {
				throw new Exception('division by zero in [eval:math]', "arg=[$arg] expr=[$expr]");
			}
		}

		if (eval('$res = '.$expr.';') === false) {
			throw new Exception('evaluation of expression failed', "arg=[$arg] expr=[$expr]");
		}
	}
	else {
		throw new Exception('invalid expression', "arg=[$arg] expr=[$expr]");
	}

	return $res;
}


}

