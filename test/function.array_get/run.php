<?php

require_once '../settings.php';

require_once __DIR__.'/../../function/array_get.php';

function call2($name, $p1, $p2) {
	print "$name(".json_encode($p1).', '.json_encode($p2).") == ".json_encode($name($p1, $p2)).";\n";
}

global $th;

$th->run(1, 1);
