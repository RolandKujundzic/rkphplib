<?php

$cat = new \rkphplib\Category();

$cat->add('Shop', 'root', null);
$cat->add('Hosen', 'h', 1);
$cat->add('Herren', 'h-h', 2);
$cat->add('Damen', 'h-d', 2);
$cat->add('Zubehör', 'z', 1);

print $cat->toCSV();
