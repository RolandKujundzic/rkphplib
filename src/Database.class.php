<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;



/**
 * Instantiate [Mysql|SQLite]Database.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Database {

/** @var string $dsn Database connection string */
private static $dsn = null;

/** @var <vector:ADatabase> $pool Database connection pool */
private static $pool = [];



/**
 * Factory method. Return ADatabase object with dsn set. Example:
 *
 * $mysqli = Database::create('mysqli://user:password@tcp+localhost/dbname');
 * $sqlite = Database::create('sqlite://[password]@path/to/file.sqlite');
 * 
 * @throws rkphplib\Exception
 * @param string $dsn (default = '', use setDSN() value)
 * @return ADatabase
 */
public static function create($dsn = '') {
	$db = null;
	
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

	if (!empty($dsn)) {
		$db->setDSN($dsn);
	}
	else {
		if (empty($this->dsn)) {
			throw new Exception('empty dsn - call setDSN() first');
		}

		$db->setDSN($this->dsn);
	}

	return $db;
}


/**
 * Singelton method. Return unused ADatabase object instance with current dsn from pool. Call setDSN() first.
 *
 * @throws rkphplib\Exception
 * @param string $dsn (default = '' = self::dsn)
 * @return ADatabase
 */
public static function getInstance($dsn = '') {

	if (empty($dsn)) {
		if (empty($this->dsn)) {
			throw new Exception('empty dsn - set Databse::$dsn first');
		}

		$dsn = $this->dsn;
	}

	for ($i = 0; is_null($db) && $i < count($pool); $i++) {
		if (self::$pool[$i]->getDSN() == $dsn && !self::$pool[$i]->hasResultSet()) {
			return self::$pool[$i];
		}
	}

	array_push(self::$pool, self::create($dsn));
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
