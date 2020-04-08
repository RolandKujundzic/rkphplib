<?php

$func = 'rkphplib\DateCalc::nowstr2time';

$now = time();

$test = [
	['wrong', 0, 0],
	['now()', $now],
	['now(-60)', $now - 60],
	['now(+60)', $now + 60],
	['now(+3600)', $now + 3600]
];

