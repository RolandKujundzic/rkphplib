<?php

namespace rkphplib\db;

require_once __DIR__.'/ADatabase.php';


/**
 * SQLite implementation of ADatabase.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class SQLite extends ADatabase {

//
private $db = null;

//
private $seek = -1;


/**
 *
 */
public function connected() : bool {
	return !is_null($this->db);
}


/**
 *
 */
public function connect() : bool {
	if (is_object($this->db)) {
		return true;
	}

	if (empty($this->dsn)) {
		return $this->error('call setDSN first', '', 2);
	}

	$dsn = self::splitDSN($this->dsn);

	if ($dsn['type'] != 'sqlite') {
		return $this->error('invalid dsn type: '.$dsn['type'], '', 2);
	}

	try {
		$this->db = new \SQLite3($dsn['file']);
	}
	catch (\Exception $e) {
		return $this->error('Failed to connect to SQLite3', $e->getMessage(), 2);
	}

	return true;
}


/**
 * Close connection.
 */
public function close() : bool {
	if (!$this->db) {
		return $this->error('no open database connection', '', 2);
	}

	$this->db->close();
	$this->db = null;
	return true;
}


/**
 *
 */
public function execute(string $query, bool $use_result = false) : bool {
	if (is_array($query)) {
		$stmt = $this->_exec_stmt($query);
		$stmt->close();
	}
	else {
		$this->_connect();

		if (!@$this->db->query($query)) {
			return $this->error('failed to execute sql query', $query);
		}
	}

	return true;
}


/**
 *
 */
public function selectColumn($query, string $colname = 'col') : ?array {
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
public function selectHash1(string $query, string $key_col = 'name', string $value_col = 'value', bool $ignore_double = false) : ?array {
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
public function selectHash($query, string $key_col = 'name', string $value_col = 'value', bool $ignore_double = false) : ?array {

	if ($value_col == '*') {
		$this->execute($query, true);
		$res = [];

		while (($row = $this->getNextRow())) {
			if (!isset($row[$key_col])) {
				return $this->error("no such column $key_col", $query, 1);
			}

			$id = $row[$key_col];

			if (isset($res[$id])) {
				if ($ignore_double) {
					if (isset($res[$id][$key_col]) && $res[$id][$key_col] == $id) {
						$tmp = $res[$id];
						$res[$id] = [];
						array_push($res[$id], $tmp);
					}

					array_push($res[$id], $row);
				}
				else {
					return $this->error("$key_col = $id already exists", $query, 1);
				}
			}
			else {
				$res[$id] = $row;
			}
		}
  }
	else {
		if (is_array($query)) {
			$res = $this->_fetch_stmt($this->_exec_stmt($query), array($key_col, $value_col)); 
		}
		else {
			$res = $this->_fetch($query, array($key_col, $value_col));
		}

		if (!$ignore_double && isset($this->cache['FETCH:DOUBLE'])) {
			return $this->error('Hashkeys are not unique', print_r($this->cache['FETCH:DOUBLE'], true), 3);
		}
	}

	return $res;
}



/**
 *
 */
public function select($query, int $res_count = 0) : ?array {
	if (is_array($query)) {
		$res = $this->_fetch_stmt($this->_exec_stmt($query), null, $res_count); 
	}
	else {
		$res = $this->_fetch($query, null, $res_count);
	}

	return $res;
}


/**
 *
 */
public function selectRow($query, int $rnum = 0) : ?array {
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
 *
 */
public function esc(string $txt) : string {
	return self::escape($txt);
}


/**
 *
 */
public function getTableDesc(string $table) : array {
	throw new Exception('ToDo');
  return [];
}


/**
 * Add (unique|primary) index to $table.$column.
 * Return false if index already exists.
 */
public function addIndex(string $table, string $column, string $type = '') : bool {
	throw new Exception('');
	return false;
}


/**
 * Return true if index on $table.$column exists.
 */
public function hasIndex(string $table, string $column, string $type = '') : bool {
	throw new Exception('ToDo');
	return false;
}


/**
 *
 */
public function disableKeys(array $table_list = [], bool $as_string = false) : string {
	throw new Exception('ToDo');
}


/**
 *
 */
public function enableKeys(array $table_list = [], bool $as_string = false) : string {
	throw new Exception('ToDo');
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
public function getDatabaseList(bool $reload_cache = false) : ?array {
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
public function createDatabase(string $dsn = '', string $opt = 'utf8') : bool {
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
public function loadDumpShell(string $file, int $flags = self::LOAD_DUMP_IGNORE_KEYS, array $tables = []) : void {
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
public function multiQuery(string $query) : ?array {
  throw new Exception('@ToDo ... ');
}


/**
 * Throw Exception if this.abort = true (default). Otherwise return false.
 * flag: 1 = return null, 2 = don't add _db.lastErrorMsg to $internal
 */
private function error(string $msg, string $internal, int $flag = 0) : ?bool {
	if ($this->abort) {
		if (2 != $flag & 2) {
			$internal .= "\n".$this->db->lastErrorMsg();
		}

		throw new Exception($msg, $internal);
	}

	return ($flag & 1) ? null : false;
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

	if (!($stmt = $this->db->prepare($query))) {
		throw new Exception("Prepare query failed", $query."\n(".$this->db->errno.') '.$this->db->error."\n$query");
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
 * Execute select query and fetch result. 
 * 
 * Return result table if $rbind = null.
 * Return hash if count($rbind) = 2. Return array if count($rbind) = 1.
 * Return hash if $rcount < 0 (result[-1 * $rcount + 1]).
 * 
 * @param string $query
 * @param vector $rbind (default = null)
 * @param int $rcount (default = 0)
 * @return table|hash|array|null
 */
private function _fetch($query, $rbind = null, $rcount = 0) {

	if (($dbres = $this->db->query($query)) === false) {
		return $this->error('failed to execute sql query', $query, 1);
	}

	$rnum = $dbres->num_rows;
	$res = array();

	if ($rcount > 0 && $rnum != $rcount) {
		if ($rnum == 0) {
			return $this->error('no result', $rcount.' rows expected', 3);
		}
		else {
			return $this->error('unexpected number of rows', $rnum.' != '.$rcount, 3);
		}
	}

	if ($rcount < 0 && -1 * $rcount > $rnum) {
		return $this->error('number of rows too low', $rnum.' < '.(-1 * $rcount), 3);
	}

	if ($rnum > 50000) {
		return $this->error('number of rows too high', $rnum, 3);
	}

	if ($rnum == 0) {
		return $res;
	}

	$end = $rnum;

  if ($rcount < 0) {
    $dbres->data_seek(-1 * $rcount + 1);
    $end = 1;
  }
  else if ($this->seek > -1) {
    $dbres->data_seek($this->seek);
    $end -= $this->seek + 1;
    $this->seek = -1;
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
 * Fetch prepared statement result. 
 * 
 * Return result table if $rbind = null.
 * Return hash if count($rbind) = 2. Return array if count($rbind) = 1.
 * Return hash if $rcount < 0 (result[-1 * $rcount + 1]).
 *
 * @param object $stmt
 * @param array $rbind (default = null)
 * @param int $rcount (default = 0)
 * @return table|map|vector|null
 */
private function _fetch_stmt($stmt, $rbind = null, $rcount = 0) {

	if (!$stmt->store_result()) {
		return $this->error('failed to store result', '', 3);
	}

	$rnum = $stmt->num_rows;
	$res = array();

	if ($rcount > 0 && $rnum != $rcount) {
		if ($rnum == 0) {
			return $this->error('no result', $rcount.' rows expected', 3);
		}
		else {
			return $this->error('unexpected number of rows', $rnum.' != '.$rcount, 3);
		}
	}

	if ($rcount < 0 && -1 * $rcount > $rnum) {
		return $this->error('number of rows too low', $rnum.' < '.(-1 * $rcount), 3);
	}

	if ($rnum > 50000) {
		return $this->error('number of rows too high', $rnum, 3);
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
	else if ($this->seek > -1) {
		$stmt->data_seek($this->seek);
		$end -= $this->seek + 1;
		$this->seek = -1;
	}

	if (call_user_func_array(array($stmt, 'bind_result'), $db_refs) === false) {
		return $this->error('failed to bind result', '', 3);
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

}

