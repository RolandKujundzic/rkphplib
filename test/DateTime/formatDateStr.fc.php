<?php

$func = 'rkphplib\DateCalc::formatDateStr';

$now_de = date('d.m.Y H:i:s');
$now_dmy = date('d.m.Y');
$now_ymd = date('Y-m-d');
$now_his = date('H:i:s');

$test = [
	['de', '0000-00-00', ''],
	['de', '', 'now', "$now_de"],
	['d.m.Y', '', 'now', "$now_dmy"],
	['Y-m-d', '', 'now', "$now_ymd"],
	['H:i:s', '', 'now', "$now_his"],
	['de', '1981-05-23 12:17:58', 'sql', '23.05.1981 12:17:58'],
	['sql', '23.05.1981 12:17:58', 'de', '1981-05-23 12:17:58'],
	['Ym', '1981-05-23', 'Y-m-d', '198105'],
	['Ymd', '1981-05-23', 'Y-m-d', '19810523'],
	['d.m.Y', '1981-05-23', 'Y-m-d', '23.05.1981']
];

