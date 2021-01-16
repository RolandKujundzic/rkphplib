<?php

require_once '../settings.php';

function fix(string $path) : string {
	$pos = strpos($path, '/rkphplib/test/');
	$res = substr($path, $pos + 15);
	error_log("fix($path) = [$res]\n", 3, 'out/fix.log');
	return $res;
}

global $th;

$th->run(1, 4);

