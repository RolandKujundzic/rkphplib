<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/lib/split_str.php');

use rkphplib\Exception;



/**
 * Abstract database access wrapper class.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ADatabase {

/** @const NOT_NULL = NOT NULL column */
const NOT_NULL = 1;

/** @const PRIMARY = PRIMARY KEY (column) */
const PRIMARY = 2;

/** @const UNIQUE = UNQUE (column) */
const UNIQUE = 4;

/** @const INDEX = KEY (column) */
const INDEX = 8;

/** @const FOREIGN = FOREIGN KEY (column) REFERENCES ... */
const FOREIGN = 16;

/** @const FOREIGN KEY ... ON DELETE CASCADE */
const UNSIGNED = 32;

/** @const FOREIGN KEY ... ON DELETE CASCADE */
const DELETE_CASCADE = 64;

/** @const FOREIGN KEY ... ON UPDATE CASCADE */
const UPDATE_CASCADE = 128;

/** @const AUTO_INCREMENT */
const AUTO_INCREMENT = 256;



/** @var bool $use_prepared */
public static $use_prepared = false;

/** @var string $time_zone (default = empty = use db default, Example: 'Europe/Berlin') */ 
public static $time_zone = '';

/** @var string $charset (default = empty = use db default, Example: 'utf8', 'utf8mb4') */
public static $charset = '';

/** @var string $_dsn */
protected $_dsn = null;

/** @var map $_query */
protected $_query = [];

/** @var map $_qinfo */
protected $_qinfo = [];



/**
 * Set database connect string. 
 *
 * Examples:
 * mysqli://user:password@tcp+localhost/dbname
 * sqlite://[password]@path/to/file.sqlite
 * 
 * @throws rkphplib\Exception if $dsn is empty or connection is already open and $dsn has changed
 * @param string $dsn
 */
public function setDSN($dsn) {

	if (!$dsn) {
		throw new Exception('empty database source name');
	}
  
	if (!empty($this->_dsn) && $this->_dsn != $dsn) {
		throw new Exception('close connection first');
	}

	$this->_dsn = $dsn;
}


/**
 * Return (split = self::splitDSN()) database connect string.
 *
 * @throws rkphplib\Exception if $dsn was not set
 * @param bool $split (default = false) 
 * @param string $dsn (default = '')
 * @return string|map 
 */
public function getDSN($split = false, $dsn = '') {

  if (empty($dsn)) {
    if (empty($this->_dsn)) {
      throw new Exception('call setDSN() first');
    }

    $dsn = $this->_dsn;
  }

	return $split ? self::splitDSN($dsn) : $dsn;
}


/**
 * Split data source name into hash (type, login, password, protocol, host, port, name, [file]). 
 *
 * Example:
 * type://login:password@protocol+host:port/name 
 * type://[password]@./file or type://@/path/to/file 
 *
 * @throws rkphplib\Exception if split failed
 * @string $dsn
 * @return map
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


/*
 * Set query extra information ($info), e.g.:
 * 
 * - type: select, insert, update, delete, alter, create, drop, show, ...
 * - auto_increment: id (name of auto_increment column)
 * - table: name of table
 * - master_query: INSERT INTO table (id, last_modified, login, password) values (0, null, CRC32(RAND()), CRC32(RAND())) 
 *
 * @throws 
 * @param string $qkey
 * @param map<string:any> $info
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
 * @param string $ikey (default = '')
 * @see setQueryInfo()
 * @throws 
 * @return any|map<string:any> 
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
 * @throws rkphplib\Exception if error
 * @param string $qkey
 * @param string $query
 * @param map<string:any> $info (default = [], see setQueryInfo())
 */
public function setQuery($qkey, $query, $info = []) {

	if (empty($qkey)) {
		throw new Exception('empty query key');
	}

	if (empty($query)) {
		throw new Exception('empty query', $qkey);
	}

	if (count($info) > 0) {
		$this->setQueryInfo($qkey, $info);
	}

	if (mb_strpos($query, '{:=') === false) {
		$this->_query[$qkey] = $query;
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
 * @param hash $replace
 * @return string|vector
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
 * True if query key exists.
 * 
 * @param string $qkey
 * @return boolean
 */
public function hasQuery($qkey) {
	return isset($this->_query[$qkey]) ? false : true;
}


/**
 * Apply setQuery() for all hash values where key has [query.] prefix and value is not empty (qkey = key without prefix).
 * Change/remove query prefix with conf_hash[@query_prefix].
 * Use query.escape_name@table=test name to replace {:=@table} with `test name`.
 *
 * @see ADatabase::setQuery()
 * @throws rkphplib\Exception if error
 * @param map $conf_hash
 * @param empty|vectory $require_keys (default = '')
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
		if (mb_substr($key, 0, $qplen) === 'query.' && !empty(trim($value))) {
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
			$query = str_replace('{:=@'.$rkey.'}', $rval, $query);
		}

		$this->setQuery($qkey, $query);
	}
}


/**
 * Escape table or column name with `col name`.
 *
 * If abort is true abort if name doesn't match [a-zA-Z0-9_\.]+.
 *
 * @throws rkphplib\Exception if error
 * @param string
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
 * @param vectory<string> $tables 
 */
abstract public function lock($tables);


/**
 *
 */
abstract public function unlock();


/**
 * Get named lock. Use releaseLock($name) to free.
 * @param string $name
 * @throws
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
 * @return vector
 */
abstract public function getDatabaseList($reload_cache = false);


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
 * Return table name vector.
 * 
 * @param boolean $reload_cache
 * @return vector
 */
abstract public function getTableList($reload_cache = false);


/**
 * True if table exists.
 * 
 * @param string $name
 * @return boolean
 */
public function hasTable($name) {
	return in_array($name, $this->getTableList());
}


/**
 * Return auto_increment column value if last 
 * query was insert and table has auto_increment column.
 *
 * @throw not_implemented|no_id
 * @return int 
 */
abstract public function getInsertId();


/**
 * Create database and account (drop if exists).
 *
 * @param string $dsn (default = '' = use internal)
 * @param string $opt (default = 'utf8')
 */
abstract public function createDatabase($dsn = '', $opt = 'utf8');


/**
 * Drop database and account (if exists).
 *
 * @param string $dsn (default = '' = use internal)
 */
abstract public function dropDatabase($dsn = '');


/**
 * Export database dump into file. Options:
 *
 * - tables: table1, table2, ...
 *
 * @param string $file
 * @param map $opt (default = null = full database dump)
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
 * @param map<string:string> $conf
 * @return string
 */
abstract public static function createTableQuery($conf);


/**
 * Create table (drop if exists).
 *
 * @see createTableQuery
 * @param map<string:string> $conf
 */
public function createTable($conf) {

	if (empty($conf['@table'])) {
    throw new Exception('missing tablename', 'empty @table');
  }
 
	$tname = self::escape_name($conf['@table']);

	if ($this->hasTable($tname)) {
		$this->dropTable($tname);
		$query = self::createTableQuery($conf);
		$this->db->execute($query);
	}
}


/**
 * Return map with resolved shortcuts (@...). Only "@table" is kept. Example:
 *
 * @table: table name, required, replace with escaped tablename 
 *
 * @language: e.g. de, en, ...
 * @multilang: e.g. name, desc = name_de, name_en, desc_de, desc_en
 *
 * @id: 1=[id, int:::291 ], 2=[id, int:::35], 3=[id, varchar:30::3]
 * @status: 1=[status, tinyint:::9]
 * @timestamp: 1=[since, datetime::NOW():1], 2=[lchange, datetime::NOW():1], 3=[since, datetime::NOW():1, lchange, datetime::NOW():1]
 *
 * @throws
 * @see createTable
 * @param map<string:string> $conf
 * @return map<string:string>
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
 * @param string $txt
 * @return string
 */
abstract public function esc($value);


/**
 * Execute query (string or prepared statement).
 *
 * @param string|vector $query
 * @param bool $use_result (default = false)
 */
abstract public function execute($query, $use_result = false);


/**
 * Return next row (or NULL).
 * 
 * @throws if no resultset
 * @return map<string:string>|null
 */
abstract public function getNextRow();


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
 * @param string|vector $query
 * @param string $colname (default = col)
 * @return vector
 */
abstract public function selectColumn($query, $colname = 'col');


/**
 * Return table description. 
 *
 * Result hash keys are column names, values are arrays with column info
 * (e.g. mysql: { type: 'double', is_null: true|false, key: '', default: '', extra: '' }).
 *
 * @param string $table
 * @return map
 */
abstract public function getTableDesc($table);


/**
 * Return hash values (key, value columns).
 *
 * @param string|vector $query
 * @param string $key_col (default = name)
 * @param string $value_col (defualt = value)
 * @param bool $ignore_double (default = false)
 * @return hash
 */
abstract public function selectHash($query, $key_col = 'name', $value_col = 'value', $ignore_double = false);


/**
 * Return query result row $rnum.
 *
 * @param string|vector $query
 * @param int $rnum (default = 0)
 * @return hash
 */
abstract public function selectRow($query, $rnum = 0);


/**
 * Return query result table. 
 *
 * If $res_count > 0 throw error if column count doesn't match.
 *
 * @param string|vector $query
 * @param int $res_count (default = 0)
 * @return table
 */
abstract public function select($query, $res_count = 0);


/**
 * Return table data checksum.
 *
 * @param string $table
 * @param bool $native (default = false)
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
 * @return map<string:string>
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
 * Return query hash (single row).
 * 
 * @throw rkphplib\Exception if rownum != 1 
 * @param string|vector $query
 * @param string $col (default = '')
 * @return array|string 
 */
public function selectOne($query, $col = '') {
	$dbres = $this->select($query, 1);
	return empty($col) ? $dbres[0] : $dbres[0][$col];
}


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
 *  - name:hash = $this->selectHash($this->getQuery($name, $replace), $opt[0], $opt[1], false) ($opt == '' == [name, value ])
 *  - name:row = $this->selectRow($this->getQuery($name, $replace), intval($opt)) 
 *
 * @throws
 * @param string $name query key
 * @param map<string:string> $replace (default = null)
 * @param int|string $opt (default = '')
 * @return false|string|vector<string>|map<string:string>
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
 * @param vector $cols
 * @param string $prefix (default = '')
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
 * @param map<string:string> $kv
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

