<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once __DIR__.'/TokPlugin.iface.php';
require_once $parent_dir.'/Exception.class.php';

use rkphplib\Exception;


/**
 * Various rarely used plugins.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TMisc implements TokPlugin {

/**
 * Return {nf:}, {number_format:}, {intval:}, {floatval:}, {rand:}, {math:} and {md5:}
 */
public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];
	$plugin['sleep'] = 0;
	return $plugin;
}


/**
 * Sleep for $wait seconds.
 *
 * @tok {sleep:2}
 * @tok {sleep:0.5}
 * @tok {sleep:}{get:ms}{:sleep}
 */
public function tok_sleep(string $pwait = '', string $await = '') : void {
	$wait = empty($pwait) ? (float) $await : (float) $pwait;
	$start = microtime(true);

	// \rkphplib\lib\log_debug([ "TMisc.tok_sleep:40> wait $wait s\nGET: <1>\nPOST: <2>", $_GET, $_POST ]);
	if ($wait > 600 || $wait < 0.001) {
		throw new Exception('invalid sleep time use [0.001, 600]', "pwait=[$pwait] await=[$wait] wait=[$wait]");
	}
	else if (is_int($wait)) {
		sleep($wait);
	}
	else {
		usleep(1000000 * $wait);
	}

	// \rkphplib\lib\log_debug("TMisc.tok_sleep:51> return after ".(microtime(true) - $start).' sec');
}

}
