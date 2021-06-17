<?php

$test = [
	'rkphplib\db\ADatabase::columnList',
	[ ['a x', 'b-y', ' c '], '`a x`, `b-y`, c' ],
	[ ['p.age', 'm.name', 'p.last name'], 'p.age AS p_age, m.name AS m_name, `p.last name` AS `p_last name`' ], 
	[ ['a', 'x y', 'c'], 'l', 'l.a AS l_a, `l.x y` AS `l_x y`, l.c AS l_c' ],
	[ ['x.c', 'üöä' ], 'lx', 'x.c AS x_c, `lx.üöä` AS `lx_üöä`' ]
];

