<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'TokPlugin.iface.php');
require_once($parent_dir.'Exception.class.php');
require_once($parent_dir.'lib/kv2conf.php');

use \rkphplib\Exception;


/**
 * Access http environment.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class THttp implements TokPlugin {


/**
 *
 */
public function getPlugins($tok) {
  $plugin = [];
  $plugin['http:get'] = TokPlugin::ONE_PARAM;
  $plugin['http'] = 0;
  return $plugin;
}


/**
 * Return value of _SERVER[$name]. If name=* return string-map.
 *
 * @tok <pre>{http:get:*}</pre>
 * @tok {http:get}{:get}
 * 
 * @throws if _SERVER[$name] is not set
 * @param string $name
 * @return string
 */
public function tok_http_get($name) {
  $res = '';

	if ($name == '*') {
		$res = \rkphplib\lib\kv2conf($_SERVER);
	}
	else if (!isset($_SERVER[$name])) {
		throw new Exception("no such key: _SERVER[$name]");
  }
	else {
		$res = $_SERVER[$name];
	}

	return $res;
}


}

