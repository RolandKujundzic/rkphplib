<?php

require_once '../settings.php';

use \rkphplib\tok\TokPlugin;
use \rkphplib\tok\Tokenizer;


/**
 *
 */
class TOutput implements TokPlugin {

private $mode;

public function __construct($mode = 'A') {
	$this->mode = $mode;
}

public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];

	$plugin['output_header'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['output_loop']   = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
	$plugin['output_footer'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT;
	$plugin['output:header2'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['output'] = 0;

	return $plugin;
}

public function tok_output_header($txt) {	return 'h['.$txt.']'; }
public function tok_output_header2($txt) {	return 'h2['.$txt.']'; }
public function tok_output_loop($txt) { return 'l['.str_replace('{x:}', 'X', $txt).']'; }
public function tok_output_footer($txt) {	return 'f['.$txt.']'; }

}


/*
 * M A I N
 */

global $th;

$tx = new Tokenizer();

$th->runCompare('escape',
	[ $tx->escape('abc efg'),
		$tx->escape('a{b}c'),
		$tx->escape('{:=c} {x:} {aa:bb} {:aa}') ], 
	[ 'abc efg',
		'a{b}c',
		'&#123;&#58;=c&#125; &#123;x&#58;&#125; &#123;aa&#58;bb&#125; &#123;&#58;aa&#125;' ]);

$th->runCompare('unescape',
	[ $tx->unescape('abc efg'),
		$tx->unescape('a{b}c'), 
		$tx->unescape('&#123;&#58;=c&#125; &#123;x&#58;&#125; &#123;aa&#58;bb&#125; &#123;&#58;aa&#125;') ],
	[	'abc efg',
		'a{b}c',
		'{:=c} {x:} {aa:bb} {:aa}' ]);

$tx = new Tokenizer(Tokenizer::TOK_IGNORE);
$tx->setText('1{x:}2{y:}3{:x}4{z:}5');
$th->runCompare('TOK_IGNORE', [ $tx->toString() ], [ '145' ]); 

$tx = new Tokenizer(Tokenizer::TOK_KEEP);
$tx->setText('1{x:}2{y:}3{:x}4{z:}5');
$th->runCompare('TOK_KEEP', [ $tx->toString() ], [ '1{x:}2{y:}3{:x}4{z:}5' ]); 

$tx = new Tokenizer(Tokenizer::TOK_DEBUG);
$tx->setText('1{x:a}2{y:b}3{:x}4{z:}5');
$th->runCompare('TOK_DEBUG', [ $tx->toString() ], [ '1{debug:x:a}2{y:b}3{:debug}4{debug:z:}5' ]); 

$th->run(1, 5);

$to = new TOutput();
$th->useTokPlugin([ $to ]);
$th->run(1, 3);

