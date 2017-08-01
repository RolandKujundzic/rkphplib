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
  $plugin['loop:list'] = TokPlugin::PARAM_LIST;
  $plugin['loop:hash'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['loop:show'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
  $plugin['loop:join'] = TokPlugin::NO_PARAM | TokPlugin::LIST_BODY | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
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
 * @param array $p
 * @param string $txt
 * @return ''
 */
public function tok_loop_list($p, $txt) {
	$delimiter = ',';

	if (count($p) > 0 && strlen($p[0]) > 0) {
		$delimiter = $p[0];
	}

	$this->loop = \rkphplib\lib\split_str($delimiter, $txt);
}


/**
 * Loop data is hash.
 * 
 *  {loop:hash}a=x|#|b=" y "|#|c=z{:loop} -> { a: "x", b: " y ", c: "z" }
 *
 * @param array $kv
 * @return ''
 */
public function tok_loop_hash($kv) {
	$this->loop = $kv;
}


/**
 * Show joined loop data. Use $p[0] as delimiter. Default delimiter is comma. 
 * Replace {:=loop} in $p[1]. Default $p[1] is "{:=loop}". Example:
 *
 * {loop:list}a.jpg,b.jpg{:loop}
 * var x = [ '{loop:join}', '|#|<img src="{:=loop}">{:loop} ] -> x = [ '<img src="a.jpg">', '<img src="b.jpg">' ]
 * 
 * @param array $p
 * @return string
 */
public function tok_loop_join($p) {
	$delimiter = array_shift($p);
	$tpl = array_shift($p);
	
	if (empty($delimiter)) {
		$delimiter = ',';
	}

	if (empty($tpl)) {
		$tpl = '{:=loop}';
	}

	$tag_loop = $this->tok->getTag('loop');
	$tag_loop_key = $this->tok->getTag('loop_key');
	$tag_loop_value = $this->tok->getTag('loop_value');
	$arr = [];
	$n = 0;

	foreach ($this->loop as $key => $value) {
		if ($key === $n) {
			array_push($arr, str_replace($tag_loop, $value, $tpl));
		}
		else {
			array_push($arr, str_replace([ $tag_loop_key, $tag_loop_value ], [ $key, $value ], $tpl)); 
		}

		$n++;
	}

	return join($delimiter, $arr);
}


/**
 * Show loop data. Example:
 *
 *  {loop:list}a,b,c{:loop}
 *  {loop:show}{:=loop}<br>{:loop} -> a<br>b<br>c<br>
 *  {loop:show}{:=loop_pos}){:=loop} {:=loop} -> 1)a 2)b 3)c
 *
 *  {loop:hash}a=x|#|b=y{:loop}
 *  { {loop:show}"{:=loop_key}": "{:=loop_value}"{:loop}, } -> { "a": "x", "b": "y", }
 *
 * @param string $txt
 * @return string
 */
public function tok_loop_show($txt) {
	$tag_loop = $this->tok->getTag('loop');
	$tag_loop_pos = $this->tok->getTag('loop_pos');
	$tag_loop_key = $this->tok->getTag('loop_key');
	$tag_loop_value = $this->tok->getTag('loop_value');
	$res = '';
	$n = 0;

	foreach ($this->loop as $key => $value) {
		if ($key === $n) {
			$res .= str_replace($tag_loop, $value, $txt);
		}
		else {
			$res .= str_replace([ $tag_loop_key, $tag_loop_value ], [ $key, $value ], $txt); 
		}

		$res = str_replace($tag_loop_pos, $n + 1, $res);
		$n++;
	}

	return $res;
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

