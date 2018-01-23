<?php

namespace rkphplib;

require_once(__DIR__.'/ADatabase.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/lib/execute.php');


/**
 * MysqlDatabase implementation of ADatabase.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class MysqlDatabase extends ADatabase {

private $_db = null;
private $_conn_ttl = 0;
private $_seek = -1;
private $_cache = array();
private $_dbres = null;



/**
 * Return true if result set exists.
 * 
 * @return bool
 */
public function hasResultSet() {
	return !is_null($this->_dbres);
}


/**
 *
 */
public function lock($tables) {
	throw new Exception('ToDo');
}


/**
 *
 */
public function unlock() {
	throw new Exception('ToDo');
}


/**
 *
 */
public function getLock($name) {
	$r = $this->selectOne("SELECT GET_LOCK('lck_$name', 10) AS r", 'r');
	return intval($r);
}


/**
 *
 */
public function hasLock($name) {
	$r = $this->selectOne("SELECT IS_FREE_LOCK('lck_$name', 10) AS r", 'r');
	return !inval($r);
}


/**
 *
 */
public function releaseLock($name) {
	$r = $this->selectOne("SELECT RELEASE_LOCK('lck_$name') AS r", 'r');
	return intval($r);
}


/**
 * Connect to mysql database.
 */
private function _connect() {

	if (is_object($this->_db)) {
		if ($this->_conn_ttl < time() && !$this->_db->ping()) {
			$this->close();
		}
		else {
			return;
		}
	}

	if (empty($this->_dsn)) {
		throw new Exception('call setDSN first');
	}

	$dsn = self::splitDSN($this->_dsn);

	if ($dsn['type'] != 'mysqli') {
		throw new Exception('invalid dsn type: '.$dsn['type']);
	}

	if (!empty($dsn['port'])) {
		$this->_db = \mysqli_init();
		if (!$this->_db->real_connect($dsn['host'], $dsn['login'], $dsn['password'], $dsn['name'], $dsn['port'])) {
			throw new Exception('Failed to connect to MySQL ('.$this->_db->connect_errno.')', $this->_db->connect_error);
		}
	}
	else {
		$this->_db = new \mysqli($dsn['host'], $dsn['login'], $dsn['password'], $dsn['name']);
		if ($this->_db->connect_errno) {
			throw new Exception('Failed to connect to MySQL ('.$this->_db->connect_errno.')', $this->_db->connect_error);
		}
	}

	if (!empty(self::$charset) && !$this->_db->set_charset(self::$charset)) {
		// $this->execute("SET names '".self::escape(self::$charset)."'");
		throw new Exception('set charset failed', self::$charset);
	}

	if (!empty(self::$time_zone)) {
		$this->execute("SET time_zone = '".self::escape(self::$time_zone)."'");
	}
	
	$this->_conn_ttl = time() + 5 * 60; // re-check connection in 5 minutes ...
}


/**
 * Close database connection.
 */
public function close() {

	if (!$this->_db) {
		throw new Exception('no open database connection');
	}

	if (!$this->_db->close()) {
		throw new Exception('failed to close database connection');
	}

	foreach ($this->_query as $key) {
		if (is_object($this->_query[$key])) {
			if (!$this->_query[$key]->close()) {
				throw new Exception('failed to close prepared statement', $key);
			}
		}
	}

	$this->_db = null;
	$this->_conn_ttl = 0;
}


/**
 * 
 */
public function createDatabase($dsn = '', $opt = 'utf8') {
	$db = empty($dsn) ? self::splitDSN($this->_dsn) : self::splitDSN($dsn);
	$name = self::escape_name($db['name']);
	$login = self::escape_name($db['login']);
	$pass = self::escape_name($db['password']);
	$host = self::escape_name($db['host']);

	$this->dropDatabase($dsn);

	if ($opt === 'utf8') {
		$opt = " CHARACTER SET='utf8mb4' COLLATE='utf8mb4_unicode_ci'";
	}

	$this->execute("CREATE DATABASE ".$name.$opt);
	$this->execute("GRANT ALL PRIVILEGES ON $name.* TO '$login'@'$host' IDENTIFIED BY '$pass'");
	$this->execute("FLUSH PRIVILEGES");
}


/**
 * 
 */
public function dropDatabase($dsn = '') {
	$db = empty($dsn) ? self::splitDSN($this->_dsn) : self::splitDSN($dsn);
	$name = self::escape_name($db['name']);
	$login = self::escape_name($db['login']);
	$host = self::escape_name($db['host']);

	$this->execute("DROP DATABASE IF EXISTS $name");

	try {
		// give dummy privilege to make sure drop user does not fail even if uses does not exist
		// ToDo: unecessary in MariaDB 10.1.3 and upwards: DROP USER IF EXISTS 'login'@'host'
		$this->execute("GRANT USAGE ON *.* TO '$login'@'$host'"); 
		$this->execute("DROP USER '$login'@'$host'");
	}
	catch (\Exception $e) {
		if (property_exists($e, 'internal_message') && mb_substr($e->internal_message, 0, 15) === 'GRANT USAGE ON ') {
			$e = new Exception('you have no grant privilege', $e->internal_message);
		}

		throw $e;
	}
}


/**
 *
 */
public function saveDump($file, $opt = null) {
  throw new Exception('ToDo ...');
}


/**
 * Allow to load via mysqldump shell command.
 *
 * @param string $file 
 * @param bool $with_usr_bin_mysql
 */
public function loadDump($file, $flags = 0) {
	File::exists($file, true);
	$table = self::escape_name(File::basename($file, true));

	if ($flags & self::LOAD_DUMP_USE_SHELL) {
		$dsn = self::splitDSN($this->_dsn);
		$handle = popen("mysql -h '".$dsn['host']."' -u '".$dsn['login']."' -p'".$dsn['password']."' '".$dsn['name']."'", 'w');
		if (!$handle) {
			throw new Exception('failed to open write pipe to mysql');
		}

		if ($flags & self::LOAD_DUMP_ADD_IGNORE_FOREIGN_KEYS) {
			fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;");
		}

		if ($flags & self::LOAD_DUMP_ADD_DROP_TABLE) {
			fwrite($handle, "DROP TABLE IF EXISTS $table;");
		}

		$fh = fopen($file, 'rb');
		if (!$fh) {
			throw new Exception('failed to read '.$file);
		}

		while (!feof($fh)) { 
			fwrite($handle, fread($fh, 4096)); 
		}

		fclose($fh);

		if ($flags & self::LOAD_DUMP_ADD_IGNORE_FOREIGN_KEYS) {
			fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;");
		}

		pclose($handle);
	}
	else {
		if ($flags & self::LOAD_DUMP_ADD_IGNORE_FOREIGN_KEYS) {
    	$this->db->execute("SET FOREIGN_KEY_CHECKS=0");
  	}

		parent::loadDump($file, $flags);

		if ($flags & self::LOAD_DUMP_ADD_IGNORE_FOREIGN_KEYS) {
    	$this->db->execute("SET FOREIGN_KEY_CHECKS=1");
  	}
	}
}


/**
 * 
 */
public function dropTable($table) {
	$this->execute("DROP TABLE IF EXISTS $table");
}


/**
 * Execute database query.
 *
 * @param string $query
 * @param bool $use_result (default = false)
 */
public function execute($query, $use_result = false) {

	if (is_array($query)) {
		if ($use_result) {
			$stmt = $this->_exec_stmt($query);
		}
		else {
			$stmt = $this->_exec_stmt($query);
			$stmt->close();
		}
	}
	else {
		$this->_connect();

		if ($this->_db->real_query($query) === false) {
			throw new Exception('failed to execute sql query', $query."\n(".$this->_db->errno.') '.$this->_db->error);
		}

		if ($use_result) {
			if (($this->_dbres = $this->_db->store_result()) === false) {
				throw new Exception('failed to use query result', $query."\n(".$this->_db->errno.') '.$this->_db->error);
			}
		}
	}
}


/**
 *
 */
public function setFirstRow($offset) {
	if (!$this->_dbres->data_seek($offset)) {
		throw new Exception('failed to scroll to position '.$offset.' in database result set');
	}
}


/**
 * Return next row (or NULL).
 * Free resultset when null is retrieved.
 *
 * @throws if no resultset
 * @return map<string:string>|null
 */
public function getNextRow() {

	if (!is_object($this->_dbres)) {
		throw new Exception('no resultset');
  }

	$is_prepared = $this->_dbres instanceof mysqli_stmt;

	if ($is_prepared) {
		// see $this->_fetch_stmt()
		throw new Exception('ToDo');
	}
	else {
		$row = $this->_dbres->fetch_assoc();
	}

	if (is_null($row)) {
		$this->freeResult();
	}

	return $row;
}


/**
 *
 */
public function freeResult() {
	if (is_null($this->_dbres)) {
		return;
	}

	$is_prepared = $this->_dbres instanceof mysqli_stmt;

	if ($is_prepared) {
		$this->_dbres->close();
	}
	else {
		$this->_dbres->free();
	}

	$this->_dbres = null;
}


/**
 * Return number of rows in resultset.
 * 
 * @throws if no resultset
 * @return int
 */
public function getRowNumber() {

	if (!is_object($this->_dbres)) {
		throw new Exception('no resultset');
  }

	return $this->_dbres->num_rows;
}


/**
 * Execute database query $query and return result column $col.
 *
 * @param string $query
 * @param string $col
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
 * Execute database query and return map.
 * 
 * @param string $query
 * @param string $key_col
 * @param string $value_col
 * @param bool $ignore_double
 * @return map
 */
public function selectHash($query, $key_col = 'name', $value_col = 'value', $ignore_double = false) {

	if (is_array($query)) {
		$res = $this->_fetch_stmt($this->_exec_stmt($query), array($key_col, $value_col)); 
	}
	else {
		$res = $this->_fetch($query, array($key_col, $value_col));
	}

	if (!$ignore_double && isset($this->_cache['FETCH:DOUBLE'])) {
		throw new Exception('Hashkeys are not unique', print_r($this->_cache['FETCH:DOUBLE'], true));
	}

	return $res;
}


/**
 * Return double keys from selectHash.
 * 
 * @return array[string]int
 */
public function getDoubles() {
	return $this->_cache['FETCH:DOUBLE'];
}


/**
 * Execute select query. If res_count > 0 and result is empty
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
 * @see ADatabase::multiQuery()
 */
public function multiQuery($query) {

	if (self::$use_prepared) {
		throw new Exception('multiQuery does not work in prepared query mode');
	}

	$this->_connect();

	if (($dbres = $this->_db->multi_query($query)) === false) {
		throw new Exception('multi query failed', $query."\n(".$this->_db->errno.') '.$this->_db->error);
	}
	
	$res = [];

	do {

		if (($dbres = $this->_db->store_result()) === false) {
			continue;
		}

		$rows = [];

		while (($row = $dbres->fetch_assoc())) {
			array_push($rows, $row);
		}

		array_push($res, $rows);
	} while ($this->_db->more_results() && $this->_db->next_result());

	return (count($res) == 1) ? array_pop($res) : $res;
}


/**
 * Execute select query and fetch result. 
 * 
 * Return result table if $rbind = null.
 * Return hash if count($rbind) = 2. Return array if count($rbind) = 1.
 * Return hash if $rcount < 0 (result[-1 * $rcount + 1]).
 *
 * @param string $query
 * @param array $rbind (default = null)
 * @param int $rcount (default = 0)
 * @return table|map|vector
 */
private function _fetch($query, $rbind = null, $rcount = 0) {

	$this->_connect();

	// query = real_query + store_result
	if (($dbres = $this->_db->query($query)) === false) {
		throw new Exception('failed to execute sql query', $query."\n(".$this->_db->errno.') '.$this->_db->error);
	}

	$rnum = $dbres->num_rows;
	$res = array();

	if ($rcount > 0 && $rnum != $rcount) {
		if ($rnum == 0) {
			throw new Exception('no result', "$rcount rows expected - query=$query");
		}
		else {
			throw new Exception('unexpected number of rows', "$rnum != $rcount query=$query");
		}
	}

	if ($rcount < 0 && -1 * $rcount > $rnum) {
		throw new Exception('number of rows too low', "$rnum < -1 * $rcount query=$query");
	}

	if ($rnum > 50000) {
		throw new Exception('number of rows too high', "$rnum query=$query");
	}

	if ($rnum === 0) {
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

		if (!empty($rbind[0]) && !isset($dbrow[$rbind[0]]) && !array_key_exists($rbind[0], $dbrow)) {
			throw new Exception('no such column ['.$rbind[0].']', $rbind[0]);
		}

		if ($bl === 1) {
			array_push($res, $dbrow[$rbind[0]]);
		}
		else if ($bl === 2) {
			$key = $dbrow[$rbind[0]];

			if (isset($res[$key])) {
				if (!isset($this->_cache['FETCH:DOUBLES'])) {
					$this->_cache['FETCH:DOUBLE'] = array();
				}

				if (!isset($this->_cache['FETCH:DOUBLE'][$key])) {
					$this->_cache['FETCH:DOUBLE'][$key] = 0;
				}

				$this->_cache['FETCH:DOUBLE'][$key]++;
			}

			$res[$key] = $dbrow[$rbind[1]];
		}
		else {
			array_push($res, $dbrow);
		}
	}

	$dbres->free();

	return $res;
}


/**
 * Execute query and return result row $rnum.
 * 
 * @param string $query
 * @param int $rnum
 * @return map
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
 * Execute prepared statement. 
 * 
 * Return statement.
 *
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

	for ($i = 0; $i < $ql - 2; $i++) {
		$key = $q[$i];

		if (!isset($replace[$key]) && !array_key_exists($key, $replace)) {
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

	if ($rnum === 0) {
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

		if ($bl === 1) {
			array_push($res, $db_data[$rbind[0]]);
		}
		else if ($bl === 2) {
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
 * Return escaped value.
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
 * Return vector with database names.
 * 
 * @param boolean $reload_cache
 * @return vector
 */
public function getDatabaseList($reload_cache = false) {

	if ($reload_cache || !isset($this->_cache['DATABASE_LIST:']) || count($this->_cache['DATABASE_LIST:']) === 0) {
		$dbres = $this->select('SHOW DATABASES');
		$this->_cache['DATABASE_LIST:'] = [ ];

		for ($i = 0; $i < count($dbres); $i++) {
			array_push($this->_cache['DATABASE_LIST:'], array_pop($dbres[$i]));
		}
	}

	return $this->_cache['DATABASE_LIST:'];
}


/**
 * Return vector with table names.
 * 
 * @param boolean $reload_cache
 * @return vector
 */
public function getTableList($reload_cache = false) {

	if ($reload_cache || !isset($this->_cache['TABLE_LIST:']) || count($this->_cache['TABLE_LIST:']) === 0) {
		$dbres = $this->select('SHOW TABLES');
		$this->_cache['TABLE_LIST:'] = [ ];

		for ($i = 0; $i < count($dbres); $i++) {
			array_push($this->_cache['TABLE_LIST:'], array_pop($dbres[$i]));
		}
	}

	return $this->_cache['TABLE_LIST:'];
}


/**
 *
 */
public function getReferences($table, $column = 'id') {
	$ckey = "FOREIGN_KEY_REFERENCES:$table.$column";
	if (isset($this->_cache[$ckey])) {
		return $this->_cache[$ckey];
	}

  $dsn = self::splitDSN($this->_dsn);
	$db_name = self::escape($dsn['name']);

	if ($column == '*') {
		$query = "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS tname, CONCAT(REFERENCED_TABLE_NAME, '.', REFERENCED_COLUMN_NAME) AS cname ".
			"FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='$db_name' ".
			"AND CONSTRAINT_SCHEMA='$db_name' AND REFERENCED_TABLE_SCHEMA='$db_name' AND TABLE_NAME='".self::escape($table)."' ".
			"ORDER BY TABLE_NAME;"; 
	}
	else {
		$query = "SELECT TABLE_NAME AS tname, COLUMN_NAME AS cname FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE ".
			"WHERE REFERENCED_TABLE_NAME='".self::escape($table)."' AND REFERENCED_COLUMN_NAME='".
			self::escape($column)."' AND TABLE_SCHEMA='$db_name' AND CONSTRAINT_SCHEMA='$db_name' ".
			"AND REFERENCED_TABLE_SCHEMA='$db_name'";
	}

	$dbres = $this->select($query);
	$res = [];

	foreach ($dbres as $row) {
		$r_table = $row['tname'];
		$r_col = $row['cname'];

    if (!isset($res[$r_table])) {
      $res[$r_table] = ($column == '*') ? $r_col : [ $r_col ];
    }
    else {
      array_push($res[$r_table], $r_col);
    }
  }

	$this->_cache[$ckey] = $res;
	return $res;
}


/**
 * Return number of affected rows of last execute query.
 * 
 * @return int
 */
public function getAffectedRows() {
	return $this->_db->affected_rows;
}


/**
 *
 */
public function getError() {

	if (!$this->_db->errno) {
		return null;
	}

	$map = [ 1146 => 'no_such_table' ];

	$error = isset($map[$this->_db->errno]) ? $map[$this->_db->errno] : '';

	return  [ $error, $this->_db->error, $this->_db->errno ];
}


/**
 * Return table description. Column map is:
 *
 * - type: double, ...
 * - is_null: true|false
 * - default: null, 0, ...
 * - extra: 
 * 
 * @param string $table
 * @return map<string:map> keys are column names
 */
public function getTableDesc($table) {

	if (isset($this->_cache['DESC:'.$table])) {
		return $this->_cache['DESC:'.$table];
	}

	$db_res = $this->select('DESC '.self::escape_name($table));
	$res = array();

	foreach ($db_res as $info) {
		$colname = $info['Field'];
		$cinfo = [];
		$cinfo['type'] = $info['Type'];
		$cinfo['is_null'] = $info['Null'] === 'YES';
		$cinfo['key'] = $info['Key'];
		$cinfo['default'] = $info['Default'];
		$cinfo['extra'] = $info['Extra'];

		$res[$colname] = $cinfo;
	}

	$this->_cache['DESC:'.$table] = $res;

	return $res;
}


/**
 * Return auto_increment column value if last 
 * query was insert and table has auto_increment column.
 *
 * @throw not_implemented|no_id
 * @return int 
 */
public function getInsertId() {

	if (!is_numeric($this->_db->insert_id) || intval($this->_db->insert_id) === 0) {
		throw new Exception('no_id', $this->_db->insert_id);
	}

	return $this->_db->insert_id;
}


/**
 * Return table data checksum.
 *
 * @param string $table
 * @param bool $native (default = false)
 * @return string
 */
public function getTableChecksum($table, $native = false) {
	$tname = self::escape_name($table);

	if ($native) {
		return $this->_db->selectOne('CHECKSUM TABLE '.$tname, 'Checksum');
	}

	// default size GROUP_CONCAT result (=1024) must be increased to at least #TABLE_ENTRIES * 34
	$gcml = intval($this->db->selectOne("SHOW GLOBAL VARIABLES LIKE 'group_concat_max_len'", 'Value'));
	$gcml_min = 34 * intval($this->db->selectOne("SELECT COUNT(*) AS anz FROM $tname", 'anz'));

	if ($gcml < $gcml_min) {
		$this->db->execute("SET SESSION group_concat_max_len=$gcml_min");
	}

	$column_names = join(',', array_keys(getTableDesc($table)));
	$res = $this->_db->selectOne("SELECT MD5(GROUP_CONCAT(MD5(CONCAT_WS('|',$column_names)))) AS md5 FROM $tname", 'md5');
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

	if (empty($table)) {
		throw new Exception('empty table name');
	}

	$dbres = $this->select("SHOW table STATUS");
	$res = null;

	foreach ($dbres as $info) {
		if ($info['Name'] !== $table) {
			continue;
		}

		$res = $info;
	}

	if (!is_null($res)) {
		throw new Exception('no such table name');
	}

	return $res;
}


}

