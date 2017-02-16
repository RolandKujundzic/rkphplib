<?php

require_once('src/lib/config.php');
require_once('src/File.class.php');

use \rkphplib\Exception;
use \rkphplib\File;



/**
 * Add or remove comment "//" (mode = on|off) before "\rkphplib\lib\log_debug(", "rkphplib\lib\log_debug(",
 * "lib\log_debug(" and "log_debug(". Apply change if found at beginning of and not already on|off.
 *  
 * @param string $file
 * @param bool $on
 */
function toggle_lib_debug($file, $on) {
	$lines = File::loadLines($file);
	$code_before = join('', $lines);
	$code = '';

	if ($on) {
		print "enable log_debug in $file\n";
	}
	else {
		print "disable log_debug in $file\n";
	}

	foreach ($lines as $line) {
		if (!$on && preg_match('/^(\s*)\\\?rkphplib\\\lib\\\log_debug\(/', $line, $match)) {
			$ml = mb_strlen($match[1]);
			$line = $match[1].'// '.mb_substr($line, $ml);
		}
		else if ($on && preg_match('/^(\s*)\/\/ \\\?rkphplib\\\lib\\\log_debug\(/', $line, $match)) {
			$ml = mb_strlen($match[1]);
			$line = $match[1].mb_substr($line, $ml + 3); 
		}

		$code .= $line;
	}

	if ($code_before != $code) {
		print "overwrite file with modified version\n";
		File::save($file, $code);
	}
	else {
		print "no change - keep file\n";
	}
}


/**
 * M A I N 
 */

if (empty($_SERVER['argv'][1]) || !File::exists($_SERVER['argv'][1]) || empty($_SERVER['argv'][2]) ||
		!in_array($_SERVER['argv'][2], ['on', 'off'])) {
	print "\nSYNTAX: php ".$_SERVER['argv'][0]." PHP_FILE on|off\n\n";
	exit(1);
}

toggle_lib_debug($_SERVER['argv'][1], ($_SERVER['argv'][2] === 'on'));
