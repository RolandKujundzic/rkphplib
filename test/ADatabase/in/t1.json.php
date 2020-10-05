<?php

$test = [
	'rkphplib\ADatabase::splitDSN',
	[ 'type://login:password@protocol+host:port/name', 
		'{"login":"login","password":"password","protocol":"protocol","host":"host","port":"port","name":"name","file":"","type":"type"}' ],
	[ 'mysqli://DBLOGIN:PASS@tcp+127.0.0.1:13306/DBNAME',
		'{"login":"DBLOGIN","password":"PASS","protocol":"tcp","host":"127.0.0.1","port":"13306","name":"DBNAME","file":"","type":"mysqli"}' ],
	[ 'sqlite://PASS@./DB.sqlite',
		'{"login":"","password":"PASS","protocol":"","host":"","port":"","name":"","file":"./DB.sqlite","type":"sqlite"}' ]
];

