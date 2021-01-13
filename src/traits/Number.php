<?php

namespace rkphplib\traits;


/**
 * Number handling
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2021 Roland Kujundzic
 */
trait Number {


/**
 * Return (float) $number (mathematical number = NN.NNN).
 *
 * @code parseFloat(37.000) = 37000
 * @code parseFloat(9,99) = 9.99
 * @code parseFloat(37.32783, 2) = 37.33
 * @code parseFloat('abc') = 0
 */
private function parseFloat(string $number, int $dp = 0) : float {
	$value = $this->fixNumber($number);

	if ($dp > 0) {
		$value = round($value, $dp);
	}

	return $value;
}


/**
 * Return formatted number. Parameter is number of decimal points. If
 * param ist empty use intval if no decimal points 1/2 points. Use [.] as 
 * thousands and [,] as decimal separator. Example:
 * 
 * @code round(2832.8134, 2) = 2.832,81
 * @code round('17,329g', 2) = 17,33 g
 */
private function round(string $param, int $decimal_places = 0) : string {
	$suffix = '';

	if (preg_match('/^([\-\+0-9,\.]+)(.*)$/', trim($param), $match) && strlen($match[2]) > 0) {
		$fval = $this->parseFloat($match[1]);
		$suffix = ' '.trim($match[2]);
	}
	else {
		$fval = $this->parseFloat($param);
	}

	$res = '';

	if ($decimal_places == 0) {
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
		$res = number_format($fval, $decimal_places, ',', '.');
	}

	return $res.$suffix;
}


/**
 * Replace ',' with '.'. Remove '.' from 1.000,83.
 */
private function fixNumber(string $number) : float {
	if (($tmp = explode('.', $number)) && count($tmp) > 2) {
		// 2.658.388[,NN] = 2658388[,NN]
		$number = join('', $tmp);
	}

	if (strpos($number, '.') === false) {
		// 1382,00 = 1382.00
		$number = str_replace(',', '.', $number);
	}
	else if (strpos($number, ',') !== false) {
		// 1.003,95 = 1003.95
		$number = str_replace('.', '', $number);
		$number = str_replace(',', '.', $number);
	}

	return (float) $number;
}

}

