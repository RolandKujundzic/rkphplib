<?php

require_once '../settings.php';
require_once PATH_SRC.'File.class.php';
require_once PATH_SRC.'GDLib.php';


function wxh(string $file) : void {
	$img = \rkphplib\File::imageInfo($file);
	print "$file: ".$img['width'].'x'.$img['height']."\n";
}


global $th;

$th->run(1, 3);

