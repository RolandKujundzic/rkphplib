<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';

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
 */
public static function tok_eval_call($p) : string {
	if (!empty($p['function'])) {
		if (!isset($p['arg1'])) {
			return $p['function']();
		}
		else if (!isset($p['arg2'])) {
			return $p['function']($p['arg1']);
		}
		else if (!isset($p['arg3'])) {
			return $p['function']($p['arg1'], $p['arg2']);
		}
		else if (!isset($p['arg4'])) {
			return $p['function']($p['arg1'], $p['arg2'], $p['arg3']);
		}
		else if (!isset($p['arg5'])) {
			return $p['function']($p['arg1'], $p['arg2'], $p['arg3'], $p['arg4']);
		}
	}
	else if (!empty($p['class']) && !empty($p['method'])) {
		$obj = new $p['class']();
		$func = $p['method'];

		if (!isset($p['arg1'])) {
			return $obj->$func();
		}
		else if (!isset($p['arg2'])) {
			return $obj->$func($p['arg1']);
		}
		else if (!isset($p['arg3'])) {
			return $obj->$func($p['arg1'], $p['arg2']);
		}
		else if (!isset($p['arg4'])) {
			return $obj->$func($p['arg1'], $p['arg2'], $p['arg3']);
		}
		else if (!isset($p['arg5'])) {
			return $obj->$func($p['arg1'], $p['arg2'], $p['arg3'], $p['arg4']);
		}
	}
	else if (!empty($p['class']) && !empty($p['static_method'])) {
		$func = $p['static_method'];

		if (!isset($p['arg1'])) {
			return $p['class']::$func->$func();
		}
		else if (!isset($p['arg2'])) {
			return $p['class']::$func($p['arg1']);
		}
		else if (!isset($p['arg3'])) {
			return $p['class']::$func($p['arg1'], $p['arg2']);
		}
		else if (!isset($p['arg4'])) {
			return $p['class']::$func($p['arg1'], $p['arg2'], $p['arg3']);
		}
		else if (!isset($p['arg5'])) {
			return $p['class']::$func($p['arg1'], $p['arg2'], $p['arg3'], $p['arg4']);
		}
	}
	else {
		throw new Exception('missing function or class and ([static_]method) parameter', print_r($p, true));
	}
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

