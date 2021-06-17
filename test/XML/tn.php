<?php

require_once '../../src/XML.php';

if (empty($_SERVER['argv'][1]) || intval($_SERVER['argv'][1]) < 1) {
	print "\nSYNTAX: php {$_SERVER['argv'][0]} N\n\nN=1 execute in/t1.php\n\n";
	exit;
}

ob_start();
include "in/t{$_SERVER['argv'][1]}.php";
$out = ob_get_contents();
ob_end_clean();

print $out;
