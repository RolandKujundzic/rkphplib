<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/lib/split_str.php');
require_once(__DIR__.'/lib/is_map.php');


/**
 * Abstract database access wrapper class.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 */
abstract class ADatabase {

/** @const int NOT_NULL = NOT NULL column */
const NOT_NULL = 1;

/** @const int PRIMARY = PRIMARY KEY (column) */
const PRIMARY = 2;

/** @const int UNIQUE = UNIQUE (column) */
const UNIQUE = 4;

/** @const int INDEX = KEY (column) */
const INDEX = 8;

/** @const int FOREIGN = FOREIGN KEY (column) REFERENCES ... */
const FOREIGN = 16;

/** @const int UNSIGNED */
const UNSIGNED = 32;

/** @const int DELETE_CASCADE */
const DELETE_CASCADE = 64;

/** @const int UPDATE_CASCADE */
const UPDATE_CASCADE = 128;

/** @const int AUTO_INCREMENT */
const AUTO_INCREMENT = 256;



/** @var bool $use_prepared */
public static $use_prepared = false;

/** @var string $time_zone (default = empty = use db default, Example: 'Europe/Berlin') */ 
public static $time_zone = '';

/** @var string $charset (default = empty = use db default, Example: 'utf8', 'utf8mb4') */
public static $charset = '';

/** @var string $_dsn */
protected $_dsn = null;

/** @var array[string]string $_query */
protected $_query = [];

/** @var array[string]array $_qinfo */
protected $_qinfo = [];



/**
 * Return md5 hash base on dsn and $query_map (see setQueryMap).
 * 
 * @throws
 * @param string $dsn
 * @param array[string]string $query_map
 * @return string 
 */
public static function computeId($dsn, $query_map = null) {
	
	if (empty($dsn)) {
		throw new Exception('empty database source name');
	}

	$res = md5($dsn);

	if (is_null($query_map) || (is_array($query_map) && count($query_map) === 0)) {
		return $res;
	}

	if (!is_array($query_map)) {
		throw new Exception('invalid query map', print_r($query_map, true));
	}

	ksort($query_map);

	foreach ($query_map as $key => $value) {
		$res = is_array($value) ? md5($res.':'.md5($key).':'.md5($value['@query'])) : md5($res.':'.md5($key).':'.md5($value));
	}

	return $res;
}


/**
 * Return md5 identifier. Same as self::computeId(getDSN(), getQueryMap()).
 *
 * @throws
 * @return string
 */
public function getId() {
	return self::computeId($this->_dsn, $this->_query);
}


/**
 * Set database connect string. If dsn is empty use default SETTINGS_DSN. 
 *
 * Examples:
 * mysqli://user:password@tcp+localhost/dbname
 * sqlite://[password]@path/to/file.sqlite
 * 
 * @throws
 * @param string $dsn 
 */
public function setDSN($dsn = '') {

	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}

	if (!$dsn) {
		throw new Exception('empty database source name');
	}
  
	if (!empty($this->_dsn) && $this->_dsn != $dsn) {
		throw new Exception('close connection first');
	}

	$this->_dsn = $dsn;
}


/**
 * Return database connect string.
 *
 * @throws
 * @return string 
 */
public function getDSN() {

	if (empty($this->_dsn)) {
		throw new Exception('call setDSN() first');
	}

	return $this->_dsn;
}


/**
 * Return query map.
 *
 * @return array[string]string
 */
public function getQueryMap() {
	return $this->_query;
}


/**
 * Split data source name into hash (type, login, password, protocol, host, port, name, [file]). 
 *
 * Example:
 * type://login:password@protocol+host:port/name 
 * type://[password]@./file or type://@/path/to/file 
 *
 * @throws 
 * @param string $dsn
 * @return array[string]string
 */
public static function splitDSN($dsn) {

	$file_db = array('sqlite');

	if (preg_match('/^([a-z0-9]+)\:\/\/(.+?)@(.+?)\/(.*)$/i', $dsn, $match)) {
		$db = array('login' => '', 'password' => '', 'protocol' => '', 'host' => '', 'port' => '', 'name' => '', 'file' => '');
		$db['type'] = $match[1];

		if (!empty($match[2])) {
			if (($pos = mb_strpos($match[2], ':')) > 0) {
				$db['login'] = mb_substr($match[2], 0, $pos);
				$db['password'] = mb_substr($match[2], $pos + 1);
			}
			else {
				$db['password'] = $match[2];
			}
		}
		
		if (in_array($db['type'], $file_db)) {
			$db['file'] = $match[3].'/'.$match[4];
		}
		else {
			if (empty($match[3])) {
				throw new Exception('empty host, use host|host:port|protocol+host:port');
			}

			if (($pos = mb_strpos($match[3], '+')) > 0) {
				$db['protocol'] = mb_substr($match[3], 0, $pos);
				$match[3] = mb_substr($match[3], $pos + 1);
			}

			if (($pos = mb_strpos($match[3], ':')) > 0) {
				$db['host'] = mb_substr($match[3], 0, $pos);
				$db['port'] = mb_substr($match[3], $pos + 1);
			}
			else {
				$db['host'] = $match[3];
			}

			$db['name'] = $match[4];
		}
	}
	else {
		throw new Exception('invalid dsn', $dsn);
	}

	if (empty($db['type'])) {
		throw new Exception('empty type');
	}

	return $db;
}


/**
 * Set query extra information ($info), e.g.:
 * 
 * - type: select, insert, update, delete, alter, create, drop, show, ...
 * - auto_increment: id (name of auto_increment column)
 * - table: name of table
 * - master_query: INSERT INTO table (id, last_modified, login, password) values (0, null, CRC32(RAND()), CRC32(RAND())) 
 *
 * @throws 
 * @param string $qkey
 * @param array[string]string $info
 */
public function setQueryInfo($qkey, $info) {
	if (empty($qkey)) {
		throw new Exception('empty query key');
	}

	if (!is_array($info) || count($info) === 0) {
		throw new Exception('invalid query info', $qkey);
	}

	$info_keys = [ 'type', 'auto_increment', 'table', 'master_query' ];

	foreach ($info as $key => $value) {
		if (!in_array($key, $info_keys)) {
			throw new Exception('invalid query info', "key=$key value=$value");
		}
	}

	$this->_qinfo[$qkey] = $info;
}


/**
 * Return query info map (or value if $ikey is set).
 *
 * @param string $qkey 
 * @param string $ikey
 * @see setQueryInfo()
 * @throws 
 * @return mixed 
 */
public function getQueryInfo($qkey, $ikey = '') {
	if (empty($qkey)) {
		throw new Exception('empty query key');
	}

	if (!isset($this->_qinfo[$qkey])) {
		throw new Exception('no query info', $qkey);
	}

	if (empty($ikey)) {
		return $this->_qinfo[$qkey];
	}

	if (!isset($this->_qinfo[$qkey][$ikey])) {
		throw new Exception('no query info key', "qkey=$qkey ikey=$ikey");
	}

	return $this->_qinfo[$qkey][$ikey];
}


/**
 * Define query with placeholders. 
 *
 * Example:
 * INSERT INTO {:=^table} (a, b, c) VALUES ('{:=a}', '{:=b}', {:=_c})
 * 
 * If tag is '{:=x}' use prepared statement value bind (or change into {:=x} if self::$use_prepared = false). 
 * If tag is {:=x} apply escape($value).
 * If tag is {:=^x} apply escape_name($value).
 * If tag is {:=_x} keep $value.
 *
 * @throws
 * @param string $qkey
 * @param string $query
 * @param array[string]string $info see setQueryInfo()
 */
public function setQuery($qkey, $query, $info = []) {

	if (empty($qkey)) {
		throw new Exception('empty query key');
	}

	if (!is_string($qkey) || is_numeric($qkey)) {
		throw new Exception('invalid query key', $qkey);
	}

	if (empty($query)) {
		throw new Exception('empty query', $qkey);
	}

	if (count($info) > 0) {
		$this->setQueryInfo($qkey, $info);
	}

	if (mb_strpos($query, '{:=') === false) {
		$this->_query[$qkey] = [ '@query' => $query ];
		return;
	}

	$tok = preg_split("/('?\{:=[a-zA-Z0-9_\^]+\}'?)/s", $query, -1, PREG_SPLIT_DELIM_CAPTURE);
	// value is: bind, escape, escape2, escape_name, keep
	$map = array('bind' => array());

	for ($i = 1; $i < count($tok) - 1; $i += 2) {
		$m = $tok[$i];

		if (mb_substr($m, 0, 1) === "'" && mb_substr($m, -1) === "'" && mb_substr($m, 4, 1) !== '^') {
			if (self::$use_prepared) {
				array_push($map['bind'], mb_substr($m, 4, -2));
				$tok[$i] = '?';
			}
			else {
				$key = mb_substr($m, 4, -2);
				$tok[$i] = substr($tok[$i], 1, -1);
				$map[$key] = 'escape';
			}
		}
		else if (mb_substr($m, 3, 1) === '_') {
			$key = mb_substr($m, 3, -1);
			$map[$key] = 'keep';
		}
		else if (mb_substr($m, 3, 1) === '^') {
			$key = mb_substr($m, 4, -1);
			$map[$key] = 'escape_name';
		}
		else {
			$key = mb_substr($m, 3, -1);
			$map[$key] = 'escape';
		}
	}

	if (count($map['bind']) === 0) {
		unset($map['bind']);
	}

	$map['@query'] = join('', $tok);
	$this->_query[$qkey] = $map;
}


/**
 * Return (prepared) query defined via setQuery($qkey, '...').
 *
 * If query is prepared return parameter array (last two entries are query and replace hash). 
 *
 * @throws rkphlib\Exception if error
 * @param string $qkey
 * @param array[string]string $replace
 * @return string|array
 */
public function getQuery($qkey, $replace = null) {

	if (!isset($this->_query[$qkey])) {
		throw new Exception('call setQuery() first', "qkey=[$qkey]");
	}

	$q = $this->_query[$qkey];

	if (!is_array($q)) {
		if (is_array($replace) && count($replace) > 0) {
			throw new Exception('query has no tags', print_r($q, true));
		}

		// return text query ...
		return $q;
	}

	$query = $q['@query'];
	unset($q['@query']);

	$bind = null;
	if (isset($q['bind']) && count($q['bind']) > 0) { 
		$bind = $q['bind'];
		unset($q['bind']);
	}

	foreach ($q as $key => $do) {
		if (!isset($replace[$key]) && !array_key_exists($key, $replace)) {
			throw new Exception("query replace key $key missing", "($qkey) $query: ".print_r($replace, true));
		}

		if ($do === 'escape') {
			$value = $replace[$key];

			if (is_null($value) || $value === 'NULL' || $value === 'null') {
				$value = 'NULL';
			}
			else {
				$value = "'".$this->esc($value)."'";
			}

			$query = str_replace("{:=$key}", $value, $query);
		}
		else if ($do === 'escape_name') {
			$query = str_replace('{:=^'.$key.'}', self::escape_name($replace[$key]), $query);
		}
		else if ($do === 'keep') {
			$query = str_replace('{:='.$key.'}', $replace[$key], $query);
		}
		else {
			throw new Exception("Unknown replace action", "do=$do query=$query");
		}

		unset($replace[$key]);
	}

	if (!$bind) {
		return $query;
	}

	array_push($bind, $query);
	array_push($bind, $replace);

	return $bind;
}


/**
 * True if query key exists. If query is not empty compare too.
 * 
 * @param string $qkey
 * @param string $query
 * @return boolean
 */
public function hasQuery($qkey, $query = '') {

	if (!isset($this->_query[$qkey])) {
		return false;
	}

	if (empty($query)) {
		return true;
	}

	$qkey_query = is_array($this->_query[$qkey]) ? $this->_query[$qkey]['@query'] : $this->_query[$qkey];

	// don't compare '{:=a}' with {:=a}
	$query = str_replace("'", '', $query);
	$qkey_query = str_replace("'", '', $qkey_query);

	return $query == $qkey_query;
}


/**
 * True if every query key from query_map exists and if query_map is map (and not vector)
 * all queries must be same.
 * 
 * @param array $query_map
 * @return boolean
 */
public function hasQueries($query_map) {

	if (!is_array($query_map)) {
		return false;
	}
	else if (count($query_map) == 0) {
		return true;
	}
	else if (\rkphplib\lib\is_map($query_map)) {
		foreach ($query_map as $qkey => $query) {
			if (!$this->hasQuery($qkey, $query)) {
				return false;
			}
		}
	}
	else {
		foreach ($query_map as $i => $qkey) {
			if (!$this->hasQuery($qkey)) {
				return false;
			}
		}
	}

	return true;
}


/**
 * Apply setQuery($key, value) for every key value pair in $query_map.
 *
 * @see ADatabase::setQuery()
 * @param array[string]string $query_map
 */
public function setQueryMap($query_map) {
	foreach ($query_map as $qkey => $query) {
		$this->setQuery($qkey, $query);
	}
}


/**
 * Apply setQuery() for all hash values where key has [query.] prefix and value is not empty (qkey = key without prefix).
 * Change/remove query prefix with conf_hash[@query_prefix].
 * Use query.escape_name@table=test name to replace {:=@table} with `test name`.
 *
 * @see setQuery(), setQueryMap()
 * @throws
 * @param array[string]string $conf_hash
 * @param array $require_keys
 */
public function setQueryHash($conf_hash, $require_keys = '') {

	if (is_array($require_keys)) {
		foreach ($require_keys as $qkey) {
			if (empty($conf_hash['query.'.$qkey]) && empty($this->_query[$qkey])) {
				throw new Exception("no such key [query.$qkey]");
			}
		}
	}

	$replace = [];
	$qlist = [];

	$qprefix = isset($conf_hash['@query_prefix']) ? $conf_hash['@query_prefix'] : 'query.';
	$qplen = mb_strlen($qprefix);

	foreach ($conf_hash as $key => $value) {
		if (mb_substr($key, 0, $qplen) === $qprefix && !empty(trim($value))) {
			$qkey = mb_substr($key, $qplen);

			if (mb_substr($qkey, 0, 12) === 'escape_name@') {
				$rkey = mb_substr($qkey, 12);
				$replace[$rkey] = self::escape_name($value);
			}
			else {
				$qlist[$qkey] = $value;
			}
		}
	}

	foreach ($qlist as $qkey => $query) {
		foreach ($replace as $rkey => $rval) {
			$qlist[$key] = str_replace('{:=@'.$rkey.'}', $rval, $query);
		}
	}

	$this->setQueryMap($qlist);
}


/**
 * Escape table or column name with `col name`.
 *
 * If abort is true abort if name doesn't match [a-zA-Z0-9_\.]+.
 *
 * @throws rkphplib\Exception if error
 * @param string $name
 * @param bool $abort = false
 * @return string
 */
public static function escape_name($name, $abort = false) {
	$res = $name;

	if (!preg_match("/^[a-zA-Z0-9_\.]+$/", $name)) {
		if ($abort) {
			throw new Exception('invalid sql name', $name);
		}

		if (mb_strpos($name, '`') !== false) {
			throw new Exception('invalid sql name', $name);
		}

		$res = '`'.$name.'`';
	}

	return $res;
}


/**
 * Write lock tables.
 *
 * @param array $tables 
 */
abstract public function lock($tables);


/**
 * Unlock locked tables.
 *
 */
abstract public function unlock();


/**
 * Get named lock. Use releaseLock($name) to free.
 *
 * @throws
 * @param string $name
 */
abstract public function getLock($name);


/**
 * True if named lock exists.
 * @param string $name
 * @return int 0|1
 */
abstract public function hasLock($name);


/**
 * Release lock $name.
 * @param string $name
 */
abstract public function releaseLock($name);


/**
 * Return true if result set exists.
 * 
 * @return bool
 */
abstract public function hasResultSet();


/**
 * Return database name vector.
 * 
 * @param boolean $reload_cache
 * @return array
 */
abstract public function getDatabaseList($reload_cache = false);


/**
 * Return table name vector.
 * 
 * @param boolean $reload_cache
 * @return array
 */
abstract public function getTableList($reload_cache = false);


/**
 * Return last error message.
 *
 * @return array [custom_error, native_error, native_error_code ]
 */
abstract public function getError();


/**
 * Return number of affected rows of last execute query.
 * 
 * @return int
 */
abstract public function getAffectedRows();


/**
 * Return next id for table. Create table_seq as sequence table by default.
 * Name use_table with '@' prefix (e.g. @table_id) to use a single table for all sequences.
 * The sequence table use_table will be auto-created.
 *  
 * @throws
 * @param string $table
 * @param string $use_table = '' = $table.'_seq'
 * @return int
 */
public function nextId($table, $use_table = '') {

	if (empty($table)) {
		throw new Exception('empty table name');
	}

	$where = '';

	if (mb_substr($use_table, 0, 1) === '@') {
		$where = " WHERE name='".self::escape($table)."'";
		$use_table = mb_substr($use_table, 1);
		if (mb_strlen($use_table) > 50) {
			throw new Exception('table name too lang', "table=$table (max 50 char)");
		}
	}

	if (empty($use_table)) {
		$use_table = $table.'_seq';
	}

	$table_seq = self::escape_name($use_table);

	try {
		$this->execute("UPDATE $table_seq SET id = LAST_INSERT_ID(id + 1)".$where);
		$id = $this->getInsertId();
	}
	catch (\Exception $e) {
		$last_error = $this->getError();

		if ($last_error && $last_error[0] == 'no_such_table') {
			// create missing sequence table and entry with value = 0 ...
			if (empty($where)) {
				// id = unsigned int not null primary key default 0
				$this->createTable([ '@table' => $use_table, 'id' => 'int::0:35' ], true);
				$this->execute("INSERT INTO $table_seq (id) VALUES (0)");
			}
			else {
				// name = varchar(50) not null primary key, id = unsigned int not null default 0
				$this->createTable([ '@table' => $use_table, 'name' => 'varchar:50::3', 'id' => 'int::0:40' ], true);
				$this->execute("INSERT INTO $table_seq (name, id) VALUES ('".self::escape($table)."', 0)");
			}

			$this->execute("UPDATE $table_seq SET id = LAST_INSERT_ID(id + 1)".$where);
			$id = $this->getInsertId();
		}
		else if ($where && $this->getAffectedRows() === 0) {
			// create missing sequence table entry with value = 0 ...
			$this->execute("INSERT INTO $table_seq (name, id) VALUES ('".self::escape($table)."', 0)");
			$this->execute("UPDATE $table_seq SET id = LAST_INSERT_ID(id + 1)".$where);
			$id = $this->getInsertId();
		}
		else {
			throw $e;
		}
	}

	if ($id < 1) {
		throw new Exception('invalid id', "id=$id table=$table use_table=$use_table");
	}

	return $id;
}


/**
 * Return unique id for string rid. Auto-create and use table.
 * 
 * @throws
 * @param string $rid
 * @param string $use_table = rid_alias
 * @return int
 */
public function nextIdAlias($rid, $use_table = 'rid_alias') {
	
	if (empty($rid)) {
		throw new Exception('empty rid');
	}

	if (empty($use_table)) {
		throw new Exception('empty table name');
	}

	$seq = 'nextIdAliasSeq';

	if ($rid === $seq) {
		throw new Exception('invalid rid', 'rid='.$seq.' is forbidden');
	}

	$tname = self::escape_name($use_table);
	$rid = self::escape($rid);
	$id = 0;

	try {
		$id = $this->selectOne("SELECT id FROM $tname WHERE name='$rid'", 'id');
	}
	catch (\Exception $e) {
		$last_error = $this->getError();

		if (($last_error && $last_error[0] == 'no_such_table') || ($e->getMessage() == 'no result')) {
			$id = $this->nextId($seq, '@'.$use_table);
			$this->execute("INSERT INTO $tname (name, id) VALUES ('$rid', '$id')");
		}
		else {
			throw $e;
		}
	}

	if ($id < 1) {
		throw new Exception('invalid id', "id=$id rid=$rid use_table=$tname");
	}

	return $id;
}


/**
 * True if database exists.
 * 
 * @param string $name
 * @return boolean
 */
public function hasDatabase($name) {
	return in_array($name, $this->getDatabaseList());
}


/**
 * True if table exists.
 * 
 * @throws if empty table name
 * @param string $name
 * @return boolean
 */
public function hasTable($name) {
	if (empty($name)) {
		throw new Exception('empty table name');
	}

	return in_array($name, $this->getTableList());
}


/**
 * Return auto_increment column value if last 
 * query was insert and table has auto_increment column.
 *
 * @throws
 * @return int 
 */
abstract public function getInsertId();


/**
 * Create database and account (drop if exists).
 *
 * @param string $dsn
 * @param string $opt
 */
abstract public function createDatabase($dsn = '', $opt = 'utf8');


/**
 * Drop database and account (if exists).
 *
 * @param string $dsn
 */
abstract public function dropDatabase($dsn = '');


/**
 * Export database dump into file. Opt parameter:
 *
 * - tables: table1, table2, ...
 *
 * @param string $file
 * @param array[string]mixed $opt
 */
abstract public function saveDump($file, $opt = null);


/**
 * Import database dump.
 *
 * @param string $file
 */
abstract public function loadDump($file);


/**
 * Return create table query. Parameter examples:
 * 
 * - @table|language|multilang|id|status|timestamp: see parseCreateTableConf
 * - colname: TYPE:SIZE:DEFAULT:EXTRA, e.g. 
 *			"colname => int:11:1:33" = "colname int(11) UNSIGNED NOT NULL DEFAULT 1"
 * 			"colname => varchar:30:admin:9" = "colname varchar(30) NOT NULL DEFAULT 'admin', KEY (colname(20))"
 *			"colname => varchar:50::5" = "colname varchar(50) NOT NULL, UNIQUE (colname(20))"
 *			"colname => enum:'a','b'::1" = "colname enum('a', 'b') NOT NULL"
 *			"colA:colB" => 4" = "UNIQUE ('colA', 'colB')"
 *			"colA:colB:colC" => 208" = "FOREIGN KEY (colA) REFERENCES colB(colC) ON DELETE CASCADE ON UPDATE CASCADE"
 *			EXTRA example: NOT_NULL|INDEX, NOT_NULL|UNIQUE, INDEX, ...
 *
 * @throws
 * @see parseCreateTableConf
 * @param array[string]string $conf
 * @return string
 */
public static function createTableQuery($conf) {

	$conf = self::parseCreateTableConf($conf);

	$tname = $conf['@table'];
	unset($conf['@table']);

	$cols = [];
	$keys = [];

	foreach ($conf as $name => $value) {
		if (mb_substr($value, 0, 1) === '@') {
			throw new Exception('invalid column description', "$name=$value");
		}

		$opt = explode(':', $value);

		if (count($opt) === 4) {
			// e.g. "colname => varchar:30:admin:9" = "colname varchar(30) NOT NULL DEFAULT 'admin', INDEX (colname(20))"
			$sql = $name.' '.$opt[0];
			$index = $name;

			if (!empty($opt[1])) {
				if (($pos = mb_strpos($opt[1], '|')) !== false) {
					$sql .= '('.mb_substr($opt[1], $pos).')';
					$index = $name.'('.mb_substr($opt[1], $pos + 1).')';
				}
				else {
					$sql .= '('.$opt[1].')';
				}
			}

			$o = empty($opt[3]) ? 0 : intval($opt[3]);

			if ($o & self::UNSIGNED) {
				$sql .= ' UNSIGNED';
			}

			if ($o & self::NOT_NULL) {
				$sql .= ' NOT NULL';
			}

			if ($o & self::AUTO_INCREMENT) {
				$sql .= ' AUTO_INCREMENT';
			}

			if (!empty($opt[2])) {
				if (is_numeric($opt[2]) || mb_strpos($opt[2], '()') !== false) {
					$sql .= " DEFAULT ".$opt[2];
				}
				else {
					$sql .= " DEFAULT '".self::escape($opt[2])."'";
				}
			}

			array_push($cols, $sql);

			if ($o & self::PRIMARY) {
				array_push($keys, 'PRIMARY KEY ('.$index.')');
			}

			if ($o & self::UNIQUE) {
				array_push($keys, 'UNIQUE ('.$index.')');
			}

			if ($o & self::INDEX) {
				array_push($keys, 'INDEX ('.$index.')');
			}
		}
		else if (count($opt) === 1) {
			if ($opt[0] & self::FOREIGN) {
				// e.g. "colA:colB:colC" => 208" = "FOREIGN KEY (colA) REFERENCES colB(colC) ON DELETE CASCADE ON UPDATE CASCADE"
				list ($ca, $tb, $cb) = explode(':', $name);
				$sql = 'FOREIGN KEY ('.$ca.') REFERENCES '.$tb.'('.$cb.')';

				if ($opt[0] & self::DELETE_CASCADE) {
					$sql .= 'ON DELETE CASCADE';
				}

				if ($opt[0] & self::UPDATE_CASCADE) {
					$sql .= 'ON UPDATE CASCADE';
				}

				array_push($keys, $sql);
			}
			else if (count($opt) === 1 && $opt[0] === 'unique') {
				array_push($keys, 'UNIQUE ('.join(',', explode(':', $name)).')');
			}
		}
	}

	$query = "CREATE TABLE $tname (\n".join(",\n", $cols);

	if (count($keys) > 0) {
		$query .= ",\n".join(",\n", $keys);
  }

	$query .= "\n)";

	return $query;
}


/**
 * Return true if table was created. Create only if table does not exists or drop_existing = true.
 *
 * @see createTableQuery
 * @param array[string]string $conf
 * @param bool $drop_existing
 * @return bool
 */
public function createTable($conf, $drop_existing = false) {

	if (empty($conf['@table'])) {
    throw new Exception('missing tablename', 'empty @table');
  }
 
	$tname = self::escape_name($conf['@table']);

	if ($this->hasTable($tname)) {
		if ($drop_existing) {
			$this->dropTable($tname);
		}
		else {
			// ToDo: throw exception if $conf has changed
			return false;
		}
	}

	$query = self::createTableQuery($conf);
	$this->execute($query);
	return true;
}


/**
 * Return map with resolved shortcuts (@...). Only "@table" is kept. Example:
 *
 * "@table": table name, required, replace with escaped tablename 
 * "@language": e.g. de, en, ...
 * "@multilang": e.g. name, desc = name_de, name_en, desc_de, desc_en
 * "@id": 1=[id, int:::291 ], 2=[id, int:::35], 3=[id, varchar:30::3]
 * "@status": 1=[status, tinyint:::9]
 * "@timestamp": 1=[since, datetime::NOW():1], 2=[lchange, datetime::NOW():1], 
 *   3=[since, datetime::NOW():1, lchange, datetime::NOW():1]
 *
 * @throws
 * @see createTable
 * @param array[string]string $conf
 * @return array[string]string
 */
public static function parseCreateTableConf($conf) {

	if (empty($conf['@table'])) {
    throw new Exception('missing tablename', 'empty @table');
  }
 
	$conf['@table'] = self::escape_name($conf['@table']);

  $shortcut = [
    '@id' => [
      '1' => [ 'id', 'int:::291' ],
      '2' => [ 'id', 'int:::35' ],
      '3' => [ 'id', 'varchar:30::3' ]],
    '@status' => [
			'1' => [ 'status', 'tinyint:::9' ]],
    '@timestamp' => [ 
      '1' => [ 'since', 'datetime::NOW():1' ],
      '2' => [ 'lchange', 'datetime::NOW():1' ],
      '3' => [ 'since', 'datetime::NOW():1', 'lchange', 'datetime::NOW():1' ]]
  ];

	// resolve multilanguage
	if (!empty($conf['@language']) && !empty($conf['@multilang'])) {
		$lang_suffix = \rkphplib\lib\split_str(',', $conf['@language']);
		$lang_cols = \rkphplib\lib\split_str(',', $conf['@multilang']);
		unset($conf['@language']);
		unset($conf['@multilang']);

		foreach ($lang_cols as $col) {
			if (empty($conf[$col])) {
				throw new Exception('missing column definition', $col);
			}

			foreach ($lang_suffix as $suffix) {
				$conf[$col.'_'.$suffix] = $conf[$col];
			}

			unset($conf[$col]);
		}
	}

	// resolve @... shortcuts
	foreach ($conf as $key => $value) {
		if ($key === '@table' || mb_substr($key, 0, 1) !== '@') {
			continue;
		}

		if (!isset($shortcut[$key]) || !isset($shortcut[$key][$value])) {
			throw new Exception('invalid createTable shortcut', "$key=$value");
		}
		
		$add_cols = $shortcut[$key][$value];

		for ($i = 0; $i < count($add_cols); $i = $i + 2) {
			$col = $add_cols[$i];
			$conf[$col] = $add_cols[$i + 1];
		}

		unset($conf[$key]);
	}

	return $conf;
}


/**
 * Drop table (if exists).
 *
 * @param string $table
 */
abstract public function dropTable($table);


/**
 * Apply database specific escape function (fallback is self::escape).
 *
 * @see self::escape
 * @param string $value
 * @return string
 */
abstract public function esc($value);


/**
 * Execute query (string or prepared statement).
 *
 * @param string|array $query
 * @param bool $use_result
 */
abstract public function execute($query, $use_result = false);


/**
 * Adjust result pointer to first row for getNextRow().
 *
 * @param int $offset 0 ... #rows-1
 */
abstract public function setFirstRow($offset);

/**
 * Return next row (or NULL).
 * 
 * @throws if no resultset
 * @return array[string]string
 */
abstract public function getNextRow();


/**
 * Free result of execute(QUERY, true).
 *
 */
abstract public function freeResult();


/**
 * Return number of rows in resultset.
 * 
 * @throws if no resultset
 * @return int
 */
abstract public function getRowNumber();


/**
 * Return column values.
 *
 * @param string|array $query
 * @param string $colname
 * @return array
 */
abstract public function selectColumn($query, $colname = 'col');


/**
 * Return table description. 
 *
 * Result hash keys are column names, values are arrays with column info
 * (e.g. mysql: { type: 'double', is_null: true|false, key: '', default: '', extra: '' }).
 *
 * @param string $table
 * @return array[string]string
 */
abstract public function getTableDesc($table);


/**
 * Return table (table, colum) with foreign key references to $table.$column.
 * Result is { table1: [ col1, ... ], ... }. If column = '*' return
 * { table.col: ftable.fcol, ... }.
 *
 * @param string $table
 * @param string $column
 * @return array 
 */
abstract public function getReferences($table, $column = 'id');


/**
 * Return hash values (key, value columns). Use
 * "id AS name, val AS value" in setQuery to make key_col and value_col match.
 *
 * @param string|array $query
 * @param string $key_col
 * @param string $value_col
 * @param bool $ignore_double
 * @return array[string]string
 */
abstract public function selectHash($query, $key_col = 'name', $value_col = 'value', $ignore_double = false);


/**
 * Return query result row $rnum.
 *
 * @param string|array $query
 * @param int $rnum (default = 0)
 * @return array[string]string
 */
abstract public function selectRow($query, $rnum = 0);


/**
 * Return query result table. If res_count > 0 and result is empty
 * throw "no result" error message. 
 *
 * If $res_count > 0 throw error if column count doesn't match.
 *
 * @param string|array $query
 * @param int $res_count (default = 0)
 * @return array
 */
abstract public function select($query, $res_count = 0);


/**
 * Return table data checksum.
 *
 * @param string $table
 * @param bool $native
 * @return string
 */
abstract public function getTableChecksum($table, $native = false);


/**
 * Return table status. Result keys (there can be more keys):
 * 
 *  - rows: number of rows
 *  - auto_increment: name of auto increment column
 *  - create_time: sql-timestamp
 * 
 * @param string $table 
 * @throws
 * @return array[string]string
 */
abstract public function getTableStatus($table);


/**
 * Set offset for following select* function.
 *
 * @param int $offset
 */
public function seek($offset) {
	$this->_seek = $offset;
}


/**
 * Return query hash (single row). Throw exception if result has more than one row. 
 * Use column alias "split_cs_list" to split comma separated value (if query was 
 * "SELECT GROUP_CONCAT(name) AS split_cs_list ...").
 * 
 * @throws
 * @param string|array $query
 * @param string $col
 * @return array|string
 */
public function selectOne($query, $col = '') {
	$dbres = $this->select($query, 1);

	if (empty($col)) {
		if (count($dbres[0]) === 1 && !empty($dbres[0]['split_cs_list'])) {
			if (mb_strlen($dbres[0]['split_cs_list']) === 1024 && mb_stripos($query, 'group_concat') > 0) {
				throw new Exception('increase group_concat_max_len');
			}

			$res = \rkphplib\lib\split_str(',', $dbres[0]['split_cs_list']);
		}
		else {
			$res = $dbres[0];
		}
	}
	else {
		$res = $dbres[0][$col];
	}

	return $res;
}


/**
 * Execute multiple queries concatenated by semicolon.
 * Does not work in prepared statement mode (this.use_prepared = true).
 * Return vector of result hashes. If vector has only one result hash
 * return result hash.
 *
 * @param string $query
 * @throws
 * @return array
 */
abstract public function multiQuery($query);


/**
 * Shortcut for select[One|Column|Hash|Row](getQuery(name, r), n).
 * If n == -1 return false if there is no result otherwise if there is
 * one result return map and throw exception if there is more than one result.
 * Name Variants:
 *
 *  - name = $this->select($this->getQuery(name, $replace), intval($opt))
 *  - name:exec = $this->execute($this->getQuery(name, $replace), false)
 *  - name:one = $this->selectOne($this->getQuery(name, $replace), $opt)
 *  - name:column =  $this->selectColumn($this->getQuery(name, $replace), $opt) ($opt == '' == 'col')
 *  - name:hash = $this->selectHash($this->getQuery($name, $replace), 
 *      $opt[0], $opt[1], false) ($opt == '' == [name, value ])
 *  - name:row = $this->selectRow($this->getQuery($name, $replace), intval($opt)) 
 *
 * @throws
 * @param string $name query key
 * @param array[string]string $replace
 * @param int|string $opt
 * @return mixed
 */
public function query($name, $replace = null, $opt = '') {

	if (($pos = mb_strpos($name, ':')) > 0) {
		// selectDo ...
		$do = mb_substr($name, $pos + 1);
		$name = mb_substr($name, 0, $pos);

		if ($do === 'exec') {
			return $this->execute($this->getQuery($name, $replace), false);
		}
		else if ($do === 'one') {
			return $this->selectOne($this->getQuery($name, $replace), $opt);
		}
		else if ($do === 'column') {
			if ($opt === '') {
				$opt = 'col';
			}

			return $this->selectColumn($this->getQuery($name, $replace), $opt);
		}
		else if ($do === 'hash') {
			if ($opt === '') {
				$opt = [ 'name', 'value' ];
			}
			else if (!is_array($opt) || count($opt) !== 2) {
				throw new Exception('invalid option', "name=$name opt: ".print_r($opt, true));
			} 

			return $this->selectHash($this->getQuery($name, $replace), $opt[0], $opt[1], false);
		}
		else if ($do === 'row') {
			return $this->selectRow($this->getQuery($name, $replace), intval($opt));
		}
		else {
			throw new Exception('invalid option', "name=$name opt: ".print_r($opt, true));
		}
	}

	// select ...
	$opt = intval($opt);

	if ($opt > -1) {
		return $this->select($this->getQuery($name, $replace), $opt);
	}

	// opt === -1
	$dbres = $this->select($this->getQuery($name, $replace), 0);
	if (count($dbres) > 1) {
		throw new Exception('more than one result', $this->getQuery($name, $replace));
	}

	return (count($dbres) === 1) ? $dbres[0] : false;
}


/**
 * Escape ' with ''. 
 * 
 * Append \ to trailing uneven number of \. 
 * 
 * @param string $txt
 * @return string
 */
public static function escape($txt) {

	if (mb_substr($txt, -1) === '\\') {
		// trailing [\'] is a problem because \ is mysql escape char
		$l = mb_strlen($txt) * -1;
		$n = -1;

		while (mb_substr($txt, $n, 1) === '\\' && $n > $l) {
			$n--;
		}

		if ($n % 2 === 0) {
			$txt .= '\\';
		}
	}

	$res = str_replace('´', "'", $txt);
	$res = str_replace("'", "''", $res);

	return $res;
}


/**
 * Return comma separted list of columns. Example:
 * self::columnList(['a', 'b', 'c'], 'l') = 'l.a AS l_a, l.b AS l_b, l.c AS l_c'. 
 * 
 * @param array $cols
 * @param string $prefix
 * @return string
 */
public static function columnList($cols, $prefix = '') {
	$cnames = [];

	if (!is_array($cols)) {
		throw new Exception('invalid column list', "prefix=$prefix cols: ".print_r($cols, true));
	} 

	if (empty($prefix)) {
		foreach ($cols as $name) {
			array_push($cnames, self::escape_name($name));
		}
	}
	else {
		foreach ($cols as $name) {
			array_push($cnames, self::escape_name($prefix.'.'.$name).' AS '.self::escape_name($prefix.'_'.$name));
		}
	}

	return join(', ', $cnames);
}


/**
 * Return insert|update query string. If type=update key '@where' = 'WHERE ...' is required in $kv.
 *
 * @param string $table
 * @param string $type insert|update
 * @param array[string]string $kv
 * @return string
 */
public function buildQuery($table, $type, $kv = []) {

	$p = $this->getTableDesc($table);
	$key_list = [];
	$val_list = [];

	foreach ($p as $col => $cinfo) {
		array_push($key_list, self::escape_name($col));

		if (is_null($kv[$col]) || (!empty($kv[$col]) && $kv[$col] === 'NULL')) {
			$val = 'NULL';
		}
		else if (isset($kv[$col])) {
			$val = "'".self::escape($kv[$col])."'";
		}
		else if ($p['is_null'] || is_null($p['default']) || $p['default'] === 'NULL') {
			$val = 'NULL';
		}
		else if ($p['default'] !== '') {
			$val = "'".self::escape($p['default'])."'";
		}

		array_push($val_list, $val);
	}

	if ($type === 'insert') {
		$res = 'INSERT INTO '.self::escape_name($table).' ('.join(', ', $key_list).') VALUES ('.join(', ', $val_list).')';
	}
	else if ($type == 'update') {
		$res = 'UPDATE '.self::escape_name($table).' SET ';
		for ($i = 0; $i < count($key_list); $i++) {
			$res .= $key_list[$i].'='.$val_list[$i];
			if ($i < count($key_list) - 1) {
				$res .= ', ';
			}
		}

		if (empty($kv['@where']) || mb_substr($kv['@where'], 0, 6) !== 'WHERE ') {
			throw new Exception('missing @where');
		}

		$res .= ' '.$kv['@where'];
	}
	else {
		throw new Exception('invalid query type - use insert|update', "table=$table type=$type"); 
	}

	return $res;
}


}

