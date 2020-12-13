<?php

global $conf;

rkphplib\lib\php_server(array_merge($conf, [ 'running' => 'stop' ]));
print "done";
