<?php

require_once '../settings.php';
require_once PATH_SRC.'Vector.php';

function normalize(array $arr) : string {
	\rkphplib\Vector::normalize($arr);
	return json_encode($arr);
}

function permutations(array $arr) : string {
	$perm = \rkphplib\Vector::permutations($arr);
	return json_encode($perm);
}

global $th;

$th->run(1, 2);
