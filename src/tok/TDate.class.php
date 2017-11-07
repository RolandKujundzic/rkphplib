<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../DateCalc.class.php');

use \rkphplib\Exception;
use \rkphplib\DateCalc;


/**
 * Date plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TDate implements TokPlugin {

/**
 *
 */
public function getPlugins($tok) {
  $plugin = [];
  $plugin['date'] = TokPlugin::PARAM_CSLIST | TokPlugin::KV_BODY;
  return $plugin;
}


/**
 * Return result of {date:time|microtime}.
 *
 * @param string $param time|microtime
 * @return string|null
 */
private function date_param($param) {
	$res = null;

	if ($param == 'time') {
		$res = time();
	}
	else if ($param == 'microtime') {
		list($usec, $sec) = explode(" ", microtime());
		// return same as javascript: new Date().valueOf()
		$res = str_replace('.', '', sprintf("%.03f", ($sec + $usec))); 
  }

	return $res;
}


/**
 * Return value of plugin {date:p1[,p2,...]}[arg]{:date}. Examples:
 *
 * {date:[format_in,] format_out}now(+/-NNN){:} or {date:[format_in]}XXX|#|format_out{:}
 * {date:}XXX{:} = {date:}XXX|#|de{:date}
 * {date:time}, {date:microtime}
 *
 * @see DateCalc::formatDateStr()
 * @param array $p
 * @param array $arg
 * @return string
 */
public function tok_date($p, $arg) {

	if (count($p) == 1 && !empty($p[0])) {
		$res = $this->date_param($p[0]);

		if (!is_null($res)) {
			return $res;
		}
	}

  $format_in = '';
  $format_out = 'de';
  $xin = '';

	if (count($p) == 2) {
		$format_in = $p[0];
		$format_out = $p[1];
	}
	else if (count($p) == 1) {
		$format_out = $p[0];
		$xin = $p[0];
	}

	$date_str = (count($arg) > 0) ? $arg[0] : '';

	if (count($arg) == 2) {
		if ($xin) {
			$format_in = $xin;
		}

    $format_out = $arg[2];
  }

  if ($format_in == 'xde') {
		if (strlen($date_str) == 10 || strlen($date_str) == 19) {
			$format_in = 'de';
		}
		else {
			$format_in = 'now';
		}
	}

  $res = '???';

	if (!empty($date_str)) {
		if ($date_str == '0000-00-00 00:00:00' || (empty($date_str) && $format_in == 'sql')) {
			$res = '';
		}
		else {
			$res = DateCalc::formatDateStr($format_out, $date_str, $format_in);
		}
	}
	else if ($format_in == 'now') {
		$res = DateCalc::formatDateStr($format_out, $date_str, $format_in);
	}
	else if (empty($date_str)) {
		$res = '';
	}

	return $res;
}


}
