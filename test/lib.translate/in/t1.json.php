<?php

$test = [
	'rkphplib\\lib\\translate',
	[ 'Ihr Name?', 'EXCEPTION' ],
	[ '@DE', 'EXCEPTION' ],
	[ '@to:en', '' ],
	[ '@php:translation', '' ],
	[ 'fehlt', 'fehlt' ],
	[ 'Name: $p1x', [ '' ], 'Name: ' ],
	[ '@p1x:1', '' ],
	[ 'Name: $p1x', [ '' ], '' ],
	[ 'Name: $p1x', [ 'John' ], 'Name: John' ],
	[ 'Telefon', 'Phone' ],
	[ '@json:translation', '' ],
	[ 'Ihr Name?', 'Your Name?',  ],
	[ 'Willkommen $p1x $p2x', [ 'Marc', 'Muster' ], 'Welcome Marc Muster' ]
];
