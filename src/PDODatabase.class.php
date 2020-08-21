<?php

namespace rkphplib;

require_once __DIR__.'/ADatabase.class.php';


/**
 * PDO implementation of ADatabase.
 *
 * @ToDo: http://henryranch.net/software/ease-into-sqlite-3-with-php-and-pdo/
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class PDODatabase extends ADatabase {


/**
 *
 */
public function getId() : string {
	throw new Exception('ToDo');
}


/**
 * Close connection.
 */
public function close() {
	throw new Exception('ToDo');
}


/**
 *
 */
public function execute(string $query, bool $use_result = false) : bool {
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
public function selectColumn($query, string $colname = 'col') : ?array {
	throw new Exception('ToDo');
}


/**
 *
 */
public function selectHash(string $query, string $key_col = 'name', string $value_col = 'value', bool $ignore_double = false) : ?array {
	throw new Exception('ToDo');
}


/**
 *
 */
public function select($query, int $res_count = 0) : ?array {
	throw new Exception('ToDo');
}


/**
 *
 */
public function selectRow($query, int $rnum = 0) : ?array {
	throw new Exception('ToDo');
}


/**
 *
 */
public function esc(string $txt) : string {
	throw new Exception('ToDo');
}


/**
 *
 */
public function getTableDesc(string $table) : array {
	throw new Exception('ToDo');
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

