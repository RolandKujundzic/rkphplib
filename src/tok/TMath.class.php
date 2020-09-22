<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';

use rkphplib\Exception;


/**
 * Math plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TMath implements TokPlugin {

/**
 * Return {nf:}, {number_format:}, {intval:}, {floatval:}, {rand:}, {math:} and {md5:}
 */
public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];
	$plugin['nf'] = 0;
	$plugin['number_format'] = 0;
	$plugin['intval'] = TokPlugin::NO_PARAM;
	$plugin['floatval'] = 0;
	$plugin['rand'] = TokPlugin::LIST_BODY;
	$plugin['math'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['md5'] = TokPlugin::NO_PARAM;
	return $plugin;
}


/**
 * Return md5(trim($arg)). Empty arg has non-empty result.
 */
public function tok_md5(string $arg) : string {
	return md5(trim($arg));
}


/**
 * Evaluate math expression.
 */
public function tok_math(string $arg) : string {
	$expr = preg_replace("/[\r\n\t ]+/", '', $arg);
	$expr = str_replace(',', '.', $expr);
	$expr_check = trim(strtr($expr, '.0123456789+-*/()&', '                  '));
	$res = '';

	$last_char_invalid = in_array(substr($expr, -1), [ '*', '/', '-', '+', '&' ]);
	$first_char_invalid = in_array(substr($expr, 0, 1), [ '*', '/', '&' ]);
	if ($expr_check == '' && preg_match('/[0-9]+/', $expr) && !$last_char_invalid && !$first_char_invalid) {
		if (strpos($expr, '/0') !== false && preg_match('/\/([0-9\.]+)/', $expr, $match)) {
			if (0 == (float) $match[1]) {
				throw new Exception('division by zero in [math:]', "arg=[$arg] expr=[$expr]");
			}
		}

		// \rkphplib\lib\log_debug('TMath.tok_math:67> eval($res = "'.$expr.'";)');
		eval('$res = '.$expr.';');
	}
	else {
		throw new Exception('invalid methematical expression', "[$expr]");
	}

	return $res;
}


/**
 * Replace ',' with '.'. Remove '.' from 1.000,83.
 */
private function removeNumberFormat(string $arg) : string {
	if (($tmp = explode('.', $arg)) && count($tmp) > 2) {
		// 2.658.388[,NN] = 2658388[,NN]
		$arg = join('', $tmp);
	}

	if (strpos($arg, '.') === false) {
		// 1382,00 = 1382.00
		$arg = str_replace(',', '.', $arg);
	}
	else if (strpos($arg, ',') !== false) {
		// 1.003,95 = 1003.95
		$arg = str_replace('.', '', $arg);
		$arg = str_replace(',', '.', $arg);
	}

	return $arg;
}


/**
 * Return inval($arg).
 *
 * @tok {intval:}37.32{:intval} = 37
 * @tok {intval:}7,8{:intval} = 7
 * @tok {intval:}abc{:intval) = 0
 */
public function tok_intval(string $arg) : int {
	return (int) $this->removeNumberFormat($arg);
}


/**
 * Return (float) $arg (mathematical number = NN.NNN).
 *
 * @tok {floatval:}37.000{:floatval} = 37000
 * @tok {floatval:}9,99{:floatval} = 9.99
 * @tok {floatval:2}37.32783{:floatval} = 37.33
 * @tok {floatval:}abc{:floatval} = 0
 */
public function tok_floatval(string $round, string $arg) : string {
	$value = (float) $this->removeNumberFormat($arg);

	if (strlen($round) > 0 && intval($round).'' == $round) {
		$value = round($value, $round);
	}

	$res = str_replace(',', '.', $value);
	return $res;
}


/**
 * Return random number or alphanumeric string.
 *
 * @tok {rand:} - md5(microtime() . rand())
 * @tok {rand:8} = aUlPmvei
 * @tok {rand:password} = str_replace(['0', 'O', 'i', 'l', 'o'], ['3', '7', '5', '2', 'e'], {rand:8})
 * @tok {rand:}5|#|10{:rand} = 10
 * @tok {rand:txt}a|#|b|#|c{:rand} = b (select random from [a,b,c])
 */
public function tok_rand(string $param, array $p) : string {
	$res = '';

	if (count($p) == 0 || (count($p) == 1 && strlen($p[0]) == 0)) {
		if (empty($param)) {
			$res = md5(microtime() . rand());
		}
		else if ($param == 'password') {
			$res = str_replace([ '0', 'O', 'i', 'l', 'o' ], [ '3', '7', '5', '2', 'e' ], self::randomString(intval($param)));
		}
		else if (($len = intval($param)) > 0 && $len <= 16384) {
			$res = self::randomString($len);
		}
	}
	else {
		if ($param == 'txt') {
			$n = mt_rand(0, count($p) - 1);
			$res = $p[$n];
		}
		else if (count($p) == 2) {
			$res = mt_rand($p[0], $p[1]);
		}
	}

	return $res;
}


/**
 * Return random alphanumeric string with $len length. 
 */
public static function randomString(int $len = 8) : string {

	if ($len == 0) {
		$len = 8;
	}

	srand((double)microtime()*1000000);
	$rand_str = '';

	$lchar = 0;
	$char = 0;

	for($i = 0; $i < $len; $i++) {
		while($char == $lchar) {
			$char = rand(48, 109);

			if ($char > 57) {
				$char += 7;
			}

			if($char > 90) {
				$char += 6;
			}
		}

		$rand_str .= chr($char);
		$lchar = $char;
	}

	return $rand_str;
}


/**
 * Alias for tok_number_format. Example:
 *
 * {nf:2}38.2883{:nf} = {number_format:2}38.2883{:number_format} = 38,28 
 *
 * @see tok_number_format
 */
public function tok_nf(string $param, string $arg) : string {
	return $this->tok_number_format($param, $arg);
}


/**
 * Return formatted number. Paramter is number of decimal points. If
 * param ist empty use intval if no decimal points 1/2 points. Use [.] as 
 * thousands and [,] as decimal separator. Example:
 * 
 * {number_format:2}2832.8134{:number_format} = 2.832,81
 * {number_format:2}17,329g{:number_format} = 17,33 g
 */
public function tok_number_format(string $param, string $arg) : string {
	$suffix = '';

	if (preg_match('/^([\-\+0-9,\.]+)(.*)$/', trim($arg), $match) && strlen($match[2]) > 0) {
		$fval = $this->tok_floatval('', $match[1]);
		$suffix = ' '.trim($match[2]);
	}
	else {
		$fval = $this->tok_floatval('', $arg);
	}

	$res = '';

	if (strlen($param) == 0) {
		$d0 = number_format($fval, 0, ',', '.');
		$d1 = number_format($fval, 1, ',', '.');
		$d2 = number_format($fval, 2, ',', '.');
  	
		if ($d0.',00' == $d2) {
			$res = $d0;
		}
		else if ($d1.'0' == $d2) {
			$res = $d1;
		}
		else {
			$res = $d2;
		}
	}
	else {
		$res = number_format($fval, intval($param), ',', '.');
	}

	return $res.$suffix;
}


}

