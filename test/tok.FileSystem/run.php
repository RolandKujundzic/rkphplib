<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TBase', 'FileSystem' ]);
$th->run(1, 1);

