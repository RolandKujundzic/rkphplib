<?php

require_once PATH_SRC.'lib/resolvPath.php';

$path = '../out/$map(rechnung,email)';
$map = [ 'rechnung' => [ 'email' => 'test@domain.tld' ] ];

print rkphplib\lib\resolvPath($path, $map);

