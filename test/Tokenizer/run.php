<?php

global $th;

if (!isset($th)) {
	require_once(dirname(dirname(__DIR__)).'/src/lib/log_debug.php');
	require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
	$th = new rkphplib\TestHelper();
}

require_once(dirname(dirname(__DIR__)).'/src/Tokenizer.class.php');

use \rkphplib\Tokenizer;


$tx = new Tokenizer();

$th->compare('escape', [ $tx->escape('abc efg'), $tx->escape('a{b}c'), $tx->escape('{:=c} {x:} {aa:bb} {:aa}') ], 
	[ 'abc efg', 'a{b}c', '&#123;&#58;=c&#125; &#123;x&#58;&#125; &#123;aa&#58;bb&#125; &#123;&#58;aa&#125;' ]);

$th->compare('unescape', [ $tx->unescape('abc efg'), $tx->unescape('a{b}c'), 
	$tx->unescape('&#123;&#58;=c&#125; &#123;x&#58;&#125; &#123;aa&#58;bb&#125; &#123;&#58;aa&#125;') ],
	[ 'abc efg', 'a{b}c', '{:=c} {x:} {aa:bb} {:aa}' ]);

$tx = new Tokenizer(Tokenizer::TOK_IGNORE);
$tx->setText('1{x:}2{y:}3{:x}4{z:}5');
$th->compare('TOK_IGNORE', [ $tx->toString() ], [ '145' ]); 

$tx = new Tokenizer(Tokenizer::TOK_KEEP);
$tx->setText('1{x:}2{y:}3{:x}4{z:}5');
$th->compare('TOK_KEEP', [ $tx->toString() ], [ '1{x:}2{y:}3{:x}4{z:}5' ]); 

$tx = new Tokenizer(Tokenizer::TOK_DEBUG);
$tx->setText('1{x:a}2{y:b}3{:x}4{z:}5');
$th->compare('TOK_DEBUG', [ $tx->toString() ], [ '1{debug:x:a}2{y:b}3{:debug}4{debug:z:}5' ]); 

$th->runTokenizer(1, array());
