<?php

require_once(__DIR__.'/testlib.php');

run_test('lib_conf2kv/run.php');
run_test('lib_split_str/run.php');
run_test('lib_csv_explode/run.php');
run_test('lib_replace_tags/run.php');
run_test('DateTime/run.php');
// run_test('MysqlDatabase/run.php');

$overall = "Overall result of ".count($test_count['overview'])." Class/Function Tests:";
printf("%s\n%'=".mb_strlen($overall)."s\n", $overall, '');
print join("\n", $test_count['overview'])."\n\n";
