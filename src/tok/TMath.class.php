<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');

use \rkphplib\Exception;


/**
 * Math plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TMath implements TokPlugin {

/**
 *
 */
public function getPlugins($tok) {
	$plugin = [];
	$plugin['nf'] = 0;
	$plugin['number_format'] = 0;
	$plugin['intval'] = TokPlugin::NO_PARAM;
	$plugin['floatval'] = 0;
	$plugin['rand'] = TokPlugin::LIST_BODY;
	$plugin['math'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	return $plugin;
}


/**
 * Evaluate math expression.
 *
 * @throws
 * @param string $arg
 * @return float
 */
public function tok_math($arg) {
	$expr = preg_replace("/[\r\n\t ]+/", '', $arg);
	$expr = str_replace(',', '.', $expr);
	$expr_check = strtr($expr, '.0123456789+-*/()&', '                  ');
	$res = '';

	if (trim($expr_check) == '' && preg_match('/[0-9]+/', $expr)) {
		if (strpos($expr, '/0') !== false && preg_match('/\/([0-9\.]+)/', $expr, $match)) {
			if (floatval($match[1]) == 0) {
				throw new Exception('division by zero in [math:]', "arg=[$arg] expr=[$expr]");
			}
		}

		if (eval('$res = '.$expr.';') === false) {
			throw new Exception("evaluation of [$expr]; failed");
		}
	}
	else {
		throw new Exception("invalid expression [$expr]");
	}

	return $res;
}


/**
 * Replace ',' with '.'. Remove '.' from 1.000,83.
 *
 * @param string $arg
 * @return string 
 */
private function removeNumberFormat($arg) {

	if (strpos($arg, '.') === false) {
		// 1382,00 = 1382.00
		$arg = str_replace(',', '.', $arg);
	}
	else if (strpos($arg, ',') !== false) {
		// 1.003,95 = 1003.95
		$arg = str_replace('.', '', $arg);
		$arg = str_replace(',', '.', $arg);
	}
	else if (($tmp = explode('.', $arg)) && count($tmp) > 1) {
		// 2.658.388 = 2658388
		$arg = join('', $tmp);
	}

	return $arg;
}


/**
 * Return inval($arg).
 *
 * @tok {intval:}37.32{:intval} = 37
 * @tok {intval:}abc{:intval) = 0
 *
 * @param string $arg
 * @return int
 */
public function tok_intval($arg) {
	return intval($this->removeNumberFormat($arg));
}


/**
 * Return floatval($arg).
 *
 * @tok {floatval:}37.32{:floatval} = 37.32
 * @tok {floatval:2}37.32783{:floatval} = 37.33
 * @tok {floatval:}abc{:floatval) = 0
 *
 * @param string $arg
 * @param int $round
 * @return int
 */
public function tok_floatval($round, $arg) {
	$value = intval($this->removeNumberFormat($arg));

	if (strlen($round) > 0 && intval($round).'' == $round) {
		$value = round($value, $round);
	}

	return $value;
}


/**
 * Return random number or alphanumeric string.
 *
 * @tok {rand:} - md5(microtime() . rand())
 * @tok {rand:8} = aUlPmvei
 * @tok {rand:password} = str_replace(['0', 'O', 'i', 'l', 'o'], ['3', '7', '5', '2', 'e'], {rand:8})
 * @tok {rand:}5|#|10{:rand} = 10
 *
 * @param int $param
 * @param vector<int> $p  [min, max ]
 */
public function tok_rand($param, $p) {
	$res = '';

	if (empty($arg)) {
		if (empty($param)) {
			$res = md5(microtime() . rand());
		}
		else if ($param == 'password') {
			$res = str_replace([ '0', 'O', 'i', 'l', 'o' ], [ '3', '7', '5', '2', 'e' ], self::randomString(intval($param)));
		}
		else {
			$res = self::randomString(intval($param));
		}
	}
	else if (!empty($p['min']) && !empty($p['max'])) {
		$res = mt_rand($p['min'], $p['max']);
	}

	return $res;
}


/**
 * Return random alphanumeric string with $len length. 
 *
 * @param int $len (default=8)
 * @return string
 */
public static function randomString($len = 8) {

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
public function tok_nf($param, $arg) {
	return $this->tok_number_format($param, $arg);
}


/**
 * Return formatted number. Paramter is number of decimal points. If
 * param ist empty use intval if no decimal points 1/2 points. Use [.] as 
 * thousands and [,] as decimal separator. Example:
 * 
 * {number_format:2}2832.8134{:number_format} = 2.832,81
 * {number_format:2}17,329g{:number_format} = 17,33 g
 *
 * @param int $param
 * @param float $arg
 * @return string
 */
public function tok_number_format($param, $arg) {
	$suffix = '';

	if (preg_match('/^([\-\+0-9,\.]+)(.*)$/', trim($arg), $match) && strlen($match[2]) > 0) {
		$fval = floatval($match[1]);
		$suffix = ' '.trim($match[2]);
	}
	else {
		$fval = floatval($arg);	
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
