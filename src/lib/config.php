<?php

namespace rkphplib\lib;

/**
 * Configuration file.
 * Set global $settings_* default values:
 *
 *  settings_TIMEZONE = GMT
 *  settings_LANGUAGE = de
 *
 * Define:
 *
 *   RKPHPLIB_VERSION = 1.0
 *
 * Register exception_handler and error_handler.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */


// E_ERROR | E_WARNING | E_PARSE | E_NOTICE or E_ALL or E_ALL ^ E_NOTICE
// error_reporting(E_ALL);

// Force UTF-8 encoding
mb_internal_encoding('UTF-8');


// set default timezone
if (isset($settings_TIMEZONE)) {
	date_default_timezone_set($settings_TIMEZONE);
}
else {
	date_default_timezone_set('GMT');
}

// set default language (=de)
if (!isset($settings_LANGUAGE)) {
	$settings_LANGUAGE = 'de';
}


// global define
define('RKPHPLIB_VERSION', 1.0);


/**
 * Default Exception catch.
 */
function exception_handler($e) {
	$msg = "\n\nABORT: ".$e->getMessage();
	$trace = $e->getFile()." on line ".$e->getLine()."\n".$e->getTraceAsString();
	$internal = empty($e->internal_message) ? '' : "INFO: ".$e->internal_message;

	if (php_sapi_name() !== 'cli') {
		error_log("$msg\n$internal\n\n$trace\n\n", 3, '/tmp/php.fatal');
	  die("<h3 style='color:red'>$msg</h3>");
	}

	die("$msg\n$internal\n\n$trace\n\n");
}

set_exception_handler('\rkphplib\lib\exception_handler');


/**
 * Custom error handler. Convert any php error into Exception.
 */
function error_handler($errNo, $errStr, $errFile, $errLine) {

	if (error_reporting() == 0) {
		// @ suppression used, ignore it
		return;
	}

	throw new \ErrorException($errStr, 0, $errNo, $errFile, $errLine);
}

set_error_handler('\rkphplib\lib\error_handler');

