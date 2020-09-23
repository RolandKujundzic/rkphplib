<?php

$tx = new \rkphplib\tok\Tokenizer();

$test = [
	'compare,[un]escape',
	[ $tx->escape('abc efg'), 'abc efg' ],
	[ $tx->escape('a{b}c'), 'a{b}c' ],
	[ $tx->escape('{:=c} {x:} {aa:bb} {:aa}'),
		'&#123;&#58;=c&#125; &#123;x&#58;&#125; &#123;aa&#58;bb&#125; &#123;&#58;aa&#125;' ],
	[ $tx->unescape('abc efg'), 'abc efg' ],
	[ $tx->unescape('a{b}c'), 'a{b}c' ],
	[ $tx->unescape('&#123;&#58;=c&#125; &#123;x&#58;&#125; &#123;aa&#58;bb&#125; &#123;&#58;aa&#125;'),
		'{:=c} {x:} {aa:bb} {:aa}' ]
];
