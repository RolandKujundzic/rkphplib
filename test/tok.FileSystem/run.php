<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'FileSystem' ]);
$th->run(0, 0);

