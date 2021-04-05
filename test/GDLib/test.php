<?php

require_once '../../src/GDLib.php';

$img = new \rkphplib\GDLib();
$img->create(800, 800);
$img->loadLayer('crosshair.png');
$img->save('out/t3.png');

