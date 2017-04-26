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

$th->result();
