<?php

$func = 'rkphplib\DateCalc::sqlTS';

$test = [
	['2015-01-01', 1420066800],
	['2015-01-01 00:00:00', 1420066800],
	['2015-01-01 00:00:60', 1420066860],
	['2015-01-01 00:01:01', 1420066861],
	['2015-01-01 00:59:59', 1420070399],
	['2015-01-01 01:00:01', 1420070401],
	['2015-01-01 03:00:00', 1420077600],
	['2015-01-01 23:59:59', -1, 1420066800],
	['2015-01-01 23:59:59', 1420153199],
	['2015-01-02', 1420153200]
];
