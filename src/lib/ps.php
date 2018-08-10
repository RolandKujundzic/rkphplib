<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/Exception.class.php');

use rkphplib\Exception;



/**
 * Retrieve "ps aux -q PID" information as hash.
 * 
 * @trigger_error E_USER_WARNING - PID $pid doesn't exists
 * @param int $pid
 * @return false|hash
 */
function ps($pid) {
	$ps = shell_exec("ps aux -q '".intval($pid)."'");
	$ps = explode("\n", $ps);
 
	if (count($ps) < 2) {
		trigger_error("PID ".$pid." doesn't exists", E_USER_WARNING);
		return false;
	}

	foreach ($ps as $key => $val) {
		$ps[$key] = explode(" ", preg_replace("/ +/", " ", trim($ps[$key])));
	}

	foreach ($ps[0] as $key => $val) {
		$pidinfo[$val] = $ps[1][$key];
		unset($ps[1][$key]);
	}
 
	if (is_array($ps[1])) {
		$pidinfo[$val] .= " ".implode(" ", $ps[1]);
	}

	return $pidinfo;
}

