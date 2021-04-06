<?php

$img = new \rkphplib\GDLib();

$img->load('crosshair.png');
$img->save('out/t2.png');
$img->load('out/t2.png');

print $img->file.': '.$img->width.'x'.$img->height.
	' has alpha='.$img->hasAlpha()."\n";

$img->create(80, 30, '255,255,255,127');
$img->save('out/t2a.png');

print $img->file.': '.$img->width.'x'.$img->height.
	' has alpha='.$img->hasAlpha()."\n";
