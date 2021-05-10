<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TBase', 'Conf' ]);
$th->run(1, 1);

