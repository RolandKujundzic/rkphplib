<?php

require_once '../../src/SQLSearch.php';



$search = new \rkphplib\SQLSearch([
	'search' => 'id,price:LE,name:LIKE'
]);

$_REQUEST = [ 's_id' => 'ID' ];
print $search->query()."\n";

$_REQUEST = [ 's_name' => 'NAME' ];
print $search->query()."\n";

$_REQUEST = [ 's_price' => '100' ];
print $search->query()."\n";
