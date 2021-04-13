<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Exception.class.php';
require_once __DIR__.'/../JSON.class.php';
require_once __DIR__.'/../lib/split_str.php';

use rkphplib\Exception;
use rkphplib\JSON;

use function rkphplib\lib\split_str;



/**
 * Format loop data.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TLoop implements TokPlugin {

// @var array $loop
protected $loop = array();

// @var Tokenizer $tok
protected $tok = null;



/**
 * Return {loop:var|list|hash|show|join|count}
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

  $plugin = [];
  $plugin['loop:var'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
  $plugin['loop:list'] = 0;
  $plugin['loop:json'] = TokPlugin::NO_PARAM;
  $plugin['loop:hash'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['loop:show'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
  $plugin['loop:join'] = TokPlugin::NO_PARAM | TokPlugin::LIST_BODY | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
  $plugin['loop:count'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
  $plugin['loop'] = 0; // no callback for base plugin

  return $plugin;
}


/**
 * Set loop to Tokenizer.vmap[$name]. Use suffix semicolon to abort
 * if variable was not found.
 *
 * @tok {loop:var}test!{:loop}
 * @tok {loop:var}a.b.c{:loop}
 */
public function tok_loop_var(string $name) : void {
	$res = $this->tok->getVar($name);

	if (!is_array($res)) {
		$res = [];
	}

	$this->loop = $res;
}


/**
 * Loop data is list. If delimiter is empty use comma. Ignore empty entries.
 *
 * @tok {loop:list}a,b,c{:loop}
 * @tok {loop:list:;}a;b;c{:loop}
 * @tok {loop:list:|#|}a|#|b|#|c{:loop}
 * @tok …
 * {loop:list:\n}a
 * b
 * ...
 * {:loop}
 * @EOL
 */
public function tok_loop_list(string $delimiter, string $txt) : void {
	if (empty($delimiter)) {
		$delimiter = ',';
	}

	$delimiter = str_replace([ '\n', '\t' ], [ "\n", "\t" ], $delimiter);

	$this->loop = split_str($delimiter, $txt, true);
}


/**
 * Loop data is hash.
 * 
 * @tok {loop:hash}a=x|#|b=" y "|#|c=z{:loop} = { a: "x", b: " y ", c: "z" }
 */
public function tok_loop_hash(array $kv) : void {
	$this->loop = $kv;
}


/**
 * Loop data is json.
 * 
 * @tok {loop:json}{ "a": "x", "b": "y", "c": "z" }{:loop}
 */
public function tok_loop_json(string $json_str) {
	$this->loop = JSON::decode($json_str);
}


/**
 * Show joined loop data. Use $p[0] as delimiter. Default delimiter is comma. 
 * Replace {:=loop} in $p[1]. Default $p[1] is "{:=loop}". Example:
 *
 * @tok {loop:list}a.jpg,b.jpg{:loop}
 */
public function tok_loop_join(array $p) : string {
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
 * Show loop data. Template $tpl may contain loop|loop_pos|loop_value|loop_key tags.
 *
 * @tok …
 * {loop:list}a,b,c{:loop}
 * {loop:show}{:=loop}<br>{:loop} -> a<br>b<br>c<br>
 * {loop:show}{:=loop_pos}){:=loop} {:=loop} -> 1)a 2)b 3)c
 *
 * {loop:hash}a=x|#|b=y{:loop}
 * { {loop:show}"{:=loop_key}": "{:=loop_value}"{:loop}, } -> { "a": "x", "b": "y", }
 * @EOF
 */
public function tok_loop_show(string $tpl) : string {
	$tag_loop = $this->tok->getTag('loop');
	$tag_loop_n = $this->tok->getTag('loop_n');
	$tag_loop_pos = $this->tok->getTag('loop_pos');
	$tag_loop_key = $this->tok->getTag('loop_key');
	$tag_loop_value = $this->tok->getTag('loop_value');
	$out = [];
	$n = 0;

	foreach ($this->loop as $key => $value) {
		$entry = trim($tpl);

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

					if (mb_strpos($entry, $tag) !== false) {
						if (is_array($subvalue)) {
						}
						else {
							$entry = str_replace($tag, $subvalue, $entry);
						}
					}

					$k++;
				}
			}
			else {
				$entry = str_replace($tag_loop, $value, $entry);
			}
		}
		else {
			$entry = str_replace([ $tag_loop_key, $tag_loop_value ], [ $key, $value ], $entry);
		}

		array_push($out, str_replace([ $tag_loop_n, $tag_loop_pos ], [ $n, $n + 1 ], $entry));
		$n++;
	}

	return join("\n", $out);
}


/**
 * Return loop length.
 */
public function tok_loop_count() : string {
	return count($this->loop);
}


}

