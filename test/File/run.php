<?php

require '../settings.php';

global $th;

$th->run(1, 1);

/*
use \rkphplib\File;


$out = $th->res2str(File::loadTable('split:file://test1.txt', [ '|&|', '|@|' ]));
$th->compare('loadTable(split:file://test1.txt)', [ $out ], [ '[["c11","c12"],["c21","c22"]]' ]);

$out = $th->res2str(File::loadTable('split:file://test2.txt', [ '|&|', '|@|', '=' ]));
$th->compare('loadTable(split:file://test2.txt)', [ $out ], [ '[{"c11":"a","c12":"b"},{"c21":"c","c22":"d"}]' ]);
*/
