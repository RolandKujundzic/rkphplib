<?php

$test = [
	'\rkphplib\Request::checkEmail',
	[ 'info@test-domain.de', 1 ],
	[ 'jürgen.müller@gmail.com', 1 ],
	[ 'info@küchen.de', 1 ],
	[ 'a b', 0 ],
	[ '...@abc.de', 1 ],
	[ 'üñîçøðé@üñîçøðé.com', 1 ],
	[ 'Pelé@example.com', 1 ],
	[ 'δοκιμή@παράδειγμα.δοκιμή.gr', 1 ],
	[ '我買@屋企.香港.cn', 1 ],
	[ '甲斐@黒川.日本.jp', 1 ],
	[ 'чебурашка@ящик-с-апельсинами.ru', 1 ]
];
