<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TBase', 'TFormValidator' ]);
$th->run(1, 5);

