<?php

$func = 'rkphplib\DateCalc::formatDateTimeStr';

$now_p10 = date('d.m.Y H:i:s', time() + 10);
$now_m10 = date('Y-m-d H:i:s', time() - 10);

$test = [
	['%d.%m.%Y %H:%i:%s', 'now(+10)', 'now', "$now_p10"],
	['d.m.Y H:i:s', 'now(+10)', 'now', "$now_p10"],
	['%Y-%m-%d %H:%i:%s', 'now(-10)', 'unix', "$now_m10"],
	['%d %B, %Y', '23.10.2016', 'de', "23 Oktober, 2016"]
];

