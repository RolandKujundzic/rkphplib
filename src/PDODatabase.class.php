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
public function getId() {
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
public function execute($query, $use_result = false) {
	throw new Exception('ToDo');
}


/**
 *
 */
public function getTableChecksum($table, $native = false) {
	throw new Exception('ToDo');
}


/**
 *
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
 *
 */
public function getNextRow() {
	throw new Exception('ToDo');
}


/**
 *
 */
public function freeResult() {
	throw new Exception('ToDo');
}


/**
 * 
 */
public function getRowNumber() {
	throw new Exception('ToDo');
}


/**
 *
 */
public function selectColumn($query, $colname = 'col') {
	throw new Exception('ToDo');
}


/**
 *
 */
public function selectHash($query, $key_col = 'name', $value_col = 'value', $ignore_double = false) {
	throw new Exception('ToDo');
}


/**
 *
 */
public function select($query, $res_count = 0) {
	throw new Exception('ToDo');
}


/**
 *
 */
public function selectRow($query, $rnum = 0) {
	throw new Exception('ToDo');
}


/**
 *
 */
public function esc($txt) {
	throw new Exception('ToDo');
}


/**
 *
 */
public function getTableDesc($table) {
	throw new Exception('ToDo');
}


/**
 *
 */
public function getDatabaseList($reload_cache = false) {
	throw new Exception('ToDo ...');	
}


/**
 *
 */
public function getTableList($reload_cache = false) {
	throw new Exception('ToDo ...');	
}


/**
 *
 */
public function getReferences($table, $column = 'id') {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function getError() {
	throw new Exception('ToDo ...');
}


/**
 * 
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
public function saveDump($opt) {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function saveTableDump($opt) {
	throw new Exception('ToDo ...');
}


/**
 *
 */
public function loadDump($file, $flags) {
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


/**
 *
 */
public function multiQuery($query) {
  throw new Exception('@ToDo ... ');
}


}

