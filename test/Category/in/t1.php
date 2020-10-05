<?php

$text_opt = [
	'branch' => '+',
	'leaf' => '--',
	'line_feed' => "\n",
	'tpl' => '{:=name}{:=ds_di}',
	'ds_di' => ' ({:=ds}/{:=id})'
];

$cat = new Category();

$cat->add('Shop', 'root', '', 0);
print $cat->toText($text_opt);

$cat->add('Hosen', 'h', '', 1);
$cat->add('Herren', 'h-h', 'h');
$cat->add('Damen', 'h-d', '', 2);
$cat->addNode([ 'name' => 'Zubehör', 'id' => 'z', 'level' => 1 ]);

print $cat->toText($text_opt);

