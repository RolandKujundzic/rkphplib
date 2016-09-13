<?php

require_once(dirname(__DIR__).'/src/TestHelper.class.php');

$th = new rkphplib\TestHelper();
$th->runTest('lib_conf2kv/run.php');
$th->runTest('lib_kv2conf/run.php');
$th->runTest('lib_split_str/run.php');
$th->runTest('lib_csv_explode/run.php');
$th->runTest('lib_replace_tags/run.php');
$th->runTest('DateTime/run.php');
$th->runTest('Tokenizer/run.php');
$th->runTest('Dir/run.php');
$th->runTest('TBase/run.php'); // ToDo: track exception as ok
$th->runTest('Session/run.php');
$th->result();

// ToDo ...
// run_test('MysqlDatabase/run.php');

