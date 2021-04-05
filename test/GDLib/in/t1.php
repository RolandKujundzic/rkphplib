<?php

$img = new \rkphplib\GDLib();
$img->create(300, 300);
$img->save('out/t1.jpg');

wxh('out/t1.jpg');

$img->load('out/t1.jpg');
$img->save('out/t1.png');

wxh('out/t1.png');

