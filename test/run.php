<?php

require_once __DIR__.'/settings.php';

$th->test('lib.conf2kv');
$th->test('lib.csv2kv');
$th->test('lib.kv2conf');
$th->test('lib.split_str');
$th->test('lib.array_join');
$th->test('lib.csv_explode');
$th->test('lib.replace_tags');
$th->test('lib.log_debug');
$th->test('lib.cli_input');
$th->test('lib.log_warn');
$th->test('lib.mysql_password');
$th->test('lib.resolvPath');

/*
$th->test('lib.sitemap_xml');
$th->test('lib.split_table');
$th->test('lib.translate');
*/

$th->test('FSEntry');
$th->test('DateCalc');
$th->test('XMLParser');

/*
$th->runTest('ArrayHelper/run.php');
$th->runTest('StringHelper/run.php');
$th->runTest('Dir/run.php');
*/

$th->test('tok.Tokenizer');

/*
$th->runTest('tok_Tokenizer/run.php');
$th->runTest('tok_TFileSystem/run.php');
$th->runTest('tok_TBase/run.php');
*/

$th->result();

exit(0);

// ob_* is necessary because of Session/run.php
ob_start();
$th->runTest('Session/run.php');
$res = ob_get_contents();
ob_end_clean();
print $res;

// ToDo ...
// run_test('MysqlDatabase/run.php');
// run_test('tok_TOutput/run.php');

