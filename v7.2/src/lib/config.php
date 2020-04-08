<?php

namespace rkphplib\lib;

/**
 * Configuration file. Include lib/log_debug.php.
 * Change PrivateTmp=true into false in 
 *  
 * /etc/systemd/system/multi-user.target.wants/apache2.service to use real /tmp directory - otherwise
 * /tmp/systemd-private-f9...09-apache2.service-eC...Xy/tmp/ will be used.
 *
 * Preset global defines SETTINGS_* with default values:
 *
 *  SETTINGS_TIMEZONE = Auto-Detect (e.g. CET)
 *  SETTINGS_LANGUAGE = de
 *
 * Set date_default_timezone_set(SETTINGS_TIMEZONE) if unset and mb_internal_encoding('UTF-8').
 *
 * Define:
 *
 *  RKPHPLIB_VERSION = 1.0
 *
 * Register exception_handler and error_handler.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */

require_once __DIR__.'/log_debug.php';
require_once __DIR__.'/log_warn.php';

// E_ERROR | E_WARNING | E_PARSE | E_NOTICE or E_ALL or E_ALL ^ E_NOTICE
// error_reporting(E_ALL);

// Force UTF-8 encoding
mb_internal_encoding('UTF-8');

// Force de_DE.UTF-8 locale - otherwise escapeshellarg is broken !!!
if (setlocale(LC_ALL, 'de_DE.UTF-8') === false) {
  die("setlocale de_DE.UTF-8 failed");
}

if (!defined('SETTINGS_REQ_DIR')) {
	// @define string SETTINGS_REQ_DIR = 'dir' 
	define('SETTINGS_REQ_DIR', 'dir');
}

if (!defined('SETTINGS_LANGUAGE')) {
	// @define string SETTINGS_LANGUAGE = 'de'
	define('SETTINGS_LANGUAGE', 'de');
}

// make create files and directories rw for webserver
if (!defined('FILE_DEFAULT_MODE') && !defined('DIR_DEFAULT_MODE')) {
	if (php_sapi_name() == 'cli') {
		define('FILE_DEFAULT_MODE', 0666);
		define('DIR_DEFAULT_MODE', 0777);
	}
	else {
		define('FILE_DEFAULT_MODE', 0660);
		define('DIR_DEFAULT_MODE', 0770);
	}
}

define('RKPHPLIB_VERSION', 'v1.0.2');


/**
 * Default Exception catch. Define ABORT_DIR (e.g. login/abort) and use {get:error_msg} and
 * {var:=#error}original error=translated error|#|...{:var} (before error) to show tokenized abort messages.
 * 
 * @param Exception $e
 */
function exception_handler($e) {
	$msg = "\n\nABORT: ".$e->getMessage();
	$trace = $e->getFile()." on line ".$e->getLine()."\n".$e->getTraceAsString();
	$internal = property_exists($e, 'internal_message') ? "INFO: ".$e->internal_message : '';

	$ts = date('d.m.Y H:i:s');
	error_log("$ts $msg\n$internal\n\n$trace\n\n", 3, SETTINGS_LOG_ERROR);

	if (php_sapi_name() !== 'cli') {
		if (!empty($_REQUEST['ajax']) || (!empty($_REQUEST[SETTINGS_REQ_DIR]) && strpos($_REQUEST[SETTINGS_REQ_DIR], 'ajax/') !== false)) {
			http_response_code(400);
			header('Content-Type: application/json');
			print '{ "error": "1", "error_message": "'.$e->getMessage().'", "error_code": "'.$e->getCode().'" }';
			exit(0);
		}
		else if (defined('ABORT_DIR') && class_exists('\rkphplib\tok\Tokenizer') && class_exists('\rkphplib\tok\TBase')) {
			$tok =& \rkphplib\tok\Tokenizer::$site;
			$localized_error_msg = $tok->getVar('error.'.$e->getMessage());
			$_REQUEST['error_msg'] = $localized_error_msg ? $localized_error_msg : $e->getMessage();

			if (!empty($_REQUEST['dir'])) {
				$_REQUEST['old_dir'] = $_REQUEST['dir'];
			}

			$_REQUEST['dir'] = ABORT_DIR;

			include 'index.php';
			return;
		}
		else {
			die("<h3 style='color:red'>$msg</h3>");
		}
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

set_error_handler('\rkphplib\lib\error_handler', error_reporting());

