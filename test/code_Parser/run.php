<?php

require_once '../settings.php';

require_once PATH_SRC.'code/Parser.php';
require_once PATH_SRC.'Dir.php';

use rkphplib\Exception;
use rkphplib\code\Parser;
use rkphplib\FSEntry;
use rkphplib\Dir;


/**
 *
 */
function _scan_code($dir, $suffix) {
	$files = Dir::scanTree($dir, [ $suffix ]);
	$code = new Parser([ 'name' => $suffix ]);

	print "Scan *.$suffix code in $dir (".count($files)." files) ... ";

	foreach ($files as $file) {
		$code->scan($file);
	}

	print "OK\n";
}


/*
 * M A I N
 */

global $th;

$load = 0;
try {
	$bash = new Parser([ 'name' => 'bash' ]);
	$bash->load('test1.sh');
	$load++;
	$bash->load('test2.sh');
	$load++;
}
catch (Exception $e) {
	print "Exception ".($load + 1).': '.$e->getMessage()."\t".$e->internal_message."\n";
}

$th->compare("new Parser('bash'): load test1.sh, test2.sh", [ $load ], [ 2 ]);

$load = 0;
try {
	$php = new Parser([ 'name' => 'php' ]);
	$php->load('test1.php');
	$load++;
	$php->load('test2.php');
	$load++;
}
catch (Exception $e) {
	print "Exception ".($load + 1).': '.$e->getMessage()."\t".$e->internal_message."\n";
}

$th->compare("new Parser('php'): load test1.php, test2.php", [ $load ], [ 2 ]);

_scan_code($src_dir, 'php');
_scan_code(DOCROOT.'/php/phplib/src', 'php');
_scan_code(DOCROOT.'/shell/rkscript/src', 'sh');
_scan_code(DOCROOT.'/shell/shlib/sh/run', 'sh');

