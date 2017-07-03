<?php

namespace rkphplib;

require_once(__DIR__.'/ADatabase.class.php');



/**
 * SQLite implementation of ADatabase.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class SQLiteDatabase extends ADatabase {

private $_db = null;
private $_seek = -1;



/**
 * Connect to sqlite3 database.
 */
private function _connect() {

	if (is_object($this->_db)) {
		return;
	}

	if (empty($this->_dsn)) {
		throw new Exception('call setDSN first');
	}

	$dsn = self::splitDSN($this->_dsn);

	if ($dsn['type'] != 'sqlite') {
		throw new Exception('invalid dsn type: '.$dsn['type']);
	}

	try {
		$this->_db = new \SQLite3($dsn['file']);
	}
	catch (\Exception $e) {
		throw new Exception('Failed to connect to SQLite3', $e->getMessage());
	}
}


/**
 * Close connection.
 */
public function close() {

	if (!$this->_db) {
		throw new Exception('no open database connection');
	}

	$this->_db->close();
	$this->_db = null;
}


/**
 * Execute query.
 *
 * @param string $query
 * @param bool $use_result (default = false)
 */
public function execute($query, $use_result = false) {

	if (is_array($query)) {
		$stmt = $this->_exec_stmt($query);
		$stmt->close();
	}
	else {
		$this->_connect();

		if (!@$this->_db->query($query)) {
			throw new Exception('failed to execute sql query', $query."\n".$this->_db->lastErrorMsg());
		}
	}
}


/**
 * Return table data checksum.
 *
 * @param string $table
 * @return string
 */
public function getTableChecksum($table, $native = false) {
	throw new Exception('ToDo');
}


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
public function getTableStatus($table) {
	throw new Exception('ToDo');
}


/**
 *
 */
public function setFirstRow($offset) {
	throw new Exception('ToDo');
}


/**
 * Return next row (or NULL).
 * 
 * @throws if no resultset
 * @return map<string:string>|null
 */
public function getNextRow() {
	throw new Exception('ToDo');
}


/**
 * Return number of rows in resultset.
 * 
 * @throws if no resultset
 * @return int
 */
public function getRowNumber() {
	throw new Exception('ToDo');
}


/**
 * Execute $query and return result column $col vector.
 * 
 * @param string $query
 * @param string $colname
 * @return vector
 */
public function selectColumn($query, $colname = 'col') {
	
	if (is_array($query)) {
		$res = $this->_fetch_stmt($this->_exec_stmt($query), array($colname)); 
	}
	else {
		$res = $this->_fetch($query, array($colname));
	}

	return $res;
}


/**
 * Execute $query and return result map $key_cols = $value_col.
 * 
 * @param string $query
 * @param string $key_col
 * @param string $value_col
 * @param bool $ignore_double
 */
public function selectHash($query, $key_col = 'name', $value_col = 'value', $ignore_double = false) {

	throw new Exception('ToDo ... ignore_double');

	if (is_array($query)) {
		$res = $this->_fetch_stmt($this->_exec_stmt($query), array($key_col, $value_col)); 
	}
	else {
		$res = $this->_fetch($query, array($key_col, $value_col));
	}

	return $res;
}


/**
 * Execute table and return result. If res_count > 0 and result is empty
 * throw "no result" error message.
 *
 * @throws
 * @param string $query
 * @param int $res_count
 * @return table
 */
public function select($query, $res_count = 0) {

	if (is_array($query)) {
		$res = $this->_fetch_stmt($this->_exec_stmt($query), null, $res_count); 
	}
	else {
		$res = $this->_fetch($query, null, $res_count);
	}

	return $res;
}


/**
 * Execute select query and fetch result. 
 * 
 * Return result table if $rbind = null.
 * Return hash if count($rbind) = 2. Return array if count($rbind) = 1.
 * Return hash if $rcount < 0 (result[-1 * $rcount + 1]).
 * 
 * @param string $query
 * @param vector $rbind (default = null)
 * @param int $rcount (default = 0)
 * @return table|hash|array
 */
private function _fetch($query, $rbind = null, $rcount = 0) {

	if (($dbres = $this->_db->query($query)) === false) {
		throw new Exception('failed to execute sql query', $query."\n(".$this->_db->errno.') '.$this->_db->error);
	}

	$rnum = $dbres->num_rows;
	$res = array();

	if ($rcount > 0 && $rnum != $rcount) {
		if ($rnum == 0) {
			throw new Exception('no result', $rcount.' rows expected');
		}
		else {
			throw new Exception('unexpected number of rows', $rnum.' != '.$rcount);
		}
	}

	if ($rcount < 0 && -1 * $rcount > $rnum) {
		throw new Exception('number of rows too low', $rnum.' < '.(-1 * $rcount));
	}

	if ($rnum > 50000) {
		throw new Exception('number of rows too high', $rnum);
	}

	if ($rnum == 0) {
		return $res;
	}

	$end = $rnum;

  if ($rcount < 0) {
    $dbres->data_seek(-1 * $rcount + 1);
    $end = 1;
  }
  else if ($this->_seek > -1) {
    $dbres->data_seek($this->_seek);
    $end -= $this->_seek + 1;
    $this->_seek = -1;
  }

	$bl = is_null($rbind) ? 0 : count($rbind);

	for ($i = 0; $i < $end; $i++) {
		$dbrow = $dbres->fetch_assoc();

		if ($bl == 1) {
			array_push($res, $dbrow[$rbind[0]]);
		}
		else if ($bl == 2) {
			$res[$dbrow[$rbind[0]]] = $dbrow[$rbind[1]];
		}
		else {
			array_push($res, $dbrow);
		}
	}

	$dbres->close();

	return $res;
}


/**
 *
 */
public function selectRow($query, $rnum = 0) {

	$rnum = -1 * $rnum - 1;

	if (is_array($query)) {
		$res = $this->_fetch_stmt($this->_exec_stmt($query), null, $rnum); 
	}
	else {
		$res = $this->_fetch($query, null, $rnum);
	}

	return $res;
}


/**
 * Execute prepared statement. Return statement.
 * @param array $q
 * @return object 
 */
private function _exec_stmt($q) {

	$this->_connect();

	$ql = count($q);
	$replace = $q[$ql - 1];
	$query = $q[$ql - 2];

	if (!($stmt = $this->_db->prepare($query))) {
		throw new Exception("Prepare query failed", $query."\n(".$this->_db->errno.') '.$this->_db->error."\n$query");
	}

	$bind_arr = array('');

	for ($i = 0; $i < count($q) - 2; $i++) {
		$key = $q[$i];

		if (!isset($replace[$key])) {
			throw new Exception("query replace key $key missing", "$query: ".print_r($replace, true));
		}

		$bind_arr[0] .= 's';
		$bind_arr[$i + 1] =& $replace[$key]; 
		unset($replace[$key]);
	}

	$ref = new \ReflectionClass('mysqli_stmt');
	$method = $ref->getMethod('bind_param');

	if ($method->invokeArgs($stmt, $bind_arr) === false) {
		throw new Exception('query bind parameter failed', "$query\n(".$stmt->errno.') '.$stmt->error."\n".print_r($bind_arr, true));
	}

	if (count($replace) > 0) {
		throw new Exception('Too many replace parameter', print_r($replace, true)); 
	}

	// prepared statement ...
	if (!$stmt->execute()) {
		throw new Exception('failed to execute sql statement');
	}

	return $stmt;
}


/**
 * Fetch prepared statement result. 
 * 
 * Return result table if $rbind = null.
 * Return hash if count($rbind) = 2. Return array if count($rbind) = 1.
 * Return hash if $rcount < 0 (result[-1 * $rcount + 1]).
 *
 * @param object $stmt
 * @param array $rbind (default = null)
 * @param int $rcount (default = 0)
 * @return table|map|vector
 */
private function _fetch_stmt($stmt, $rbind = null, $rcount = 0) {

	if (!$stmt->store_result()) {
		throw new Exception('failed to store result');
	}

	$rnum = $stmt->num_rows;
	$res = array();

	if ($rcount > 0 && $rnum != $rcount) {
		if ($rnum == 0) {
			throw new Exception('no result', $rcount.' rows expected');
		}
		else {
			throw new Exception('unexpected number of rows', $rnum.' != '.$rcount);
		}
	}

	if ($rcount < 0 && -1 * $rcount > $rnum) {
		throw new Exception('number of rows too low', $rnum.' < '.(-1 * $rcount));
	}

	if ($rnum > 50000) {
		throw new Exception('number of rows too high', $rnum);
	}

	if ($rnum == 0) {
		return $res;
	}

	if (is_null($rbind)) {
		$md = $stmt->result_metadata();
		$rbind = array();
		$db_data = array();
		$db_refs = array(); // db_refs is necessary because call_use_func_array needs array with references

		while (($field = $md->fetch_field())) {
			$db_refs[] =& $db_data[$field->name]; // bind db_refs[n] to db_data[key]
			array_push($rbind, $field->name);
		}
	}

	$end = $rnum;

	if ($rcount < 0) {
		$stmt->data_seek(-1 * $rcount + 1);
		$end = 1;
	}
	else if ($this->_seek > -1) {
		$stmt->data_seek($this->_seek);
		$end -= $this->_seek + 1;
		$this->_seek = -1;
	}

	if (call_user_func_array(array($stmt, 'bind_result'), $db_refs) === false) {
		throw new Exception('failed to bind result');
	}

	$bl = count($rbind);

	for ($i = 0; $i < $end; $i++) {
		$stmt->fetch();

    if ($bl == 1) {
      array_push($res, $db_data[$rbind[0]]);
    }
    else if ($bl == 2) {
      $res[$db_data[$rbind[0]]] = $db_data[$rbind[1]];
    }
    else if ($bl > 2) {
			$row = array();

			foreach ($rbind as $key) {
				$row[$key] = $db_data[$key];
			}

			array_push($res, $row);
		}
  }

	$stmt->close();

	return $res;
}


/**
 * Escape value $txt.
 * 
 * @param string $txt
 * @return string
 */
public function esc($txt) {

	if (!$this->_db) {
		return self::escape($txt);
	}

	return $this->_db->real_escape_string($txt);	
}


/**
 * Return table description.
 *
 * @param string $table
 * @return map 
 */
public function getTableDesc($table) {

	if (isset($this->_cache['DESC:'.$table])) {
    return $this->_cache['DESC:'.$table];
  }

  $db_res = $this->select('DESC '.self::escape_name($table));
  $res = array();

  foreach ($db_res as $info) {
    $colname = $info['Field'];
    unset($info['Field']);
    $res[$colname] = $info;
  }

  $this->_cache['DESC:'.$table] = $res;

  return $res;
}

/**
 * Return vector with database names.
 *
 * @param boolean $reload_cache
 * @return vector
 */
public function getDatabaseList($reload_cache = false) {
	throw new Exception('ToDo ...');	
}


/**
 * Return vector with table names.
 *
 * @param boolean $reload_cache
 * @return vector
 */
public function getTableList($reload_cache = false) {
	throw new Exception('ToDo ...');	
}


/**
 * Return last error info. Custom error values:
 *
 * - no_such_table 
 *
 * @return null|vector [custom_error, native_error, native_error_code ]
 */
public function getError() {
	throw new Exception('ToDo ...');
}


/**
 * Return number of affected rows of last execute query.
 * 
 * @return int
 */
public function getAffectedRows() {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function createDatabase($dsn = '', $opt = 'utf8') {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function dropDatabase($dsn = '') {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function saveDump($file, $opt = null) {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function loadDump($file) {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public static function createTableQuery($conf) {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function dropTable($table) {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function hasResultSet() {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function getInsertId() {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function lock($tables) {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function unlock() {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function getLock($name) {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function hasLock($name) {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function releaseLock($name) {
  throw new Exception('@ToDo ... ');
}


}

