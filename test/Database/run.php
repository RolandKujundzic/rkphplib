<?php

define('DSN', 'mysqli://unit_test:magic123@tcp+localhost/unit_test');
define('ADMIN_DSN', 'mysqli://sql:admin@tcp+localhost/');

global $th;

if (!isset($th)) {
	require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
	$th = new rkphplib\TestHelper();
}

$th->load('Database.class.php');
$th->load('TestHelper.class.php');
$th->load('lib/log_debug.php');
$th->load('lib/config.php');

use \rkphplib\ADatabase;
use \rkphplib\Database;


/**
 * M A I N
 */

// re-create database unit_test
$adb = Database::getInstance(ADMIN_DSN);

$dsn_info = ADatabase::splitDSN(DSN);
$adb->createDatabase(DSN);

$db = Database::getInstance(DSN);

$db->execute("SHOW TABLES", true);
$db2 = Database::getInstance(DSN);
$db3 = Database::getInstance(DSN);

$th->compare('Database::getInfo', Database::getInfo(), "@getInfo.json");

$dbx = [];
$dbx_num = 10000;
print "get $dbx_num database instances\n";

$query_map = [ 'select' => "SELECT * FROM x", 'insert' => "INSERT INTO x (a, b) VALUES ('{:=a}', '{:=b}')" ];

for ($i = 0; $i < $dbx_num; $i++) {
	$dbx[$i] = Database::getInstance(DSN, $query_map);
	
}

if (Database::getPoolSize() != 4) { 
	throw new Exception("DatabasePool size = ".Database::getPoolSize()." != 4");
}

$id1 = $dbx[0]->getId();
for ($i = 1; $i < $dbx_num; $i++) {
	if ($id1 != $dbx[$i]->getId()) {
		throw new Exception("dbx[0].id = $id1 != ".$dbx[$i]->getId()." = dbx[$i].id");
	}
}

$db = Database::getInstance(DSN);
$db->createTable([ '@table' => 'user', '@id' => '1', '@status' => '1', '@timestamp' => '1',
	'name' => 'varchar:30::', 'age' => 'int:::33' ]);

$A_Z = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$a_z = 'abcdefghijklmnopqrstuvwxyz';

for ($i = 0; $i < 99; $i++) {
	$name = substr($A_Z, rand(0, 25), 1);
	
	for ($j = 0; $j < rand(5,12); $j++) {
		$name .= substr($a_z, rand(0, 25), 1);
	}

	$age = rand(14,87);
	$db->execute("INSERT INTO user (name, age) VALUES ('$name', '$age')");
}

print "done.\n";

$th->result();
