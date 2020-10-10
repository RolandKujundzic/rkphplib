<?php

require_once '../settings.php';

global $th;

$th->useTokPlugin([ 'TBase', 'TMath' ]);
$th->run(1, 6);

