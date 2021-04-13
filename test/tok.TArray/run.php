<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TArray' ]);
$th->run(1, 3);

