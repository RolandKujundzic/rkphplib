<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}

$th->load('src/File.class.php');


use \rkphplib\File;

$out = $th->res2str(File::loadTable('csv:file://test1.csv', [ ';' ]));
$th->compare('loadTable(csv:file://test1.csv)', [ $out ], [ '[["ax\nay","b","c","dx ; dy"],["a2","b2","c2","d2"]]' ]);

$ok = '[["a1","b1","c1"],["a2","b2","c2"]]';

$out = $th->res2str(File::loadTable('csv:file://test2.csv', [ ';' ]));
$th->compare('loadTable(csv:file://test2.csv)', [ $out ], [ $ok ]);

$out = $th->res2str(File::loadTable('unserialize:file://test.ser'));
$th->compare('loadTable(unserialize:file://test.ser)', [ $out ], [ $ok ]);

$out = $th->res2str(File::loadTable('json:file://test.json'));
$th->compare('loadTable(json:file://test.json)', [ $out ], [ $ok ]);

$out = $th->res2str(File::loadTable('split:file://test1.txt', [ '|&|', '|@|' ]));
$th->compare('loadTable(split:file://test1.txt)', [ $out ], [ '[["c11","c12"],["c21","c22"]]' ]);

$out = $th->res2str(File::loadTable('split:file://test2.txt', [ '|&|', '|@|', '=' ]));
$th->compare('loadTable(split:file://test2.txt)', [ $out ], [ '[{"c11":"a","c12":"b"},{"c21":"c","c22":"d"}]' ]);
