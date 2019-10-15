<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once dirname(__DIR__).'/StringHelper.class.php';

use rkphplib\Exception;
use rkphplib\StringHelper;



/**
 * String plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TString implements TokPlugin {

/**
 *
 */
public function getPlugins($tok) {
	$plugin = [];
	$plugin['string2url'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	return $plugin;
}


/**
 * Return StringHelper::url($arg).
 *
 * @example {string2url:}Haus und Gartenmöbel{:string2url}.html = haus-und-gartenmoebel.html
 */
public function tok_string2url($arg) {
	return StringHelper::url($arg);
}


}
