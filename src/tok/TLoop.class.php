<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Exception.class.php');
require_once(__DIR__.'/../lib/split_str.php');

use \rkphplib\Exception;


/**
 * Format loop data.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TLoop implements TokPlugin {

/** @var array $loop */
protected $loop = array();

/** @var Tokenizer $tok */
protected $tok = null;



/**
 * Register output plugin {output:conf|init|loop}.
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$this->tok = $tok;

  $plugin = [];
  $plugin['loop:list'] = 0;
  $plugin['loop:show'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
  $plugin['loop:count'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
  $plugin['loop'] = 0; // no callback for base plugin

  return $plugin;
}


/**
 * Loop data is list. If delimiter is empty use comma. Example:
 * 
 *  {loop:list}a,b,c{:loop}
 *  {loop:list:;}a;b;c{:loop}
 *  {loop:list:|#|}a|#|b|#|c{:loop}
 *
 * @param string $delimiter
 * @param string $txt
 * @return ''
 */
public function tok_loop_list($delimiter, $txt) {
	if (strlen($delimiter) == 0) {
		$delimiter = ',';
	}

	$this->loop = \rkphplib\lib\split_str($delimiter, $txt);
}


/**
 * Show loop data. Example:
 *
 *  {loop:list}a,b,c{:loop}
 *  {loop:show}{:=key}={:=value}<br>{:loop}
 *
 */
public function tok_loop_show($txt) {
	$tag_loop = $this->tok->getTag('loop');
	$tag_loop_key = $this->tok->getTag('loop_key');
	$tag_loop_value = $this->tok->getTag('loop_value');
	$n = 0;

	foreach ($this->loop as $key => $value) {
		if ($key === $n) {
			$txt = str_replace($tag_loop, $value, $txt);
		}
		else {
			$txt = str_replace([ $tag_loop_key, $tag_loop_value ], [ $key, $value ], $txt); 
		}

		$n++;
	}

	return $txt;
}


/**
 * Return loop length.
 * 
 * @return int
 */
public function tok_loop_count() {
	return count($this->loop);
}


}

