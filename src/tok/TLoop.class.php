<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Exception.class.php';
require_once __DIR__.'/../lib/split_str.php';

use rkphplib\Exception;




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
 * Return {loop:var|list|hash|show|join|count}
 */
public function getPlugins($tok) {
	$this->tok = $tok;

  $plugin = [];
  $plugin['loop:var'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
  $plugin['loop:list'] = TokPlugin::PARAM_LIST;
  $plugin['loop:hash'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['loop:show'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
  $plugin['loop:join'] = TokPlugin::NO_PARAM | TokPlugin::LIST_BODY | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
  $plugin['loop:count'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
  $plugin['loop'] = 0; // no callback for base plugin

  return $plugin;
}


/**
 * Set loop to Tokenizer.vmap[$name]. Example:
 *
 * PHP: tok->vmap[test] = [ ... ]; tok->vmap['a']['b']['c'] = $x;
 * TEMPLATE: {loop:var}test!{:loop} {loop:var}a.b.c{:loop}
 *
 * Use suffix semicolon to abort if variable was not found.
 *
 * @param string $name
 * @return ''
 */
public function tok_loop_var($name) {
	$res = $this->tok->getVar($name);

	if (!is_array($res)) {
		$res = [];
	}

	$this->loop = $res;
}


/**
 * Loop data is list. If delimiter is empty use comma. Ignore empty entries. Example:
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

	$this->loop = \rkphplib\lib\split_str($delimiter, $txt, true);
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
		$tpl = $this->tok->getTag('loop');
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
	$tag_loop_n = $this->tok->getTag('loop_n');
	$tag_loop_pos = $this->tok->getTag('loop_pos');
	$tag_loop_key = $this->tok->getTag('loop_key');
	$tag_loop_value = $this->tok->getTag('loop_value');
	$out = [];
	$n = 0;

	foreach ($this->loop as $key => $value) {
		$tpl = trim($txt);

		if ($key === $n) {
			if (is_array($value)) {
				$k = 0;

				foreach ($value as $subkey => $subvalue) {
					if ($subkey === $k) {
						$tag = $this->tok->getTag('c'.($k+1));
					}
					else {
						$tag = $this->tok->getTag($subkey);
					}

					if (mb_strpos($tpl, $tag) !== false) {
						if (is_array($subvalue)) {
						}
						else {
							$tpl = str_replace($tag, $subvalue, $tpl);
						}
					}

					$k++;
				}
			}
			else {
				$tpl = str_replace($tag_loop, $value, $tpl);
			}
		}
		else {
			$tpl = str_replace([ $tag_loop_key, $tag_loop_value ], [ $key, $value ], $tpl);
		}

		array_push($out, str_replace([ $tag_loop_n, $tag_loop_pos ], [ $n, $n + 1 ], $tpl));
		$n++;
	}

	return join("\n", $out);
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

