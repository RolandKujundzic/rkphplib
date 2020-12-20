<?php

require_once '../settings.php';
require_once '../../src/BuildinHttpServer.php';

global $th;

$th->run(1, 2);

// restart default localhost:15081
$th->prepare();

