<?php

require_once(dirname(__DIR__).'/testlib.php');
require_once(dirname(dirname(__DIR__)).'/src/MysqlDatabase.class.php');
require_once(dirname(dirname(__DIR__)).'/src/Profiler.class.php');

define('SETTINGS_DSN', 'mysqli://unit_test:magic123@tcp+localhost/unit_test');



/**
 *
 */
function get_db() {
	global $prof;

	$db = new \rkphplib\MysqlDatabase();
	$db->setDSN(SETTINGS_DSN);
	$prof->log('setDSN');

	return $db;
}


/**
 *
 */
function create() {
	global $prof;

	$prof->log('enter create');
	$res = array(0, 0, 0, 0);
	$db = get_db();

try {

	$db->execute("DROP TABLE IF EXISTS phplib");
	$prof->log('drop table if exists');
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
	$prof->log('create table');
	$res[1] = 1;

	$colnames = join(',', array_keys($db->getTableDesc('phplib')));
	$prof->log('getTableDesc()');

	if ($colnames == 'id,rid,name,age,height,weight') {
		$res[2] = 1;
	}

	$db->setQuery('desc', "DESC {:=^table}");
	$colnames = join(',', $db->selectColumn($db->getQuery('desc', array('table' => 'phplib')), 'Field'));
	$prof->log('get table desc');

	if ($colnames == 'id,rid,name,age,height,weight') {
		$res[3] = 1;
	}

}
catch (Exception $e) {
	// ignore
}

	$prof->log('exit create');
	return $res;
}


/**
 *
 */
function insert() {
	global $prof;

	$prof->log('enter insert');
	$res = array(0, 0, 0, 0);
	$db = get_db();

try {

	$db->setQuery('insert', "INSERT INTO phplib (rid, name, age, height, weight) VALUES ".
		"({:=rid}, {:=name}, {:=age}, {:=height}, {:=weight})");
	$prof->log('set insert query');

	for ($i = 1001; $i <= 2000; $i++) {
		$r = array('rid' => $i, 'name' => 'i_'.$i, 'age' => 30, 'height' => 175, 'weight' => 90);
		$db->execute($db->getQuery('insert', $r));
	}

	$prof->log('execute 1000 insert queries');
	$res[0] = 1;

	$db->setQuery('insert_bind', "INSERT INTO phplib (rid, name, age, height, weight) VALUES ".
		"('{:=rid}', '{:=name}', '{:=age}', '{:=height}', '{:=weight}')");
	$prof->log('set insert_bind query');
	
	for ($i = 2001; $i <= 3000; $i++) {
		$r = array('rid' => $i, 'name' => 'ib_'.$i, 'age' => 25, 'height' => 185, 'weight' => 102);
		$db->execute($db->getQuery('insert_bind', $r));
	}
	
	$prof->log('execute 1000 insert bind queries');
	$res[1] = 1;
}
catch (Exception $e) {
	// ignore
}

	$prof->log('exit insert');
	return $res;
}


/*
@ToDo: auto_incr

CREATE TABLE `aaa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `n` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12634 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

$db = new Database();
$db->setDSN($settings_DSN);

for ($i = 0; $i < 50; $i++) {
  $id = $db->auto_incr_insert("INSERT INTO aaa (name) VALUES ('$i')", "aaa", "id");
  $db->execute("UPDATE aaa SET value='$i' WHERE id='$id'");
}

for ($i = 0; $i < 50; $i++) {
  $id = $db->auto_incr_insert("INSERT INTO aaa (n, name) VALUES ('\$detect_max_n', '$i')", "aaa", "n");
  $db->execute("UPDATE aaa SET value='$i' WHERE n='$id'");
}

$db->select("SELECT * FROM aaa WHERE name != value");

--> per System Aufruf

php aaa.php &
php aaa.php &
php aaa.php &
php aaa.php &

*/



/**
 * M A I N
 */

// create database if necessary ...
$db = new \rkphplib\MysqlDatabase([ 'abort' => false ]);
$db->setDSN(SETTINGS_DSN);

$dsn_info = \rkphplib\ADatabase::splitDSN(SETTINGS_DSN);
if (!$db->hasDatabase($dsn_info['name'])) {
	if (!$db->createDatabase(SETTINGS_DSN)) {
		print "create database ${dsn_info['name']} failed try:\n";
		print "sudo rks-db account ${dsn_info['name']} ${dsn_info['password']}\n";
		exit(1);
	}
}

$prof = new \rkphplib\Profiler();
$prof->startXDTrace();
$prof->log('start test');

// \rkphplib\MysqlDatabase::$use_prepared = false;

call_test('create', array(), array('MysqlDatabase create/desc', 1, 1, 1, 1));
call_test('insert', array(), array('MysqlDatabase insert', 1, 1, 1, 1));

$prof->log('done.');
$prof->stopXDTrace();
print "\nProfiler Log:\n";
$prof->writeLog();
print "\n\n";

