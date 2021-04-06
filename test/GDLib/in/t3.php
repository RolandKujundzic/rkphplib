<?php

function merge(string $save_as, int $w, int $h) : void {
	$img = new \rkphplib\GDLib();
	$img->create($w, $h);
	$img->loadLayer('crosshair.png');
	$img->save($save_as);
	wxh($save_as);
}

merge('out/t3a.jpg', 200, 500);
merge('out/t3b.jpg', 500, 200);
merge('out/t3c.jpg', 200, 200);
merge('out/t3d.jpg', 400, 400);

