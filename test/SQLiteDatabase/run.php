<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.php';
	$th = new rkphplib\TestHelper();
}

$th->load('src/SQLiteDatabase.php');


/**
 *
 */
function get_db() {
	$db = new \rkphplib\SQLiteDatabase();
	$db->setDSN(TEST_SQLITE);

	return $db;
}


/**
 *
 */
function create() {
	$res = array(0, 0, 0, 0);
	$db = get_db();

try {

	$db->execute("DROP TABLE IF EXISTS phplib");
	$res[0] = 1;

$query = <<<END
CREATE TABLE phplib (
id int not null auto_increment,
rid int not null,
name varchar(50) not null,
age int not null,
height double,
weight double,
PRIMARY KEY (id),
INDEX (name),
INDEX (rid)
)
END;
	$db->execute($query);
	$res[1] = 1;

	$colnames = join(',', array_keys($db->getTableDesc('phplib')));

	if ($colnames == 'id,rid,name,age,height,weight') {
		$res[2] = 1;
	}

	$db->setQuery('desc', "DESC {:=^table}");
	$colnames = join(',', $db->selectColumn($db->getQuery('desc', array('table' => 'phplib')), 'Field'));

	if ($colnames == 'id,rid,name,age,height,weight') {
		$res[3] = 1;
	}

}
catch (Exception $e) {
	// ignore
}

	return $res;
}


/**
 *
 */
function insert() {
	$res = array(0, 0, 0, 0);
	$db = get_db();

try {

	$db->setQuery('insert', "INSERT INTO phplib (rid, name, age, height, weight) VALUES ".
		"({:=rid}, {:=name}, {:=age}, {:=height}, {:=weight})");

	for ($i = 1001; $i <= 2000; $i++) {
		$r = array('rid' => $i, 'name' => 'i_'.$i, 'age' => 30, 'height' => 175, 'weight' => 90);
		$db->execute($db->getQuery('insert', $r));
	}
	
	$res[0] = 1;


	$db->setQuery('insert_bind', "INSERT INTO phplib (rid, name, age, height, weight) VALUES ".
		"('{:=rid}', '{:=name}', '{:=age}', '{:=height}', '{:=weight}')");

	for ($i = 2001; $i <= 3000; $i++) {
		$r = array('rid' => $i, 'name' => 'ib_'.$i, 'age' => 25, 'height' => 185, 'weight' => 102);
		$db->execute($db->getQuery('insert_bind', $r));
	}
	
	$res[1] = 1;
}
catch (Exception $e) {
	// ignore
}

	return $res;
}


/**
 * M A I N
 */

/** ToDo: 
https://www.daniweb.com/web-development/php/code/442441/using-phpsqlite3-with-error-checking
http://devzone.zend.com/14/php-101-part-9-sqlite-my-fire_part-1/
http://babbage.cs.qc.cuny.edu/courses/cs903/2013_02/using_sqlite3.html
http://www.tutorialspoint.com/sqlite/sqlite_php.htm
http://www.mediaevent.de/tutorial/php-sqlite3.html
http://stackoverflow.com/questions/18485026/using-prepared-statements-with-sqlite3-and-php
http://php.net/manual/en/class.sqlite3.php
*/

$db = get_db();
$db->execute("CREATE yala TABLE test (id int not null, name varchar(20) not null)");

// call_test('create', array(), array('MysqlDatabase create/desc', 1, 1, 1, 1));
// call_test('insert', array(), array('MysqlDatabase insert', 1, 1, 1, 1));

