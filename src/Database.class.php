<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/ADatabase.class.php');

use rkphplib\Exception;



/**
 * Instantiate [Mysql|SQLite]Database.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Database {

/** @var int $map_id $pool[$map_id] is db connection for get() after setMap() */
public static $map = null;

/** @var <vector:ADatabase> $pool Database connection pool */
private static $pool = [];



/**
 * Factory method. Return ADatabase object with dsn set. Example:
 *
 * $mysqli = Database::create('mysqli://user:password@tcp+localhost/dbname');
 * $sqlite = Database::create('sqlite://[password]@path/to/file.sqlite');
 * 
 * @throws rkphplib\Exception
 * @param string $dsn (default = '' = use SETTINGS_DSN)
 * @param map $query_map (default = null)
 * @return ADatabase
 */
public static function create($dsn = '', $query_map = null) {
	$db = null;

	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}
	
	if (empty($dsn)) {
		throw new Exception('empty dsn');
	}

	if (mb_substr($dsn, 0, 9) == 'mysqli://') {
		require_once(__DIR__.'/MysqlDatabase.class.php');
		$db = new MysqlDatabase();
	}
	else if (mb_substr($dsn, 0, 9) == 'sqlite://') {
		require_once(__DIR__.'/SQLiteDatabase.class.php');
		$db = new SQLiteDatabase();
	}
	else {
		throw new Exception('invalid dsn', "dsn=$dsn");
	}

	$db->setDSN($dsn);

	if (!is_null($query_map)) {
		$db->setQueryHash($query_map);
	}

	return $db;
}


/**
 * Set query map.
 *
 * @param map @query_map
 */
public static function setMap($query_map) {

	if (!isset($query_map['@query_prefix'])) {
		$query_map['@query_prefix'] = '';
	}

	self::getInstance('', $query_map);
	self::$map = count(self::$pool) - 1;
}


/**
 * Return select result. Throw exception if result has more than one row.
 *
 * @throws
 * @param string $qkey
 * @param map $replace
 * @return map 
 */
public static function get($qkey, $replace) {

	if (is_null(self::$map)) {
		throw new Exception('call setMap first');
	}

	$db = self::$pool[self::$map];
	$dbres = $db->select($db->getQuery($qkey, $replace), 1);
	return $dbres[0];
}


/**
 * Return select result.
 *
 * @throws
 * @param string $qkey
 * @param map $replace
 * @return table
 */
public static function select($qkey, $replace) {

	if (is_null(self::$map)) {
		throw new Exception('call setMap first');
	}

	$db = self::$pool[self::$map];
	return $db->select($db->getQuery($qkey, $replace));
}


/**
 * Singelton method. Return unused ADatabase object instance with dsn from pool. 
 *
 * @throws rkphplib\Exception
 * @param string $dsn (default = '' = use SETTINGS_DSN) 
 * @param map $query_map (default = null)
 * @return ADatabase
 */
public static function getInstance($dsn = '', $query_map = null) {

	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}

	if (empty($dsn)) {
		throw new Exception('empty dsn');
	}

	$db_id = ADatabase::computeId($dsn, $query_map);

	for ($i = 0; $i < count(self::$pool); $i++) {
		if (self::$pool[$i]->getId() == $db_id && !self::$pool[$i]->hasResultSet()) {
			return self::$pool[$i];
		}
	}

	array_push(self::$pool, self::create($dsn, $query_map));
	return self::$pool[$i];
}


/**
 *
 */
public static function getPoolSize() {
	return count(self::$pool);
}


/**
 * Prevent creating a new instance of the *Singleton* via the `new` operator.
 */
protected function __construct() { }


/**
 * Prevent cloning of the instance of the *Singleton* instance.
 */
private function __clone() { }


/**
 * Prevent unserializing of the *Singleton* instance.
 */
private function __wakeup() { }

}
