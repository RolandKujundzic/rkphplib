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

/** @var map first_row = null */
protected $first_row = null;



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
	$plugin['sql:json'] = TokPlugin::REQUIRE_BODY;
	$plugin['sql:col'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['sql:getId'] = TokPlugin::NO_PARAM;
	$plugin['sql:nextId'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['sql:in'] = TokPlugin::CSLIST_BODY;
	$plugin['sql:password'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
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
 * Return next unique id. 
 *
 * @tok {sql:nextId:$table}
 *
 * @see ADatabase.nextId($table)
 * @param string $table
 * @return int
 */
public function tok_sql_nextId($table) {
	return $this->db->nextId($table);
}


/**
 * Return result of mysql query "SELECT PASSWORD('$password')".
 *
 * @tok {sql:password}secret{:sql} = PASSWORD('secret') = *0B32... 
 *
 * @param string $password
 * @return string
 */
public function tok_sql_password($password) {
	return '*'.strtoupper(sha1(sha1($password, true)));
}


/**
 * Return last auto_increment id.
 * 
 * @tok {sql:getId}[$query]{:sql}
 *
 * @see ADatabase.getInsertId()
 * @param string $query (optional)
 * @return int
 */
public function tok_sql_getId($query) {
	if (!empty($query)) {
		$this->db->execute($query, true);
	}

	return $this->db->getInsertId();
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
	$use_result = (strpos($query_prefix, 'select ') === 0) || (strpos($query_prefix, 'show ') === 0);

	if ($use_result) {
		$this->first_row = null;
	}

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
	if (is_null($this->first_row)) {
		$this->first_row = $this->db->getNextRow();

		if (is_null($this->first_row)) {
			$this->first_row = [];
		}
	}

	return (isset($this->first_row[$name]) || array_key_exists($name, $this->first_row)) ? $this->first_row[$name] : '';
}


/**
 * Return query result as json. Use mode = hash (key AS name, value AS value) to result hash.
 * Otherwise return table.
 * 
 * @throws
 * @param string $mode 
 * @param string $query
 * @return table|hash
 */
public function tok_sql_json($mode, $query) {
	require_once(__DIR__.'/../JSON.class.php');

	if ($mode == 'hash') {
		$dbres = $this->db->selectHash($query);
	}
	else {
		$dbres = $this->db->select($query);
	}

	return \rkphplib\JSON::encode($dbres);
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

