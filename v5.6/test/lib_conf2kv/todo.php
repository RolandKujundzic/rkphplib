<?php

// define('HASH_DELIMITER', '∥');
define('PATH_RKPHPLIB', '/webhome/.php/rkphplib/src/');
require_once PATH_RKPHPLIB.'lib/conf2kv.php';
require_once PATH_RKPHPLIB.'lib/kv2conf.php';




$a = [ 'a', ' b ', "y|#|\n\t c∥d\nx" ];

$a_str = \rkphplib\lib\kv2conf($a);
$a2 = \rkphplib\lib\conf2kv($a_str);

print "a_str: [$a_str]\n";
print "a2: ".print_r($a, true)."\n";

print "a[1] = [".$a[1]."] ? [".$a2[1]."] = a[2]\n"; 
