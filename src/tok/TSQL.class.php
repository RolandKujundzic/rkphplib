<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Database.class.php');
require_once(__DIR__.'/../lib/conf2kv.php');

use \rkphplib\Database;



/**
 * Execute SQL queries.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TSQL implements TokPlugin {

/** @var ADatabase $db */
protected $db = null;



/**
 * Register output plugins. Examples:
 *
 * @tok {sql:query}SELECT * FROM test WHERE name LIKE '{:=name}%' OR id={:=name}{:sql}
 *
 * @tok {sql:qkey:test}SELECT * FROM test WHERE name LIKE '{:=name}%' OR id={:=name}{:sql}
 * @tok {sql:query:test}name=something{:sql}
 *
 * @tok {sql:dsn}mysqli://user:pass@tcp+localhost/dbname{:sql} (use SETTINGS_DSN by default)
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$this->tok = $tok;

	$plugin = [];
	$plugin['sql:query'] = 0;
	$plugin['sql:dsn'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY; 
	$plugin['sql:name'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['sql:qkey'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['sql:col'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['sql:in'] = TokPlugin::CSLIST_BODY;
	$plugin['sql'] = 0;
	$plugin['null'] = TokPlugin::NO_PARAM;

	return $plugin;
}


/**
 * Constructor.
 */
public function __construct() {
	if (defined('SETTINGS_DSN')) {
		$this->db = Database::getInstance();
	}
}


/**
 * Convert list into escaped sql list.
 *
 * @tok {sql:in:age}18,19,20{:sql} -> age IN ('18', '19', '20')
 * @tok {sql:in}admin, user{:sql} -> ('admin', 'user')
 * 
 * @param string $param
 * @param array $list
 */
public function tok_sql_in($param, $list) {
	$in = [];

	for ($i = 0; i < count($list); $i++) {
		array_push($in, $this->db->esc($list[$i]));
	}

	$res = "('".join("', '", $in)."')";

	if ($param) {
		$res = $this->db->esc_name($param).' IN '.$res;
	}

	return $res;
}


/**
 * Set database connection string. Example:
 *
 * @tok {sql:dsn}mysqli://user:pass@tcp+localhost/dbname{:sql}
 *
 * @throws
 * @param string $dsn
 * @return ''
 */
public function tok_sql_dsn($dsn) {
	$this->db = Database::getInstance($dsn);
}


/**
 * Define query. Example:
 *
 * @tok {sql:qkey:test}SELECT * FROM test WHERE id={:=id}{:sql}
 * @tok {sql:query:test}
 *
 * @throws
 * @param string $qkey
 * @param string $query
 * @return ''
 */
public function tok_sql_qkey($qkey, $query) {
	$this->db->setQuery($qkey, $query);
}


/**
 * Execute sql query. Example:
 *
 * @tok {sql:query}UPDATE test SET name={:=name} WHERE id={:=id}{:sql}
 *
 * @tok {sql:qkey:test}SELECT * FROM test WHERE id={:=id}{:sql}
 * @tok {sql:query:test}id=31{:sql}
 *
 * @throws
 * @param string
 * @param string
 * @return ''
 */
public function tok_sql_query($qkey, $query) {

	if (!empty($qkey)) {
		$replace = \rkphplib\lib\conf2kv($query);
		$query = $this->db->getQuery($qkey, $replace);
	}

	$query_prefix = strtolower(substr(trim($query), 0, 20));
	$use_result = (strpos($query_prefix, 'select ') === 0);

	$this->db->execute($query, $use_result);
	return '';
}


/**
 * Return colum value of last {sql_query:}.
 * 
 * @throws
 * @param string $name
 * @return string
 */
public function tok_sql_col($name) {
	throw new Exception('ToDo ...');
}


/**
 * Escape null value. Example:
 *
 * @tok {null:}abc{:null} = 'abc'
 * @tok {null:}null{:null} = {null:}Null{:null} = {null:}{:null} = NULL
 *
 * @param string $param
 * @param string $arg
 * @return string
 */
public function tok_null($arg) {

  if (strtolower(trim($arg)) == 'null') {
		$res = 'NULL';
  }
	else if (strlen(trim($arg)) > 0) {
		$res = "'".\rkphplib\ADatabase::escape($res)."'";
  }
	else {
  	$res = 'NULL';
	}

  return $res;
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
  return \rkphplib\ADatabase::escape_name(trim($name));
}

	
}

