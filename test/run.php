<?php

require_once __DIR__.'/settings.php';


/** 
 * NEXT:
 * repair all skipped tests
 * refactor tok_*
 * update File, tok.TBase
 * allow localhost:15081/run.php (html output)
 * use http:1 in [Database]Session
 * add all missing
 * check if public function tests are missing
 */

$th->prepare();

$tests = $th->getTests('../src', [
	'DatabaseSessionHandler',
	'FileSessionHandler',
	'Session',
	'XML',
	'Dir',
	'MysqlDatabase',
	'Database',
	'Catalog',
	'ArrayHelper',
	'StringHelper',
	'ValueCheck',
	'ShellCode',
	'SQLiteDatabase',
	'tok.TArray'
]);

foreach ($tests as $test) {
	$th->test($test);
}

$th->result();

