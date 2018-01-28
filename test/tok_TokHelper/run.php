<?php

require_once('src/tok/TokHelper.trait.php');


class X {
	use \rkphplib\tok\TokHelper;

	public function get($key, $map) {
		return $this->getMapKeysLike($key, $map);
	}
}


$a = [
	'm' => 7,
	'v' => 8,
	'A' => [
		'X' => [
			'm' => 17,
			],
		'Y' => 9,
		'Z' => [
			'm' => 27
			]
		]
	];

$x = new X();

print 'a[v]='.print_r($x->get('v', $a), true)."\n";
print 'a[A.Y]='.print_r($x->get('A.Y', $a), true)."\n";
exit(0);

print 'a[A.X.m]='.print_r($x->get('A.X.m', $a), true)."\n";
print 'a[A.Z.m]='.print_r($x->get('A.Z.m', $a), true)."\n";
print 'a[A]='.print_r($x->get('A', $a), true)."\n";
print 'a[A.Y]='.print_r($x->get('A.Y', $a), true)."\n";
print 'a[A.Z]='.print_r($x->get('A.Z', $a), true)."\n";
print 'a[A.X.*]='.print_r($x->get('A.X.*', $a), true)."\n";

