<?php

$img = new \rkphplib\GDLib();

$img->load('crosshair.png');
$img->save('out/t2.png');
$img->load('out/t2.png');

print $img->get('file').': '.$img->get('width').'x'.$img->get('height').
	' has alpha='.$img->hasAlpha()."\n";

$img->create(80, 30, '255,255,255,127');
$img->save('out/t2a.png');

print $img->get('file').': '.$img->get('width').'x'.$img->get('height').
	' has alpha='.$img->hasAlpha()."\n";
