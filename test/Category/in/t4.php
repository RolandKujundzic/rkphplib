<?php

$cat = new \rkphplib\Category();

$cat->add('Shop', 'root', null);
$cat->add('Hosen', 'h', 1);
$cat->add('Herren', 'h-h', 2);
$cat->add('Damen', 'h-d', 2);
$cat->add('Zubehör', 'z', 1);

$html_opt = [
	'ul.id' => 'main_menu',
	'ul.class' => 'menu',
	'li.node_type' => 1,
	'li.node_level' => 1,
	'li.ds' => 1,
	'li.ts' => 1,
	'li.id' => '{:=id}'
];

print $cat->toHTML($html_opt);
