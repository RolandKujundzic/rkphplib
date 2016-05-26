<?php

namespace rkphplib\lib;

/**
 * Configuration file.
 *
 * Set global $settings_* default values:
 *
 *  $settings_TIMEZONE = GMT
 *  $settings_LANGUAGE = de
 *  $settings_LOG_ERROR = 1
 *  $settings_LOG_DEBUG = 1
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

if (!isset($settings_TIMEZONE)) {
	/** @global string $settings_TIMEZONE = 'GMT' */
	global $settings_TIMEZONE;
	$settings_TIMEZONE = 'GMT';
}

date_default_timezone_set($settings_TIMEZONE);

if (!isset($settings_LANGUAGE)) {
	/** @global string $settings_LANGUAGE = 'de' */
	global $settings_LANGUAGE;
	$settings_LANGUAGE = 'de';
}

if (!isset($settings_LOG_ERROR)) {
	/** @global string $settings_LOG_ERROR = 1 */
	global $settings_LOG_ERROR;
	$settings_LOG_ERROR = 1;
}

if (!isset($settings_LOG_DEBUG)) {
	/** @global string $settings_LOG_DEBUG = 1 */
	global $settings_LOG_DEBUG;
	$settings_LOG_DEBUG = 1;
}


// global define
define('RKPHPLIB_VERSION', 1.0);


/**
 * Default Exception catch.
 * 
 * @param Exception $e
 */
function exception_handler($e) {
	$msg = "\n\nABORT: ".$e->getMessage();
	$trace = $e->getFile()." on line ".$e->getLine()."\n".$e->getTraceAsString();
	$internal = property_exists($e, 'internal_message') ? "INFO: ".$e->internal_message : '';

	if (php_sapi_name() !== 'cli') {
		$ts = date('d.m.Y H:i:s');
		error_log("$ts $msg\n$internal\n\n$trace\n\n", 3, '/tmp/php.fatal');
	  die("<h3 style='color:red'>$msg</h3>");
	}
  else if (php_sapi_name() === 'cli' && property_exists($e, 'internal_message') && substr($e->internal_message, 0, 1) === '@') {
    if ($e->internal_message == '@ABORT') {
      print "\nABORT: ".$e->getMessage()."\n\n"; exit(1);
    }
    else if ($e->internal_message == '@SYNTAX') {
      print "\nSYNTAX: ".$e->getMessage()."\n\n"; exit(1);
    }
  }

	die("$msg\n$internal\n\n$trace\n\n");
}

set_exception_handler('\rkphplib\lib\exception_handler');


/**
 * Custom error handler. 
 *
 * Convert any php error into Exception.
 * 
 * @param int $errNo
 * @param string $errStr
 * @param string $errFile
 * @param int $errLine
 */
function error_handler($errNo, $errStr, $errFile, $errLine) {

	if (error_reporting() == 0) {
		// @ suppression used, ignore it
		return;
	}

	throw new \ErrorException($errStr, 0, $errNo, $errFile, $errLine);
}

set_error_handler('\rkphplib\lib\error_handler');

