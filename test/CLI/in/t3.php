<?php

require_once PATH_SRC.'CLI.php';

use rkphplib\CLI;

$_REQUEST = [];
CLI::parse('run.php test --name=abc -uvw @req:name=value @req:list=v1 @req:list=v2');

print 'CLI::argv='.print_r(CLI::$argv, true);
print 'CLI::arg='.print_r(CLI::$arg, true);
print '_REQUEST='.print_r($_REQUEST, true);

CLI::parse('run.php @http:host=domain.tld @server:addr=1.2.3.4 @srv:request_method=post');
print '_SERVER[HTTP_HOST]='.$_SERVER['HTTP_HOST']."\n";
print '_SERVER[SERVER_ADDR]='.$_SERVER['SERVER_ADDR']."\n";
print '_SERVER[REQUEST_METHOD]='.$_SERVER['REQUEST_METHOD']."\n";

