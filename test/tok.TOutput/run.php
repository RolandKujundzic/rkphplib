<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TOutput' ]);
$th->run(1, 2);

