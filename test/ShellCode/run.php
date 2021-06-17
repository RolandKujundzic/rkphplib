<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.php';
	$th = new rkphplib\TestHelper();
}

$th->load('src/ShellCode.php');

$code = new \rkphplib\ShellCode();
$code->parse(DOCROOT.'/run.sh');
print_r($code->getStatus());

/*
$namespace = $code->getNamespace(true);
$th->compare("PhpCode->getNamespace", [ 'rkphplib\tok' ], [ 'rkphplib\tok' ]);

$class = $code->getClass(true);
$th->compare("PhpCode->getClass", [ $th->res2str($class) ], 
	[ '{"abstract":false,"name":"TBase","path":"rkphplib\\\tok\\\TBase","implements":["TokPlugin"]}' ]);

print "load multi_class.php:\n";
$code->load(__DIR__.'/multi_class.php');
$out = '';

while ($code->getNamespace(2)) {
	$out .= "namespace: ".$code->getNamespace()."\n";
	while ($code->getClass(2)) {
		$out .= "class: ".$code->getClass()['name']."\n";
	}
}

print $out;
*/
