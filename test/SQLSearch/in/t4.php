<?php

$search = new \rkphplib\SQLSearch([
	'search' => 'id,price:LE,name:LIKE'
]);

$_REQUEST = [ 'scol' => 'id', 'sval' => 'ID' ];
print $search->query()."\n";

$_REQUEST = [ 'scol' => 'id', 'sval' => '' ];
print $search->query()."\n";

$_REQUEST = [ 'scol' => 'name', 'sval' => 'NAME' ];
print $search->query()."\n";

$_REQUEST = [ 'scol' => 'name', 'sval' => "NULL" ];
print $search->query()."\n";

$_REQUEST = [ 'scol' => 'name', 'sval' => "''" ];
print $search->query()."\n";

$_REQUEST = [ 'scol' => 'name', 'sval' => "EMPTY" ];
print $search->query()."\n";

$_REQUEST = [ 'scol' => 'price', 'sval' => '100' ];
print $search->query()."\n";
