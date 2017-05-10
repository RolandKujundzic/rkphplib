<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/ADatabase.class.php');
require_once(__DIR__.'/lib/split_str.php');
require_once(__DIR__.'/lib/is_map.php');

use rkphplib\Exception;



/**
 * Instantiate [Mysql|SQLite]Database.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Database {

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
		$db->setQueryMap($query_map);
	}

	return $db;
}


/**
 * Singelton method. Return unused ADatabase object instance with dsn from pool.
 * Use query_map with no prefix. 
 *
 * @throws 
 * @param string $dsn (default = '' = use SETTINGS_DSN) 
 * @param null|map|vector $query_map (default = null)
 * @return ADatabase
 */
public static function getInstance($dsn = '', $query_map = []) {

	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}

	if (empty($dsn)) {
		throw new Exception('empty dsn');
	}

	$found = []; 

	for ($i = 0; $i < count(self::$pool); $i++) {
		if (self::$pool[$i]->getDSN() == $dsn && self::$pool[$i]->hasQueryMap($query_map)) {
			array_push($found, $i);

			if (!self::$pool[$i]->hasResultSet()) {
				return self::$pool[$i];
			}
		}
	}

	if (\rkphplib\lib\is_map($query_map)) {
		array_push(self::$pool, self::create($dsn, $query_map));
		return self::$pool[$i];
	}
	else if (count($found) > 0) {
		// check if instance has become available ...
		for ($i = 0; $i < count($found); $i++) {
			if (!self::$pool[$i]->hasResultSet()) {
				return self::$pool[$i];
			}
		}

		// create new instance
		array_push(self::$pool, self::create($dsn, self::$pool[$found[0]]->getQueryMap()));
		return self::$pool[$i];	
	}
	else {
		throw new Exception('no matching database instance', 'dsn='.$dsn.' query_map='.print_r($query_map, true));
	}
}


/**
 * Return pool size.
 *
 * @return int
 */
public static function getPoolSize() {
	return count(self::$pool);
}


/**
 * Return database pool info.
 * @return vector
 */
public static function getInfo() {
	$res = [];

	for ($i = 0; $i < count(self::$pool); $i++) {
		$info = [ 'id' => self::$pool[$i]->getId(), 'hasResultSet' => self::$pool[$i]->hasResultSet() ];
		array_push($res, $info);
	}

	return $res;
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
