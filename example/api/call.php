<?php

define('PATH_RKPHPLIB', __DIR__.'/../../src/');

require_once(PATH_RKPHPLIB.'APICall.class.php');

use \rkphplib\APICall;


$data = [ 'x' => 18, 'y' => 'yalla' ];

$api = new APICall();

$api->set('url', 'http://localhost:10080');
$api->set('uri', '/some/path');
$api->set('method', 'get');
$api->set('auth', 'basic_auth');
$api->set('token', 'test:test');
$api->set('content', 'application/json');
$api->set('accept', 'application/xml');

if ($api->exec($data)) {
	print "\nAPICall succeded\n";
}
else {
	print "\nAPICall failed\n";
}

print "INFO: ".print_r($api->info, true)."\n";
print "RESULT: ".print_r($api->result, true)."\n";
