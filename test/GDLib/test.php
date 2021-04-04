<?php

require_once '../../src/GDLib.php';

$img = new \rkphplib\GDLib();
$img->create(300, 300);
$img->save('out/test.jpg');

