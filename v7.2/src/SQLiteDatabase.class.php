<?php

namespace rkphplib;

require_once __DIR__.'/ADatabase.class.php';



/**
 * SQLite implementation of ADatabase.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class SQLiteDatabase extends ADatabase {

private $_db = null;
private $_seek = -1;



/**
 *
 */
public function getId() : string {
	throw new Exception('ToDo');
}


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
 *
 */
public function execute(string $query, bool $use_result = false) : void {

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
 *
 */
public function getTableChecksum(string $table, bool $native = false) : string {
	throw new Exception('ToDo');
}


/**
 *
 */
public function getTableStatus(string $table) : array {
	throw new Exception('ToDo');
}


/**
 *
 */
public function setFirstRow(int $offset) : void {
	throw new Exception('ToDo');
}


/**
 *
 */
public function getNextRow() : ?array {
	throw new Exception('ToDo');
}


/**
 *
 */
public function freeResult() : void {
	throw new Exception('ToDo');
}


/**
 * 
 */
public function getRowNumber() : int {
	throw new Exception('ToDo');
}


/**
 *
 */
public function selectColumn($query, string $colname = 'col') : array {
	
	if (is_array($query)) {
		$res = $this->_fetch_stmt($this->_exec_stmt($query), array($colname)); 
	}
	else {
		$res = $this->_fetch($query, array($colname));
	}

	return $res;
}


/**
 *
 */
public function selectHash(string $query, string $key_col = 'name', string $value_col = 'value', bool $ignore_double = false) : array {

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
 *
 */
public function select($query, int $res_count = 0) : array {

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
public function selectRow($query, int $rnum = 0) : array {

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
 *
 */
public function esc(string $txt) : string {

	if (!$this->_db) {
		return self::escape($txt);
	}

	return $this->_db->real_escape_string($txt);	
}


/**
 *
 */
public function getTableDesc(string $table) : array {

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
 *
 */
public function getDatabaseList(bool $reload_cache = false) : array {
	throw new Exception('ToDo ...');	
}


/**
 *
 */
public function getTableList(bool $reload_cache = false) : array {
	throw new Exception('ToDo ...');	
}


/**
 *
 */
public function getReferences(string $table, string $column = 'id') : array {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function getError() : ?array {
	throw new Exception('ToDo ...');
}


/**
 * 
 */
public function getAffectedRows() : int {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function createDatabase(string $dsn = '', string $opt = 'utf8') : void {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function dropDatabase(string $dsn = '') : void {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function saveDump(array $opt) : void {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function saveTableDump(array $opt) : void {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function loadDump(string $file, int $flags) : void {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public static function createTableQuery(array $conf) : string {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function dropTable(string $table) : void {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function hasResultSet() : bool {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function getInsertId() : int {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function lock(array $tables) : void {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function unlock() : void {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function getLock(string $name) : int {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function hasLock(string $name) : bool {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function releaseLock(string $name) : int {
  throw new Exception('@ToDo ... ');
}


/**
 *
 */
public function multiQuery(string $query) : array {
  throw new Exception('@ToDo ... ');
}


}
