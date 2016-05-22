<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;



/**
 * Abstract database access wrapper class.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ADatabase {

/** @var bool $use_prepared */
public static $use_prepared = false;

protected $_dsn = null;

protected $_query = array();



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
 * @param boole $split (default = false) 
 * @return string 
 */
public function getDSN($split = false) {

	if (empty($this->_dsn)) {
		throw new Exception('call setDSN() first');
	}

	return $split ? self::splitDSN($this->_dsn) : $this->_dsn;
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

	return $db;
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
 */
public function setQuery($qkey, $query) {

	if (empty($qkey)) {
		throw new Exception('empty query key');
	}

	if (empty($query)) {
		throw new Exception('empty query');
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

		if (mb_substr($m, 0, 1) == "'" && mb_substr($m, -1) == "'" && mb_substr($m, 4, 1) != '^') {
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
		else if (mb_substr($m, 3, 1) == '_') {
			$key = mb_substr($m, 3, -1);
			$map[$key] = 'keep';
		}
		else if (mb_substr($m, 3, 1) == '^') {
			$key = mb_substr($m, 4, -1);
			$map[$key] = 'escape_name';
		}
		else {
			$key = mb_substr($m, 3, -1);
			$map[$key] = 'escape';
		}
	}

	if (count($map['bind']) == 0) {
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

		if ($do == 'escape') {
			$value = $replace[$key];

			if (is_null($value) || $value == 'NULL' || $value == 'null') {
				$value = 'NULL';
			}
			else {
				$value = "'".$this->esc($value)."'";
			}

			$query = str_replace("{:=$key}", $value, $query);
		}
		else if ($do == 'escape_name') {
			$query = str_replace('{:=^'.$key.'}', self::escape_name($replace[$key]), $query);
		}
		else if ($do == 'keep') {
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
 *
 * @see ADatabse::setQuery()
 * @throws rkphplib\Exception if error
 * @param map $conf_hash
 * @param empty|array $require_keys
 */
public function setQueryHash($conf_hash, $require_keys = '') {

	if (is_array($require_keys)) {
		foreach ($require_keys as $qkey) {
			if (empty($conf_hash['query.'.$qkey]) && empty($this->_query[$qkey])) {
				throw new Exception("no such key [query.$qkey]");
			}
		}
	}

	foreach ($conf_hash as $key => $value) {
		if (mb_substr($key, 0, 6) == 'query.' && !empty(trim($value))) {
			$this->setQuery(mb_substr($key, 6), $value);
		}
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
 * Create database and account.
 *
 * @param string $dsn
 */
abstract public function createDatabase($dsn);


/**
 * Drop database and account.
 *
 * @param string $dsn
 */
abstract public function dropDatabase($dsn);


/**
 * Create table. Value of $table_conf:
 * 
 * - @id: 1 = primary key int unsigned not null auto_increment, 2 = primary key int unsigned not null, 3 = primary key varchar(30) not null
 * - @status: 1 = tinyint unsigned + index
 * - @timestamp: 1 = since, 2 = last_change, 3 = since + last_change datetime cols
 * - colname: TYPE:SIZE:NOT_NULL:DEFAULT, e.g. 
 *			"colname => int:11:1:1:0" = "colname int(11) NOT NULL DEFAULT 1"
 * 			"colname => varchar:30:1:admin:1" = "colname varchar(30) NOT NULL DEFAULT 'admin', KEY (colname(20))"
 *			"colname => varchar:50:1::2" = "colname varchar(50) NOT NULL, UNIQUE (colname(20))"
 *			"colname => enum::1:a,b" = "colname enum('a', 'b') NOT NULL"
 *			"colA:colB" => unique" = "UNIQUE KEY ('colA', 'colB')"
 *			"colA:colB:colC" => foreign:1:1" = "FOREIGN KEY (colA) REFERENCES colB(colC) ON DELETE CASCADE ON UPDATE CASCADE"
 *
 * @param hash $table_list
 * @param hash $config (default: drop_existing=false, ignore_existing = false)
 */
abstract public function createTables($table_conf, $config = [ 'drop_existing' => false, 'ignore_existing' => false ]);


/**
 * Drop tables.
 *
 * @param array $table_list
 */
abstract public function dropTables($table_list);


/**
 * Apply database specific escape function (fallback is self::escape).
 *
 * @param string $txt
 * @return string
 */
abstract public function esc($txt);


/**
 * Execute query (string or prepared statement).
 *
 * @param string|object $query
 */
abstract public function execute($query);


/**
 * Return column values.
 *
 * @param string $query
 * @param string $colname (default = col)
 * @return array
 */
abstract public function selectColumn($query, $colname = 'col');


/**
 * Return table description. 
 *
 * Result hash keys are column names, values are arrays with column info
 * (e.g. mysql: { Type: 'double', Null: 'YES', Key: '', Default: '', Extra: '' }).
 *
 * @param string $table
 * @return map
 */
abstract public function getTableDesc($table);


/**
 * Return hash values (key, value columns).
 *
 * @param string $query
 * @param string $key_col (default = name)
 * @param string $value_col (defualt = value)
 * @param bool $ignore_double (default = false)
 * @return hash
 */
abstract public function selectHash($query, $key_col = 'name', $value_col = 'value', $ignore_double = false);


/**
 * Return query result row $rnum.
 *
 * @param string $query
 * @param int $rnum (default = 0)
 * @return hash
 */
abstract public function selectRow($query, $rnum = 0);


/**
 * Return query result table. 
 *
 * If $res_count > 0 throw error if column count doesn't match.
 *
 * @param string $query
 * @param int $res_count (default = 0)
 * @return table
 */
abstract public function select($query, $res_count = 0);


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
 * @param string $query
 */
public function selectOne($query) {
	$dbres = $this->select($query, 1);
	return $dbres[0];
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

	if (mb_substr($txt, -1) == '\\') {
		// trailing [\'] is a problem because \ is mysql escape char
		$l = mb_strlen($txt) * -1;
		$n = -1;

		while (mb_substr($txt, $n, 1) == '\\' && $n > $l) {
			$n--;
		}

		if ($n % 2 == 0) {
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

}

