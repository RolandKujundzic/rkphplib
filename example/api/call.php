<?php

define('PATH_RKPHPLIB', __DIR__.'/../../src/');

require_once(PATH_RKPHPLIB.'APICall.class.php');

use \rkphplib\APICall;


/**
 * Execute api call.
 *
 * @param map $conf
 * @param map|string $data
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


// test invalid route
// apicall([ 'uri' => '/some/action/?a=hello&x=12', 'method' => 'get', 'content' => '', 'accept' => '' ]);

// valid GET route - data is appended to query (overwrite)
// apicall([ 'uri' => '/user/388?a=hello&x=12', 'method' => 'get', 
//	'content' => 'application/x-www-form-urlencoded', 'accept' => 'application/json' ], [ 'a' => 'yuck', 'x' =>5 ]);

// valid POST route - post is prefered to get - output is xml
// apicall([ 'uri' => '/user/manager/en?x=3&z=5', 'accept' => 'application/xml', 'method' => 'post', 
//	'content' => 'application/x-www-form-urlencoded' ], [ 'a' => 'hello', 'x' => 12 ]);  

// change user 1 data
// apicall([ 'uri' => '/user/1', 'method' => 'put', 'content' => 'application/json', 'accept' => '' ], 
//	[ 'firstname' => 'John', 'lastname' => 'Doe' ]);

// get user without id
// apicall([ 'uri' => '/user' ]);

// get user 17
// apicall([ 'uri' => '/user/17' ]);

// delete user file 132
// apicall([ 'uri' => '/user/file/132', 'method' => 'delete' ]);

// post file
// apicall([ 'uri' => '/user/file', 'method' => 'post', 'accept' => 'application/json', 'content' => 'multipart/form-data' ], 
//	[ 'uid' => 15, 'upload_type' => 'logo', 'upload' => '@call.sh', 'file' => '@call.sh' ]);

// post file as content (no _FILES on recipient side)
// apicall([ 'uri' => '/user/file', 'method' => 'post', 'accept' => 'application/json', 'content' => 'multipart/form-data' ], 
//	[ 'uid' => 15, 'upload_type' => 'logo', 'upload' => file_get_contents('call.sh') ]);

// change user file
// apicall([ 'uri' => '/user/file/4', 'method' => 'put', 'accept' => 'application/json', 'content' => 'application/octet-stream' ],
//	file_get_contents('call.sh'));

// change user file
// apicall([ 'uri' => '/user/file/4', 'method' => 'put', 'accept' => 'application/json', 'content' => 'multipart/form-data' ], 
//	[ 'upload' => '@call.sh' ]);

