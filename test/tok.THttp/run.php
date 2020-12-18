<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TBase', 'THttp' ]);
$th->run(0, 0); // todo: in/t1.txt

