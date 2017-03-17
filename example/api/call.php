<?php

define('PATH_RKPHPLIB', __DIR__.'/../../src/');

require_once(PATH_RKPHPLIB.'APICall.class.php');

use \rkphplib\APICall;


/**
 *
 */
function apicall($conf, $data) {
	$api_default = [ 
		'url' => 'http://localhost:10080',
		'auth' => 'basic_auth',
		'token' => 'test:test',
	];


	$api = new APICall(array_merge($api_default, $conf));

	if ($api->exec($data)) {
		print "\nAPICall succeded\n";
	}
	else {
		print "\nAPICall failed\n";
	}

	print "INFO: ".print_r($api->info, true)."\n";
	print "RESULT: ".print_r($api->result, true)."\n";
}


/*
apicall([
	'uri' => '/some/action',
	'method' => 'get',
	'content' => '',
	'accept' => ''
], [ 'x' => 18, 'y' => 'yalla' ]);
*/

/*
apicall([
	'uri' => 'some/action/?x=5&y=hello&z=hohoho',
	'method' => 'get',
	'content' => 'application/x-www-form-urlencoded',
	'accept' => 'text/plain'
], [ 'x' => 18, 'y' => 'yalla' ]);
*/

apicall([
	'uri' => '/signup/user/de?x=5&y=hello',
	'method' => 'post',
	'content' => 'application/x-www-form-urlencoded',
	'accept' => 'application/json'
], [ 'x' => 18, 'z' => 'yalla' ]);

