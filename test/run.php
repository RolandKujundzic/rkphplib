<?php

require_once dirname(__DIR__).'/src/TestHelper.class.php';

$th = new rkphplib\TestHelper();

// ob_* is necessary because of Session/run.php
ob_start();
$th->runTest('Session/run.php');
$res = ob_get_contents();
ob_end_clean();
print $res;

$th->runTest('lib_conf2kv/run.php');
$th->runTest('lib_kv2conf/run.php');
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

