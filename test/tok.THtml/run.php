<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TBase', 'THtml' ]);
$th->run(1, 2);

