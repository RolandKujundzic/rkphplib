<?php

namespace rkphplib\lib;

/**
 * Configuration file. Usually included automatically by Exception, DateCalc, Session,
 * tok/TokPlugin.iface.php, lib/log_debug or lib/log_warn. Load lib/log_debug.php and 
 * lib/log_warn.php. Define:
 *
 * TAG_PREFIX = '{:='
 * TAG_SUFFIX = '}'
 * HASH_DELIMITER = |#|
 * DOCROOT = $PWD|$_SERVER[CONTEXT_DOCUMENT_ROOT]
 * SETTINGS_LOG_ERROR = (DOCROOT/data/.log|/tmp)/php.fatal
 * SETTINGS_LOG_WARN = dirname(SETTINGS_LOG_ERROR)/php.warn
 * SETTINGS_LOG_DEBUG = dirname(SETTINGS_LOG_ERROR)/php.log
 * SETTINGS_TIMEZONE = date_default_timezone_get()
 * SETTINGS_LANGUAGE = de|$_SERVER[HTTP_ACCEPT_LANGUAGE]
 * SETTINGS_REQ_DIR = dir
 * SETTINGS_CACHE_DIR = DOCROOT/data/.tmp
 * RKPHPLIB_VERSION = 1.0.3
 * FILE_DEFAULT_MODE = 0644(=cli)|0660
 * DIR_DEFAULT_MODE = 0755(=cli)|0770
 * $_GLOBALS[SETTINGS] = []
 *
 * Set date_default_timezone_set(SETTINGS_TIMEZONE) if unset.
 * Set mb_internal_encoding('UTF-8') and LC_ALL='de_DE.UTF-8'.
 *
 * Register exception_handler and error_handler.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */

if (!defined('DOCROOT')) {
	if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT']) && is_dir($_SERVER['CONTEXT_DOCUMENT_ROOT'].'/data/.log')) {
		define('DOCROOT', $_SERVER['CONTEXT_DOCUMENT_ROOT']);
	}
	else {
		define('DOCROOT', getcwd()); 
	}
}

if (!defined('SETTINGS_LOG_ERROR')) {
	// @define string SETTINGS_LOG_ERROR = '[DOCROOT/data/.log|/tmp]/php.fatal'
	if (defined('DOCROOT') && is_dir(DOCROOT.'/data/.log')) {
		define('SETTINGS_LOG_ERROR', DOCROOT.'/data/.log/php.fatal');
	}
	else {
		define('SETTINGS_LOG_ERROR', '/tmp/php.fatal');
	}
}

if (!defined('SETTINGS_LOG_WARN')) {
	// @define string SETTINGS_LOG_WARN = dirname(SETTINGS_LOG_ERROR).'/php.warn'
	define('SETTINGS_LOG_WARN', dirname(SETTINGS_LOG_ERROR).'/php.warn');
}

if (!defined('SETTINGS_LOG_DEBUG')) {
	// @define string SETTINGS_LOG_DEBUG = dirname(SETTINGS_LOG_ERROR)/php.log'
	define('SETTINGS_LOG_DEBUG', dirname(SETTINGS_LOG_ERROR).'/php.log');
}

if (!defined('SETTINGS_TIMEZONE')) {
  // @define string SETTINGS_TIMEZONE = Auto-Detect
  date_default_timezone_set(@date_default_timezone_get());
  define('SETTINGS_TIMEZONE', date_default_timezone_get());
}
else {
  date_default_timezone_set(SETTINGS_TIMEZONE);
}

if (!isset($GLOBALS['SETTINGS'])) {
	$GLOBALS['SETTINGS'] = [];
}


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

if (!defined('TAG_PREFIX')) {
  // @define string TAG_PREFIX = '{:='
  define('TAG_PREFIX', '{:=');
}

if (!defined('TAG_SUFFIX')) {
  // @define string TAG_SUFFIX = '}'
  define('TAG_SUFFIX', '}');
}

if (!defined('HASH_DELIMITER')) {
	// @const HASH_DELIMITER = '|#|' if undefined 
	define('HASH_DELIMITER', '|#|');
}

if (!defined('SETTINGS_REQ_DIR')) {
	// @define string SETTINGS_REQ_DIR = 'dir' 
	define('SETTINGS_REQ_DIR', 'dir');
}

if (!defined('SETTINGS_CACHE_DIR')) {
	// @define string SETTINGS_CACHE_DIR = DOCROOT.'/data/.tmp'
	define('SETTINGS_CACHE_DIR', DOCROOT.'/data/.tmp');
}

if (!defined('SETTINGS_TIMEZONE')) {
	date_default_timezone_set(@date_default_timezone_get());
	// @const string SETTINGS_TIMEZONE = Auto-Detect
	define('SETTINGS_TIMEZONE', date_default_timezone_get());
}
else {
	date_default_timezone_set(SETTINGS_TIMEZONE);
}

if (!defined('SETTINGS_LANGUAGE')) {
	// @const string SETTINGS_LANGUAGE = 'de|HTTP_ACCEPT_LANGUAGE'
	if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		define('SETTINGS_LANGUAGE', substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
	}
	else {
		// @define string SETTINGS_LANGUAGE = 'de'
		define('SETTINGS_LANGUAGE', 'de');
	}
}

// make create files and directories rw for webserver
if (!defined('FILE_DEFAULT_MODE') && !defined('DIR_DEFAULT_MODE')) {
	if (php_sapi_name() == 'cli') {
		// @const FILE_DEFAULT_MODE = 0666 (UID < 1000) or 0644 (UID >= 1000) 
		if (!defined('FILE_DEFAULT_MODE')) {
			if (posix_getuid() < 1000) {
				define('FILE_DEFAULT_MODE', 0666);
			}
			else {
				define('FILE_DEFAULT_MODE', 0644);
			}
		}

		// @const int DIR_DEFAULT_MODE octal, default directory creation mode, 0777 (uid < 1000) or 0755
		if (!defined('DIR_DEFAULT_MODE')) {
			if (posix_getuid() < 1000) {
				define('DIR_DEFAULT_MODE', 0777);
			}
			else {
				define('DIR_DEFAULT_MODE', 0755);
			}
		}
	}
	else {
		define('FILE_DEFAULT_MODE', 0660);
		define('DIR_DEFAULT_MODE', 0770);
	}
}

define('RKPHPLIB_VERSION', 'v1.0.3');


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

