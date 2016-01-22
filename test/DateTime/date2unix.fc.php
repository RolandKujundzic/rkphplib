<?php

$func = 'rkphplib\DateCalc::date2unix';

$test = [
	['01.01.2015', 1, 1420070400],
	['01.01.2015 00:00:00', 1420070400],
	['01.01.2015 00:00:60', 1420070460],
	['2015-01-01 00:01:01', 1420070461],
	['2015-01-01 00:59:59', 1420073999],
	['01.01.2015 01:00:01', 1420074001],
	['2015-01-01 03:00:00', 1420081200],
	['20150101235959', 1420156799],
	['01.01.2015 23:59:59', 1420156799],
	['2015-01-02', 1, 1420156800]
];

