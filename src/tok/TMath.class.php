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
  return $plugin;
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
