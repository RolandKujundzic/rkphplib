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
	$plugin['sleep'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	return $plugin;
}


/**
 * Sleep for $wait seconds.
 */
public function tok_sleep(float $wait) : void {
	// \rkphplib\lib\log_debug("TMisc.tok_sleep:33> wait $wait s");
	if ($wait >= 1) {
		sleep((int)$wait);
	}
	else {
		usleep((int)(1000000 * $wait));
	}
	// \rkphplib\lib\log_debug("TMisc.tok_sleep:40> return");
}

}
