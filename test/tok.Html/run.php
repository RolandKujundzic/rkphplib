<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TBase', 'Html' ]);
$th->run(1, 2);

