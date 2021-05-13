<?php

require_once 

$cat = new Category();

print $cat->toJSON();

$cat->loadJSON('[
	{ "id": "h-h", "name": "Herren", "pid": "h", "s": 1 },
	{ "id": "z", "name": "Zubehör", "pid": "root", "s": 2 },
	{ "id": "root", "name": "Shop", "pid": "" },
	[ "h", "root", "Hosen", "s": 1 ],
	{ "id": "h-d", "name": "Damen", "pid": "h", "s": 2 }
]', false);

print $cat->toJSON();
