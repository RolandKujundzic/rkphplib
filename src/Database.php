<?php

namespace rkphplib;

require_once __DIR__.'/Exception.php';
require_once __DIR__.'/db/ADatabase.php';
require_once __DIR__.'/lib/is_map.php';

use rkphplib\Exception;
use rkphplib\db\ADatabase;

use function rkphplib\lib\is_map;



/**
 * Instantiate [Mysql|SQLite|Dummy]Database.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Database {

// @var ADatabase[] $pool Database connection pool
private static $pool = [];


/**
 * Factory method. Return ADatabase object with dsn set (use SETTINGS_DSN if $dsn is empty). Example:
 *
 * $mysqli = Database::create('mysqli://user:password@tcp+localhost/dbname');
 * $sqlite = Database::create('sqlite://[password]@path/to/file.sqlite');
 */
public static function create(string $dsn = '', array $query_map = [], bool $add_to_pool = false) : ADatabase {
	$db = null;

	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}
	
	if (empty($dsn)) {
		throw new Exception('empty dsn');
	}

	if (mb_substr($dsn, 0, 9) == 'mysqli://') {
		require_once __DIR__.'/db/MySQL.php';
		$db = new \rkphplib\db\MySQL();
	}
	else if (mb_substr($dsn, 0, 9) == 'sqlite://') {
		require_once __DIR__.'/db/SQLite.php';
		$db = new \rkphplib\db\SQLite();
	}
	else if (mb_substr($dsn, 0, 8) == 'dummy://') {
		require_once __DIR__.'/db/Dummy.php';
		$db = new \rkphplib\db\Dummy();
	}
	else {
		throw new Exception('invalid dsn', "dsn=$dsn");
	}

	$db->setDSN($dsn);

	if (count($query_map) > 0) {
		$db->setQueryMap($query_map);
	}

	if ($add_to_pool) {
		\rkphplib\Log::debug("Database::getInstance> create instance <1> = <2>\nqueries: <3>", count(self::$pool), $db->getId(), array_keys($query_map));
		array_push(self::$pool, $db);
	}

	return $db;
}


/**
 * Return escaped table name $table
 */
public static function table(string $table) : string {
	return ADatabase::escape_name($table);
}


/**
 * Return escaped $value 
 */
public static function esc(string $value, bool $quote = false) : string {
	$res = $quote ? "'".ADatabase::escape($value)."'" : ADatabase::escape($value);
	return $res;
}


/**
 * Return escaped tablename/colname $name 
 */
public static function escName(string $name, bool $abort = false) : string {
	return ADatabase::escape_name($name, $abort);
}


/**
 * Singelton method. Return unused ADatabase object instance with dsn from pool.
 * Use query_map with no prefix. Use SETTINGS_DSN if $dsn is empty (=default). 
 * Use SETTINGS_DSN = dummy:// to skip database action.
 */
public static function getInstance(string $dsn = '', array $query_map = []) : ?ADatabase {
	if (empty($dsn) && defined('SETTINGS_DSN')) {
		$dsn = SETTINGS_DSN;
	}

	if ($dsn == 'SKIP') {
		return null;
	}

	if (empty($dsn)) {
		throw new Exception('empty dsn');
	}

	$found = []; 

	for ($i = 0; $i < count(self::$pool); $i++) {
		if (self::$pool[$i]->getDSN() == $dsn && self::$pool[$i]->hasQueries($query_map)) {
			array_push($found, $i);

			if (!self::$pool[$i]->hasResultSet()) {
				\rkphplib\Log::debug("Database::getInstance> use instance $i = ".self::$pool[$i]->getId());
				return self::$pool[$i];
			}
		}
	}

	if (is_map($query_map, true)) {
		return self::create($dsn, $query_map, true);
	}
	else if (count($found) > 0) {
		// check if instance has become available ...
		for ($i = 0; $i < count($found); $i++) {
			if (!self::$pool[$i]->hasResultSet()) {
				\rkphplib\Log::debug("Database::getInstance> use available instance $i = ".self::$pool[$i]->getId());
				return self::$pool[$i];
			}
		}

		return self::create($dsn, self::$pool[$found[0]]->getQueryMap(), true);
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
