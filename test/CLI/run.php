<?php

require_once '../settings.php';
require_once PATH_SRC.'CLI.php';

use \rkphplib\CLI;

function syntax(string $cmd, array $check, array $opt = []) : void {
	$error = '';
	CLI::$abort = 0;
	CLI::$log = &$error;
	CLI::parse($cmd);
	CLI::syntax($check, $opt);
	print '# cmd=['.join(' ', CLI::$argv).'] check=['.join('|', $check).
		'] opt=['.join('|', $opt)."]\n".trim(CLI::$log)."\n---\n";
}

global $th;

$th->run(1, 7);
