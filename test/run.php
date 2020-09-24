<?php

require_once __DIR__.'/settings.php';

$th->test('lib.conf2kv');
$th->test('lib.csv2kv');
$th->test('lib.kv2conf');
$th->test('DateCalc');
$th->test('XMLParser');
$th->test('tok.Tokenizer');

exit(0);

// ob_* is necessary because of Session/run.php
ob_start();
$th->runTest('Session/run.php');
$res = ob_get_contents();
ob_end_clean();
print $res;

$th->runTest('lib_split_str/run.php');
$th->runTest('lib_array_join/run.php');
$th->runTest('lib_csv_explode/run.php');
$th->runTest('lib_replace_tags/run.php');
$th->runTest('lib_log_debug/run.php');
$th->runTest('ArrayHelper/run.php');
$th->runTest('StringHelper/run.php');
$th->runTest('DateTime/run.php');
$th->runTest('Dir/run.php');
$th->runTest('tok_Tokenizer/run.php');
$th->runTest('tok_TFileSystem/run.php');
$th->runTest('tok_TBase/run.php');
$th->result();

// ToDo ...
// run_test('MysqlDatabase/run.php');
// run_test('tok_TOutput/run.php');

