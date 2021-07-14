<?php

namespace rkphplib\db;

require_once __DIR__.'/ADatabase.php';


/**
 * Dummy database (does nothing)
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Dummy extends ADatabase {

/**
 *
 */
public function disableKeys(array $table_list = [], bool $as_string = false) : string {
	return '';
}


/**
 *
 */
public function enableKeys(array $table_list = [], bool $as_string = false) : string {
	return '';
}


/**
 *
 */
public function getId() : ?string {
	return null;
}


/**
 *
 */
public function addIndex(string $table, string $column, string $type = '') : bool {
	return true;
}


/**
 *
 */
public function hasIndex(string $table, string $column, string $type = '') : bool {
	return true;
}


/**
 *
 */
public function lock(array $tables) : void {
}


/**
 *
 */
public function unlock() : void {
}


/**
 *
 */
public function getLock(string $name) : int {
	return 0;
}


/**
 *
 */
public function hasLock(string $name) : bool {
	return false;
}


/**
 *
 */
public function releaseLock(string $name) : int {
	return 0;
}


/**
 *
 */
public function hasResultSet() : bool {
	return false;
}


/**
 *
 */
public function getDatabaseList(bool $reload_cache = false) : ?array {
	return isset($this->cache['DATABASE_LIST:']) ? $this->cache['DATABASE_LIST:'] : [];
}


/**
 *
 */
public function getTableList(bool $reload_cache = false) : array {
	return isset($this->cache['TABLE_LIST:']) ? $this->cache['TABLE_LIST:'] : [];
}


/**
 *
 */
public function getError() : ?array {
	return [];
}


/**
 *
 */
public function getAffectedRows() : int {
	return 0;
}


/**
 *
 */
public function getInsertId() : int {
	return 0;
}


/**
 *
 */
public function createDatabase(string $dsn = '', string $opt = 'utf8') : bool {
	return true;
}


/**
 *
 */
public function dropDatabase(string $dsn = '') : void {
}


/**
 *
 */
public function saveDump(array $opt) : void {
}


/**
 *
 */
public function saveTableDump(array $opt) : void {
}


/**
 *
 */
public function loadDumpShell(string $file, int $flags = self::LOAD_DUMP_IGNORE_KEYS, array $tables = []) : void {
}


/**
 *
 */
public function dropTable(string $table) : void {
}


/**
 *
 */
public function esc(string $value) : string {
	return self::escape($value);
}


/**
 *
 */
public function execute(string $query, bool $use_result = false) : bool {
	return true;
}


/**
 *
 */
public function setFirstRow(int $offset) : void {
}


/**
 *
 */
public function getNextRow() : ?array {
	return null;
}


/**
 *
 */
public function freeResult() : void {
}


/**
 *
 */
public function getRowNumber() : int {
	return 0;
}


/**
 *
 */
public function selectColumn($query, string $colname = 'col') : ?array {
	return [];
}


/**
 *
 */
public function getTableDesc(string $table) : array {
	return isset($this->cache['DESC:'.$table]) ? $this->cache['DESC:'.$table] : [];
}


/**
 *
 */
public function getReferences(string $table, string $column = 'id') : array {
	return [];
}


/**
 *
 */
public function selectHash(string $query, string $key_col = 'name', 
		string $value_col = 'value', bool $ignore_double = false) : ?array {
	return [];
}


/**
 *
 */
public function selectRow($query, int $rnum = 0) : ?array {
	return [];
}


/**
 *
 */
public function select($query, int $res_count = 0) : ?array {
	return [];
}


/**
 *
 */
public function getTableChecksum(string $table, bool $native = false) : string {
	return '';
}


/**
 *
 */
public function getTableStatus(string $table) : array {
	return [];
}


/**
 *
 */
public function multiQuery(string $query) : ?array {
	return [];
}


/**
 *
 */
public function connect() : bool {
	return true;
}


/**
 *
 */
public function close() : bool {
	return true;
}


}

