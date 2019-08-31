<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/ADatabase.class.php';
require_once __DIR__.'/lib/split_str.php';
require_once __DIR__.'/lib/is_map.php';

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
 * Factory method. Return ADatabase object with dsn set (use SETTINGS_DSN if $dsn is empty). Example:
 *
 * $mysqli = Database::create('mysqli://user:password@tcp+localhost/dbname');
 * $sqlite = Database::create('sqlite://[password]@path/to/file.sqlite');
 */
public static function create(string $dsn = '', array $query_map = []) : object {
	$db = null;

	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}
	
	if (empty($dsn)) {
		throw new Exception('empty dsn');
	}

	if (mb_substr($dsn, 0, 9) == 'mysqli://') {
		require_once __DIR__.'/MysqlDatabase.class.php';
		$db = new MysqlDatabase();
	}
	else if (mb_substr($dsn, 0, 9) == 'sqlite://') {
		require_once __DIR__.'/SQLiteDatabase.class.php';
		$db = new SQLiteDatabase();
	}
	else {
		throw new Exception('invalid dsn', "dsn=$dsn");
	}

	$db->setDSN($dsn);

	if (count($query_map) > 0) {
		$db->setQueryMap($query_map);
	}

	return $db;
}


/**
 * Singelton method. Return unused ADatabase object instance with dsn from pool.
 * Use query_map with no prefix. Use SETTINGS_DSN if $dsn is empty (=default). 
 */
public static function getInstance(string $dsn = '', array $query_map = []) : object {

	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}

	if (empty($dsn)) {
		throw new Exception('empty dsn');
	}

	$found = []; 

	for ($i = 0; $i < count(self::$pool); $i++) {
		if (self::$pool[$i]->getDSN() == $dsn && self::$pool[$i]->hasQueries($query_map)) {
			array_push($found, $i);

			if (!self::$pool[$i]->hasResultSet()) {
				// \rkphplib\lib\log_debug("Database::getInstance> use instance $i = ".self::$pool[$i]->getId());
				return self::$pool[$i];
			}
		}
	}

	if (\rkphplib\lib\is_map($query_map, true)) {
		array_push(self::$pool, self::create($dsn, $query_map));
		// \rkphplib\lib\log_debug("Database::getInstance> create and use new instance $i = ".self::$pool[$i]->getId());
		return self::$pool[$i];
	}
	else if (count($found) > 0) {
		// check if instance has become available ...
		for ($i = 0; $i < count($found); $i++) {
			if (!self::$pool[$i]->hasResultSet()) {
				// \rkphplib\lib\log_debug("Database::getInstance> use available instance $i = ".self::$pool[$i]->getId());
				return self::$pool[$i];
			}
		}

		// create new instance
		array_push(self::$pool, self::create($dsn, self::$pool[$found[0]]->getQueryMap()));
		// \rkphplib\lib\log_debug("Database::getInstance> found=$found - create and use new instance $i");
		return self::$pool[$i];	
	}
	else {
		throw new Exception('no matching database instance', 'dsn='.$dsn.' query_map='.print_r($query_map, true));
	}
}


/**
 * Return pool size.
 */
public static function getPoolSize() : int {
	return count(self::$pool);
}


/**
 * Return database pool info ([ { id:, hasResultSet: }, ... ]).
 * @return vector
 */
public static function getInfo() : array {
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
protected function __construct() {
}


/**
 * Prevent cloning of the instance of the *Singleton* instance.
 */
private function __clone() {
}


/**
 * Prevent unserializing of the *Singleton* instance.
 */
private function __wakeup() {
}


}
