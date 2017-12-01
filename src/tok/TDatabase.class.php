<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Exception.class.php');
require_once(__DIR__.'/../Database.class.php');

use \rkphplib\Exception;
use \rkphplib\Database;
use \rkphplib\ADatabase;


/**
 * Database Tokenizer plugins.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TDatabase implements TokPlugin {

/** @var \rkphplib\ADatabase $db (default = null) */
private $db = null;



/**
 * Return Tokenizer plugin list:
 *
 * esc, null, sql_name, sql_query, sql_col
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {

	$plugin = [];
  $plugin['sql_dsn'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::NO_PARAM;
  $plugin['sql_name'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::NO_PARAM;
  $plugin['sql_query'] = TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::NO_PARAM;
  $plugin['sql_col'] = TokPlugin::NO_BODY | TokPlugin::REQUIRE_PARAM;

	return $plugin;
}


/**
 * Constructor. Create database connection.
 * 
 * @see Database::getInstance
 * @param string $dsn (default = '', use SETTINGS_DSN if not empty)
 */
public function __construct($dsn = '') {

	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}

	$this->db = Database::getInstance($dsn);
}


/**
 * Set database connection string.
 *
 * @tok {sql_name:}a b{:sql_name} -> `a b`
 * @see \rkphplib\ADatabase::setDSN()
 * @param string $arg
 * @return empty string
 */
public function tok_sql_dsn($dsn) {
	$this->db = Database::getInstance($dsn);
	return '';
}


/**
 * SQL Escape trim($name).
 *
 * @tok {sql_name:}a b{:sql_name} -> `a b`
 * @see \rkphplib\ADatabase::escape_name
 * @param string $name
 * @return string
 */
public function tok_sql_name($name) {
	return ADatabase::escape_name(trim($name));
}


/**
 * Execute sql query.
 *
 * @param string $param
 * @param string $arg
 * @return empty string
 */
public function sql_query($param, $arg) {
	throw new Exception('ToDo');
	return '';
}


/**
 * Return column value of last sql query.
 *
 * @param string $param
 * @param string $arg
 * @return string
 */
public function sql_col($param, $arg) {
	throw new Exception('ToDo');
}


}

