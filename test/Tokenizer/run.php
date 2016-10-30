<?php

global $th;

if (!isset($th)) {
	require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
	$th = new rkphplib\TestHelper();
}

require_once(dirname(dirname(__DIR__)).'/src/Tokenizer.class.php');

$tx = new rkphplib\Tokenizer();


$th->compare('escape', [ $tx->escape('abc efg'), $tx->escape('a{b}c'), $tx->escape('{:=c} {x:} {aa:bb} {:aa}') ], 
	[ 'abc efg', 'a{b}c', '&#123;&#58;=c&#125; &#123;x&#58;&#125; &#123;aa&#58;bb&#125; &#123;&#58;aa&#125;' ]);

$th->compare('unescape', [ $tx->unescape('abc efg'), $tx->unescape('a{b}c'), 
	$tx->unescape('&#123;&#58;=c&#125; &#123;x&#58;&#125; &#123;aa&#58;bb&#125; &#123;&#58;aa&#125;') ],
	[ 'abc efg', 'a{b}c', '{:=c} {x:} {aa:bb} {:aa}' ]);

$th->runTokenizer(1, array());
