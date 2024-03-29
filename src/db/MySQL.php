<?php

namespace rkphplib\db;

require_once __DIR__.'/ADatabase.php';
require_once __DIR__.'/../PipeExecute.php';
require_once __DIR__.'/../File.php';

use \rkphplib\Exception;


/**
 * MysqlDatabase implementation of ADatabase.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class MySQL extends ADatabase {

// @var mysqli $db
private $db = null;

// @var mysqli_result|mysqli_stmt|null $dbres
private $dbres = null;

// @var int $conn_ttl
private $conn_ttl = 0;

// @var int $seek
private $seek = -1;

// @var array $abort_error
private $abort_error = null;

// @var bool $rollback
private $rollback = false;


/**
 * @example $mysql = new \rkphplib\db\MySQL([ 'abort' => false, 'charset' => 'latin1' ]);
 */
public function __construct(array $options = []) {
	foreach ($options as $key => $value) {
		if (property_exists($this, $key)) {
			$this->$key = $value;
		}
	}
}


/**
 *
 */
public function disableKeys(array $table_list = [], bool $as_string = false) : string {
	$res = '';

	foreach ($table_list as $table) {
		$query = 'ALTER TABLE '.self::escape_name($table).' DISABLE KEYS';
		if ($as_string) {
			$res .= $query.";\n";
		}
		else {
			$this->execute($query);
		}
	}

	if ($as_string) {
		$res .= "SET FOREIGN_KEY_CHECKS = 0;\n".
			"SET UNIQUE_CHECKS = 0;\n".
			"SET AUTOCOMMIT = 0;\n";
	}
	else {
		$this->execute('SET FOREIGN_KEY_CHECKS = 0');
		$this->execute('SET UNIQUE_CHECKS = 0');
		$this->execute('SET AUTOCOMMIT = 0');
	}

	return $res;
}


/**
 * Force key creation after import
 */
public function enableKeys(array $table_list = [], bool $as_string = false) : string {
	$res = '';

	foreach ($table_list as $table) {
		$query = 'ALTER TABLE '.self::escape_name($table).' ENABLE KEYS';
		if ($as_string) {
			$res .= $query.";\n";
		}
		else {
			$this->execute($query);
		}
	}

	if ($as_string) {
		$res .= "SET FOREIGN_KEY_CHECKS = 1;\n".
			"SET UNIQUE_CHECKS = 1;\n".
			"SET AUTOCOMMIT = 1;\n";
	}
	else {
		$this->execute('SET FOREIGN_KEY_CHECKS = 1');
		$this->execute('SET UNIQUE_CHECKS = 1');
		$this->execute('SET AUTOCOMMIT = 1');
	}

	return $res;
}


/**
 * Load mysql host, user and password from $path (my.cnf) and define DB_HOST|USER|PASS.
 */
public function loadMyCnf(string $path) : void {
  $mysql_conf = File::load($path);

	$get_value = function (string $key, string $define_key) use ($mysql_conf, $path) {
		if (preg_match('/\s+'.$key.'\s*=(.+?)\s/s', $mysql_conf, $match)) {
			define($define_key, trim($match[1]));
		}
		else {
			throw new Exception("no $key= in $path");
		}
	};

	$get_value('host', 'DB_HOST');
	$get_value('password', 'DB_PASS');
	$get_value('user', 'DB_USER');
}


/**
 * Add (unique|primary) index to $table.$column.
 * Return false if index already exists.
 */
public function addIndex(string $table, string $column, string $type = '') : bool {
	if ($this->hasIndex($table, $column, $type)) {
		return false;
	}

	if ($type == 'primary') {
		$index = 'PRIMARY KEY';
	}
	else if ($type == 'unique') {
		$index = 'UNIQUE';
	}
	else if (empty($type)) {
		$index = 'INDEX';
	}
	else {
		throw new Exception("invalid index type '$type' use primary|unique");
	}

	$this->execute('ALTER TABLE '.self::escape_name($table).' ADD '.$index.'('.$this->esc($column).')');
	return true;
}


/**
 * Return true if index on $table.$column exists.
 */
public function hasIndex(string $table, string $column, string $type = '') : bool {
	if ($type == 'primary') {
		$itype = " AND Key_name='PRIMARY'";
	}
	else if ($type == 'unique') {
		$itype = " AND Non_unique=0";
	}
	else if (empty($type)) {
		$itype = '';
	}
	else {
		throw new Exception("invalid index type '$type' use primary|unique");
	}


	$dbres = $this->select("SHOW INDEX FROM ".self::escape_name($table)." WHERE Column_name='".$this->esc($column)."'".$itype);
	return count($dbres) > 0;
}


/**
 * Load mysql configuration file (my.cnf) from $dsn if dsn starts with "/" or "mysqli:///".
 * If dsn is empty try SETTINGS_DSN.
 */
public function setDSN(string $dsn = '') : void {

  if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}

	if (substr($dsn, 0, 10) == 'mysqli:///') {
		$dsn = substr($dsn, 9);
	}

	if (substr($dsn, 0, 1) == '/') {
		$this->loadMyCnf($dsn);
  	$dsn = 'mysqli://'.DB_NAME.':'.DB_PASS.'@tcp+'.DB_HOST.'/'.DB_NAME;
	}

	parent::setDSN($dsn);
}


/**
 *
 */
public function hasResultSet() : bool {
	return !is_null($this->dbres);
}


/**
 *
 */
public function lock(array $tables) : void {
	throw new Exception('ToDo');
}


/**
 *
 */
public function unlock() : void {
	throw new Exception('ToDo');
}


/**
 *
 */
public function getLock(string $name) : int {
	$r = $this->selectOne("SELECT GET_LOCK('lck_$name', 10) AS r", 'r');
	return intval($r);
}


/**
 *
 */
public function hasLock(string $name) : bool {
	$r = $this->selectOne("SELECT IS_FREE_LOCK('lck_$name', 10) AS r", 'r');
	return !inval($r);
}


/**
 *
 */
public function releaseLock(string $name) : int {
	$r = $this->selectOne("SELECT RELEASE_LOCK('lck_$name') AS r", 'r');
	return intval($r);
}


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
		if ($this->conn_ttl < time() && !$this->db->ping()) {
			// \rkphplib\Log::debug('MySQL.connect> close expired connection '.$this->getId());
			$this->close();
		}
		else {
			return true;
		}
	}

	if (empty($this->dsn)) {
		return $this->error('call setDSN first', '', 2);
	}

	$dsn = self::splitDSN($this->dsn);
	// \rkphplib\Log::debug('MySQL.connect> login@host='.$dsn['login'].'@'.$dsn['host'].', name='.$dsn['name']);

	if ($dsn['type'] != 'mysqli') {
		return $this->error('invalid dsn type: '.$dsn['type'], '', 2);
	}

	if (!empty($dsn['port'])) {
		$this->db = \mysqli_init();
		if (!$this->db->real_connect($dsn['host'], $dsn['login'], $dsn['password'], $dsn['name'], $dsn['port'])) {
			return $this->error('Failed to connect to MySQL ('.$this->db->connect_errno.')', $this->db->connect_error, 2);
		}
	}
	else {
		try {
			$this->db = new \mysqli($dsn['host'], $dsn['login'], $dsn['password'], $dsn['name']);
		}
		catch (\Exception $e) {
			return $this->error('Failed to connect to MySQL ('.$e->getCode().')', $e->getMessage(), 2);
		}
	}

	if (!empty($this->charset) && !$this->db->set_charset($this->charset)) {
		// $this->execute("SET names '".self::escape($this->charset)."'");
		return $this->error('set charset failed', $this->charset, 2);
	}

	$res = true;

	if (!empty($this->time_zone)) {
		$res = $this->execute("SET time_zone = '".self::escape($this->time_zone)."'");
	}
	
	// \rkphplib\Log::debug('MySQL.connect> id='.$this->getId());
	$this->conn_ttl = time() + 5 * 60; // re-check connection in 5 minutes ...
	return $res;
}


/**
 * Close database connection.
 */
public function close() : bool {
	if (!$this->db) {
		return $this->error('no open database connection', '', 2);
	}

	if (!$this->db->close()) {
		return $this->error('failed to close database connection', '', 2);
	}

	foreach ($this->query as $key) {
		if (is_object($this->query[$key])) {
			if (!$this->query[$key]->close()) {
				return $this->error('failed to close prepared statement', $key, 2);
			}
		}
	}

	$this->db = null;
	$this->conn_ttl = 0;
	return true;
}


/**
 * 
 */
public function createDatabase(string $dsn = '', string $opt = 'utf8') : bool {
	$db = empty($dsn) ? self::splitDSN($this->dsn) : self::splitDSN($dsn);
	$name = self::escape_name($db['name']);
	$login = self::escape_name($db['login']);
	$pass = self::escape_name($db['password']);
	$host = self::escape_name($db['host']);

	if (!$this->dropDatabase($dsn)) {
		return false;
	}

	if ($opt === 'utf8') {
		$opt = " CHARACTER SET='utf8mb4' COLLATE='utf8mb4_unicode_ci'";
	}

	if (!$this->execute("CREATE DATABASE ".$name.$opt)) {
		return false;
	}

	if (!$this->execute("GRANT ALL PRIVILEGES ON $name.* TO '$login'@'$host' IDENTIFIED BY '$pass'")) {
		return false;
	}

	return $this->execute("FLUSH PRIVILEGES");
}


/**
 * 
 */
public function dropDatabase(string $dsn = '') : void {
	$db = empty($dsn) ? self::splitDSN($this->dsn) : self::splitDSN($dsn);
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
public function saveDump(array $opt) : void {

	if (empty($opt['save_dir'])) {
		$opt['save_dir'] = getcwd();
	}

	if (empty($opt['tables'])) {
		$opt['tables'] = $this->getTableList();
	}

	$use = [ 'ignore_foreign_keys', 'delete_entries' ];

	foreach ($opt['tables'] as $table) {
		$t_opt = [ 'table' => $table ];
		$t_opt['save_as'] = $opt['save_dir'].'/'.$table.'.sql';

		foreach ($opt as $key => $value) {
			if (in_array($key, $use)) {
				$t_opt[$key] = $value;
			}
			else if (strpos($key, $table.'.') === 0) {
				$tkey = substr($key, strlen($table.'.'));
				$t_opt[$tkey] = $value;
			}
		}

		$this->saveTableDump($t_opt);
	}
}


/**
 *
 */
public function saveTableDump(array $opt) : void {
	$table = self::escape_name($opt['table']);

	if (empty($opt['query'])) {
		if (!empty($opt['table'])) {
			$opt['query'] = "SELECT * FROM $table";
		}
		else {
			throw new Exception('Parameter table and query are empty', print_r($opt, true));
		}
	}

	if (empty($opt['save_as'])) {
		throw new Exception('Parameter save_as is empty', print_r($opt, true));
	}

	$fh = File::open($opt['save_as'], 'wb');

	if (!empty($opt['ignore_foreign_keys'])) {
		File::write($fh, $this->disableKeys([], true));
	}

	if (empty($opt['delete_query']) && !empty($opt['delete_entries'])) {
		$opt['delete_query'] = "DELETE FROM $table";
	}

	if (!empty($opt['delete_query'])) {
		File::write($fh, $opt['delete_query'].";\n");
	}

	if (empty($opt['cols'])) {
		$opt['cols'] = array_keys($this->getTableDesc($table));
	}

	$col_tags = [];
	foreach ($opt['cols'] as $col) {
		array_push($col_tags, TAG_PREFIX.$col.TAG_SUFFIX); 
	}

	$col_tags_list = join(', ', $col_tags);
	if (strlen($col_tags_list) < 60) {
		$this->setQuery('saveTableDump_insert', "INSERT INTO $table (".join(', ', $opt['cols']).") VALUES ($col_tags_list);\n");
	}
	else {
		$this->setQuery('saveTableDump_insert', "INSERT INTO $table (".join(', ', $opt['cols']).") VALUES \n\t($col_tags_list);\n");
	}

	$this->execute($opt['query'], true);

	while (($row = $this->getNextRow())) {
		File::write($fh, $this->getQuery('saveTableDump_insert', $row));
	}

	if (!empty($opt['ignore_foreign_keys'])) {
		File::write($fh, $this->enableKeys([], true));
	}

	File::close($fh);
}


/**
 *
 */
public function loadDumpShell(string $file, int $flags = self::LOAD_DUMP_IGNORE_KEYS, array $tables = []) : void {
	$dsn = self::splitDSN($this->dsn);
	$mysql = new PipeExecute('mysql -h {:=host} -u {:=login} -p{:=password} {:=name}', $dsn);

	$tkeys = ($flags & self::LOAD_DUMP_DROP_TABLES) ? [] : $tables;

	if ($flags & self::LOAD_DUMP_IGNORE_KEYS) {
		$mysql->write($this->disableKeys($tkeys, true));
	}

	if ($flags & self::LOAD_DUMP_DROP_TABLES) {
		foreach ($tables as $table) {
			$mysql->write("DROP TABLE IF EXISTS $table;\n");
		}
	}

	$mysql->load($file);

	if ($flags & self::LOAD_DUMP_IGNORE_KEYS) {
		$mysql->write($this->enableKeys($tkeys, true));
	}

	list ($retval, $error, $output) = $mysql->close();

	if ($error || $retval !== 0) {
		throw new Exception("loadDumpShell($file, $flags) failed: ".$error, "retval=[$retval] output=[$output]");
	}
}


/**
 * 
 */
public function dropTable(string $table) : void {
	$this->execute("DROP TABLE IF EXISTS $table");
}


/**
 *
 */
public function execute($query, bool $use_result = false) : bool {
	// \rkphplib\Log::debug("MySQL.execute> (<1>, <2>) id=".$this->getId(), $query, $use_result);
	if ($use_result && !is_null($this->dbres)) {
		throw new Exception('resultset already exists');
	}

	if (is_array($query)) {
		if ($use_result) {
			if (($stmt = $this->_exec_stmt($query)) === null) {
				return false;
			}
		}
		else {
			if (!empty($this->query['@fh']) && !$use_result) {
				throw new Exception('saveTo() does not work in prepared mode');
			}
			else if (($stmt = $this->_exec_stmt($query)) === null) {
				return false;
			}

			$stmt->close();
		}
	}
	else {
		if (!$this->connect()) {
			return false;
		}

		if (!empty($this->query['@fh']) && !$use_result) {
			File::write($this->query['@fh'], $query.";\n");
			return true;
		}
		else if ($this->rollback) {
			if (!$this->db->query($query)) {
				$this->db->rollback();
				$this->rollback = false;
				return $this->error('failed to execute sql query', $query);
			}
		}
		else if ($this->db->real_query($query) === false) {
			return $this->error('failed to execute sql query', $query);
		}

		if ($use_result) {
			if (($this->dbres = $this->db->store_result()) === false) {
				return $this->error('failed to use query result', $query);
			}
		}
	}

	return true;
}


/**
 * Start transaction if $begin=true. Otherwise end (commit) transaction.
 * 
 * @example
 * $db->transaction(true);
 * // execute queries …
 * $db->transaction();
 * @eol
 */
public function transaction(bool $begin = false) : void {
	// \rkphplib\Log::debug('MySQL.transaction> begin=<1> rollback=<2>', $begin, $this->rollback);
	if ($this->rollback) {
		$this->db->commit();
		$this->rollback = false;
	}
	else if ($begin) {
		$this->connect();
		$this->db->begin_transaction();
		$this->rollback = true;
	}
}


/**
 * Throw Exception if this.abort = true (default). Otherwise return false. Flags: 
 * 
 * 1 = return null
 * 2 = don't add _db.errno and _db.error to $internal
 * 4 = log_debug instead of log_warn
 */
private function error(string $msg, string $internal = '', int $flag = 0) : ?bool {
	if ($this->abort) {
		if (2 != $flag & 2) {
			$internal .= "\n(".$this->db->errno.') '.$this->db->error;
		}

		throw new Exception($msg, $internal);
	}

	$this->abort_error = [ $msg, null, null, $internal ];
	if (2 != $flag & 2) {
		$this->abort_error[1] = $this->db->error;
		$this->abort_error[2] = $this->db->errno;
	}

	if ($flag & 4) {
		// \rkphplib\Log::debug("MySQL.error> $msg (flag=$flag internal=$internal)");
	}
	else {
		\rkphplib\lib\log_warn("MysqlDatabase.error> $msg (flag=$flag internal=$internal)");
	}

	return ($flag & 1) ? null : false;
}


/**
 *
 */
public function setFirstRow(int $offset) : void {
	if (!$this->dbres->data_seek($offset)) {
		throw new Exception('failed to scroll to position '.$offset.' in database result set');
	}
}


/**
 *
 */
public function getNextRow() : ?array {
	if (!is_object($this->dbres)) {
		throw new Exception('no resultset');
  }

	$is_prepared = $this->dbres instanceof mysqli_stmt;

	if ($is_prepared) {
		// see $this->_fetch_stmt()
		throw new Exception('ToDo');
	}
	else {
		$row = $this->dbres->fetch_assoc();
	}

	if (is_null($row)) {
		$this->freeResult();
	}

	return $row;
}


/**
 *
 */
public function freeResult() : void {
	if (is_null($this->dbres)) {
		return;
	}

	$is_prepared = $this->dbres instanceof mysqli_stmt;

	if ($is_prepared) {
		$this->dbres->close();
	}
	else {
		$this->dbres->free();
	}

	$this->dbres = null;
}


/**
 *
 */
public function getRowNumber() : int {
	if (!is_object($this->dbres)) {
		throw new Exception('no resultset');
  }

	return $this->dbres->num_rows;
}


/**
 *
 */
public function selectColumn($query, $colname = 'col') : ?array {
	
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
 * Return double keys from selectHash.
 */
public function getDoubles() : array {
	return $this->cache['FETCH:DOUBLE'];
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
public function multiQuery(string $query) : ?array {

	if ($this->use_prepared) {
		throw new Exception('multiQuery does not work in prepared query mode');
	}

	if (!$this->connect()) {
		return null;
	}

	if (($dbres = $this->db->multi_query($query)) === false) {
		$this->error('multi query failed', $query, 1);
	}
	
	$res = [];

	do {

		if (($dbres = $this->db->store_result()) === false) {
			continue;
		}

		$rows = [];

		while (($row = $dbres->fetch_assoc())) {
			array_push($rows, $row);
		}

		array_push($res, $rows);
	} while ($this->db->more_results() && $this->db->next_result());

	return (count($res) == 1) ? array_pop($res) : $res;
}


/**
 * Execute select query and fetch result. 
 * 
 * Return result table if $rbind = null.
 * Return hash if count($rbind) = 2. Return array if count($rbind) = 1.
 * Return hash if $rcount < 0 (result[-1 * $rcount + 1]).
 */
private function _fetch(string $query, ?array $rbind = null, int $rcount = 0) : ?array {
	if (!$this->connect()) {
		return null;
	}

	// query = real_query + store_result
	if (($dbres = $this->db->query($query)) === false) {
		return $this->error('failed to execute sql query', $query, 1);
	}

	$rnum = $dbres->num_rows;
	$res = array();

	if ($rcount > 0 && $rnum != $rcount) {
		if ($rnum == 0) {
			return $this->error('no result', "$rcount rows expected - query=$query", 7);
		}
		else {
			return $this->error('unexpected number of rows', "$rnum != $rcount query=$query", 3);
		}
	}

	if ($rcount < 0 && -1 * $rcount > $rnum) {
		return $this->error('number of rows too low', "$rnum < -1 * $rcount query=$query", 3);
	}

	if ($rnum > 50000) {
		return $this->error('number of rows too high', "$rnum query=$query", 3);
	}

	if ($rnum === 0) {
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

		if (!empty($rbind[0]) && !isset($dbrow[$rbind[0]]) && !array_key_exists($rbind[0], $dbrow)) {
			return $this->error('no such column ['.$rbind[0].']', $rbind[0], 3);
		}

		if ($bl === 1) {
			array_push($res, $dbrow[$rbind[0]]);
		}
		else if ($bl === 2) {
			$key = $dbrow[$rbind[0]];

			if (isset($res[$key])) {
				if (!isset($this->cache['FETCH:DOUBLE'])) {
					$this->cache['FETCH:DOUBLE'] = array();
				}

				if (!isset($this->cache['FETCH:DOUBLE'][$key])) {
					$this->cache['FETCH:DOUBLE'][$key] = 0;
				}

				$this->cache['FETCH:DOUBLE'][$key]++;
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
 * Execute prepared statement. Return statement.
 */
private function _exec_stmt(array $q) : ?object {
	if (!$this->connect()) {
		return null;
	}

	$ql = count($q);
	$replace = $q[$ql - 1];
	$query = $q[$ql - 2];

	if (!($stmt = $this->db->prepare($query))) {
		return $this->error('Prepared query failed', $query, 1);
	}

	$bind_arr = array('');

	for ($i = 0; $i < $ql - 2; $i++) {
		$key = $q[$i];

		if (!isset($replace[$key]) && !array_key_exists($key, $replace)) {
			return $this->error("query replace key $key missing", "$query: ".print_r($replace, true), 3);
		}

		$bind_arr[0] .= 's';
		$bind_arr[$i + 1] =& $replace[$key]; 
		unset($replace[$key]);
	}

	$ref = new \ReflectionClass('mysqli_stmt');
	$method = $ref->getMethod('bind_param');

	if ($method->invokeArgs($stmt, $bind_arr) === false) {
		return $this->error('query bind parameter failed', "$query\n(".$stmt->errno.') '.$stmt->error."\n".print_r($bind_arr, true), 3);
	}

	if (count($replace) > 0) {
		return $this->error('Too many replace parameter', print_r($replace, true), 3); 
	}

	// prepared statement ...
	if (!$stmt->execute()) {
		return $this->error('failed to execute sql statement', '', 3);
	}

	return $stmt;
}


/**
 * Fetch prepared statement result. 
 *
 * Return result table if $rbind = null.
 * Return hash if count($rbind) = 2. Return array if count($rbind) = 1.
 * Return hash if $rcount < 0 (result[-1 * $rcount + 1]).
 */
private function _fetch_stmt(object $stmt, ?array $rbind = null, int $rcount = 0) : ?array {
	if (is_null($stmt)) {
		return null;
	}

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
 * 
 */
public function esc(string $txt) : string {

	if (!$this->db) {
		return self::escape($txt);
	}

	return $this->db->real_escape_string($txt);	
}


/**
 * 
 */
public function getDatabaseList(bool $reload_cache = false) : ?array {
	if ($reload_cache || !isset($this->cache['DATABASE_LIST:']) || count($this->cache['DATABASE_LIST:']) === 0) {
		if (($dbres = $this->select('SHOW DATABASES')) === null) { 
			return null;
		}

		$this->cache['DATABASE_LIST:'] = [ ];

		for ($i = 0; $i < count($dbres); $i++) {
			array_push($this->cache['DATABASE_LIST:'], array_pop($dbres[$i]));
		}
	}

	return $this->cache['DATABASE_LIST:'];
}


/**
 * 
 */
public function getTableList(bool $reload_cache = false) : array {
	if ($reload_cache || !isset($this->cache['TABLE_LIST:']) || count($this->cache['TABLE_LIST:']) === 0) {
		$dbres = $this->select('SHOW TABLES');
		$this->cache['TABLE_LIST:'] = [ ];

		for ($i = 0; $i < count($dbres); $i++) {
			array_push($this->cache['TABLE_LIST:'], array_pop($dbres[$i]));
		}
	}

	return $this->cache['TABLE_LIST:'];
}


/**
 *
 */
public function getReferences(string $table, string $column = 'id') : array {
	$ckey = "FOREIGN_KEY_REFERENCES:$table.$column";
	if (isset($this->cache[$ckey])) {
		return $this->cache[$ckey];
	}

  $dsn = self::splitDSN($this->dsn);
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

	$this->cache[$ckey] = $res;
	return $res;
}


/**
 * 
 */
public function getAffectedRows() : int {
	return $this->db->affected_rows;
}


/**
 *
 */
public function getError() : ?array {
	if (!$this->db->errno) {
		return $this->abort_error;
	}

	if (!is_null($this->abort_error) && $this->abort_error[2] == $this->db->errno) {
		return $this->abort_error;
	}

	$map = [ 1146 => 'no_such_table' ];

	$error = isset($map[$this->db->errno]) ? $map[$this->db->errno] : '';

	return  [ $error, $this->db->error, $this->db->errno, null ];
}


/**
 *
 */
public function getTableDesc(string $table) : array {
	if (isset($this->cache['DESC:'.$table])) {
		return $this->cache['DESC:'.$table];
	}

	$db_res = $this->select('DESC '.self::escape_name($table));
	$res = array();

	foreach ($db_res as $info) {
		$colname = $info['Field'];
		$type = $info['Type'];
		$cinfo = [];
		$flag = 0;

		$cinfo['type'] = $type;
		$cinfo['key'] = $info['Key'];
		$cinfo['is_null'] = $info['Null'] === 'YES';
		$cinfo['default'] = $info['Default'];
		$cinfo['extra'] = $info['Extra'];

		if ($type == 'double' || strpos($type, 'int(') !== false || strpos($type, 'decimal(') !== false) {
			$flag += 1;
		}

		if ($type == 'text' || strpos($type, 'varchar(') === 0 || strpos($type, 'varbinary(') === 0) {
			$flag += 2;
		}

		if (in_array($type, [ 'date', 'datetime', 'timestamp' ])) {
			$flag += 4;
		}

		$cinfo['flag'] = $flag;

		$res[$colname] = $cinfo;
	}

	$this->cache['DESC:'.$table] = $res;
	return $res;
}


/**
 *
 */
public function getInsertId() : int {
	// \rkphplib\Log::debug("MySQL.getInsertId> id=".$this->getId().", insert_id=".$this->db->insert_id);
	if (!is_numeric($this->db->insert_id) || intval($this->db->insert_id) === 0) {
		return intval($this->error('no_id', $this->db->insert_id, 2));
	}

	return $this->db->insert_id;
}


/**
 *
 */
public function getTableChecksum(string $table, bool $native = false) : string {
	$tname = self::escape_name($table);

	if ($native) {
		return $this->db->selectOne('CHECKSUM TABLE '.$tname, 'Checksum');
	}

	// default size GROUP_CONCAT result (=1024) must be increased to at least #TABLE_ENTRIES * 34
	$gcml = intval($this->db->selectOne("SHOW GLOBAL VARIABLES LIKE 'group_concat_max_len'", 'Value'));
	$gcml_min = 34 * intval($this->db->selectOne("SELECT COUNT(*) AS anz FROM $tname", 'anz'));

	if ($gcml < $gcml_min) {
		$this->db->execute("SET SESSION group_concat_max_len=$gcml_min");
	}

	$column_names = join(',', array_keys(getTableDesc($table)));
	$res = $this->db->selectOne("SELECT MD5(GROUP_CONCAT(MD5(CONCAT_WS('|',$column_names)))) AS md5 FROM $tname", 'md5');
}


/**
 * 
 */
public function getTableStatus(string $table) : array {
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

